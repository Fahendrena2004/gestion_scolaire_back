<?php

namespace App\Http\Controllers\Gestion_note;

use App\Http\Controllers\Controller;
use App\Models\Gestion_note\Matieres;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class MatieresController extends Controller
{
    public function index(Request $request)
    {
        $query = Matieres::with('classe.niveau');

        if ($request->filled('classe_id')) {
            $query->where('classe_id', $request->classe_id);
        }

        if ($request->filled('niveau_id')) {
            $query->where(function($q) use ($request) {
                $q->whereHas('classe', function ($q2) use ($request) {
                    $q2->where('niveau_id', $request->niveau_id);
                })->orWhere(function($q2) use ($request) {
                    $niveau = \App\Models\Inscription\Niveau::find($request->niveau_id);
                    if ($niveau) {
                        $q2->whereNull('classe_id')
                           ->where('cycle', $niveau->cycle)
                           ->where('niveau_classe', $niveau->nom_niveau);
                    }
                });
            });
        }

        if ($request->filled('cycle')) {
            $query->where(function($q) use ($request) {
                $q->whereHas('classe.niveau', function ($q2) use ($request) {
                    $q2->where('cycle', $request->cycle);
                })->orWhere(function($q2) use ($request) {
                    $q2->whereNull('classe_id')
                       ->where('cycle', $request->cycle);
                });
            });
        }

        $matieres = $query
            ->orderBy('classe_id')
            ->orderBy('nom')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $matieres,
            'count' => $matieres->count(),
        ]);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'nom' => [
                'required',
                'string',
                'max:100',
                Rule::unique('matieres')->where(function ($query) use ($request) {
                    return $query->where('classe_id', $request->classe_id);
                }),
            ],
            'coefficient' => 'required|integer|min:1|max:10',
            'classe_id' => 'nullable|exists:classes,id',
            'cycle' => 'nullable|string|max:50',
            'niveau_classe' => 'nullable|string|max:50',
            'section' => 'nullable|string|max:50',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $matiere = Matieres::create($request->only(['nom', 'coefficient', 'classe_id', 'cycle', 'niveau_classe', 'section']));

        return response()->json([
            'success' => true,
            'message' => 'Matiere ajoutee avec succes',
            'data' => $matiere->load('classe.niveau'),
        ], 201);
    }

    public function storeMultiple(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'matieres' => 'required|array|min:1',
            'matieres.*.nom' => 'required|string|max:100',
            'matieres.*.coefficient' => 'required|integer|min:1|max:10',
            'matieres.*.classe_id' => 'nullable|exists:classes,id',
            'matieres.*.cycle' => 'nullable|string|max:50',
            'matieres.*.niveau_classe' => 'nullable|string|max:50',
            'matieres.*.section' => 'nullable|string|max:50',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $doublonsPayload = collect($request->matieres)
            ->map(fn ($matiere) => strtolower(trim(($matiere['nom'] ?? '') . '|' . ($matiere['classe_id'] ?? ''))))
            ->duplicates()
            ->values();

        if ($doublonsPayload->isNotEmpty()) {
            return response()->json([
                'errors' => [
                    'matieres' => ['Le payload contient des matieres en double.'],
                ],
            ], 422);
        }

        foreach ($request->matieres as $index => $matiereData) {
            $query = Matieres::where('nom', $matiereData['nom']);
            
            if (isset($matiereData['classe_id'])) {
                $query->where('classe_id', $matiereData['classe_id']);
            } else {
                $query->whereNull('classe_id')
                      ->where('cycle', $matiereData['cycle'] ?? null)
                      ->where('niveau_classe', $matiereData['niveau_classe'] ?? null);
            }

            if ($query->exists()) {
                return response()->json([
                    'errors' => [
                        "matieres.$index.nom" => ['Cette matiere existe deja avec ces parametres.'],
                    ],
                ], 422);
            }
        }

        $created = [];

        foreach ($request->matieres as $matiereData) {
            $created[] = Matieres::create($matiereData)->load('classe.niveau');
        }

        return response()->json([
            'success' => true,
            'message' => count($created) . ' matieres ajoutees',
            'data' => $created,
        ]);
    }

    public function show($id)
    {
        $matiere = Matieres::with('classe.niveau')->find($id);

        if (!$matiere) {
            return response()->json([
                'success' => false,
                'message' => 'Matiere non trouvee',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $matiere,
        ]);
    }

    public function update(Request $request, $id)
    {
        $matiere = Matieres::findOrFail($id);
        $classeId = $request->input('classe_id', $matiere->classe_id);

        $validator = Validator::make($request->all(), [
            'nom' => [
                'sometimes',
                'string',
                'max:100',
                Rule::unique('matieres')
                    ->ignore($id)
                    ->where(function ($query) use ($classeId) {
                        return $query->where('classe_id', $classeId);
                    }),
            ],
            'coefficient' => 'sometimes|integer|min:1|max:10',
            'classe_id' => 'sometimes|nullable|exists:classes,id',
            'cycle' => 'sometimes|nullable|string|max:50',
            'niveau_classe' => 'sometimes|nullable|string|max:50',
            'section' => 'sometimes|nullable|string|max:50',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $matiere->update($request->only(['nom', 'coefficient', 'classe_id', 'cycle', 'niveau_classe', 'section']));

        return response()->json([
            'success' => true,
            'message' => 'Matiere modifiee avec succes',
            'data' => $matiere->load('classe.niveau'),
        ]);
    }

    public function destroy($id)
    {
        Matieres::findOrFail($id)->delete();

        return response()->json([
            'success' => true,
            'message' => 'Matiere supprimee',
        ]);
    }

    public function suggestions($cycle)
    {
        $suggestions = [
            'primaire' => [
                ['nom' => 'Mathematiques', 'coefficient' => 4],
                ['nom' => 'Francais', 'coefficient' => 4],
                ['nom' => 'Lecture et Ecriture', 'coefficient' => 3],
                ['nom' => 'Education Civique', 'coefficient' => 1],
                ['nom' => 'Histoire-Geographie', 'coefficient' => 2],
                ['nom' => 'Sciences', 'coefficient' => 2],
                ['nom' => 'Anglais', 'coefficient' => 2],
                ['nom' => 'Education Physique', 'coefficient' => 1],
                ['nom' => 'Arts', 'coefficient' => 1],
                ['nom' => 'Musique', 'coefficient' => 1],
            ],
            'college' => [
                ['nom' => 'Mathematiques', 'coefficient' => 4],
                ['nom' => 'Francais', 'coefficient' => 4],
                ['nom' => 'Anglais', 'coefficient' => 3],
                ['nom' => 'Histoire-Geographie', 'coefficient' => 2],
                ['nom' => 'Sciences Physiques', 'coefficient' => 3],
                ['nom' => 'SVT', 'coefficient' => 3],
                ['nom' => 'Technologie', 'coefficient' => 1],
                ['nom' => 'Education Physique', 'coefficient' => 1],
                ['nom' => 'Arts Plastiques', 'coefficient' => 1],
                ['nom' => 'Musique', 'coefficient' => 1],
                ['nom' => 'Espagnol', 'coefficient' => 2],
                ['nom' => 'Allemand', 'coefficient' => 2],
            ],
            'lycee' => [
                ['nom' => 'Mathematiques', 'coefficient' => 4],
                ['nom' => 'Francais', 'coefficient' => 4],
                ['nom' => 'Philosophie', 'coefficient' => 3],
                ['nom' => 'Anglais', 'coefficient' => 3],
                ['nom' => 'Histoire-Geographie', 'coefficient' => 2],
                ['nom' => 'Sciences Physiques', 'coefficient' => 3],
                ['nom' => 'SVT', 'coefficient' => 3],
                ['nom' => 'Education Physique', 'coefficient' => 1],
                ['nom' => 'Espagnol', 'coefficient' => 2],
                ['nom' => 'Allemand', 'coefficient' => 2],
                ['nom' => 'Comptabilite', 'coefficient' => 3],
                ['nom' => 'Gestion', 'coefficient' => 3],
                ['nom' => 'Economie', 'coefficient' => 3],
                ['nom' => 'Droit', 'coefficient' => 2],
            ],
        ];

        if (!isset($suggestions[$cycle])) {
            return response()->json([
                'success' => false,
                'message' => 'Cycle non reconnu. Utilisez: primaire, college, lycee',
            ], 400);
        }

        return response()->json([
            'success' => true,
            'cycle' => $cycle,
            'suggestions' => $suggestions[$cycle],
        ]);
    }
}
