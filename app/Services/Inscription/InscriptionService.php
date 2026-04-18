<?php

namespace App\Services\Inscription;

use App\Models\Inscription\Inscription as InscriptionModel;
use App\Models\Inscription\Paiement;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\QueryException;

class InscriptionService
{
    protected $eleveService;
    protected $classeService;
    protected $fraisService;

    public function __construct(
        EleveService $eleveService,
        ClasseService $classeService,
        FraisService $fraisService
    ) {
        $this->eleveService = $eleveService;
        $this->classeService = $classeService;
        $this->fraisService = $fraisService;
    }

    public function processInscription(
        array $fixedFields,
        array $inscriptionFields,
        array $dynamicFields = []
    ): array {

        DB::beginTransaction();

        try {
            // 1. Récupérer la classe
            $classe = $this->classeService->getClasseWithNiveau($inscriptionFields['classe_id']);

            if (!$classe) {
                throw new \Exception("Classe non trouvée");
            }

            // 2. Récupérer l'année active
            $anneeActive = $this->classeService->getAnneeActive();

            if (!$anneeActive) {
                throw new \Exception("Aucune année scolaire active");
            }

            // 3. Créer ou récupérer l'élève (Évite les doublons)
            $eleve = $this->eleveService->trouverOuCreerEleve($fixedFields, $classe->niveau->cycle);

            // 4. Ajouter les infos dynamiques (seulement si nouvelles)
            if (!empty($dynamicFields)) {
                $this->eleveService->ajouterInformationsDynamiques($eleve, $dynamicFields);
            }


            // 5. Créer l'inscription (avec vérification de doublon)
            try {
                $inscription = InscriptionModel::create([
                    'id_eleve' => $eleve->id,
                    'id_classe' => $inscriptionFields['classe_id'],
                    'id_annee_scolaire' => $anneeActive->id,
                    'date_inscription' => now(),
                    'parascolaire' => $inscriptionFields['parascolaire'] ?? false,
                    'cantine' => $inscriptionFields['cantine'] ?? false,
                    'montant_total' => 0,
                    'montant_net' => 0,
                    'utilisateur_id' => "1",
                ]);
            } catch (QueryException $e) {

                // Vérifier si c'est une erreur de contrainte unique (doublon)
                if ($e->errorInfo[1] == 1062 || str_contains($e->getMessage(), 'unique_inscription_par_an')) {
                    DB::rollBack();
                    return [
                        'success' => false,
                        'message' => 'Cet élève est déjà inscrit pour cette année scolaire',
                        'code' => 'DOUBLON_INSCRIPTION'
                    ];
                }
                throw $e;
            }

            // 6. Appliquer les frais
            $montantTotal = $this->fraisService->appliquerFrais(
                $inscription,
                $classe->niveau->cycle,
                [
                    'parascolaire' => $inscriptionFields['parascolaire'] ?? false,
                    'cantine' => $inscriptionFields['cantine'] ?? false,
                ]
            );

            // 7. Mettre à jour les montants
            $inscription->update([
                'montant_total' => $montantTotal,
                'montant_net' => $montantTotal,
            ]);


            // 8. ENREGISTRER LE PAIEMENT (NOUVEAU!)

            $montantVerse = $inscriptionFields['montant_verse'] ?? 0;
            $resteAPayer = $montantTotal - $montantVerse;

            if ($montantVerse > 0) {
                Paiement::create([
                    'inscription_id' => $inscription->id,
                    'montant' => min($montantVerse, $montantTotal),
                    'date_paiement' => now(),
                    'utilisateur_id' => "1",
                ]);
            }

            DB::commit();

            return [
                'success' => true,
                'message' => $eleve->wasRecentlyCreated ? 'Inscription réussie' : 'Élève existant, inscription ajoutée',
                'data' => [
                    'matricule' => $eleve->matricule,
                    'eleve_id' => $eleve->id,
                    'inscription_id' => $inscription->id,
                    'classe' => $classe->nom_classe,
                    'niveau' => $classe->niveau->nom_niveau,
                    'cycle' => $classe->niveau->cycle,
                    'annee_scolaire' => $anneeActive->date_debut . ' - ' . $anneeActive->date_fin,
                    'nouvel_eleve' => $eleve->wasRecentlyCreated,
                    'reste_a_payer' => $resteAPayer,
                    'montant_total' => $montantTotal,
                    'montant_verse' => $montantVerse,
                ]
            ];

        } catch (\Exception $e) {
            DB::rollBack();
            return [
                'success' => false,
                'message' => 'Erreur lors de l\'inscription',
                'error' => $e->getMessage(),
                'line' => $e->getLine(),
                'file' => $e->getFile()
            ];
        }
    }

    public function getInscription(int $id): ?InscriptionModel
    {
        return InscriptionModel::with([
            'eleve',
            'classe.niveau',
            'anneeScolaire',
            'fraisAppliques.typeFrais',
            'Paiements'
        ])->find($id);
    }
}