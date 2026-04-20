<?php

namespace App\Http\Controllers\Gestion_note;

use App\Http\Controllers\Controller;
use App\Models\Gestion_note\Matieres;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class MatieresController extends Controller
{
    public function index()
    {
        $matieres = Matieres::all();
        
        return response()->json([
            'success' => true,
            'data' => $matieres,
            'count' => $matieres->count()
        ]);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'nom' => 'required|string|max:100|unique:matieres,nom',
            'coefficient' => 'required|integer|min:1|max:10'
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $matiere = Matieres::create($request->only(['nom', 'coefficient']));

        return response()->json([
            'success' => true,
            'message' => 'Matière ajoutée avec succès',
            'data' => $matiere
        ], 201);
    }

    public function storeMultiple(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'matieres' => 'required|array',
            'matieres.*.nom' => 'required|string|max:100|unique:matieres,nom',
            'matieres.*.coefficient' => 'required|integer|min:1|max:10'
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $created = [];
        foreach ($request->matieres as $matiereData) {
            $created[] = Matieres::create($matiereData);
        }

        return response()->json([
            'success' => true,
            'message' => count($created) . ' matières ajoutées',
            'data' => $created
        ]);
    }

    public function show($id)
    {
        $matiere = Matieres::find($id);
        
        if (!$matiere) {
            return response()->json([
                'success' => false,
                'message' => 'Matière non trouvée'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $matiere
        ]);
    }

    public function update(Request $request, $id)
    {
        $matiere = Matieres::findOrFail($id);
        
        if (!$matiere) {
            return response()->json([
                'success' => false,
                'message' => 'Matière non trouvée'
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'nom' => 'sometimes|string|max:100|unique:matieres,nom,' . $id,
            'coefficient' => 'sometimes|integer|min:1|max:10'
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $matiere->update($request->only(['nom', 'coefficient']));

        return response()->json([
            'success' => true,
            'message' => 'Matière modifiée avec succès',
            'data' => $matiere
        ]);
    }

    
    public function destroy($id)
    {
        Matieres::findOrFail($id)->delete();

        return response()->json([
            'success' => true,
            'message' => 'Matière supprimée'
        ]);
    }


    public function suggestions($cycle)
    {
        $suggestions = [
            'primaire' => [
                ['nom' => 'Mathématiques', 'coefficient' => 4],
                ['nom' => 'Français', 'coefficient' => 4],
                ['nom' => 'Lecture et Écriture', 'coefficient' => 3],
                ['nom' => 'Éducation Civique', 'coefficient' => 1],
                ['nom' => 'Histoire-Géographie', 'coefficient' => 2],
                ['nom' => 'Sciences', 'coefficient' => 2],
                ['nom' => 'Anglais', 'coefficient' => 2],
                ['nom' => 'Éducation Physique', 'coefficient' => 1],
                ['nom' => 'Arts', 'coefficient' => 1],
                ['nom' => 'Musique', 'coefficient' => 1],
            ],
            'college' => [
                ['nom' => 'Mathématiques', 'coefficient' => 4],
                ['nom' => 'Français', 'coefficient' => 4],
                ['nom' => 'Anglais', 'coefficient' => 3],
                ['nom' => 'Histoire-Géographie', 'coefficient' => 2],
                ['nom' => 'Sciences Physiques', 'coefficient' => 3],
                ['nom' => 'SVT', 'coefficient' => 3],
                ['nom' => 'Technologie', 'coefficient' => 1],
                ['nom' => 'Éducation Physique', 'coefficient' => 1],
                ['nom' => 'Arts Plastiques', 'coefficient' => 1],
                ['nom' => 'Musique', 'coefficient' => 1],
                ['nom' => 'Espagnol', 'coefficient' => 2],
                ['nom' => 'Allemand', 'coefficient' => 2],
            ],
            'lycee' => [
                ['nom' => 'Mathématiques', 'coefficient' => 4],
                ['nom' => 'Français', 'coefficient' => 4],
                ['nom' => 'Philosophie', 'coefficient' => 3],
                ['nom' => 'Anglais', 'coefficient' => 3],
                ['nom' => 'Histoire-Géographie', 'coefficient' => 2],
                ['nom' => 'Sciences Physiques', 'coefficient' => 3],
                ['nom' => 'SVT', 'coefficient' => 3],
                ['nom' => 'Éducation Physique', 'coefficient' => 1],
                ['nom' => 'Espagnol', 'coefficient' => 2],
                ['nom' => 'Allemand', 'coefficient' => 2],
                ['nom' => 'Comptabilité', 'coefficient' => 3],
                ['nom' => 'Gestion', 'coefficient' => 3],
                ['nom' => 'Économie', 'coefficient' => 3],
                ['nom' => 'Droit', 'coefficient' => 2],
            ]
        ];

        if (!isset($suggestions[$cycle])) {
            return response()->json([
                'success' => false,
                'message' => 'Cycle non reconnu. Utilisez: primaire, college, lycee'
            ], 400);
        }

        return response()->json([
            'success' => true,
            'cycle' => $cycle,
            'suggestions' => $suggestions[$cycle]
        ]);
    }
}