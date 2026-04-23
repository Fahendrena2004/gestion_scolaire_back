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
        $validator = Validator::make($request->all(), [
            'cycle' => 'required|string|in:primaire,college,lycee',
            'annee_scolaire_id' => 'nullable|exists:annee_scolaires,id',
            'parascolaire' => 'sometimes|boolean',
            'cantine' => 'sometimes|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        $anneeScolaire = $request->filled('annee_scolaire_id')
            ? AnneeScolaire::find($request->annee_scolaire_id)
            : AnneeScolaire::where('statut', 'en_cours')->first();

        $total = 0;
        $details = [];

        $fraisObligatoires = [
            'Inscription',
            $this->getLibelleScolarite($request->cycle),
            'Frais technologiques',
        ];

        foreach ($fraisObligatoires as $libelle) {
            $typeFrais = $this->getTypeFrais($libelle, $anneeScolaire?->id);

            if (!$typeFrais) {
                continue;
            }

            $total += (float) $typeFrais->montant;
            $details[] = [
                'libelle' => $typeFrais->libelle,
                'montant' => $typeFrais->montant,
                'type' => 'obligatoire',
                'annee_scolaire_id' => $typeFrais->annee_scolaire_id,
            ];
        }

        if ($request->boolean('parascolaire')) {
            $parascolaire = $this->getTypeFrais('Parascolaire', $anneeScolaire?->id);

            if ($parascolaire) {
                $total += (float) $parascolaire->montant;
                $details[] = [
                    'libelle' => $parascolaire->libelle,
                    'montant' => $parascolaire->montant,
                    'type' => 'option',
                    'annee_scolaire_id' => $parascolaire->annee_scolaire_id,
                ];
            }
        }

        if ($request->boolean('cantine')) {
            $cantine = $this->getTypeFrais('Cantine', $anneeScolaire?->id);

            if ($cantine) {
                $total += (float) $cantine->montant;
                $details[] = [
                    'libelle' => $cantine->libelle,
                    'montant' => $cantine->montant,
                    'type' => 'option',
                    'annee_scolaire_id' => $cantine->annee_scolaire_id,
                ];
            }
        }

        return response()->json([
            'success' => true,
            'data' => [
                'annee_scolaire_id' => $anneeScolaire?->id,
                'total' => $total,
                'details' => $details,
            ],
        ]);
    }

    private function getTypeFrais(string $libelle, ?int $anneeScolaireId): ?TypeFrais
    {
        return TypeFrais::where('libelle', $libelle)
            ->where(function ($query) use ($anneeScolaireId) {
                if ($anneeScolaireId) {
                    $query->where('annee_scolaire_id', $anneeScolaireId)
                        ->orWhereNull('annee_scolaire_id');
                } else {
                    $query->whereNull('annee_scolaire_id');
                }
            })
            ->orderByRaw('CASE WHEN annee_scolaire_id IS NULL THEN 1 ELSE 0 END')
            ->first();
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
