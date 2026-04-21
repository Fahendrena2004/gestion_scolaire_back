<?php

namespace App\Http\Controllers\Paiements;

use App\Http\Controllers\Controller;
use App\Models\Inscription\Inscription;
use App\Models\Inscription\Echeance;
use App\Models\Inscription\Paiement;
use App\Models\Paiement\Recu;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ScolariteController extends Controller
{
    /**
     * 1. Récupérer les mois de scolarité à payer
     * GET /api/scolarite/mois/{inscriptionId}
     */
    public function getMoisAPayer($inscriptionId)
    {
        $inscription = Inscription::with(['eleve', 'classe.niveau'])->findOrFail($inscriptionId);

        $echeances = Echeance::where('inscription_id', $inscriptionId)
            ->whereHas('typeFrais', function($q) {
                $q->where('libelle', 'like', '%Scolarité%');
            })
            ->where('statut', '!=', 'paye')
            ->orderBy('annee')
            ->orderBy('mois')
            ->get();

        $mois = [];
        $totalRestant = 0;

        foreach ($echeances as $e) {
            $mois[] = [
                'echeance_id' => $e->id,
                'libelle' => $e->libelle,
                'montant' => $e->montant,
                'montant_paye' => $e->montant_paye,
                'montant_restant' => $e->montant_restant,
                'date_echeance' => $e->date_echeance->format('d/m/Y'),
                'statut' => $e->statut
            ];
            $totalRestant += $e->montant_restant;
        }

        return response()->json([
            'success' => true,
            'data' => [
                'eleve' => [
                    'id' => $inscription->eleve->id,
                    'nom' => $inscription->eleve->nom,
                    'prenom' => $inscription->eleve->prenom,
                    'matricule' => $inscription->eleve->matricule,
                    'classe' => $inscription->classe->nom_classe,
                    'niveau' => $inscription->classe->niveau->nom_niveau
                ],
                'mois' => $mois,
                'total_restant' => $totalRestant
            ]
        ]);
    }

    /**
     * 2. Payer les mois de scolarité sélectionnés
     * POST /api/scolarite/payer
     */
    public function payerMois(Request $request)
    {
        $request->validate([
            'inscription_id' => 'required|exists:inscriptions,id',
            'echeances_ids' => 'required|array|min:1',
            'echeances_ids.*' => 'exists:echeances,id'
        ]);

        DB::beginTransaction();

        try {
            $echeances = Echeance::whereIn('id', $request->echeances_ids)
                ->where('statut', '!=', 'paye')
                ->get();

            if ($echeances->isEmpty()) {
                DB::rollBack();
                return response()->json([
                    'success' => false,
                    'message' => 'Aucune échéance valide sélectionnée'
                ], 422);
            }

            $totalMontant = 0;
            $paiementsEnregistres = [];

            foreach ($echeances as $echeance) {
                $montant = $echeance->montant_restant;
                $totalMontant += $montant;

                // ✅ Créer le paiement (sans mode et type)
                $paiement = Paiement::create([
                    'reference' => $this->genererReference(),
                    'inscription_id' => $request->inscription_id,
                    'echeance_id' => $echeance->id,
                    'montant' => $montant,
                    'date_paiement' => now(),
                    'utilisateur_id' => 1  // ← ID utilisateur valide
                ]);

                // Mettre à jour l'échéance
                $echeance->montant_paye = $echeance->montant;
                $echeance->montant_restant = 0;
                $echeance->statut = 'paye';
                $echeance->save();

                // Créer le reçu
                $recu = Recu::create([
                    'numero' => Recu::genererNumero(),
                    'paiement_id' => $paiement->id,
                    'inscription_id' => $request->inscription_id,
                    'montant' => $montant,
                    'date_emission' => now(),
                    'libelle' => $echeance->libelle,
                    'details' => null
                ]);

                $paiementsEnregistres[] = [
                    'echeance_id' => $echeance->id,
                    'mois' => $echeance->libelle,
                    'montant' => $montant,
                    'paiement_id' => $paiement->id,
                    'recu_id' => $recu->id
                ];
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Paiement de la scolarité effectué avec succès',
                'data' => [
                    'total_paye' => $totalMontant,
                    'nombre_mois' => count($paiementsEnregistres),
                    'paiements' => $paiementsEnregistres
                ]
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors du paiement: ' . $e->getMessage(),
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * 3. Paiement unique (tous les mois restants)
     * POST /api/scolarite/payer-tout/{inscriptionId}
     */
    public function payerTout($inscriptionId, Request $request)
    {
        DB::beginTransaction();

        try {
            $echeances = Echeance::where('inscription_id', $inscriptionId)
                ->whereHas('typeFrais', function($q) {
                    $q->where('libelle', 'like', '%Scolarité%');
                })
                ->where('statut', '!=', 'paye')
                ->get();

            if ($echeances->isEmpty()) {
                DB::rollBack();
                return response()->json([
                    'success' => false,
                    'message' => 'Aucune échéance impayée'
                ], 422);
            }

            $totalMontant = 0;
            $paiementsEnregistres = [];

            foreach ($echeances as $echeance) {
                $montant = $echeance->montant_restant;
                $totalMontant += $montant;

                $paiement = Paiement::create([
                    'reference' => $this->genererReference(),
                    'inscription_id' => $inscriptionId,
                    'echeance_id' => $echeance->id,
                    'montant' => $montant,
                    'date_paiement' => now(),
                    'utilisateur_id' => 1
                ]);

                $echeance->montant_paye = $echeance->montant;
                $echeance->montant_restant = 0;
                $echeance->statut = 'paye';
                $echeance->save();

                $recu = Recu::create([
                    'numero' => Recu::genererNumero(),
                    'paiement_id' => $paiement->id,
                    'inscription_id' => $inscriptionId,
                    'montant' => $montant,
                    'date_emission' => now(),
                    'libelle' => $echeance->libelle,
                    'details' => null
                ]);

                $paiementsEnregistres[] = [
                    'echeance_id' => $echeance->id,
                    'mois' => $echeance->libelle,
                    'montant' => $montant
                ];
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Paiement total de la scolarité effectué',
                'data' => [
                    'total_paye' => $totalMontant,
                    'nombre_mois' => count($paiementsEnregistres),
                    'mois_payes' => $paiementsEnregistres
                ]
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors du paiement: ' . $e->getMessage(),
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * 4. Récupérer l'historique des paiements de scolarité
     * GET /api/scolarite/historique/{inscriptionId}
     */
    public function getHistorique($inscriptionId)
    {
        $paiements = Paiement::where('inscription_id', $inscriptionId)
            ->with('echeance')
            ->orderBy('date_paiement', 'desc')
            ->get();

        $historique = [];
        foreach ($paiements as $p) {
            $historique[] = [
                'id' => $p->id,
                'reference' => $p->reference,
                'montant' => $p->montant,
                'date_paiement' => $p->date_paiement->format('d/m/Y'),
                'mois' => $p->echeance ? $p->echeance->libelle : null
            ];
        }

        return response()->json([
            'success' => true,
            'data' => $historique
        ]);
    }

    /**
     * 5. Générer une référence unique
     */
    private function genererReference(): string
    {
        $lastId = Paiement::max('id') ?? 0;
        $numero = str_pad($lastId + 1, 6, '0', STR_PAD_LEFT);
        return 'PAY-' . date('Y') . '-' . $numero;
    }
}