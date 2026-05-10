<?php

namespace App\Http\Controllers\Inscription;

use App\Http\Controllers\Controller;
use App\Models\Inscription\Niveau;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class NiveauController extends Controller
{
    public function index()
    {
        return response()->json([
            'success'=>true,
            'data'=>Niveau::all()
        ]);
    }

    public function getByCycle($cycle)
    {
        return response()->json([
            'success'=>true,
            'data'=>Niveau::where('cycle',$cycle)->get()
        ]);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'cycle'      => 'required|in:primaire,college,lycee',
            'nom_niveau' => 'required|unique:niveaux,nom_niveau',
            'serie'      => 'nullable|in:S,L,OSE,Technique',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        $niveau = Niveau::create($request->only(['cycle', 'nom_niveau', 'serie']));

        return response()->json([
            'success' => true,
            'message' => 'Niveau créé',
            'data'    => $niveau
        ], 201);
    }

    public function show($id)
    {
        return response()->json([
            'success'=>true,
            'data'=>Niveau::findOrFail($id)
        ]);
    }

    public function update(Request $request, $id)
    {
        $niveau = Niveau::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'cycle'      => 'sometimes|in:primaire,college,lycee',
            'nom_niveau' => 'sometimes|unique:niveaux,nom_niveau,' . $id,
            'serie'      => 'nullable|in:S,L,OSE,Technique',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        $niveau->update($request->only(['cycle', 'nom_niveau', 'serie']));

        return response()->json([
            'success' => true,
            'message' => 'Niveau mis à jour',
            'data'    => $niveau->refresh()
        ]);
    }

    public function destroy($id)
    {
        Niveau::findOrFail($id)->delete();

        return response()->json([
            'success'=>true,
            'message'=>'Niveau supprimé'
        ]);
    }
}