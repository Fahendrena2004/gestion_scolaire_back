<?php

namespace App\Services\Inscription;

use App\Models\Inscription\Inscription as InscriptionModel;
use App\Models\Inscription\Paiement;
use App\Services\Paiement\EcheanceGenerator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class InscriptionService
{
    protected $eleveService;
    protected $classeService;
    protected $fraisService;
    protected $echeanceGenerator;

    public function __construct(
        EleveService $eleveService,
        ClasseService $classeService,
        FraisService $fraisService,
        EcheanceGenerator $echeanceGenerator
    ) {
        $this->eleveService = $eleveService;
        $this->classeService = $classeService;
        $this->fraisService = $fraisService;
        $this->echeanceGenerator = $echeanceGenerator;
    }

    public function processInscription(
        array $fixedFields,
        array $inscriptionFields,
        array $dynamicFields = [],
        $userId = null
    ): array {

        DB::beginTransaction();

        try {
            //  Récupérer la classe
            $classe = $this->classeService->getClasseWithNiveau($inscriptionFields['classe_id']);

            if (!$classe) {
                throw new \Exception("Classe non trouvée");
            }

            //  Récupérer l'année active
            $anneeActive = $this->classeService->getAnneeActive();

            if (!$anneeActive) {
                throw new \Exception("Aucune année scolaire active");
            }

            //  Créer l'élève
            $eleve = $this->eleveService->trouverOuCreerEleve($fixedFields, $classe->niveau->cycle);

            //  Ajouter les infos dynamiques
            if (!empty($dynamicFields)) {
                $this->eleveService->ajouterInformationsDynamiques($eleve, $dynamicFields);
            }

            //  Créer l'inscription
            $inscription = InscriptionModel::create([
                'id_eleve' => $eleve->id,
                'id_classe' => $inscriptionFields['classe_id'],
                'id_annee_scolaire' => $anneeActive->id,
                'date_inscription' => now(),
                'parascolaire' => $inscriptionFields['parascolaire'] ?? false,
                'cantine' => $inscriptionFields['cantine'] ?? false,
                'montant_total' => 0,
                'montant_net' => 0,
                'utilisateur_id' => $userId,
            ]);

            //  Appliquer les frais
            $montantTotal = $this->fraisService->appliquerFrais(
                $inscription,
                $classe->niveau->cycle,
                [
                    'parascolaire' => $inscriptionFields['parascolaire'] ?? false,
                    'cantine' => $inscriptionFields['cantine'] ?? false,
                ]
            );

            // Mettre à jour les montants
            $inscription->update([
                'montant_total' => $montantTotal,
                'montant_net' => $montantTotal,
            ]);


            //  GÉNÉRATION AUTOMATIQUE DES ÉCHÉANCES

            $this->echeanceGenerator->genererToutesEcheances($inscription);



            //  Enregistrer le premier paiement si montant versé
            $montantVerse = $inscriptionFields['montant_verse'] ?? 0;
            if ($montantVerse > 0) {
                $this->enregistrerPremierPaiement($inscription, $montantVerse, $userId);
            }

            DB::commit();

            return [
                'success' => true,
                'message' => 'Inscription réussie avec génération des échéances',
                'data' => [
                    'matricule' => $eleve->matricule,
                    'eleve_id' => $eleve->id,
                    'inscription_id' => $inscription->id,
                    'classe' => $classe->nom_classe,
                    'niveau' => $classe->niveau->nom_niveau,
                    'cycle' => $classe->niveau->cycle,
                    'annee_scolaire' => $anneeActive->date_debut . ' - ' . $anneeActive->date_fin,
                    'montant_total' => $montantTotal,
                    'montant_verse' => $montantVerse,
                    'reste_a_payer' => $montantTotal - $montantVerse,
                    'nb_echeances' => $inscription->echeances()->count(),
                ]
            ];

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Erreur inscription: ' . $e->getMessage());
            throw $e;
        }
    }

    private function enregistrerPremierPaiement($inscription, $montantVerse, $userId)
    {
        // Créer le paiement
        $paiement = Paiement::create([
            'reference' => $this->genererReferencePaiement(),
            'inscription_id' => $inscription->id,
            'montant' => $montantVerse,
            'date_paiement' => now(),
            'utilisateur_id' => $userId
        ]);

        // Appliquer le paiement à la première échéance impayée
        $premiereEcheance = $inscription->echeances()
            ->where('statut', 'impaye')
            ->orWhere('statut', 'partiel')
            ->orderBy('date_echeance')
            ->first();

        if ($premiereEcheance) {
            $nouveauPaye = $premiereEcheance->montant_paye + $montantVerse;
            $premiereEcheance->update([
                'montant_paye' => $nouveauPaye,
                'montant_restant' => $premiereEcheance->montant - $nouveauPaye,
                'statut' => $nouveauPaye >= $premiereEcheance->montant ? 'paye' : 'partiel'
            ]);
        }
    }

    private function genererReferencePaiement(): string
    {
        $lastId = Paiement::max('id') ?? 0;
        $numero = str_pad($lastId + 1, 6, '0', STR_PAD_LEFT);
        return 'PAY-' . date('Y') . '-' . $numero;
    }

    public function getInscription(int $id): ?InscriptionModel
    {
        return InscriptionModel::with([
            'eleve',
            'classe.niveau',
            'anneeScolaire',
            'fraisAppliques.typeFrais',
            'paiements',
            'echeances.details'
        ])->find($id);
    }
}