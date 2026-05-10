<?php

namespace App\Http\Controllers\Paiements;

use App\Http\Controllers\Controller;
use App\Models\Finance\Caisse;
use App\Models\Finance\CategorieEntree;
use App\Models\Finance\Entree;
use App\Models\Inscription\AnneeScolaire;
use App\Models\Inscription\Inscription;
use App\Models\Inscription\Paiement;
use App\Models\Inscription\TypeFrais;
use App\Models\Paiement\PaiementMensuel;
use App\Models\Paiement\Recu;
use App\Models\Paiement\ResumePaiement;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ScolariteController extends Controller
{
    public function getMoisAPayer($inscriptionId)
    {
        $inscription = Inscription::with([
            'eleve',
            'classe.niveau',
            'anneeScolaire',
            'resumePaiement',
            'paiements',
        ])->findOrFail($inscriptionId);

        $montantMensuel = $this->getMontantMensuel($inscription);
        $moisAnnee = $this->genererMoisAnneeScolaire($inscription);
        
        $moisPayes = PaiementMensuel::with('paiement')->whereHas('resume', function ($query) use ($inscriptionId) {
            $query->where('inscription_id', $inscriptionId);
        })->get()->keyBy(fn ($item) => $item->annee . '-' . $item->mois);

        // Fetch all partial payments for scolarite to calculate exactly how much was paid
        $paiementsPartiels = Paiement::where('inscription_id', $inscriptionId)
            ->where('type', 'scolarite_mensuelle')
            ->get();
            
        $sommePartielle = [];
        foreach ($paiementsPartiels as $p) {
            $details = is_string($p->details) ? json_decode($p->details, true) : $p->details;
            if (is_array($details) && isset($details['mois']) && isset($details['annee'])) {
                $key = $details['annee'] . '-' . $details['mois'];
                $sommePartielle[$key] = ($sommePartielle[$key] ?? 0) + (float) $p->montant;
            }
        }

        $mois = collect($moisAnnee)->map(function ($periode) use ($moisPayes, $montantMensuel, $sommePartielle) {
            $key = $periode['annee'] . '-' . $periode['mois'];
            $lignePaye = $moisPayes->get($key);
            
            $dejaPaye = $lignePaye ? $montantMensuel : ($sommePartielle[$key] ?? 0);

            return [
                'libelle' => $this->libelleMois($periode['mois'], $periode['annee']),
                'mois' => $periode['mois'],
                'annee' => $periode['annee'],
                'montant' => $montantMensuel,
                'montant_initial' => $montantMensuel,
                'montant_paye' => $dejaPaye,
                'montant_restant' => max(0, $montantMensuel - $dejaPaye),
                'est_paye' => $lignePaye !== null || $dejaPaye >= ($montantMensuel - 0.01),
                'paiement_mensuel_id' => $lignePaye?->id,
                'paiement_id' => $lignePaye?->paiement_id,
                'date_paiement' => $lignePaye?->paiement?->date_paiement?->format('d/m/Y'),
            ];
        })->values();

        return response()->json([
            'success' => true,
            'data' => [
                'eleve' => $this->formatEleve($inscription),
                'mois' => $mois,
                'total_restant' => $mois->sum('montant_restant'),
            ],
        ]);
    }

    public function payerMois(Request $request)
    {
        $request->validate([
            'inscription_id' => 'required|exists:inscriptions,id',
            'mois' => 'nullable|array|min:1',
            'mois.*.mois' => 'required_with:mois|integer|min:1|max:12',
            'mois.*.annee' => 'required_with:mois|integer|min:2000|max:2100',
            'paiements_mensuels_ids' => 'nullable|array|min:1',
            'paiements_mensuels_ids.*' => 'integer|exists:paiements_mensuels,id',
            'reference' => 'nullable|string',
        ]);

        DB::beginTransaction();

        try {
            $inscription = Inscription::with(['resumePaiement', 'anneeScolaire', 'classe.niveau'])->findOrFail($request->inscription_id);
            $resume = $inscription->resumePaiement;

            if (!$resume) {
                throw new \Exception('Resume de paiement introuvable');
            }

            $moisAutorises = collect($this->genererMoisAnneeScolaire($inscription))
                ->keyBy(fn ($periode) => $periode['annee'] . '-' . $periode['mois']);

            $moisDemandes = $this->extraireMoisDemandes($request, $resume);

            if ($moisDemandes->isEmpty()) {
                DB::rollBack();

                return response()->json([
                    'success' => false,
                    'message' => 'Pour payer la scolarite, envoyez le champ "mois" sous la forme [{"mois":11,"annee":2025}]. Les "paiements_mensuels_ids" ne servent que pour des lignes deja creees.',
                ], 422);
            }

            $montantMensuel = $this->getMontantMensuel($inscription);
            $userId = $this->getUtilisateurId($request);
            $typeFraisId = $this->getTypeFraisScolariteId($inscription);

            if (!$typeFraisId) {
                \Log::warning('Type de frais scolarité non trouvé pour l\'élève ID: ' . $inscription->id);
                return response()->json([
                    'success' => false,
                    'message' => 'Type de frais scolarité non trouvé pour cet élève (Vérifiez la configuration des frais dans l\'onglet "Configuration")',
                ], 422);
            }

            // --- NOUVELLE LOGIQUE DE PAIEMENT PARTIEL ---
            $montantMensuel = $this->getMontantMensuel($inscription);
            $userId = $this->getUtilisateurId($request);
            
            // 1. Calcul du montant à distribuer
            $montantVerseTotal = (float) $request->input('montant_verse', 0);
            if ($montantVerseTotal <= 0) {
                $montantVerseTotal = $montantMensuel * count($moisDemandes);
            }
            $resteADistribuer = $montantVerseTotal;

            // 2. Historique des paiements partiels existants
            $paiementsPartiels = Paiement::where('inscription_id', $inscription->id)
                ->where('type', 'scolarite_mensuelle')
                ->get();
                
            $sommePartielle = [];
            foreach ($paiementsPartiels as $p) {
                $details = is_string($p->details) ? json_decode($p->details, true) : $p->details;
                if (is_array($details) && isset($details['mois']) && isset($details['annee'])) {
                    $key = $details['annee'] . '-' . $details['mois'];
                    $sommePartielle[$key] = ($sommePartielle[$key] ?? 0) + (float) $p->montant;
                }
            }

            $paiementsEnregistres = [];
            $totalMontantReellementPaye = 0;

            foreach ($moisDemandes as $periode) {
                if ($resteADistribuer <= 0) {
                    break;
                }

                $key = $periode['annee'] . '-' . $periode['mois'];

                if (!$moisAutorises->has($key)) {
                    continue;
                }

                // Si le mois est déjà verrouillé (PaiementMensuel existe), on saute
                $existant = PaiementMensuel::where('resume_id', $resume->id)
                    ->where('mois', $periode['mois'])
                    ->where('annee', $periode['annee'])
                    ->first();

                if ($existant) {
                    continue;
                }

                $dejaPaye = $sommePartielle[$key] ?? 0;
                $resteAPayerPourCeMois = max(0, $montantMensuel - $dejaPaye);

                if ($resteAPayerPourCeMois <= 0) {
                    continue; 
                }

                $montantAPayerMois = min($resteAPayerPourCeMois, $resteADistribuer);
                $resteADistribuer -= $montantAPayerMois;

                // 3. Création de la transaction (acompte ou solde)
                $paiement = Paiement::create([
                    'reference' => $this->genererReference(),
                    'inscription_id' => $inscription->id,
                    'type_frais_id' => $typeFraisId,
                    'type' => 'scolarite_mensuelle',
                    'libelle' => $this->libelleMois($periode['mois'], $periode['annee']) . ($montantAPayerMois < $resteAPayerPourCeMois ? ' (Acompte)' : ''),
                    'details' => $periode,
                    'montant' => $montantAPayerMois,
                    'date_paiement' => now(),
                    'utilisateur_id' => $userId,
                ]);

                // 4. Verrouillage du mois si soldé
                $ligneId = null;
                if (($dejaPaye + $montantAPayerMois) >= ($montantMensuel - 0.01)) {
                    $ligne = PaiementMensuel::create([
                        'resume_id' => $resume->id,
                        'mois' => $periode['mois'],
                        'annee' => $periode['annee'],
                        'montant' => $montantMensuel,
                        'paiement_id' => $paiement->id,
                    ]);
                    $ligneId = $ligne->id;
                }

                $recu = Recu::create([
                    'numero' => Recu::genererNumero(),
                    'paiement_id' => $paiement->id,
                    'inscription_id' => $inscription->id,
                    'montant' => $montantAPayerMois,
                    'date_emission' => now(),
                    'libelle' => 'Scolarite - ' . $this->libelleMois($periode['mois'], $periode['annee']),
                    'details' => json_encode($periode),
                ]);

                $totalMontantReellementPaye += $montantAPayerMois;
                $paiementsEnregistres[] = [
                    'paiement_mensuel_id' => $ligneId,
                    'mois' => $this->libelleMois($periode['mois'], $periode['annee']),
                    'montant' => $montantAPayerMois,
                    'paiement_id' => $paiement->id,
                    'recu_id' => $recu->id,
                ];
            }

            // Check if any payments were actually made
            if (empty($paiementsEnregistres) && !$moisDemandes->isEmpty()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Les mois sélectionnés sont déjà payés ou ne font pas partie de l\'année scolaire en cours.',
                ], 422);
            }

            // --- INTEGRATION FINANCE ---
            $typeScolarite = CategorieEntree::where('nom', 'like', '%Scolarité%')->first();
            if ($typeScolarite && $totalMontantReellementPaye > 0) {
                Entree::create([
                    'reference' => 'ENT-SCO-' . time(),
                    'montant' => $totalMontantReellementPaye,
                    'date_entree' => now(),
                    'type_entree_id' => $typeScolarite->id,
                    'inscription_id' => $inscription->id,
                    'annee_scolaire_id' => $inscription->id_annee_scolaire,
                    'description' => 'Paiement scolarité (' . count($paiementsEnregistres) . ' transaction(s))',
                    'created_by' => $userId
                ]);

                $caisse = Caisse::firstOrCreate(
                    ['annee_scolaire_id' => $inscription->id_annee_scolaire],
                    ['nom' => 'Caisse Principale', 'solde' => 0]
                );
                $caisse->increment('solde', $totalMontantReellementPaye);
            }
            // ---------------------------

            $this->mettreAJourResume($resume);

            // Notification de paiement
            $eleveNom = "{$inscription->eleve->nom} {$inscription->eleve->prenom}";
            \App\Http\Controllers\NotificationController::push(
                "Paiement Scolarité",
                "Paiement reçu — {$eleveNom} (" . number_format($totalMontantReellementPaye, 0, ',', ' ') . " Ar)",
                'success',
                null,
                "/caissier/paiement?student_id={$inscription->id}"
            );

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Paiement de la scolarite effectue avec succes',
                'data' => [
                    'total_paye' => $totalMontantReellementPaye,
                    'nombre_mois' => count($paiementsEnregistres),
                    'paiements' => $paiementsEnregistres,
                ],
            ]);
        } catch (\Throwable $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Erreur lors du paiement: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function payerTout($inscriptionId, Request $request)
    {
        $inscription = Inscription::with('anneeScolaire')->findOrFail($inscriptionId);

        $mois = collect($this->genererMoisAnneeScolaire($inscription))
            ->reject(function ($periode) use ($inscriptionId) {
                return PaiementMensuel::whereHas('resume', function ($query) use ($inscriptionId) {
                    $query->where('inscription_id', $inscriptionId);
                })->where('mois', $periode['mois'])
                    ->where('annee', $periode['annee'])
                    ->exists();
            })
            ->values()
            ->all();

        $request->merge([
            'inscription_id' => $inscriptionId,
            'mois' => $mois,
        ]);

        return $this->payerMois($request);
    }

    public function getHistorique($inscriptionId)
    {
        $paiements = Paiement::where('inscription_id', $inscriptionId)
            ->where('type', 'scolarite_mensuelle')
            ->orderBy('date_paiement', 'desc')
            ->get();

        $historique = $paiements->map(function ($paiement) {
            return [
                'id' => $paiement->id,
                'reference' => $paiement->reference,
                'montant' => $paiement->montant,
                'date_paiement' => $paiement->date_paiement->format('d/m/Y'),
                'mois' => $paiement->libelle,
            ];
        });

        return response()->json([
            'success' => true,
            'data' => $historique,
        ]);
    }

    private function formatEleve(Inscription $inscription): array
    {
        return [
            'id' => $inscription->eleve->id,
            'nom' => $inscription->eleve->nom,
            'prenom' => $inscription->eleve->prenom,
            'matricule' => $inscription->eleve->matricule,
            'classe' => $inscription->classe->nom_classe,
            'niveau' => $inscription->classe->niveau->nom_niveau,
        ];
    }

    private function genererMoisAnneeScolaire(Inscription $inscription): array
    {
        $anneeScolaire = $inscription->anneeScolaire ?? $inscription->classe?->anneeScolaire;
        
        if (!$anneeScolaire) {
            $anneeScolaire = AnneeScolaire::where('statut', 'en_cours')->first() 
                          ?? AnneeScolaire::latest()->first();
        }
        
        // Fallback dates if missing
        $dateDebutStr = $anneeScolaire?->date_debut ?? (date('Y') . '-09-01');
        $dateFinStr = $anneeScolaire?->date_fin ?? ((date('Y') + 1) . '-06-30');

        $dateDebut = Carbon::parse($dateDebutStr)->startOfMonth();
        $dateFin = Carbon::parse($dateFinStr)->startOfMonth();
        
        // Ensure at least 10 months if the range is too small
        if ($dateDebut->diffInMonths($dateFin) < 5) {
            $dateFin = $dateDebut->copy()->addMonths(9);
        }

        $mois = [];
        $tempDate = $dateDebut->copy();

        while ($tempDate <= $dateFin) {
            $mois[] = [
                'mois' => $tempDate->month,
                'annee' => $tempDate->year,
            ];

            $tempDate->addMonth();
        }

        return $mois;
    }

    private function getMontantMensuel(Inscription $inscription): float
    {
        $typeFraisId = $this->getTypeFraisScolariteId($inscription);
        if (!$typeFraisId) return 0;

        $fraisApplique = $inscription->fraisAppliques()
            ->where('id_frais', $typeFraisId)
            ->first();

        $montantTotal = 0;
        if ($fraisApplique) {
            $montantTotal = (float) $fraisApplique->montant;
        } else {
            // Fallback: use global TypeFrais amount
            $typeFrais = TypeFrais::find($typeFraisId);
            if ($typeFrais) {
                // We need to know the number of months for the academic year
                $nbMois = max($this->compterMoisScolaires($inscription->anneeScolaire ?? AnneeScolaire::where('statut', 'en_cours')->first() ?? AnneeScolaire::latest()->first()), 10);
                $montantTotal = (float) $typeFrais->montant * $nbMois;
            }
        }

        if ($montantTotal <= 0) return 0;

        $nbMois = max(count($this->genererMoisAnneeScolaire($inscription)), 1);
        return round($montantTotal / $nbMois, 2);
    }

    private function compterMoisScolaires($anneeScolaire): int
    {
        if (!$anneeScolaire || !$anneeScolaire->date_debut || !$anneeScolaire->date_fin) {
            return 10; // Default
        }
        $dateDebut = Carbon::parse($anneeScolaire->date_debut)->startOfMonth();
        $dateFin = Carbon::parse($anneeScolaire->date_fin)->startOfMonth();

        return $dateDebut->diffInMonths($dateFin) + 1;
    }

    private function getTypeFraisScolariteId(Inscription $inscription): ?int
    {
        $cycle = $inscription->classe?->niveau?->cycle ?? '';
        $libelle = match ($cycle) {
            'primaire' => 'Scolarité - Primaire',
            'college' => 'Scolarité - Collège',
            'lycee' => 'Scolarité - Lycée',
            default => 'Scolarité',
        };

        // Try primary labels (Scolarité)
        $id = $inscription->fraisAppliques()
            ->whereHas('typeFrais', function ($query) use ($libelle) {
                $query->where('libelle', 'like', '%' . $libelle . '%');
            })
            ->value('id_frais');

        if (!$id) {
            // Fallback 1: try generic Scolarité
            $id = $inscription->fraisAppliques()
                ->whereHas('typeFrais', function ($query) {
                    $query->where('libelle', 'like', '%Scolarité%');
                })
                ->value('id_frais');
        }

        if (!$id) {
            // Fallback 2: try Ecolage
            $id = $inscription->fraisAppliques()
                ->whereHas('typeFrais', function ($query) {
                    $query->where('libelle', 'like', '%Ecolage%');
                })
                ->value('id_frais');
        }

        if (!$id) {
            // Fallback 3: Search global TypeFrais table directly
            $id = TypeFrais::where('libelle', 'like', '%Scolarité%')
                ->orWhere('libelle', 'like', '%Ecolage%')
                ->orderByRaw('CASE WHEN libelle like "%' . $libelle . '%" THEN 0 ELSE 1 END')
                ->value('id');
        }

        return $id;
    }

    private function mettreAJourResume(?ResumePaiement $resume): void
    {
        if (!$resume) {
            return;
        }

        $totalPaye = (float) Paiement::where('inscription_id', $resume->inscription_id)->sum('montant');

        $resume->update([
            'total_paye' => $totalPaye,
            'total_restant' => max((float) $resume->total_du - $totalPaye, 0),
        ]);
    }

    private function genererReference(): string
    {
        $lastId = Paiement::max('id') ?? 0;
        $numero = str_pad($lastId + 1, 6, '0', STR_PAD_LEFT);

        return 'PAY-' . date('Y') . '-' . $numero;
    }

    private function getUtilisateurId(Request $request): ?int
    {
        $user = $request->user();

        return $user ? (int) $user->getKey() : null;
    }

    private function libelleMois(int $mois, int $annee): string
    {
        return Carbon::create($annee, $mois, 1)->translatedFormat('F Y');
    }

    private function extraireMoisDemandes(Request $request, ResumePaiement $resume)
    {
        if (is_array($request->mois) && !empty($request->mois)) {
            $mois = collect($request->mois)
                ->map(fn ($periode) => [
                    'mois' => (int) $periode['mois'],
                    'annee' => (int) $periode['annee'],
                ])
                ->unique(fn ($periode) => $periode['annee'] . '-' . $periode['mois'])
                ->values();
            
            if ($mois->isEmpty()) {
                \Log::info('extraireMoisDemandes: mois array is empty after mapping', ['raw' => $request->mois]);
            }
            return $mois;
        }

        if (is_array($request->paiements_mensuels_ids) && !empty($request->paiements_mensuels_ids)) {
            return PaiementMensuel::where('resume_id', $resume->id)
                ->whereIn('id', $request->paiements_mensuels_ids)
                ->get(['mois', 'annee'])
                ->map(fn ($periode) => [
                    'mois' => (int) $periode['mois'],
                    'annee' => (int) $periode['annee'],
                ])
                ->unique(fn ($periode) => $periode['annee'] . '-' . $periode['mois'])
                ->values();
        }

        \Log::info('extraireMoisDemandes: no mois or paiements_mensuels_ids provided', $request->all());
        return collect([]);
    }
}