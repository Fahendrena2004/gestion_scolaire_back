<?php

namespace App\Http\Controllers\Inscription;

use App\Http\Controllers\Controller;
use App\Models\Inscription\TypeFrais;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class TypeFraisController extends Controller
{
    public function index()
    {
        return response()->json([
            'success' => true,
            'data' => TypeFrais::latest()->get()
        ]);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'libelle' => 'required|string|max:255',
            'montant' => 'required|numeric|min:0',
            'est_obligatoire' => 'required|boolean'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $frais = TypeFrais::create([
            'libelle' => $request->libelle,
            'montant' => $request->montant,
            'est_obligatoire' => $request->est_obligatoire
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Type de frais créé',
            'data' => $frais
        ], 201);
    }

    public function update(Request $request, $id)
    {
        $frais = TypeFrais::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'libelle' => 'sometimes|string|max:255',
            'montant' => 'sometimes|numeric|min:0',
            'est_obligatoire' => 'sometimes|boolean'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        // On met à jour uniquement les champs présents
        $frais->update($request->only([
            'libelle',
            'montant',
            'est_obligatoire'
        ]));

        // refresh = recharge depuis la DB
        $frais->refresh();

        return response()->json([
            'success' => true,
            'message' => 'Type de frais mis à jour',
            'data' => $frais
        ]);
    }

    public function destroy($id)
    {
        $frais = TypeFrais::findOrFail($id);

        $frais->delete();

        return response()->json([
            'success' => true,
            'message' => 'Type de frais supprimé'
        ]);
    }
}