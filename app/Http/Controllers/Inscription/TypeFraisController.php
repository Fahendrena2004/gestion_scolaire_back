<?php

namespace App\Http\Controllers\Inscription;

use App\Http\Controllers\Controller;
use App\Models\Inscription\TypeFrais;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class TypeFraisController extends Controller
{
    public function index(Request $request)
    {
        $query = TypeFrais::with('anneeScolaire')->latest();

        if ($request->filled('annee_scolaire_id')) {
            $query->where('annee_scolaire_id', $request->annee_scolaire_id);
        }

        return response()->json([
            'success' => true,
            'data' => $query->get(),
        ]);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'annee_scolaire_id' => 'required|exists:annee_scolaires,id',
            'libelle' => [
                'required',
                'string',
                'max:255',
                Rule::unique('type_frais')->where(function ($query) use ($request) {
                    $query->where('annee_scolaire_id', $request->annee_scolaire_id)
                          ->where('target_type', $request->target_type ?? 'general');

                    if ($request->filled('target_value')) {
                        $query->where('target_value', $request->target_value);
                    } else {
                        $query->whereNull('target_value');
                    }

                    return $query;
                }),
            ],
            'montant' => 'required|numeric|min:0',
            'est_obligatoire' => 'required|boolean',
            'target_type' => 'nullable|in:general,cycle,niveau',
            'target_value' => 'nullable|string|max:100',
            'frequence' => 'nullable|in:unique,mensuel',
            'categorie' => 'nullable|string|max:50',
            'ordre_affichage' => 'nullable|integer',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        $frais = TypeFrais::create([
            'annee_scolaire_id' => $request->annee_scolaire_id,
            'libelle' => $request->libelle,
            'montant' => $request->montant,
            'est_obligatoire' => $request->est_obligatoire,
            'target_type' => $request->target_type ?? 'general',
            'target_value' => $request->target_value,
            'frequence' => $request->frequence ?? 'unique',
            'categorie' => $request->categorie ?? 'autre',
            'ordre_affichage' => $request->ordre_affichage ?? 0,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Type de frais cree',
            'data' => $frais->load('anneeScolaire'),
        ], 201);
    }

    public function update(Request $request, $id)
    {
        $frais = TypeFrais::findOrFail($id);
        $anneeId = $request->input('annee_scolaire_id', $frais->annee_scolaire_id);

        $validator = Validator::make($request->all(), [
            'annee_scolaire_id' => 'sometimes|exists:annee_scolaires,id',
            'libelle' => [
                'sometimes',
                'string',
                'max:255',
                Rule::unique('type_frais')
                    ->ignore($id)
                    ->where(function ($query) use ($request, $anneeId) {
                        $query->where('annee_scolaire_id', $anneeId)
                              ->where('target_type', $request->target_type ?? 'general');

                        if ($request->filled('target_value')) {
                            $query->where('target_value', $request->target_value);
                        } else {
                            $query->whereNull('target_value');
                        }

                        return $query;
                    }),
            ],
            'montant' => 'sometimes|numeric|min:0',
            'est_obligatoire' => 'sometimes|boolean',
            'target_type' => 'sometimes|in:general,cycle,niveau',
            'target_value' => 'nullable|string|max:100',
            'frequence' => 'sometimes|in:unique,mensuel',
            'categorie' => 'sometimes|string|max:50',
            'ordre_affichage' => 'sometimes|integer',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        $frais->update($request->only([
            'annee_scolaire_id',
            'libelle',
            'montant',
            'est_obligatoire',
            'target_type',
            'target_value',
            'frequence',
            'categorie',
            'ordre_affichage',
        ]));

        return response()->json([
            'success' => true,
            'message' => 'Type de frais mis a jour',
            'data' => $frais->fresh()->load('anneeScolaire'),
        ]);
    }

    public function destroy($id)
    {
        TypeFrais::findOrFail($id)->delete();

        return response()->json([
            'success' => true,
            'message' => 'Type de frais supprime',
        ]);
    }
}
