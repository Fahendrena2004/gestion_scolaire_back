<?php

namespace App\Http\Controllers\Inscription;

use App\Http\Controllers\Controller;
use App\Models\Inscription\Paiement;
use App\Models\Inscription\Inscription;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Auth;

class PaiementController extends Controller
{
    public function index($inscriptionId)
    {
        return response()->json([
            'success'=>true,
            'data'=>Paiement::where('inscription_id',$inscriptionId)
                ->with('utilisateur')
                ->latest('date_paiement')
                ->get()
        ]);
    }

    public function store(Request $request, $inscriptionId)
    {
        $inscription = Inscription::findOrFail($inscriptionId);

        $validator = Validator::make($request->all(), [
            'montant'=>'required|numeric|min:1',
            'date_paiement'=>'required|date',
            'reference'=>'nullable|string|max:100'
        ]);

        if ($validator->fails()) {
            return response()->json(['success'=>false,'errors'=>$validator->errors()],422);
        }

        $paiement = Paiement::create([
            'inscription_id'=>$inscription->id,
            'montant'=>$request->montant,
            'date_paiement'=>$request->date_paiement,
            'reference'=>$request->reference ?? 'PAY-'.time(),
            'utilisateur_id'=>Auth::id()
        ]);

        return response()->json([
            'success'=>true,
            'message'=>'Paiement enregistré',
            'data'=>$paiement->load('utilisateur')
        ],201);
    }

    public function show($id)
    {
        return response()->json([
            'success'=>true,
            'data'=>Paiement::with(['utilisateur','inscription'])->findOrFail($id)
        ]);
    }

    public function destroy($id)
    {
        Paiement::findOrFail($id)->delete();

        return response()->json([
            'success'=>true,
            'message'=>'Paiement supprimé'
        ]);
    }
}