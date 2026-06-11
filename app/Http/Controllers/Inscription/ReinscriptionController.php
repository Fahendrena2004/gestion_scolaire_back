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

            $montantTotal = $this->appliquerFrais($nouvelleInscription, $classeCible->niveau->cycle, $anneeScolaire, $request);

            $nouvelleInscription->update([
                'montant_total' => $montantTotal,
                'montant_net' => $montantTotal,
            ]);

            $resume = $this->creerOuMettreAJourResumePaiement($nouvelleInscription, $montantTotal);
            $montantVerse = (float) $request->input('montant_verse', 0);

            if ($montantVerse > 0) {
                $this->enregistrerPaiementInitial($nouvelleInscription, $resume, $request, Auth::id());
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

    private function appliquerFrais(Inscription $inscription, string $cycle, AnneeScolaire $anneeScolaire, Request $request = null): float
    {
        $montantTotal = 0;

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
            $inscriptionFee = $this->getTypeFrais('Inscription', $anneeScolaire->id);
            if ($inscriptionFee && !in_array($inscriptionFee->id, $appliedIds)) {
                $montantTotal += $this->ajouterFrais($inscription, 'Inscription');
            }

            $scolariteFee = $this->getTypeFrais($this->getLibelleScolarite($cycle), $anneeScolaire->id);
            if ($scolariteFee && !in_array($scolariteFee->id, $appliedIds)) {
                $montantTotal += $this->ajouterFrais($inscription, $this->getLibelleScolarite($cycle), $this->compterMoisScolaires($anneeScolaire));
                $appliedIds[] = $scolariteFee->id;
            }

            if ($inscription->cantine) {
                $cantineFee = $this->getTypeFrais('Cantine', $anneeScolaire->id);
                if ($cantineFee && !in_array($cantineFee->id, $appliedIds)) {
                    $montantTotal += $this->ajouterFrais($inscription, 'Cantine');
                    $appliedIds[] = $cantineFee->id;
                }
            }

            if ($inscription->parascolaire) {
                $paraFee = $this->getTypeFrais('Parascolaire', $anneeScolaire->id);
                if ($paraFee && !in_array($paraFee->id, $appliedIds)) {
                    $montantTotal += $this->ajouterFrais($inscription, 'Parascolaire');
                    $appliedIds[] = $paraFee->id;
                }
            }

        } else {
            // Fallback old logic
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
                'reference' => $this->genererReferencePaiement(),
                'inscription_id' => $inscription->id,
                'type_frais_id' => $frais->id_frais,
                'type' => 'autre_frais',
                'libelle' => $frais->typeFrais?->libelle,
                'details' => null,
                'montant' => $montantAPayer,
                'date_paiement' => now(),
                'utilisateur_id' => $userId,
            ]);

            $montantRestant -= (float) $montantAPayer;
        }

        // --- Paiement de la Scolarité si des mois sont sélectionnés ---
        $selectedMonths = $request->input('selected_months', []);
        $paymentSchoolMonths = $request->input('payment_school_months', []);

        if (!empty($selectedMonths) && !empty($paymentSchoolMonths) && $montantRestant > 0) {
            $typeFraisScolarite = $this->getTypeFrais($this->getLibelleScolarite($inscription->classe->niveau->cycle), $inscription->id_annee_scolaire);

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
            $typeInscription = CategorieEntree::where('nom', 'like', '%Inscription%')->first()
                            ?? CategorieEntree::firstOrCreate(['nom' => 'Inscription'], ['description' => 'Frais d\'inscription']);
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

    private function trouverClasseDisponible(int $niveauId, int $anneeScolaireId): ?Classe
    {
        return Classe::where('niveau_id', $niveauId)
            ->where('anneeScolaire_id', $anneeScolaireId)
            ->whereRaw('effectif < COALESCE(max_effectif, 50)')
            ->orderBy('code_division', 'asc')
            ->first();
    }
}
