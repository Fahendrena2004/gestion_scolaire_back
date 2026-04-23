<?php

namespace App\Http\Controllers\Inscription;

use App\Http\Controllers\Controller;
use App\Models\Inscription\AnneeScolaire;
use App\Models\Inscription\AutreInformation;
use App\Models\Inscription\Classe;
use App\Models\Inscription\Eleve;
use App\Models\Inscription\FraisApplique;
use App\Models\Inscription\Inscription;
use App\Models\Inscription\Paiement;
use App\Models\Inscription\TypeFrais;
use App\Models\Paiement\ResumePaiement;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class InscriptionController extends Controller
{
    public function index()
    {
        $inscriptions = Inscription::with([
            'eleve',
            'classe.niveau',
            'anneeScolaire',
            'resumePaiement',
        ])->get();

        return response()->json([
            'success' => true,
            'data' => $inscriptions,
        ]);
    }

    public function store(Request $request)
    {
        $currentUser = $request->user();
        $utilisateurId = $currentUser ? (int) $currentUser->getKey() : null;

        $validator = Validator::make($request->all(), [
            'nom' => 'required|string|max:100',
            'prenom' => 'required|string|max:100',
            'date_naissance' => 'required|date|before:today',
            'lieu_naissance' => 'required|string|max:150',
            'sexe' => 'required|in:M,F',
            'niveau_id' => 'required|exists:niveaux,id',
            'classe_id' => 'required|exists:classes,id',
            'adresse' => 'nullable|string|max:255',
            'parascolaire' => 'sometimes|boolean',
            'cantine' => 'sometimes|boolean',
            'montant_verse' => 'nullable|numeric|min:0',
            'responsable_nom' => 'nullable|string|max:150',
            'responsable_telephone' => 'nullable|string|max:30',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        $classe = Classe::with('niveau')->find($request->classe_id);

        if (!$classe) {
            return response()->json([
                'success' => false,
                'message' => 'Classe non trouvee.',
            ], 404);
        }

        if ((int) $classe->niveau_id !== (int) $request->niveau_id) {
            return response()->json([
                'success' => false,
                'message' => 'La classe selectionnee ne correspond pas au niveau fourni.',
            ], 422);
        }

        $anneeActive = AnneeScolaire::where('statut', 'en_cours')->first();

        if (!$anneeActive) {
            return response()->json([
                'success' => false,
                'message' => 'Aucune annee scolaire active.',
            ], 404);
        }

        DB::beginTransaction();

        try {
            $eleve = Eleve::where('nom', $request->nom)
                ->where('prenom', $request->prenom)
                ->where('date_naissance', $request->date_naissance)
                ->where('lieu_naissance', $request->lieu_naissance)
                ->first();

            if (!$eleve) {
                $eleve = Eleve::create([
                    'nom' => $request->nom,
                    'prenom' => $request->prenom,
                    'date_naissance' => $request->date_naissance,
                    'lieu_naissance' => $request->lieu_naissance,
                    'sexe' => $request->sexe,
                    'adresse' => $request->adresse,
                    'matricule' => $this->genererMatricule($classe->niveau->cycle),
                ]);
            }

            $this->enregistrerInformationDynamique($eleve->id, 'responsable_nom', $request->responsable_nom);
            $this->enregistrerInformationDynamique($eleve->id, 'responsable_telephone', $request->responsable_telephone);

            $inscription = Inscription::create([
                'id_eleve' => $eleve->id,
                'id_classe' => $classe->id,
                'id_annee_scolaire' => $anneeActive->id,
                'date_inscription' => now(),
                'parascolaire' => $request->boolean('parascolaire'),
                'cantine' => $request->boolean('cantine'),
                'montant_total' => 0,
                'montant_net' => 0,
                'utilisateur_id' => $utilisateurId,
            ]);

            $montantTotal = $this->appliquerFrais($inscription, $classe->niveau->cycle, $anneeActive);

            $inscription->update([
                'montant_total' => $montantTotal,
                'montant_net' => $montantTotal,
            ]);

            $resume = $this->creerOuMettreAJourResumePaiement($inscription, $montantTotal);

            $montantVerse = (float) $request->input('montant_verse', 0);

            if ($montantVerse > 0) {
                $this->enregistrerPaiementInitial($inscription, $resume, $montantVerse, $utilisateurId);
            } else {
                $this->mettreAJourResumePaiement($resume);
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Inscription reussie',
                'data' => [
                    'matricule' => $eleve->matricule,
                    'eleve_id' => $eleve->id,
                    'inscription_id' => $inscription->id,
                    'classe' => $classe->nom_classe,
                    'niveau' => $classe->niveau->nom_niveau,
                    'cycle' => $classe->niveau->cycle,
                    'annee_scolaire_id' => $anneeActive->id,
                    'montant_total' => $montantTotal,
                    'montant_verse' => $montantVerse,
                    'resume' => $resume->fresh(),
                    'nombre_mois_scolarite' => $this->compterMoisScolaires($anneeActive),
                ],
            ], 201);
        } catch (\Throwable $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de l inscription',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function show($id)
    {
        $inscription = Inscription::with([
            'eleve.infosDynamiques',
            'classe.niveau',
            'anneeScolaire',
            'fraisAppliques.typeFrais',
            'paiements.utilisateur',
            'paiements.typeFrais',
            'resumePaiement.paiementsMensuels.paiement',
            'presencesCantine.paiement',
        ])->find($id);

        if (!$inscription) {
            return response()->json([
                'success' => false,
                'message' => 'Inscription non trouvee',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $inscription,
        ]);
    }

    public function getDynamicInfos($id)
    {
        $inscription = Inscription::with('eleve.infosDynamiques')->find($id);

        if (!$inscription) {
            return response()->json([
                'success' => false,
                'message' => 'Inscription non trouvee',
            ], 404);
        }

        $infos = $inscription->eleve?->infosDynamiques
            ?->mapWithKeys(fn ($info) => [$info->nom_champ => $info->valeur_champ])
            ->toArray() ?? [];

        return response()->json([
            'success' => true,
            'data' => [
                'inscription_id' => $inscription->id,
                'eleve_id' => $inscription->id_eleve,
                'infos_dynamiques' => $infos,
            ],
        ]);
    }

    private function enregistrerInformationDynamique(int $eleveId, string $champ, ?string $valeur): void
    {
        if (!$valeur) {
            return;
        }

        AutreInformation::updateOrCreate(
            [
                'id_eleve' => $eleveId,
                'nom_champ' => $champ,
            ],
            [
                'valeur_champ' => $valeur,
            ]
        );
    }

    private function appliquerFrais(Inscription $inscription, string $cycle, AnneeScolaire $anneeScolaire): float
    {
        $montantTotal = 0;

        $montantTotal += $this->ajouterFrais($inscription, 'Inscription');
        $montantTotal += $this->ajouterFrais(
            $inscription,
            $this->getLibelleScolarite($cycle),
            $this->compterMoisScolaires($anneeScolaire)
        );
        $montantTotal += $this->ajouterFrais($inscription, 'Frais technologiques');

        if ($inscription->parascolaire) {
            $montantTotal += $this->ajouterFrais($inscription, 'Parascolaire');
        }

        return $montantTotal;
    }

    private function ajouterFrais(Inscription $inscription, string $libelle, int $multiplicateur = 1): float
    {
        $typeFrais = $this->getTypeFrais($libelle, $inscription->id_annee_scolaire);

        if (!$typeFrais) {
            return 0;
        }

        $montant = (float) $typeFrais->montant * max($multiplicateur, 1);

        FraisApplique::create([
            'id_frais' => $typeFrais->id,
            'id_inscription' => $inscription->id,
            'montant' => $montant,
        ]);

        return $montant;
    }

    private function creerOuMettreAJourResumePaiement(Inscription $inscription, float $montantTotal): ResumePaiement
    {
        return ResumePaiement::updateOrCreate(
            ['inscription_id' => $inscription->id],
            [
                'total_du' => $montantTotal,
                'total_paye' => 0,
                'total_restant' => $montantTotal,
            ]
        );
    }

    private function enregistrerPaiementInitial(
        Inscription $inscription,
        ResumePaiement $resume,
        float $montantVerse,
        ?int $userId
    ): void {
        $montantRestant = $montantVerse;

        $fraisSimples = $inscription->fraisAppliques()
            ->with('typeFrais')
            ->whereHas('typeFrais', function ($query) {
                $query->where('libelle', 'not like', 'Scolarité%')
                    ->where('libelle', '!=', 'Cantine');
            })
            ->orderBy('id')
            ->get();

        foreach ($fraisSimples as $frais) {
            if ($montantRestant < $frais->montant) {
                continue;
            }

            Paiement::create([
                'reference' => $this->genererReferencePaiement(),
                'inscription_id' => $inscription->id,
                'type_frais_id' => $frais->id_frais,
                'type' => 'autre_frais',
                'libelle' => $frais->typeFrais?->libelle,
                'details' => null,
                'montant' => $frais->montant,
                'date_paiement' => now(),
                'utilisateur_id' => $userId,
            ]);

            $montantRestant -= (float) $frais->montant;
        }

        if ($montantRestant > 0) {
            Paiement::create([
                'reference' => $this->genererReferencePaiement(),
                'inscription_id' => $inscription->id,
                'type' => 'avance',
                'libelle' => 'Avance inscription',
                'details' => null,
                'montant' => $montantRestant,
                'date_paiement' => now(),
                'utilisateur_id' => $userId,
            ]);
        }

        $this->mettreAJourResumePaiement($resume);
    }

    private function mettreAJourResumePaiement(ResumePaiement $resume): void
    {
        $totalPaye = (float) Paiement::where('inscription_id', $resume->inscription_id)->sum('montant');

        $resume->update([
            'total_paye' => $totalPaye,
            'total_restant' => max((float) $resume->total_du - $totalPaye, 0),
        ]);
    }

    private function getTypeFrais(string $libelle, ?int $anneeScolaireId): ?TypeFrais
    {
        return TypeFrais::where('libelle', $libelle)
            ->where(function ($query) use ($anneeScolaireId) {
                if ($anneeScolaireId) {
                    $query->where('annee_scolaire_id', $anneeScolaireId)
                        ->orWhereNull('annee_scolaire_id');
                } else {
                    $query->whereNull('annee_scolaire_id');
                }
            })
            ->orderByRaw('CASE WHEN annee_scolaire_id IS NULL THEN 1 ELSE 0 END')
            ->first();
    }

    private function getLibelleScolarite(string $cycle): string
    {
        return match ($cycle) {
            'primaire' => 'Scolarité - Primaire',
            'college' => 'Scolarité - Collège',
            'lycee' => 'Scolarité - Lycée',
            default => 'Scolarité',
        };
    }

    private function compterMoisScolaires(AnneeScolaire $anneeScolaire): int
    {
        $dateDebut = Carbon::parse($anneeScolaire->date_debut)->startOfMonth();
        $dateFin = Carbon::parse($anneeScolaire->date_fin)->startOfMonth();

        return $dateDebut->diffInMonths($dateFin) + 1;
    }

    private function genererMatricule(string $cycle): string
    {
        $code = match ($cycle) {
            'primaire' => 'PR',
            'college' => 'CL',
            'lycee' => 'LY',
            default => 'XX',
        };

        $lastId = Eleve::max('id') ?? 0;

        return 'REG-' . $code . '-' . date('Y') . '-' . str_pad($lastId + 1, 4, '0', STR_PAD_LEFT);
    }

    private function genererReferencePaiement(): string
    {
        $lastId = Paiement::max('id') ?? 0;

        return 'PAY-' . date('Y') . '-' . str_pad($lastId + 1, 6, '0', STR_PAD_LEFT);
    }
}
