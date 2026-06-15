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
use App\Models\Finance\Caisse;
use App\Models\Finance\CategorieEntree;
use App\Models\Finance\Entree;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class InscriptionController extends Controller
{
    public function index(Request $request)
    {
        $query = Inscription::with([
            'eleve',
            'classe.niveau',
            'anneeScolaire',
            'resumePaiement',
        ]);

        if ($request->has('inscription_id')) {
            $query->where('id', $request->inscription_id);
        }

        if ($request->has('id')) {
            $query->where('id', $request->id);
        }

        if ($request->has('annee_scolaire_id')) {
            $query->where('id_annee_scolaire', $request->annee_scolaire_id);
        }

        if ($request->has('classe_id')) {
            $query->where('id_classe', $request->classe_id);
        }

        if ($request->has('niveau_id')) {
            $query->whereHas('classe.niveau', function ($q) use ($request) {
                $val = $request->niveau_id;
                if (is_numeric($val)) {
                    $q->where('id', $val);
                } else {
                    $q->where('nom_niveau', $val);
                }
            });
        }

        if ($request->has('cycle')) {
            $query->whereHas('classe.niveau', function ($q) use ($request) {
                $q->where('cycle', $request->cycle);
            });
        }

        if ($request->has('search')) {
            $search = $request->search;
            $query->whereHas('eleve', function ($q) use ($search) {
                $q->where('nom', 'like', '%' . $search . '%')
                  ->orWhere('prenom', 'like', '%' . $search . '%')
                  ->orWhere('matricule', 'like', '%' . $search . '%');
            });
        }

        if ($request->has('statut_paiement')) {
            $statut = $request->statut_paiement; // 'paye' ou 'non_paye'
            $query->whereHas('resumePaiement', function ($q) use ($statut) {
                if ($statut === 'paye') {
                    $q->where('total_restant', '<=', 0);
                } else if ($statut === 'non_paye') {
                    $q->where('total_restant', '>', 0);
                }
            });
        }

        $inscriptions = $query->get();

        return response()->json([
            'success' => true,
            'data'    => $inscriptions,
        ]);
    }

    public function store(Request $request)
    {
        $currentUser   = $request->user();
        $utilisateurId = $currentUser ? (int) $currentUser->getKey() : null;

        $payload = $request->all();
        if (isset($payload['classe_id']) && (int) $payload['classe_id'] === 0) {
            $payload['classe_id'] = null;
        }

        $validator = Validator::make($payload, [
            'nom'                    => 'required|string|max:100',
            'prenom'                 => 'nullable|string|max:100',
            'date_naissance'         => 'required|date|before:today',
            'lieu_naissance'         => 'required|string|max:150',
            'sexe'                   => 'required|in:M,F',
            'niveau_id'              => 'required|exists:niveaux,id',
            // classe_id devient optionnel : si absent, attribution automatique
            'classe_id'              => 'nullable|exists:classes,id',
            'adresse'                => 'nullable|string|max:255',
            'parascolaire'           => 'sometimes|boolean',
            'cantine'                => 'sometimes|boolean',
            'montant_verse'          => 'nullable|numeric|min:0',
            'responsable_nom'        => 'nullable|string|max:150',
            'responsable_telephone'  => 'nullable|string|max:30',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors'  => $validator->errors(),
            ], 422);
        }

        $anneeActive = AnneeScolaire::where('statut', 'en_cours')->first();

        if (!$anneeActive) {
            return response()->json([
                'success' => false,
                'message' => 'Inscription non trouvée (aucune année scolaire active).',
            ], 404);
        }

        $nowDate = now()->toDateString();
        // DESACTIVE POUR LES TESTS
        /*
        if ($anneeActive->date_debut_inscription && $nowDate < $anneeActive->date_debut_inscription) {
            return response()->json([
                'success' => false,
                'message' => 'La periode d inscription n a pas encore commence.',
            ], 422);
        }

        if ($anneeActive->date_fin_inscription && $nowDate > $anneeActive->date_fin_inscription) {
            return response()->json([
                'success' => false,
                'message' => 'La periode d inscription est terminee.',
            ], 422);
        }
        */

        // Résoudre la classe : manuelle ou automatique
        $classeId = (int) $request->input('classe_id');
        if ($classeId > 0) {
            $classe = Classe::with('niveau')->find($classeId);

            if (!$classe) {
                return response()->json([
                    'success' => false,
                    'message' => 'Classe non trouvee.',
                ], 404);
            }

            if ((int) $classe->niveau_id !== (int) $request->input('niveau_id')) {
                return response()->json([
                    'success' => false,
                    'message' => 'La classe selectionnee ne correspond pas au niveau fourni.',
                ], 422);
            }

            if ($classe->estPleine()) {
                return response()->json([
                    'success' => false,
                    'message' => 'La classe ' . $classe->nom_classe . ' est pleine (max ' . ($classe->max_effectif ?? 50) . ' eleves). Choisissez une autre classe ou laissez le systeme en choisir une automatiquement.',
                ], 422);
            }
        } else {
            // Attribution automatique : première classe disponible du niveau
            $classe = $this->trouverClasseDisponible($request->input('niveau_id'), $anneeActive->id);

            if (!$classe) {
                return response()->json([
                    'success' => false,
                    'message' => 'Aucune classe disponible pour ce niveau. Toutes les classes sont pleines. Veuillez contacter l administrateur.',
                ], 422);
            }
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
                    'nom'             => $request->nom,
                    'prenom'          => $request->prenom,
                    'date_naissance'  => $request->date_naissance,
                    'lieu_naissance'  => $request->lieu_naissance,
                    'sexe'            => $request->sexe,
                    'adresse'         => $request->adresse,
                    'matricule'       => $this->genererMatricule($classe->niveau->cycle),
                ]);
            }

            if (Inscription::where('id_eleve', $eleve->id)
                ->where('id_annee_scolaire', $anneeActive->id)
                ->exists()) {
                DB::rollBack();

                return response()->json([
                    'success' => false,
                    'message' => 'Cet élève est déjà inscrit pour l’année scolaire active.',
                ], 422);
            }

            $this->enregistrerInformationDynamique($eleve->id, 'responsable_nom', $request->responsable_nom);
            $this->enregistrerInformationDynamique($eleve->id, 'responsable_telephone', $request->responsable_telephone);

            $inscription = Inscription::create([
                'id_eleve'          => $eleve->id,
                'id_classe'         => $classe->id,
                'id_annee_scolaire' => $anneeActive->id,
                'date_inscription'  => now(),
                'parascolaire'      => $request->boolean('parascolaire'),
                'cantine'           => $request->boolean('cantine'),
                'description_frais' => $request->input('description_frais'),
                'montant_total'     => 0,
                'montant_net'       => 0,
                'utilisateur_id'    => $utilisateurId,
            ]);

            // Incrémenter l'effectif de la classe
            $classe->increment('effectif');

            $montantTotal = $this->appliquerFrais($inscription, $classe->niveau->cycle, $anneeActive, $request);

            $inscription->update([
                'montant_total' => $montantTotal,
                'montant_net'   => $montantTotal,
            ]);

            $resume = $this->creerOuMettreAJourResumePaiement($inscription, $montantTotal);

            $montantVerse = (float) $request->input('montant_verse', 0);

            if ($montantVerse > 0) {
                $this->enregistrerPaiementInitial($inscription, $resume, $request, $utilisateurId);
            }
            $this->mettreAJourResumePaiement($resume);

            // Notification pour l'administration
            \App\Http\Controllers\NotificationController::push(
                "Nouvelle Inscription",
                "Nouvel étudiant inscrit : {$eleve->nom} {$eleve->prenom} (Matricule: {$eleve->matricule})",
                'success',
                null, // Tous les admins
                "/caissier/paiement?student_id={$inscription->id}"
            );

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Inscription reussie',
                'data'    => [
                    'matricule'              => $eleve->matricule,
                    'eleve_id'               => $eleve->id,
                    'inscription_id'         => $inscription->id,
                    'classe'                 => $classe->nom_classe,
                    'niveau'                 => $classe->niveau->nom_niveau,
                    'cycle'                  => $classe->niveau->cycle,
                    'annee_scolaire_id'      => $anneeActive->id,
                    'montant_total'          => $montantTotal,
                    'montant_verse'          => $montantVerse,
                    'resume'                 => $resume->fresh(),
                    'nombre_mois_scolarite'  => $this->compterMoisScolaires($anneeActive),
                    'effectif_classe'        => $classe->fresh()->effectif,
                    'max_effectif_classe'    => $classe->max_effectif ?? 50,
                ],
            ], 201);
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('Inscription store failed', ['exception' => $e]);

            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de l inscription',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

    public function show(int $id)
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
            'data'    => $inscription,
        ]);
    }

    public function update(Request $request, int $id)
    {
        $inscription = Inscription::with('eleve')->find($id);

        if (!$inscription) {
            return response()->json([
                'success' => false,
                'message' => 'Inscription non trouvee',
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'nom'                   => 'sometimes|required|string|max:100',
            'prenom'                => 'sometimes|nullable|string|max:100',
            'date_naissance'        => 'sometimes|required|date|before:today',
            'lieu_naissance'        => 'sometimes|required|string|max:150',
            'sexe'                  => 'sometimes|required|in:M,F',
            'niveau_id'             => 'sometimes|required|exists:niveaux,id',
            'classe_id'             => 'sometimes|nullable|exists:classes,id',
            'adresse'               => 'sometimes|nullable|string|max:255',
            'parascolaire'          => 'sometimes|boolean',
            'cantine'               => 'sometimes|boolean',
            'responsable_nom'       => 'sometimes|nullable|string|max:150',
            'responsable_telephone' => 'sometimes|nullable|string|max:30',
            'dynamic_infos'         => 'sometimes|array',
            'dynamic_infos.*.libelle' => 'sometimes|required|string',
            'dynamic_infos.*.valeur' => 'sometimes|required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors'  => $validator->errors(),
            ], 422);
        }

        $eleve = $inscription->eleve;
        if (!$eleve) {
            return response()->json([
                'success' => false,
                'message' => 'Eleve lie a l inscription non trouve',
            ], 404);
        }

        DB::beginTransaction();

        try {
            if ($request->filled('classe_id') && $request->classe_id !== $inscription->id_classe) {
                $nouvelleClasse = Classe::find($request->classe_id);

                if (!$nouvelleClasse) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Classe non trouvee.',
                    ], 404);
                }

                if ($request->filled('niveau_id') && (int) $nouvelleClasse->niveau_id !== (int) $request->niveau_id) {
                    return response()->json([
                        'success' => false,
                        'message' => 'La classe selectionnee ne correspond pas au niveau fourni.',
                    ], 422);
                }

                if ($nouvelleClasse->estPleine()) {
                    return response()->json([
                        'success' => false,
                        'message' => 'La classe ' . $nouvelleClasse->nom_classe . ' est pleine. Choisissez une autre classe.',
                    ], 422);
                }

                $ancienneClasse = Classe::find($inscription->id_classe);
                if ($ancienneClasse && $ancienneClasse->id !== $nouvelleClasse->id) {
                    $ancienneClasse->decrement('effectif');
                    $nouvelleClasse->increment('effectif');
                }

                $inscription->id_classe = $nouvelleClasse->id;
            }

            if ($request->filled('nom')) {
                $eleve->nom = $request->nom;
            }
            if ($request->filled('prenom')) {
                $eleve->prenom = $request->prenom;
            }
            if ($request->filled('date_naissance')) {
                $eleve->date_naissance = $request->date_naissance;
            }
            if ($request->filled('lieu_naissance')) {
                $eleve->lieu_naissance = $request->lieu_naissance;
            }
            if ($request->filled('sexe')) {
                $eleve->sexe = $request->sexe;
            }
            if ($request->filled('adresse')) {
                $eleve->adresse = $request->adresse;
            }
            $eleve->save();

            if ($request->has('parascolaire')) {
                $oldValue = $inscription->parascolaire;
                $newValue = $request->boolean('parascolaire');
                $inscription->parascolaire = $newValue;

                if (!$oldValue && $newValue) {
                    $cycle = $inscription->classe?->niveau?->cycle ?? '';
                    $niveauNom = $inscription->classe?->niveau?->nom ?? '';

                    $existe = $inscription->fraisAppliques()->whereHas('typeFrais', function ($q) {
                        $q->where('libelle', 'like', '%parascolaire%')
                          ->orWhere('libelle', 'like', '%para-scolaire%')
                          ->orWhere('libelle', 'like', '%para scolaire%');
                    })->exists();

                    if (!$existe) {
                        $addedAmount = $this->ajouterFrais($inscription, 'Parascolaire', 1, $cycle, $niveauNom);
                        if ($addedAmount > 0 && $inscription->resumePaiement) {
                            $inscription->resumePaiement->increment('total_du', $addedAmount);
                            $inscription->resumePaiement->increment('total_restant', $addedAmount);
                            $inscription->increment('montant_total', $addedAmount);
                            $inscription->increment('montant_net', $addedAmount);
                        }
                    }
                }
            }
            if ($request->has('cantine')) {
                $oldValue = $inscription->cantine;
                $newValue = $request->boolean('cantine');
                $inscription->cantine = $newValue;

                if (!$oldValue && $newValue) {
                    $cycle = $inscription->classe?->niveau?->cycle ?? '';
                    $niveauNom = $inscription->classe?->niveau?->nom ?? '';

                    $existe = $inscription->fraisAppliques()->whereHas('typeFrais', function ($q) {
                        $q->where('libelle', 'like', '%cantine%');
                    })->exists();

                    if (!$existe) {
                        $addedAmount = $this->ajouterFrais($inscription, 'Cantine', 1, $cycle, $niveauNom);
                        if ($addedAmount > 0 && $inscription->resumePaiement) {
                            $inscription->resumePaiement->increment('total_du', $addedAmount);
                            $inscription->resumePaiement->increment('total_restant', $addedAmount);
                            $inscription->increment('montant_total', $addedAmount);
                            $inscription->increment('montant_net', $addedAmount);
                        }
                    }
                }
            }

            $inscription->save();

            if ($request->filled('responsable_nom')) {
                $this->enregistrerInformationDynamique($eleve->id, 'responsable_nom', $request->responsable_nom);
            }
            if ($request->filled('responsable_telephone')) {
                $this->enregistrerInformationDynamique($eleve->id, 'responsable_telephone', $request->responsable_telephone);
            }
            if ($request->has('dynamic_infos') && is_array($request->dynamic_infos)) {
                foreach ($request->dynamic_infos as $info) {
                    if (!empty($info['libelle']) && isset($info['valeur'])) {
                        $this->enregistrerInformationDynamique($eleve->id, $info['libelle'], $info['valeur']);
                    }
                }
            }

            DB::commit();

            $inscription->load([
                'eleve.infosDynamiques',
                'classe.niveau',
                'anneeScolaire',
                'fraisAppliques.typeFrais',
                'paiements.utilisateur',
                'paiements.typeFrais',
                'resumePaiement.paiementsMensuels.paiement',
                'presencesCantine.paiement',
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Inscription mise a jour',
                'data'    => $inscription,
            ]);
        } catch (\Throwable $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la mise a jour de l inscription',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

    public function destroy(int $id)
    {
        DB::beginTransaction();
        try {
            $inscription = Inscription::find($id);

            if (!$inscription) {
                return response()->json([
                    'success' => false,
                    'message' => 'Inscription non trouvee',
                ], 404);
            }

            $classe = Classe::find($inscription->id_classe);
            if ($classe && $classe->effectif > 0) {
                $classe->decrement('effectif');
            }

            // Supprimer les résumés et paiements liés pour éviter les erreurs de clés étrangères
            $inscription->fraisAppliques()->delete();
            
            $resume = $inscription->resumePaiement()->first();
            if ($resume) {
                $resume->paiementsMensuels()->delete();
                $resume->delete();
            }

            $inscription->paiements->each(function ($paiement) {
                if ($paiement->recu) {
                    $paiement->recu()->delete();
                }
                $paiement->delete();
            });

            $inscription->delete();

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Inscription supprimee avec succes',
            ]);
        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la suppression',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

    public function getDynamicInfos(int $id)
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
            'data'    => [
                'inscription_id'   => $inscription->id,
                'eleve_id'         => $inscription->id_eleve,
                'infos_dynamiques' => $infos,
            ],
        ]);
    }

    /**
     * Trouve la première classe disponible (non pleine) pour un niveau donné.
     * Trie par code_division (A, B, C...) pour remplir dans l'ordre.
     */
    private function trouverClasseDisponible(int $niveauId, int $anneeScolaireId): ?Classe
    {
        return Classe::where('niveau_id', $niveauId)
            ->where('anneeScolaire_id', $anneeScolaireId)
            ->whereRaw('effectif < COALESCE(max_effectif, 50)')
            ->orderBy('code_division', 'asc')
            ->first();
    }

    /**
     * Retourne les classes d'un niveau avec leur disponibilité.
     */
    public function getClasseAuto(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'niveau_id' => 'required|exists:niveaux,id',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        $anneeActive = AnneeScolaire::where('statut', 'en_cours')->first();

        if (!$anneeActive) {
            return response()->json(['success' => false, 'message' => 'Aucune annee scolaire active.'], 404);
        }

        $classes = Classe::where('niveau_id', $request->niveau_id)
            ->where('anneeScolaire_id', $anneeActive->id)
            ->orderBy('code_division', 'asc')
            ->get()
            ->map(function (Classe $classe) {
                $max = $classe->max_effectif ?? 50;
                return [
                    'id'            => $classe->id,
                    'nom_classe'    => $classe->nom_classe,
                    'code_division' => $classe->code_division,
                    'effectif'      => $classe->effectif,
                    'max_effectif'  => $max,
                    'places_restantes' => max($max - $classe->effectif, 0),
                    'est_pleine'    => $classe->estPleine(),
                ];
            });

        $classeDisponible = $classes->firstWhere('est_pleine', false);

        return response()->json([
            'success'           => true,
            'classe_suggeree'   => $classeDisponible,
            'toutes_les_classes' => $classes,
        ]);
    }

    private function enregistrerInformationDynamique(int $eleveId, string $champ, ?string $valeur): void
    {
        if (!$valeur) {
            return;
        }

        AutreInformation::updateOrCreate(
            ['id_eleve' => $eleveId, 'nom_champ' => $champ],
            ['valeur_champ' => $valeur]
        );
    }

    private function appliquerFrais(Inscription $inscription, string $cycle, AnneeScolaire $anneeScolaire, Request $request = null): float
    {
        $montantTotal = 0;
        $niveauNom = $inscription->classe?->niveau?->nom ?? '';

        if ($request && $request->has('selected_frais') && $request->has('payment_frais_details')) {
            $selectedFrais = $request->input('selected_frais', []);
            $fraisDetails = $request->input('payment_frais_details', []);

            $appliedIds = [];

            foreach ($selectedFrais as $index) {
                if (isset($fraisDetails[$index]['id'])) {
                    $typeFraisId = $fraisDetails[$index]['id'];
                    $typeFrais = TypeFrais::find($typeFraisId);

                    if ($typeFrais) {
                        // Check if it's scolarité to apply multiplier
                        $normalizedLibelle = str_replace(['é', 'è', 'ê'], 'e', strtolower($typeFrais->libelle));
                        $isScolarite = str_contains($normalizedLibelle, 'scolarit') || str_contains($normalizedLibelle, 'ecolage') || str_contains($normalizedLibelle, 'mensualit') || str_contains($normalizedLibelle, 'mensuel') || str_contains($normalizedLibelle, 'pension');

                        $multiplicateur = 1;
                        if ($isScolarite) {
                            $multiplicateur = $this->compterMoisScolaires($anneeScolaire);
                        }

                        $montant = (float) $typeFrais->montant * max($multiplicateur, 1);

                        FraisApplique::create([
                            'id_frais'       => $typeFrais->id,
                            'id_inscription' => $inscription->id,
                            'montant'        => $montant,
                        ]);

                        $montantTotal += $montant;
                        $appliedIds[] = $typeFrais->id;
                    }
                }
            }

            // On s'assure d'appliquer la scolarité obligatoire et l'inscription obligatoire si pas dans la liste
            $inscriptionFee = $this->getTypeFrais('Inscription', $anneeScolaire->id, $cycle, $niveauNom);
            if ($inscriptionFee && !in_array($inscriptionFee->id, $appliedIds)) {
                $montantTotal += $this->ajouterFrais($inscription, 'Inscription', 1, $cycle, $niveauNom);
            }

            $scolariteFee = $this->getTypeFrais($this->getLibelleScolarite($cycle), $anneeScolaire->id, $cycle, $niveauNom);
            if ($scolariteFee && !in_array($scolariteFee->id, $appliedIds)) {
                $montantTotal += $this->ajouterFrais($inscription, $this->getLibelleScolarite($cycle), $this->compterMoisScolaires($anneeScolaire), $cycle, $niveauNom);
                $appliedIds[] = $scolariteFee->id;
            }

            if ($inscription->cantine) {
                $cantineFee = $this->getTypeFrais('Cantine', $anneeScolaire->id, $cycle, $niveauNom);
                if ($cantineFee && !in_array($cantineFee->id, $appliedIds)) {
                    $montantTotal += $this->ajouterFrais($inscription, 'Cantine', 1, $cycle, $niveauNom);
                    $appliedIds[] = $cantineFee->id;
                }
            }

            if ($inscription->parascolaire) {
                $paraFee = $this->getTypeFrais('Parascolaire', $anneeScolaire->id, $cycle, $niveauNom);
                if ($paraFee && !in_array($paraFee->id, $appliedIds)) {
                    $montantTotal += $this->ajouterFrais($inscription, 'Parascolaire', 1, $cycle, $niveauNom);
                    $appliedIds[] = $paraFee->id;
                }
            }

        } else {
            // Fallback old logic
            $montantTotal += $this->ajouterFrais($inscription, 'Inscription', 1, $cycle, $niveauNom);
            $montantTotal += $this->ajouterFrais(
                $inscription,
                $this->getLibelleScolarite($cycle),
                $this->compterMoisScolaires($anneeScolaire),
                $cycle,
                $niveauNom
            );
            $montantTotal += $this->ajouterFrais($inscription, 'Frais technologiques', 1, $cycle, $niveauNom);

            if ($inscription->parascolaire) {
                $montantTotal += $this->ajouterFrais($inscription, 'Parascolaire', 1, $cycle, $niveauNom);
            }

            if ($inscription->cantine) {
                $montantTotal += $this->ajouterFrais($inscription, 'Cantine', 1, $cycle, $niveauNom);
            }
        }

        return $montantTotal;
    }

    private function ajouterFrais(Inscription $inscription, string $libelle, int $multiplicateur = 1, string $cycle = '', string $niveau = ''): float
    {
        $typeFrais = $this->getTypeFrais($libelle, $inscription->id_annee_scolaire, $cycle, $niveau);

        if (!$typeFrais) {
            return 0;
        }

        $montant = (float) $typeFrais->montant * max($multiplicateur, 1);

        FraisApplique::create([
            'id_frais'       => $typeFrais->id,
            'id_inscription' => $inscription->id,
            'montant'        => $montant,
        ]);

        return $montant;
    }

    private function creerOuMettreAJourResumePaiement(Inscription $inscription, float $montantTotal): ResumePaiement
    {
        return ResumePaiement::updateOrCreate(
            ['inscription_id' => $inscription->id],
            [
                'total_du'      => $montantTotal,
                'total_paye'    => 0,
                'total_restant' => $montantTotal,
            ]
        );
    }

    private function enregistrerPaiementInitial(
        Inscription $inscription,
        ResumePaiement $resume,
        Request $request,
        ?int $userId
    ): void {
        $montantVerse = (float) $request->input('montant_verse', 0);
        $montantRestant = $montantVerse;

        // Paiement des frais simples sélectionnés ou tous les frais simples par défaut
        $selectedFrais = $request->input('selected_frais', []);
        $fraisDetails = $request->input('payment_frais_details', []);

        $fraisSimplesQuery = $inscription->fraisAppliques()
            ->with('typeFrais');

        if (!empty($selectedFrais) && !empty($fraisDetails)) {
            $typeFraisIdsToPay = [];
            foreach ($selectedFrais as $index) {
                if (isset($fraisDetails[$index]['id'])) {
                    $typeFraisIdsToPay[] = $fraisDetails[$index]['id'];
                }
            }

            if (!empty($typeFraisIdsToPay)) {
                $fraisSimplesQuery->whereIn('id_frais', $typeFraisIdsToPay);
            }
        } else {
            $fraisSimplesQuery->whereHas('typeFrais', function ($query) {
                $query->where('libelle', 'not like', '%Scolarit%')
                      ->where('libelle', 'not like', '%Ecolage%')
                      ->where('libelle', 'not like', '%Mensualit%');
            });
        }

        $fraisSimples = $fraisSimplesQuery->orderBy('id')->get();

        foreach ($fraisSimples as $frais) {
            if ($montantRestant <= 0) {
                break;
            }

            $montantAPayer = min($montantRestant, $frais->montant);

            Paiement::create([
                'reference'      => $this->genererReferencePaiement(),
                'inscription_id' => $inscription->id,
                'type_frais_id'  => $frais->id_frais,
                'type'           => 'autre_frais',
                'libelle'        => $frais->typeFrais?->libelle,
                'details'        => null,
                'montant'        => $montantAPayer,
                'date_paiement'  => now(),
                'utilisateur_id' => $userId,
            ]);

            $montantRestant -= (float) $montantAPayer;
        }

        // --- Paiement de la Scolarité si des mois sont sélectionnés ---
        $selectedMonths = $request->input('selected_months', []);
        $paymentSchoolMonths = $request->input('payment_school_months', []);

        if (!empty($selectedMonths) && !empty($paymentSchoolMonths) && $montantRestant > 0) {
            $cycle = $inscription->classe?->niveau?->cycle ?? '';
            $niveauNom = $inscription->classe?->niveau?->nom ?? '';
            $typeFraisScolarite = $this->getTypeFrais($this->getLibelleScolarite($cycle), $inscription->id_annee_scolaire, $cycle, $niveauNom);

            if ($typeFraisScolarite) {
                // Montant mensuel
                $anneeScolaire = $inscription->anneeScolaire ?? AnneeScolaire::where('statut', 'en_cours')->first();
                $nbMois = max($this->compterMoisScolaires($anneeScolaire), 1);
                $montantMensuel = (float)$typeFraisScolarite->montant;

                foreach ($selectedMonths as $mId) {
                    if ($montantRestant <= 0) break;

                    // Chercher le mois dans le tableau
                    $monthObj = collect($paymentSchoolMonths)->firstWhere('id', $mId);
                    if (!$monthObj) continue;

                    $montantAPayer = min($montantRestant, $montantMensuel);

                    if ($montantAPayer > 0) {
                        $paiement = Paiement::create([
                            'reference'      => $this->genererReferencePaiement(),
                            'inscription_id' => $inscription->id,
                            'type_frais_id'  => $typeFraisScolarite->id,
                            'type'           => 'scolarite_mensuelle',
                            'libelle'        => $monthObj['label'] . ' ' . (isset($monthObj['annee']) ? $monthObj['annee'] : date('Y')),
                            'details'        => ['mois' => $monthObj['value'], 'annee' => isset($monthObj['annee']) ? $monthObj['annee'] : date('Y')],
                            'montant'        => $montantAPayer,
                            'date_paiement'  => now(),
                            'utilisateur_id' => $userId,
                        ]);

                        if ($montantAPayer >= ($montantMensuel - 0.01)) {
                            \App\Models\Paiement\PaiementMensuel::create([
                                'resume_id' => $resume->id,
                                'mois' => $monthObj['value'],
                                'annee' => isset($monthObj['annee']) ? $monthObj['annee'] : date('Y'),
                                'montant' => $montantMensuel,
                                'paiement_id' => $paiement->id,
                            ]);
                        }

                        $montantRestant -= $montantAPayer;
                    }
                }
            }
        }

        if ($montantRestant > 0) {
            $paiementScolarite = Paiement::create([
                'reference'      => $this->genererReferencePaiement(),
                'inscription_id' => $inscription->id,
                'type'           => 'scolarite_mensuelle',
                'libelle'        => 'Avance scolarite',
                'details'        => null,
                'montant'        => $montantRestant,
                'date_paiement'  => now(),
                'utilisateur_id' => $userId,
            ]);

            // Allouer le montant aux mois de scolarité
            $typeFraisScolarite = $this->getTypeFrais($this->getLibelleScolarite($inscription->classe->niveau->cycle), $inscription->id_annee_scolaire);
            $montantMensuel = $typeFraisScolarite ? (float) $typeFraisScolarite->montant : 0;
            
            if ($montantMensuel > 0) {
                $anneeScolaire = $inscription->anneeScolaire;
                $dateDebutStr = $anneeScolaire?->date_debut ?? (date('Y') . '-09-01');
                $currentDate = Carbon::parse($dateDebutStr)->startOfMonth();
                
                $montantAlloue = 0;
                while (($montantRestant - $montantAlloue) >= ($montantMensuel - 0.01)) {
                    \App\Models\Paiement\PaiementMensuel::create([
                        'resume_id' => $resume->id,
                        'mois' => $currentDate->month,
                        'annee' => $currentDate->year,
                        'montant' => $montantMensuel,
                        'paiement_id' => $paiementScolarite->id,
                    ]);
                    $montantAlloue += $montantMensuel;
                    $currentDate->addMonth();
                }
            }
        }

        // --- INTEGRATION FINANCE ---
        if ($montantVerse > 0) {
            $typeInscription = CategorieEntree::where('nom', 'like', '%Inscription%')->first()
                            ?? CategorieEntree::firstOrCreate(['nom' => 'Inscription'], ['description' => 'Frais d\'inscription']);

            if ($typeInscription) {
                Entree::create([
                    'reference' => 'ENT-INS-' . time(),
                    'montant' => $montantVerse,
                    'date_entree' => now(),
                    'type_entree_id' => $typeInscription->id,
                    'inscription_id' => $inscription->id,
                    'annee_scolaire_id' => $inscription->id_annee_scolaire,
                    'description' => 'Paiement initial lors de l\'inscription',
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
            'total_paye'    => $totalPaye,
            'total_restant' => max((float) $resume->total_du - $totalPaye, 0),
        ]);
    }

    private function getTypeFrais($libelle, $anneeId, $cycle = '', $niveau = '')
    {
        $query = TypeFrais::query();

        $query->where(function ($q) use ($libelle) {
            $q->where('libelle', $libelle)
              ->orWhere('libelle', 'like', '%' . $libelle . '%');

            if (str_contains($libelle, 'Scolarité')) {
                $base = trim(str_replace(['Scolarité', '-'], '', $libelle));

                $q->orWhere(function($sub) use ($base) {
                    $sub->where(function($s) {
                        $s->where('libelle', 'like', '%scolarit%')
                          ->orWhere('libelle', 'like', '%ecolage%')
                          ->orWhere('libelle', 'like', '%mensualit%')
                          ->orWhere('libelle', 'like', '%mensuel%')
                          ->orWhere('libelle', 'like', '%pension%');
                    });
                    if (!empty($base)) {
                        $sub->where('libelle', 'like', '%' . $base . '%');
                    }
                });
            }

            if (str_contains(strtolower($libelle), 'inscription')) {
                $q->orWhere('libelle', 'like', '%inscription%');
            }
            if (str_contains(strtolower($libelle), 'cantine')) {
                $q->orWhere('libelle', 'like', '%cantine%');
            }
            if (str_contains(strtolower($libelle), 'parascolaire')) {
                $q->orWhere('libelle', 'like', '%parascolaire%')
                  ->orWhere('libelle', 'like', '%para-scolaire%')
                  ->orWhere('libelle', 'like', '%para scolaire%');
            }
        });

        if ($anneeId) {
            $query->where(function ($q) use ($anneeId) {
                $q->where('annee_scolaire_id', $anneeId)
                  ->orWhereNull('annee_scolaire_id');
            });
        } else {
            $query->whereNull('annee_scolaire_id');
        }

        // Récupérer tous les résultats potentiels
        $results = $query->orderByRaw('CASE WHEN annee_scolaire_id IS NOT NULL THEN 1 ELSE 0 END')->get();

        // S'il y a plus d'un résultat, on filtre par cycle et niveau
        if ($results->count() > 1 && (!empty($cycle) || !empty($niveau))) {
            foreach ($results as $res) {
                $low = strtolower($res->libelle);
                $isTargetCycle = true;
                $isTargetNiveau = true;

                // Vérifier cycle
                if (!empty($cycle)) {
                    $normalizedCycle = str_replace(['é', 'è'], ['e', 'e'], strtolower($cycle));
                    $otherCycles = ['maternelle', 'primaire', 'college', 'collège', 'lycee', 'lycée', 'creche', 'crèche', 'prescolaire', 'préscolaire'];
                    foreach ($otherCycles as $other) {
                        $normalizedOther = str_replace(['é', 'è'], ['e', 'e'], $other);
                        if ($normalizedOther !== $normalizedCycle && str_contains($low, $other)) {
                            $isTargetCycle = false;
                            break;
                        }
                    }
                    if (str_contains($low, $normalizedCycle)) {
                        $isTargetCycle = true;
                    }
                }

                // Vérifier niveau
                if (!empty($niveau) && str_contains($low, ' - ')) {
                    $parts = array_map('trim', explode('-', $low));

                    if (count($parts) >= 3) {
                        $targetNiveau = end($parts);
                        $cleanTarget = trim(preg_replace('/\s*\([^)]*\)/', '', $targetNiveau));
                        if (!empty($cleanTarget) && !str_contains(strtolower($niveau), $cleanTarget) && !str_contains($cleanTarget, strtolower($niveau))) {
                            $isTargetNiveau = false;
                        }
                    } else if (count($parts) == 2) {
                        $targetNiveau = end($parts);
                        $cleanTarget = trim(preg_replace('/\s*\([^)]*\)/', '', $targetNiveau));

                        if (!str_contains($normalizedCycle, str_replace(['é', 'è'], ['e', 'e'], $cleanTarget)) && !str_contains(str_replace(['é', 'è'], ['e', 'e'], $cleanTarget), $normalizedCycle)) {
                            if (!str_contains(strtolower($niveau), $cleanTarget) && !str_contains($cleanTarget, strtolower($niveau))) {
                                $isTargetNiveau = false;
                            }
                        }
                    }
                }

                if ($isTargetCycle && $isTargetNiveau) {
                    return $res; // Le match parfait
                }
            }
        }

        return $results->first();
    }

    private function getLibelleScolarite(string $cycle): string
    {
        return match ($cycle) {
            'primaire' => 'Scolarité - Primaire',
            'college'  => 'Scolarité - Collège',
            'lycee'    => 'Scolarité - Lycée',
            default    => 'Scolarité',
        };
    }

    private function compterMoisScolaires(AnneeScolaire $anneeScolaire): int
    {
        $dateDebut = Carbon::parse($anneeScolaire->date_debut)->startOfMonth();
        $dateFin   = Carbon::parse($anneeScolaire->date_fin)->startOfMonth();

        return $dateDebut->diffInMonths($dateFin) + 1;
    }

    private function genererMatricule(string $cycle): string
    {
        $code = match ($cycle) {
            'primaire' => 'PR',
            'college'  => 'CL',
            'lycee'    => 'LY',
            default    => 'XX',
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
