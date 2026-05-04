<?php

namespace App\Http\Controllers\Paiements;

use App\Http\Controllers\Controller;
use App\Models\Finance\Caisse;
use App\Models\Finance\CategorieEntree;
use App\Models\Finance\Entree;
use App\Models\Inscription\AnneeScolaire;
use App\Models\Inscription\Inscription;
use App\Models\Inscription\Paiement;
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
        $moisPayes = PaiementMensuel::whereHas('resume', function ($query) use ($inscriptionId) {
            $query->where('inscription_id', $inscriptionId);
        })->get()->keyBy(fn ($item) => $item->annee . '-' . $item->mois);

        $mois = collect($moisAnnee)->map(function ($periode) use ($moisPayes, $montantMensuel) {
            $key = $periode['annee'] . '-' . $periode['mois'];
            $lignePaye = $moisPayes->get($key);

            return [
                'libelle' => $this->libelleMois($periode['mois'], $periode['annee']),
                'mois' => $periode['mois'],
                'annee' => $periode['annee'],
                'montant' => $montantMensuel,
                'est_paye' => !is_null($lignePaye),
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
                'total_restant' => $mois->where('est_paye', false)->sum('montant'),
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
            $paiementsEnregistres = [];
            $totalMontant = 0;

            foreach ($moisDemandes as $periode) {
                $key = $periode['annee'] . '-' . $periode['mois'];

                if (!$moisAutorises->has($key)) {
                    continue;
                }

                $existant = PaiementMensuel::where('resume_id', $resume->id)
                    ->where('mois', $periode['mois'])
                    ->where('annee', $periode['annee'])
                    ->first();

                if ($existant) {
                    continue;
                }

                $paiement = Paiement::create([
                    'reference' => $this->genererReference(),
                    'inscription_id' => $inscription->id,
                    'type_frais_id' => $typeFraisId,
                    'type' => 'scolarite_mensuelle',
                    'libelle' => $this->libelleMois($periode['mois'], $periode['annee']),
                    'details' => $periode,
                    'montant' => $montantMensuel,
                    'date_paiement' => now(),
                    'utilisateur_id' => $userId,
                ]);

                $ligne = PaiementMensuel::create([
                    'resume_id' => $resume->id,
                    'mois' => $periode['mois'],
                    'annee' => $periode['annee'],
                    'montant' => $montantMensuel,
                    'paiement_id' => $paiement->id,
                ]);

                $recu = Recu::create([
                    'numero' => Recu::genererNumero(),
                    'paiement_id' => $paiement->id,
                    'inscription_id' => $inscription->id,
                    'montant' => $montantMensuel,
                    'date_emission' => now(),
                    'libelle' => 'Scolarite - ' . $this->libelleMois($periode['mois'], $periode['annee']),
                    'details' => json_encode($periode),
                ]);

                $totalMontant += $montantMensuel;
                $paiementsEnregistres[] = [
                    'paiement_mensuel_id' => $ligne->id,
                    'mois' => $this->libelleMois($periode['mois'], $periode['annee']),
                    'montant' => $montantMensuel,
                    'paiement_id' => $paiement->id,
                    'recu_id' => $recu->id,
                ];
            }

            if (empty($paiementsEnregistres)) {
                DB::rollBack();

                return response()->json([
                    'success' => false,
                    'message' => 'Aucun mois valide selectionne',
                ], 422);
            }

            // --- INTEGRATION FINANCE ---
            $typeScolarite = CategorieEntree::where('nom', 'Scolarité')->first();
            if ($typeScolarite) {
                Entree::create([
                    'reference' => 'ENT-SCO-' . time(),
                    'montant' => $totalMontant,
                    'date_entree' => now(),
                    'type_entree_id' => $typeScolarite->id,
                    'inscription_id' => $inscription->id,
                    'annee_scolaire_id' => $inscription->annee_scolaire_id,
                    'description' => 'Paiement scolarité pour ' . count($paiementsEnregistres) . ' mois',
                    'created_by' => $userId
                ]);

                $caisse = Caisse::firstOrCreate(
                    ['annee_scolaire_id' => $inscription->annee_scolaire_id],
                    ['nom' => 'Caisse Principale', 'solde' => 0]
                );
                $caisse->increment('solde', $totalMontant);
            }
            // ---------------------------

            $this->mettreAJourResume($resume);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Paiement de la scolarite effectue avec succes',
                'data' => [
                    'total_paye' => $totalMontant,
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
        $dateDebut = Carbon::parse($inscription->anneeScolaire->date_debut)->startOfMonth();
        $dateFin = Carbon::parse($inscription->anneeScolaire->date_fin)->startOfMonth();
        $mois = [];

        while ($dateDebut <= $dateFin) {
            $mois[] = [
                'mois' => $dateDebut->month,
                'annee' => $dateDebut->year,
            ];

            $dateDebut->addMonth();
        }

        return $mois;
    }

    private function getMontantMensuel(Inscription $inscription): float
    {
        $libelle = match ($inscription->classe->niveau->cycle) {
            'primaire' => 'Scolarité - Primaire',
            'college' => 'Scolarité - Collège',
            'lycee' => 'Scolarité - Lycée',
            default => 'Scolarité',
        };

        $fraisApplique = $inscription->fraisAppliques()
            ->whereHas('typeFrais', function ($query) use ($libelle) {
                $query->where('libelle', $libelle);
            })
            ->first();

        if (!$fraisApplique) {
            return 0;
        }

        $nbMois = max(count($this->genererMoisAnneeScolaire($inscription)), 1);

        return round(((float) $fraisApplique->montant) / $nbMois, 2);
    }

    private function getTypeFraisScolariteId(Inscription $inscription): ?int
    {
        $libelle = match ($inscription->classe->niveau->cycle) {
            'primaire' => 'Scolarité - Primaire',
            'college' => 'Scolarité - Collège',
            'lycee' => 'Scolarité - Lycée',
            default => 'Scolarité',
        };

        return $inscription->fraisAppliques()
            ->whereHas('typeFrais', function ($query) use ($libelle) {
                $query->where('libelle', $libelle);
            })
            ->value('id_frais');
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
            return collect($request->mois)
                ->map(fn ($periode) => [
                    'mois' => (int) $periode['mois'],
                    'annee' => (int) $periode['annee'],
                ])
                ->unique(fn ($periode) => $periode['annee'] . '-' . $periode['mois'])
                ->values();
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

        return collect([]);
    }
}
