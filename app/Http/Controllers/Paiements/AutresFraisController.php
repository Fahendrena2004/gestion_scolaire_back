<?php

namespace App\Http\Controllers\Paiements;

use App\Http\Controllers\Controller;
use App\Models\Inscription\Inscription;
use App\Models\Inscription\Echeance;
use App\Models\Inscription\Paiement;
use App\Models\Paiement\Recu;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AutresFraisController extends Controller
{
    public function payer(Request $request)
    {
        return $this->payerFrais($request);
    }

    /**
     * 1. Récupérer tous les autres frais à payer (Parascolaire, Inscription, Frais techno)
     * GET /api/autres-frais/{inscriptionId}
     */
    public function getFraisAPayer($inscriptionId)
    {
        $inscription = Inscription::with(['eleve', 'classe.niveau'])->findOrFail($inscriptionId);

        // Exclure la cantine et la scolarité
        $echeances = Echeance::where('inscription_id', $inscriptionId)
            ->whereHas('typeFrais', function($q) {
                $q->where('libelle', '!=', 'Cantine')
                  ->where('libelle', 'not like', '%Scolarité%');
            })
            ->where('statut', '!=', 'paye')
            ->get();

        $resultats = [];

        foreach ($echeances as $echeance) {
            $libelle = $echeance->typeFrais->libelle;

            $resultats[] = [
                'echeance_id' => $echeance->id,
                'libelle' => $echeance->libelle,
                'type' => $libelle,
                'montant' => $echeance->montant,
                'montant_paye' => $echeance->montant_paye,
                'montant_restant' => $echeance->montant_restant,
                'date_echeance' => $echeance->date_echeance->format('d/m/Y'),
                'statut' => $echeance->statut
            ];
        }

        return response()->json([
            'success' => true,
            'data' => [
                'eleve' => [
                    'id' => $inscription->eleve->id,
                    'nom' => $inscription->eleve->nom,
                    'prenom' => $inscription->eleve->prenom,
                    'matricule' => $inscription->eleve->matricule,
                    'classe' => $inscription->classe->nom_classe
                ],
                'frais' => $resultats,
                'total_restant' => collect($resultats)->sum('montant_restant')
            ]
        ]);
    }

    /**
     * 2. Payer des frais sélectionnés (Parascolaire, Inscription, Frais techno)
     * POST /api/autres-frais/payer
     */
    public function payerFrais(Request $request)
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
                    'message' => 'Aucun frais valide sélectionné'
                ], 422);
            }

            $totalMontant = 0;
            $paiementsEnregistres = [];

            foreach ($echeances as $echeance) {
                $montant = $echeance->montant_restant;
                $totalMontant += $montant;

                // Créer le paiement
                $paiement = Paiement::create([
                    'reference' => $this->genererReference(),
                    'inscription_id' => $request->inscription_id,
                    'echeance_id' => $echeance->id,
                    'montant' => $montant,
                    'date_paiement' => now(),
                    'utilisateur_id' => "1",
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
                    'libelle' => $echeance->libelle,
                    'type' => $echeance->typeFrais->libelle,
                    'montant' => $montant,
                    'paiement_id' => $paiement->id,
                    'recu_id' => $recu->id
                ];
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Paiement effectué avec succès',
                'data' => [
                    'total_paye' => $totalMontant,
                    'nombre_frais' => count($paiementsEnregistres),
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
     * Générer une référence unique
     */
    private function genererReference(): string
    {
        $lastId = Paiement::max('id') ?? 0;
        $numero = str_pad($lastId + 1, 6, '0', STR_PAD_LEFT);
        return 'PAY-' . date('Y') . '-' . $numero;
    }
}
