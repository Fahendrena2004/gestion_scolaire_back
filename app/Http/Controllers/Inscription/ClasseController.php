<?php

namespace App\Http\Controllers\Inscription;

use App\Http\Controllers\Controller;
use App\Models\Inscription\Classe;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class ClasseController extends Controller
{
    public function index()
    {
        $classes = Classe::with(['niveau','anneeScolaire'])->get();
        return response()->json([
            'success'=>true,
            'data'=>$classes
        ]);
    }

    public function getByNiveau($niveauId)
    {
        $classes = Classe::where('niveau_id',$niveauId)->with('anneeScolaire')->get();  
        return response()->json([
            'success'=>true,
            'data'=>$classes
        ]);
    }

    public function getByCycle($cycle)
    {
        $classes = Classe::whereHas('niveau',fn($q)=>$q->where('cycle',$cycle))
            ->with(['niveau','anneeScolaire'])->get();
        return response()->json([
            'success'=>true,
            'data'=>$classes
        ]);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'nom_classe'=>'required|string|max:100',
            'niveau_id'=>'required|exists:niveaux,id',
            'code_division'=>'required|string|max:10',
            'anneeScolaire_id'=>'required|exists:annee_scolaires,id'
        ]);

        if ($validator->fails()) {
            return response()->json(['success'=>false,'errors'=>$validator->errors()],422);
        }

        $classe = Classe::create($request->only([
            'nom_classe','niveau_id','code_division','anneeScolaire_id'
        ]));

        return response()->json([
            'success'=>true,
            'message'=>'Classe créée',
            'data'=>$classe->load('niveau','anneeScolaire')
        ],201);
    }

    public function show($id)
    {
        $classes = Classe::with(['niveau','anneeScolaire'])->findOrFail($id);
        return response()->json([
            'success'=>true,
            'data'=>$classes
        ]);
    }

    public function update(Request $request, $id)
    {
        $classe = Classe::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'nom_classe'=>'sometimes|string|max:100',
            'niveau_id'=>'sometimes|exists:niveaux,id',
            'code_division'=>'sometimes|string|max:10',
            'effectif'=>'sometimes|integer|min:0'
        ]);

        if ($validator->fails()) {
            return response()->json(['success'=>false,'errors'=>$validator->errors()],422);
        }

        $classe->update($request->only([
            'nom_classe','niveau_id','code_division','effectif'
        ]));

        return response()->json([
            'success'=>true,
            'message'=>'Classe mise à jour',
            'data'=>$classe->fresh()->load('niveau','anneeScolaire')
        ]);
    }

    public function destroy($id)
    {
        Classe::findOrFail($id)->delete();

        return response()->json([
            'success'=>true,
            'message'=>'Classe supprimée'
        ]);
    }
}