<?php

namespace App\Http\Controllers\Inscription;

use App\Http\Controllers\Controller;
use App\Models\Inscription\AnneeScolaire;
use App\Models\Inscription\Classe;
use App\Models\Inscription\Eleve;
use App\Models\Inscription\FraisApplique;
use App\Models\Inscription\Inscription;
use App\Models\Inscription\Paiement;
use App\Models\Inscription\Reinscription;
use App\Models\Inscription\TypeFrais;
use App\Models\Paiement\ResumePaiement;
use App\Models\Finance\Caisse;
use App\Models\Finance\CategorieEntree;
use App\Models\Finance\Entree;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class ReinscriptionController extends Controller
{
    public function rechercherParMatricule(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'matricule' => 'required|string',
            'annee_scolaire_id' => 'required|exists:annee_scolaires,id',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $eleve = Eleve::where('matricule', $request->matricule)
            ->with(['inscriptions' => function ($q) {
                $q->with(['classe.niveau', 'anneeScolaire']);
            }])
            ->first();

        if (!$eleve) {
            return response()->json([
                'message' => 'Aucun eleve trouve avec ce matricule',
                'matricule_recherche' => $request->matricule,
            ], 404);
        }

        $dejaReinscrit = Reinscription::where('eleve_id', $eleve->id)
            ->where('annee_scolaire_id', $request->annee_scolaire_id)
            ->exists();

        $derniereInscription = $eleve->inscriptions()
            ->with(['classe.niveau', 'anneeScolaire'])
            ->latest('date_inscription')
            ->first();

        if (!$derniereInscription) {
            return response()->json([
                'message' => 'Cet eleve n a pas d inscription anterieure',
                'eleve' => [
                    'id' => $eleve->id,
                    'matricule' => $eleve->matricule,
                    'nom' => $eleve->nom,
                    'prenom' => $eleve->prenom,
                ],
            ], 400);
        }

        $classeActuelle = $derniereInscription->classe;
        $classeSuperieure = null;

        if ($classeActuelle) {
            $classeSuperieure = Classe::where('niveau_id', $classeActuelle->niveau_id + 1)->first();
        }

        return response()->json([
            'eleve' => [
                'id' => $eleve->id,
                'matricule' => $eleve->matricule,
                'nom' => $eleve->nom,
                'prenom' => $eleve->prenom,
                'sexe' => $eleve->sexe ?? null,
                'date_naissance' => $eleve->date_naissance ?? null,
                'lieu_naissance' => $eleve->lieu_naissance ?? null,
            ],
            'derniere_inscription' => [
                'id' => $derniereInscription->id,
                'annee_scolaire' => $derniereInscription->anneeScolaire->libelle ?? null,
                'classe' => $classeActuelle->nom_classe ?? null,
                'classe_id' => $classeActuelle->id ?? null,
                'montant_total' => $derniereInscription->montant_total ?? null,
                'date_inscription' => $derniereInscription->date_inscription ?? null,
            ],
            'classe_actuelle' => $classeActuelle ? [
                'id' => $classeActuelle->id,
                'nom' => $classeActuelle->nom_classe,
                'niveau_id' => $classeActuelle->niveau_id,
                'niveau' => $classeActuelle->niveau->nom_niveau ?? null,
            ] : null,
            'classe_superieure_proposee' => $classeSuperieure ? [
                'id' => $classeSuperieure->id,
                'nom' => $classeSuperieure->nom,
            ] : null,
            'deja_reinscrit' => $dejaReinscrit,
        ]);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'matricule' => 'required|string|exists:eleves,matricule',
            'inscription_id' => 'required|exists:inscriptions,id',
            'annee_scolaire_id' => 'required|exists:annee_scolaires,id',
            'classe_id' => 'nullable|exists:classes,id',
            'statut' => 'required|in:Passant,Redoublant',
            'parascolaire' => 'sometimes|boolean',
            'cantine' => 'sometimes|boolean',
            'montant_verse' => 'nullable|numeric|min:0',
            'date_reinscription' => 'required|date',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $eleve = Eleve::where('matricule', $request->matricule)->first();

        if (!$eleve) {
            return response()->json([
                'message' => 'Eleve non trouve avec le matricule : ' . $request->matricule,
            ], 404);
        }

        $ancienneInscription = Inscription::with('classe.niveau')->find($request->inscription_id);

        if (!$ancienneInscription || (int) $ancienneInscription->id_eleve !== (int) $eleve->id) {
            return response()->json([
                'message' => 'L inscription source ne correspond pas a cet eleve',
            ], 422);
        }

        $anneeScolaire = AnneeScolaire::find($request->annee_scolaire_id);

        if (!$anneeScolaire) {
            return response()->json([
                'message' => 'Annee scolaire introuvable',
            ], 404);
        }

        $nowDate = now()->toDateString();
        // DESACTIVE POUR LES TESTS
        /*
        if ($anneeScolaire->date_debut_inscription && $nowDate < $anneeScolaire->date_debut_inscription) {
            return response()->json([
                'message' => 'La periode de reinscription n a pas encore commence.',
            ], 422);
        }

        if ($anneeScolaire->date_fin_inscription && $nowDate > $anneeScolaire->date_fin_inscription) {
            return response()->json([
                'message' => 'La periode de reinscription est terminee.',
            ], 422);
        }
        */

        if ($request->filled('classe_id')) {
            $classeCible = Classe::with('niveau')->find($request->classe_id);
            if (!$classeCible) {
                return response()->json([
                    'message' => 'Classe cible introuvable',
                ], 404);
            }
            if ($classeCible->estPleine()) {
                return response()->json([
                    'message' => 'La classe ' . $classeCible->nom_classe . ' est pleine (max ' . ($classeCible->max_effectif ?? 50) . ' eleves).',
                ], 422);
            }
        } else {
            $niveauCibleId = $ancienneInscription->classe->niveau_id;
            if ($request->statut === 'Passant') {
                $niveauCibleId++;
            }
            $classeCible = $this->trouverClasseDisponible($niveauCibleId, $anneeScolaire->id);
            
            if (!$classeCible) {
                return response()->json([
                    'message' => 'Aucune classe disponible pour le niveau cible. Veuillez contacter l administrateur.',
                ], 422);
            }
        }

        $existe = Reinscription::where('eleve_id', $eleve->id)
            ->where('annee_scolaire_id', $request->annee_scolaire_id)
            ->exists();

        if ($existe) {
            return response()->json([
                'message' => 'Cet eleve est deja reinscrit pour l annee scolaire choisie',
            ], 409);
        }

        $inscriptionExistante = Inscription::where('id_eleve', $eleve->id)
            ->where('id_annee_scolaire', $request->annee_scolaire_id)
            ->exists();

        if ($inscriptionExistante) {
            return response()->json([
                'message' => 'Une inscription existe deja pour cet eleve dans l annee scolaire cible',
            ], 409);
        }

        DB::beginTransaction();

        try {
            $nouvelleInscription = Inscription::create([
                'id_eleve' => $eleve->id,
                'id_classe' => $classeCible->id,
                'id_annee_scolaire' => $anneeScolaire->id,
                'date_inscription' => $request->date_reinscription,
                'parascolaire' => $request->boolean('parascolaire'),
                'cantine' => $request->boolean('cantine'),
                'montant_total' => 0,
                'montant_net' => 0,
                'utilisateur_id' => Auth::id(),
            ]);

            $classeCible->increment('effectif');

            $montantTotal = $this->appliquerFrais($nouvelleInscription, $classeCible->niveau->cycle, $anneeScolaire);

            $nouvelleInscription->update([
                'montant_total' => $montantTotal,
                'montant_net' => $montantTotal,
            ]);

            $resume = $this->creerOuMettreAJourResumePaiement($nouvelleInscription, $montantTotal);
            $montantVerse = (float) $request->input('montant_verse', 0);

            if ($montantVerse > 0) {
                $this->enregistrerPaiementInitial($nouvelleInscription, $resume, $montantVerse, Auth::id());
                $resume->refresh();
            }

            $reinscription = Reinscription::create([
                'inscription_id' => $ancienneInscription->id,
                'nouvelle_inscription_id' => $nouvelleInscription->id,
                'eleve_id' => $eleve->id,
                'annee_scolaire_id' => $anneeScolaire->id,
                'classe_id' => $classeCible->id,
                'statut' => $request->statut,
                'montant_reinscription' => $montantTotal,
                'parascolaire' => $request->boolean('parascolaire'),
                'cantine' => $request->boolean('cantine'),
                'est_paye' => (float) $resume->total_restant <= 0,
                'date_reinscription' => $request->date_reinscription,
                'utilisateur_id' => Auth::id(),
            ]);

            // Notification pour l'administration
            \App\Http\Controllers\NotificationController::push(
                "Réinscription",
                "Réinscription effectuée : {$eleve->nom} {$eleve->prenom} (Classe: {$classeCible->nom_classe})",
                'success',
                null,
                "/admin/etudiant/{$nouvelleInscription->id}"
            );

            DB::commit();

            return response()->json([
                'message' => 'Reinscription enregistree avec succes',
                'reinscription' => [
                    'id' => $reinscription->id,
                    'matricule_eleve' => $eleve->matricule,
                    'eleve_nom' => $eleve->nom,
                    'eleve_prenom' => $eleve->prenom,
                    'ancienne_inscription_id' => $reinscription->inscription_id,
                    'nouvelle_inscription_id' => $reinscription->nouvelle_inscription_id,
                    'classe_id' => $reinscription->classe_id,
                    'annee_scolaire_id' => $reinscription->annee_scolaire_id,
                    'statut' => $reinscription->statut,
                    'montant_reinscription' => $reinscription->montant_reinscription,
                    'montant_verse' => $montantVerse,
                    'est_paye' => $reinscription->est_paye,
                    'date_reinscription' => $reinscription->date_reinscription,
                ],
            ], 201);
        } catch (\Throwable $e) {
            DB::rollBack();

            return response()->json([
                'message' => 'Erreur lors de l enregistrement',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function index(Request $request)
    {
        $query = Reinscription::with(['eleve', 'classe.niveau', 'anneeScolaire', 'nouvelleInscription']);

        if ($request->has('annee_scolaire_id')) {
            $query->where('annee_scolaire_id', $request->annee_scolaire_id);
        }

        if ($request->has('classe_id')) {
            $query->where('classe_id', $request->classe_id);
        }

        if ($request->has('statut')) {
            $query->where('statut', $request->statut);
        }

        if ($request->has('matricule')) {
            $query->whereHas('eleve', function ($q) use ($request) {
                $q->where('matricule', 'like', '%' . $request->matricule . '%');
            });
        }

        $reinscriptions = $query->orderBy('created_at', 'desc')->paginate(20);

        return response()->json($reinscriptions);
    }

    public function show($id)
    {
        $reinscription = Reinscription::with([
            'eleve',
            'classe.niveau',
            'anneeScolaire',
            'inscription.classe.niveau',
            'nouvelleInscription.classe.niveau',
            'nouvelleInscription.anneeScolaire',
            'nouvelleInscription.resumePaiement',
            'utilisateur',
        ])->find($id);

        if (!$reinscription) {
            return response()->json(['message' => 'Reinscription non trouvee'], 404);
        }

        return response()->json($reinscription);
    }

    public function updatePaiement($id)
    {
        $reinscription = Reinscription::find($id);

        if (!$reinscription) {
            return response()->json(['message' => 'Reinscription non trouvee'], 404);
        }

        $reinscription->update(['est_paye' => true]);

        return response()->json([
            'message' => 'Statut de paiement mis a jour',
            'est_paye' => true,
        ]);
    }

    public function destroy($id)
    {
        $reinscription = Reinscription::find($id);

        if (!$reinscription) {
            return response()->json(['message' => 'Reinscription non trouvee'], 404);
        }

        $reinscription->delete();

        return response()->json([
            'message' => 'Reinscription supprimee avec succes',
        ]);
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

        if ($inscription->cantine) {
            $montantTotal += $this->ajouterFrais($inscription, 'Cantine');
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
                $query->whereNotIn('libelle', [
                    'Scolarite',
                    'Scolarite - Primaire',
                    'Scolarite - College',
                    'Scolarite - Lycee',
                    'Scolarité',
                    'Scolarité - Primaire',
                    'Scolarité - Collège',
                    'Scolarité - Lycée',
                    'Cantine',
                ]);
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

        // --- INTEGRATION FINANCE ---
        if ($montantVerse > 0) {
            $typeInscription = CategorieEntree::where('nom', 'Inscription')->first();
            if ($typeInscription) {
                Entree::create([
                    'reference' => 'ENT-REI-' . time(),
                    'montant' => $montantVerse,
                    'date_entree' => now(),
                    'type_entree_id' => $typeInscription->id,
                    'inscription_id' => $inscription->id,
                    'annee_scolaire_id' => $inscription->id_annee_scolaire,
                    'description' => 'Paiement initial lors de la réinscription',
                    'created_by' => $userId
                ]);

                $caisse = Caisse::firstOrCreate(
                    ['annee_scolaire_id' => $inscription->id_annee_scolaire],
                    ['nom' => 'Caisse Principale', 'solde' => 0]
                );
                $caisse->increment('solde', $montantVerse);
            }
        }
        // ---------------------------

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

    private function genererReferencePaiement(): string
    {
        $lastId = Paiement::max('id') ?? 0;

        return 'PAY-' . date('Y') . '-' . str_pad($lastId + 1, 6, '0', STR_PAD_LEFT);
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

    private function trouverClasseDisponible(int $niveauId, int $anneeScolaireId): ?Classe
    {
        return Classe::where('niveau_id', $niveauId)
            ->where('anneeScolaire_id', $anneeScolaireId)
            ->whereRaw('effectif < COALESCE(max_effectif, 50)')
            ->orderBy('code_division', 'asc')
            ->first();
    }
}
