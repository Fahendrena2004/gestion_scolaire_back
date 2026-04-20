<?php

namespace App\Http\Controllers\Inscription;

use App\Http\Controllers\Controller;
use App\Models\Inscription\TypeFrais;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class FraisController extends Controller
{
    public function calcul(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'cycle' => 'required|string|in:primaire,college,lycee',
            'parascolaire' => 'sometimes|boolean',
            'cantine' => 'sometimes|boolean',
            'sports' => 'sometimes|boolean'
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $total = 0;
        $details = [];

        // Frais d'inscription (obligatoire)
        $inscription = TypeFrais::where('libelle', 'Inscription')->first();
        if ($inscription) {
            $total += $inscription->montant;
            $details[] = [
                'libelle' => $inscription->libelle,
                'montant' => $inscription->montant,
                'type' => 'obligatoire'
            ];
        }

        // Scolarité selon le cycle
        $scolarite = TypeFrais::where('libelle', 'like', 'Scolarité%')
            ->where('libelle', 'like', '%' . ucfirst($request->cycle) . '%')
            ->first();
        
        if ($scolarite) {
            $total += $scolarite->montant;
            $details[] = [
                'libelle' => $scolarite->libelle,
                'montant' => $scolarite->montant,
                'type' => 'obligatoire'
            ];
        }

        // Frais technologiques
        $techno = TypeFrais::where('libelle', 'Frais technologiques')->first();
        if ($techno) {
            $total += $techno->montant;
            $details[] = [
                'libelle' => $techno->libelle,
                'montant' => $techno->montant,
                'type' => 'obligatoire'
            ];
        }

        // Options
        if ($request->parascolaire) {
            $para = TypeFrais::where('libelle', 'Parascolaire')->first();
            if ($para) {
                $total += $para->montant;
                $details[] = [
                    'libelle' => $para->libelle,
                    'montant' => $para->montant,
                    'type' => 'optionnel'
                ];
            }
        }

        if ($request->cantine) {
            $cantine = TypeFrais::where('libelle', 'Cantine')->first();
            if ($cantine) {
                $total += $cantine->montant;
                $details[] = [
                    'libelle' => $cantine->libelle,
                    'montant' => $cantine->montant,
                    'type' => 'optionnel'
                ];
            }
        }

        if ($request->sports) {
            $sports = TypeFrais::where('libelle', 'Sports')->first();
            if ($sports) {
                $total += $sports->montant;
                $details[] = [
                    'libelle' => $sports->libelle,
                    'montant' => $sports->montant,
                    'type' => 'optionnel'
                ];
            }
        }

        return response()->json([
            'success' => true,
            'data' => [
                'total' => $total,
                'details' => $details
            ]
        ]);
    }
}