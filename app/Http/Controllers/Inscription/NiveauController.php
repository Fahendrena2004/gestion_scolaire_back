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
            'cycle'      => 'required|string',
            'nom_niveau' => 'required|string',
            'serie'      => 'nullable|string|max:50',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        // We allow any serie to be added dynamically without hardcoded level checks

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
            'cycle'      => 'sometimes|string',
            'nom_niveau' => 'sometimes|string',
            'serie'      => 'nullable|string|max:50',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        // We allow any serie to be added dynamically without hardcoded level checks

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