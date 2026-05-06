<?php

namespace App\Http\Controllers\Paiements;

use App\Http\Controllers\Controller;
use App\Models\Inscription\Inscription;
use App\Models\Inscription\Paiement;
use App\Models\Paiement\Recu;
use App\Models\Finance\Caisse;
use App\Models\Finance\CategorieEntree;
use App\Models\Finance\Entree;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AutresFraisController extends Controller
{
    public function payer(Request $request)
    {
        return $this->payerFrais($request);
    }

    public function getFraisAPayer($inscriptionId)
    {
        $inscription = Inscription::with(['eleve', 'classe.niveau', 'fraisAppliques.typeFrais'])->findOrFail($inscriptionId);

        $frais = $inscription->fraisAppliques
            ->filter(function ($frais) {
                $libelle = $frais->typeFrais?->libelle;

                return $libelle !== 'Cantine' && !str_starts_with((string) $libelle, 'Scolarité');
            })
            ->map(function ($frais) use ($inscriptionId) {
                $montantPaye = (float) Paiement::where('inscription_id', $inscriptionId)
                    ->where('type', 'autre_frais')
                    ->where('type_frais_id', $frais->id_frais)
                    ->sum('montant');

                return [
                    'frais_applique_id' => $frais->id,
                    'type_frais_id' => $frais->id_frais,
                    'libelle' => $frais->typeFrais?->libelle,
                    'type' => $frais->typeFrais?->libelle,
                    'montant' => $frais->montant,
                    'montant_paye' => $montantPaye,
                    'montant_restant' => max((float) $frais->montant - $montantPaye, 0),
                    'statut' => $montantPaye >= $frais->montant ? 'paye' : ($montantPaye > 0 ? 'partiel' : 'impaye'),
                ];
            })
            ->values();

        return response()->json([
            'success' => true,
            'data' => [
                'eleve' => [
                    'id' => $inscription->eleve->id,
                    'nom' => $inscription->eleve->nom,
                    'prenom' => $inscription->eleve->prenom,
                    'matricule' => $inscription->eleve->matricule,
                    'classe' => $inscription->classe->nom_classe,
                ],
                'frais' => $frais,
                'total_restant' => $frais->sum('montant_restant'),
            ],
        ]);
    }

    public function payerFrais(Request $request)
    {
        $request->validate([
            'inscription_id' => 'required|exists:inscriptions,id',
            'frais_ids' => 'required|array|min:1',
            'frais_ids.*' => 'exists:frais_appliques,id',
            'reference' => 'nullable|string',
        ]);

        $ids = $request->input('frais_ids', []);

        if (empty($ids)) {
            return response()->json([
                'success' => false,
                'message' => 'Aucun frais selectionne',
            ], 422);
        }

        DB::beginTransaction();

        try {
            $inscription = Inscription::with(['resumePaiement', 'fraisAppliques.typeFrais'])->findOrFail($request->inscription_id);

            $fraisSelectionnes = $inscription->fraisAppliques()
                ->with('typeFrais')
                ->whereIn('id', $ids)
                ->get()
                ->filter(function ($frais) {
                    $libelle = $frais->typeFrais?->libelle;

                    return $libelle !== 'Cantine' && !str_starts_with((string) $libelle, 'Scolarité');
                });

            if ($fraisSelectionnes->isEmpty()) {
                DB::rollBack();

                return response()->json([
                    'success' => false,
                    'message' => 'Aucun frais valide selectionne',
                ], 422);
            }

            $userId = $this->getUtilisateurId($request);
            $totalMontant = 0;
            $paiementsEnregistres = [];

            foreach ($fraisSelectionnes as $frais) {
                $montantPaye = (float) Paiement::where('inscription_id', $inscription->id)
                    ->where('type', 'autre_frais')
                    ->where('type_frais_id', $frais->id_frais)
                    ->sum('montant');

                $montantRestant = max((float) $frais->montant - $montantPaye, 0);

                if ($montantRestant <= 0) {
                    continue;
                }

                $paiement = Paiement::create([
                    'reference' => $this->genererReference(),
                    'inscription_id' => $inscription->id,
                    'type_frais_id' => $frais->id_frais,
                    'type' => 'autre_frais',
                    'libelle' => $frais->typeFrais?->libelle,
                    'details' => [
                        'frais_applique_id' => $frais->id,
                    ],
                    'montant' => $montantRestant,
                    'date_paiement' => now(),
                    'utilisateur_id' => $userId,
                ]);

                $recu = Recu::create([
                    'numero' => Recu::genererNumero(),
                    'paiement_id' => $paiement->id,
                    'inscription_id' => $inscription->id,
                    'montant' => $montantRestant,
                    'date_emission' => now(),
                    'libelle' => $frais->typeFrais?->libelle,
                    'details' => json_encode([
                        'frais_applique_id' => $frais->id,
                    ]),
                ]);

                $totalMontant += $montantRestant;
                $paiementsEnregistres[] = [
                    'frais_applique_id' => $frais->id,
                    'libelle' => $frais->typeFrais?->libelle,
                    'montant' => $montantRestant,
                    'paiement_id' => $paiement->id,
                    'recu_id' => $recu->id,
                ];
            }

            $this->mettreAJourResume($inscription->resumePaiement);

            // --- INTEGRATION FINANCE ---
            if ($totalMontant > 0) {
                // On essaie de trouver une catégorie qui correspond au premier frais, sinon "Inscription" par défaut
                $firstFrais = $fraisSelectionnes->first();
                $libelleFrais = $firstFrais->typeFrais?->libelle ?? 'Autres Frais';
                
                $typeEntree = CategorieEntree::where('nom', $libelleFrais)->first() 
                            ?? CategorieEntree::where('nom', 'Inscription')->first();

                Entree::create([
                    'reference' => 'ENT-AUT-' . time(),
                    'montant' => $totalMontant,
                    'date_entree' => now(),
                    'type_entree_id' => $typeEntree?->id ?? 2, // 2 = Inscription par défaut si rien d'autre
                    'inscription_id' => $inscription->id,
                    'annee_scolaire_id' => $inscription->id_annee_scolaire,
                    'description' => 'Paiement ' . $libelleFrais . (count($paiementsEnregistres) > 1 ? ' et autres' : ''),
                    'created_by' => $userId
                ]);

                $caisse = Caisse::firstOrCreate(
                    ['annee_scolaire_id' => $inscription->id_annee_scolaire],
                    ['nom' => 'Caisse Principale', 'solde' => 0]
                );
                $caisse->increment('solde', $totalMontant);
            }
            // ---------------------------

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Paiement effectue avec succes',
                'data' => [
                    'total_paye' => $totalMontant,
                    'nombre_frais' => count($paiementsEnregistres),
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

    private function mettreAJourResume($resume): void
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
}