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
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        $total = 0;
        $details = [];

        $fraisObligatoires = [
            'Inscription',
            $this->getLibelleScolarite($request->cycle),
            'Frais technologiques',
        ];

        foreach ($fraisObligatoires as $libelle) {
            $typeFrais = TypeFrais::where('libelle', $libelle)->first();

            if (!$typeFrais) {
                continue;
            }

            $total += $typeFrais->montant;
            $details[] = [
                'libelle' => $typeFrais->libelle,
                'montant' => $typeFrais->montant,
                'type' => 'obligatoire',
            ];
        }

        if ($request->boolean('parascolaire')) {
            $parascolaire = TypeFrais::where('libelle', 'Parascolaire')->first();

            if ($parascolaire) {
                $total += $parascolaire->montant;
                $details[] = [
                    'libelle' => $parascolaire->libelle,
                    'montant' => $parascolaire->montant,
                    'type' => 'option',
                ];
            }
        }

        if ($request->boolean('cantine')) {
            $cantine = TypeFrais::where('libelle', 'Cantine')->first();

            if ($cantine) {
                $total += $cantine->montant;
                $details[] = [
                    'libelle' => $cantine->libelle,
                    'montant' => $cantine->montant,
                    'type' => 'option',
                ];
            }
        }

        return response()->json([
            'success' => true,
            'data' => [
                'total' => $total,
                'details' => $details,
            ],
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
}
