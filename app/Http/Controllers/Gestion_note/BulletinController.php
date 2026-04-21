<?php

namespace App\Http\Controllers\Gestion_note;

use App\Http\Controllers\Controller;
use App\Models\Gestion_note\Bulletin;
use App\Models\Gestion_note\DetailBulletins;
use App\Models\Inscription\Inscription;
use App\Services\BulletinService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class BulletinController extends Controller
{
    protected $bulletinService;

    public function __construct(BulletinService $bulletinService)
    {
        $this->bulletinService = $bulletinService;
    }

    public function generate(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'inscription_id' => 'required|exists:inscriptions,id',
            'periode' => 'required|string'
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        try {
            $bulletin = $this->bulletinService->genererBulletin(
                $request->inscription_id,
                $request->periode
            );
            
            return response()->json([
                'success' => true,
                'message' => 'Bulletin généré avec succès',
                'data' => $bulletin
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    public function generateForClass(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'classe_id' => 'required|exists:classes,id',
            'periode' => 'required|string',
            'annee_scolaire_id' => 'required|exists:annee_scolaires,id'
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        try {
            $resultats = $this->bulletinService->genererBulletinsClasse(
                $request->classe_id,
                $request->periode,
                $request->annee_scolaire_id
            );
            
            $successCount = count(array_filter($resultats, function($r) {
                return $r['success'];
            }));
            
            return response()->json([
                'success' => true,
                'message' => $successCount . ' bulletins générés avec succès',
                'data' => $resultats
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la génération des bulletins',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function getByEleve($inscriptionId)
    {
        $inscription = Inscription::find($inscriptionId);
        
        if (!$inscription) {
            return response()->json([
                'success' => false,
                'message' => 'Inscription non trouvée'
            ], 404);
        }
        
        $bulletins = Bulletin::where('inscription_id', $inscriptionId)
            ->with(['detailBulletins.matiere', 'inscription.eleve', 'inscription.classe'])
            ->orderBy('created_at', 'desc')
            ->get();
        
        return response()->json([
            'success' => true,
            'data' => $bulletins
        ]);
    }

    public function show($id)
    {
        $bulletin = Bulletin::with([
            'inscription.eleve',
            'inscription.classe.niveau',
            'detailBulletins.matiere'
        ])->find($id);
        
        if (!$bulletin) {
            return response()->json([
                'success' => false,
                'message' => 'Bulletin non trouvé'
            ], 404);
        }
        
        return response()->json([
            'success' => true,
            'data' => $bulletin
        ]);
    }

    public function updateAppreciation(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'appreciation' => 'required|string|max:255'
        ]);
        
        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }
        
        $bulletin = Bulletin::find($id);
        
        if (!$bulletin) {
            return response()->json([
                'success' => false,
                'message' => 'Bulletin non trouvé'
            ], 404);
        }
        
        try {
            Bulletin::where('id', $id)->update(['appreciation' => $request->appreciation]);
            
            $updatedBulletin = Bulletin::find($id);
            
            return response()->json([
                'success' => true,
                'message' => 'Appréciation mise à jour avec succès',
                'data' => $updatedBulletin
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la mise à jour',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function getByClass(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'classe_id' => 'required|exists:classes,id',
            'periode' => 'required|string',
            'annee_scolaire_id' => 'required|exists:annee_scolaires,id'
        ]);
        
        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }
        
        $bulletins = Bulletin::whereHas('inscription', function($query) use ($request) {
                $query->where('id_classe', $request->classe_id)
                      ->where('id_annee_scolaire', $request->annee_scolaire_id);
            })
            ->where('periode', $request->periode)
            ->with(['inscription.eleve', 'detailBulletins.matiere'])
            ->orderBy('rang', 'asc')
            ->get();
        
        return response()->json([
            'success' => true,
            'data' => $bulletins
        ]);
    }

    public function destroy($id)
    {
        $bulletin = Bulletin::find($id);
        
        if (!$bulletin) {
            return response()->json([
                'success' => false,
                'message' => 'Bulletin non trouvé'
            ], 404);
        }
        
        DB::beginTransaction();
        try {
            DetailBulletins::where('bulletin_id', $id)->delete();
            Bulletin::where('id', $id)->delete();
            
            DB::commit();
            
            return response()->json([
                'success' => true,
                'message' => 'Bulletin supprimé avec succès'
            ]);
            
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la suppression',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function exportPDF($id)
    {
        $bulletin = Bulletin::with([
            'inscription.eleve',
            'inscription.classe.niveau',
            'detailBulletins.matiere'
        ])->find($id);
        
        if (!$bulletin) {
            return response()->json([
                'success' => false,
                'message' => 'Bulletin non trouvé'
            ], 404);
        }
        
        return response()->json([
            'success' => true,
            'message' => 'Fonctionnalité d\'export PDF à implémenter',
            'data' => $bulletin
        ]);
    }
}