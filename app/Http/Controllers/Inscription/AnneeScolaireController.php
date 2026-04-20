<?php

namespace App\Http\Controllers\Inscription;

use App\Http\Controllers\Controller;
use App\Models\Inscription\AnneeScolaire;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class AnneeScolaireController extends Controller
{
    public function index()
    {
        return response()->json([
            'success' => true,
            'data' => AnneeScolaire::latest('date_debut')->get()
        ]);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'date_debut' => 'required|date',
            'date_fin' => 'required|date|after:date_debut',
            'statut' => 'required|in:en_cours,termine,planifie'
        ]);

        if ($validator->fails()) {
            return response()->json(['success'=>false,'errors'=>$validator->errors()], 422);
        }

        if ($request->statut === 'en_cours') {
            AnneeScolaire::where('statut', 'en_cours')->update(['statut' => 'termine']);
        }

        $annee = AnneeScolaire::create($request->only(['date_debut','date_fin','statut']));

        return response()->json([
            'success' => true,
            'message' => 'Année scolaire créée',
            'data' => $annee
        ], 201);
    }

    public function show($id)
    {
        $annee = AnneeScolaire::findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => $annee
        ]);
    }

    public function update(Request $request, $id)
    {
        $annee = AnneeScolaire::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'date_debut' => 'sometimes|date',
            'date_fin' => 'sometimes|date|after:date_debut',
            'statut' => 'sometimes|in:en_cours,termine,planifie'
        ]);

        if ($validator->fails()) {
            return response()->json(['success'=>false,'errors'=>$validator->errors()], 422);
        }

        if ($request->statut === 'en_cours') {
            AnneeScolaire::where('statut','en_cours')
                ->where('id','!=',$id)
                ->update(['statut'=>'termine']);
        }

        $annee->update($request->only(['date_debut','date_fin','statut']));
        $annee->refresh();

        return response()->json([
            'success' => true,
            'message' => 'Année mise à jour',
            'data' => $annee
        ]);
    }

    public function destroy($id)
    {
        $annee = AnneeScolaire::findOrFail($id);
        $annee->delete();

        return response()->json([
            'success' => true,
            'message' => 'Année supprimée'
        ]);
    }

    public function getActive()
    {
        return response()->json([
            'success' => true,
            'data' => AnneeScolaire::where('statut','en_cours')->first()
        ]);
    }
}