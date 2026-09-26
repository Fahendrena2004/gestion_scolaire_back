<?php

namespace App\Http\Controllers\Inscription;

use App\Http\Controllers\Controller;
use App\Models\Inscription\AnneeScolaire;
use App\Models\Inscription\TypeFrais;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class FraisController extends Controller
{
    public function calcul(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'cycle'        => 'required|string',
                'parascolaire' => 'sometimes',
                'cantine'      => 'sometimes',
            ]);

            if ($validator->fails()) {
                return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
            }

            $anneeScolaire = AnneeScolaire::where('statut', 'en_cours')->first();

            if (!$anneeScolaire) {
                return response()->json(['success' => false, 'message' => 'Aucune année scolaire active.'], 404);
            }

            $cycle        = $request->input('cycle');
            $parascolaire = $request->input('parascolaire') == 1 || $request->input('parascolaire') === 'true';
            $cantine      = $request->input('cantine') == 1 || $request->input('cantine') === 'true';

            $frais = [];

            // 1. Inscription
            $frais[] = $this->getTypeFrais('Inscription', $anneeScolaire->id);

            // 2. Scolarité (Recherche flexible)
            $libelleScolarite = 'Scolarité';
            if ($cycle == 'primaire') $libelleScolarite = 'Scolarité - Primaire';
            else if ($cycle == 'college') $libelleScolarite = 'Scolarité - Collège';
            else if ($cycle == 'lycee') $libelleScolarite = 'Scolarité - Lycée';
            
            $frais[] = $this->getTypeFrais($libelleScolarite, $anneeScolaire->id);

            // 3. Frais technologiques
            $frais[] = $this->getTypeFrais('Frais technologiques', $anneeScolaire->id);

            // 4. Options
            if ($parascolaire) $frais[] = $this->getTypeFrais('Parascolaire', $anneeScolaire->id);
            if ($cantine) $frais[] = $this->getTypeFrais('Cantine', $anneeScolaire->id);

            // Nettoyage des résultats (filtre les null)
            $finalFrais = [];
            foreach ($frais as $f) {
                if ($f) $finalFrais[] = $f;
            }

            $total = 0;
            foreach ($finalFrais as $item) {
                $total += (float) $item->montant;
            }

            return response()->json([
                'success' => true,
                'data'    => [
                    'details'      => $finalFrais,
                    'total_fixe'   => $total,
                    'cycle'        => $cycle,
                    'annee_active' => $anneeScolaire->libelle,
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur Backend: ' . $e->getMessage()
            ], 500);
        }
    }

    private function getTypeFrais($libelle, $anneeId)
    {
        // 1. Tentative de correspondance exacte
        $frais = TypeFrais::where('libelle', $libelle)
            ->where(function($q) use ($anneeId) {
                $q->where('annee_scolaire_id', $anneeId)->orWhereNull('annee_scolaire_id');
            })
            ->orderByRaw('CASE WHEN annee_scolaire_id IS NULL THEN 1 ELSE 0 END')
            ->first();

        if ($frais) return $frais;

        // 2. Recherche flexible pour la scolarité / écolage
        $low = strtolower($libelle);
        if (strpos($low, 'scolarit') !== false || strpos($low, 'ecolage') !== false) {
            return TypeFrais::where(function($q) {
                    $q->where('libelle', 'LIKE', '%Scolarité%')
                      ->orWhere('libelle', 'LIKE', '%Ecolage%')
                      ->orWhere('libelle', 'LIKE', '%Mensualité%')
                      ->orWhere('libelle', 'LIKE', '%Scolarite%');
                })
                ->where(function($q) use ($anneeId) {
                    $q->where('annee_scolaire_id', $anneeId)->orWhereNull('annee_scolaire_id');
                })
                ->orderByRaw('CASE WHEN annee_scolaire_id IS NULL THEN 1 ELSE 0 END')
                ->first();
        }

        return null;
    }
}