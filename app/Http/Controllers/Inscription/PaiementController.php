<?php

namespace App\Http\Controllers\Inscription;

use App\Http\Controllers\Controller;
use App\Models\Inscription\Inscription;
use App\Models\Inscription\Paiement;
use App\Models\Paiement\PresenceCantine;
use App\Models\Paiement\Recu;
use App\Models\Finance\Caisse;
use App\Models\Finance\CategorieEntree;
use App\Models\Finance\Entree;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class PaiementController extends Controller
{
    public function index($inscriptionId)
    {
        return response()->json([
            'success' => true,
            'data' => Paiement::where('inscription_id', $inscriptionId)
                ->with(['utilisateur', 'typeFrais', 'recu'])
                ->latest('date_paiement')
                ->get(),
        ]);
    }

    public function store(Request $request, $inscriptionId)
    {
        $inscription = Inscription::with('resumePaiement')->findOrFail($inscriptionId);
        $user = $request->user();
        $utilisateurId = $user ? (int) $user->getKey() : null;

        $validator = Validator::make($request->all(), [
            'montant' => 'required|numeric|min:1',
            'date_paiement' => 'required|date',
            'reference' => 'nullable|string|max:100',
            'type' => 'nullable|string|max:50',
            'libelle' => 'nullable|string|max:200',
            'type_frais_id' => 'nullable|exists:type_frais,id',
            'details' => 'nullable|array',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        DB::beginTransaction();

        try {
            $paiement = Paiement::create([
                'inscription_id' => $inscription->id,
                'type_frais_id' => $request->type_frais_id,
                'type' => $request->type ?? 'paiement_libre',
                'libelle' => $request->libelle ?? 'Paiement libre',
                'details' => $request->details,
                'montant' => $request->montant,
                'date_paiement' => $request->date_paiement,
                'reference' => $request->reference ?? $this->genererReference(),
                'utilisateur_id' => $utilisateurId,
            ]);

            $recu = Recu::create([
                'numero' => Recu::genererNumero(),
                'paiement_id' => $paiement->id,
                'inscription_id' => $inscription->id,
                'montant' => $paiement->montant,
                'date_emission' => $request->date_paiement,
                'libelle' => $paiement->libelle,
                'details' => $paiement->details ? json_encode($paiement->details) : null,
            ]);

            // --- INTEGRATION FINANCE ---
            if ($paiement->montant > 0) {
                // Déterminer la catégorie
                $libelle = $paiement->libelle ?? 'Autre';
                $typeEntree = CategorieEntree::where('nom', 'like', '%' . $libelle . '%')->first()
                            ?? CategorieEntree::where('nom', 'Scolarité')->first(); // Défaut scolarité ou autre selon le cas

                Entree::create([
                    'reference' => 'ENT-LIB-' . time(),
                    'montant' => $paiement->montant,
                    'date_entree' => $paiement->date_paiement,
                    'type_entree_id' => $typeEntree?->id ?? 1,
                    'inscription_id' => $inscription->id,
                    'annee_scolaire_id' => $inscription->id_annee_scolaire,
                    'description' => 'Paiement libre: ' . $paiement->libelle,
                    'created_by' => $utilisateurId
                ]);

                $caisse = Caisse::firstOrCreate(
                    ['annee_scolaire_id' => $inscription->id_annee_scolaire],
                    ['nom' => 'Caisse Principale', 'solde' => 0]
                );
                $caisse->increment('solde', $paiement->montant);
            }
            // ---------------------------

            $this->mettreAJourResume($inscription);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Paiement enregistre',
                'data' => $paiement->load(['utilisateur', 'typeFrais', 'recu']),
            ], 201);
        } catch (\Throwable $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de l enregistrement du paiement',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function show($id)
    {
        return response()->json([
            'success' => true,
            'data' => Paiement::with(['utilisateur', 'inscription', 'typeFrais', 'recu'])->findOrFail($id),
        ]);
    }

    public function destroy($id)
    {
        DB::beginTransaction();

        try {
            $paiement = Paiement::with('inscription.resumePaiement')->findOrFail($id);

            PresenceCantine::where('paiement_id', $paiement->id)->update([
                'paiement_id' => null,
                'est_paye' => false,
            ]);

            DB::table('paiements_mensuels')
                ->where('paiement_id', $paiement->id)
                ->delete();

            $paiement->recu()?->delete();
            $inscription = $paiement->inscription;
            $paiement->delete();

            if ($inscription) {
                $this->mettreAJourResume($inscription);
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Paiement supprime',
            ]);
        } catch (\Throwable $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la suppression du paiement',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    private function mettreAJourResume(Inscription $inscription): void
    {
        if (!$inscription->resumePaiement) {
            return;
        }

        $totalPaye = (float) Paiement::where('inscription_id', $inscription->id)->sum('montant');

        $inscription->resumePaiement->update([
            'total_paye' => $totalPaye,
            'total_restant' => max((float) $inscription->resumePaiement->total_du - $totalPaye, 0),
        ]);
    }

    private function genererReference(): string
    {
        $lastId = Paiement::max('id') ?? 0;

        return 'PAY-' . date('Y') . '-' . str_pad($lastId + 1, 6, '0', STR_PAD_LEFT);
    }
}
