<?php

namespace App\Http\Controllers\Gestion_note;

use App\Http\Controllers\Controller;
use App\Models\Gestion_note\Bulletin;
use App\Models\Gestion_note\BulletinAnnuel;
use App\Models\Gestion_note\DetailBulletins;
use App\Models\Inscription\Inscription;
use App\Services\BulletinService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class BulletinController extends Controller
{
    protected BulletinService $bulletinService;

    public function __construct(BulletinService $bulletinService)
    {
        $this->bulletinService = $bulletinService;
    }

    // =========================================================================
    // BULLETINS TRIMESTRIELS
    // =========================================================================

    /** POST /bulletins/generate */
    public function generate(Request $request)
    {
        $v = Validator::make($request->all(), [
            'inscription_id' => 'required|exists:inscriptions,id',
            'periode'        => 'required|string|in:TRIMESTRE_1,TRIMESTRE_2,TRIMESTRE_3',
        ]);
        if ($v->fails()) return response()->json(['errors' => $v->errors()], 422);

        try {
            $bulletin = $this->bulletinService->genererBulletin(
                (int)$request->inscription_id,
                $request->periode
            );
            return response()->json(['success' => true, 'message' => 'Bulletin généré avec succès', 'data' => $bulletin]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /** POST /bulletins/generate-class */
    public function generateForClass(Request $request)
    {
        $v = Validator::make($request->all(), [
            'classe_id'        => 'required|exists:classes,id',
            'periode'          => 'required|string|in:TRIMESTRE_1,TRIMESTRE_2,TRIMESTRE_3',
            'annee_scolaire_id'=> 'required|exists:annee_scolaires,id',
        ]);
        if ($v->fails()) return response()->json(['errors' => $v->errors()], 422);

        try {
            $resultats   = $this->bulletinService->genererBulletinsClasse(
                (int)$request->classe_id,
                $request->periode,
                (int)$request->annee_scolaire_id
            );
            $successCount = count(array_filter($resultats, fn($r) => $r['success']));
            return response()->json([
                'success' => true,
                'message' => "$successCount bulletin(s) généré(s) avec succès",
                'data'    => $resultats,
            ]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /** GET /bulletins/eleve/{inscriptionId} */
    public function getByEleve(int $inscriptionId)
    {
        $inscription = Inscription::find($inscriptionId);
        if (!$inscription) {
            return response()->json(['success' => false, 'message' => 'Inscription non trouvée'], 404);
        }

        $bulletins = Bulletin::where('inscription_id', $inscriptionId)
            ->with(['detailBulletins.matiere', 'inscription.eleve', 'inscription.classe'])
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json(['success' => true, 'data' => $bulletins]);
    }

    /** GET /bulletins/classe */
    public function getByClass(Request $request)
    {
        $v = Validator::make($request->all(), [
            'classe_id'        => 'required|exists:classes,id',
            'periode'          => 'required|string',
            'annee_scolaire_id'=> 'required|exists:annee_scolaires,id',
        ]);
        if ($v->fails()) return response()->json(['errors' => $v->errors()], 422);

        $bulletins = Bulletin::whereHas('inscription', function ($q) use ($request) {
            $q->where('id_classe', $request->classe_id)
              ->where('id_annee_scolaire', $request->annee_scolaire_id);
        })
            ->where('periode', $request->periode)
            ->with(['inscription.eleve', 'detailBulletins.matiere'])
            ->orderBy('rang', 'asc')
            ->get();

        $effectif = $bulletins->count();
        $admis    = $bulletins->where('decision', 'ADMIS')->count();
        $moyenneClasse = $effectif > 0 ? round($bulletins->avg('moyenne_eleve'), 2) : 0;

        return response()->json([
            'success' => true,
            'data'    => $bulletins,
            'stats'   => [
                'effectif'      => $effectif,
                'admis'         => $admis,
                'redoublants'   => $bulletins->where('decision', 'REDOUBLANT')->count(),
                'non_evalues'   => $bulletins->where('decision', 'NON_EVALUE')->count(),
                'moyenne_classe'=> $moyenneClasse,
                'taux_reussite' => $effectif > 0 ? round(($admis / $effectif) * 100, 1) . '%' : '0%',
            ],
        ]);
    }

    /** GET /bulletins/{id} */
    public function show(int $id)
    {
        $bulletin = Bulletin::with([
            'inscription.eleve',
            'inscription.classe.niveau',
            'detailBulletins.matiere',
        ])->find($id);

        if (!$bulletin) {
            return response()->json(['success' => false, 'message' => 'Bulletin non trouvé'], 404);
        }

        return response()->json(['success' => true, 'data' => $bulletin]);
    }

    /** GET /bulletins/{id}/pdf  — données JSON pour le frontend jsPDF */
    public function exportPDF(int $id)
    {
        $bulletin = Bulletin::with([
            'inscription.eleve',
            'inscription.classe.niveau',
            'detailBulletins.matiere',
        ])->find($id);

        if (!$bulletin) {
            return response()->json(['success' => false, 'message' => 'Bulletin non trouvé'], 404);
        }

        // Calcul de l'effectif de la classe pour afficher "rang / effectif"
        $effectif = $this->bulletinService->getEffectifClasse(
            $bulletin->inscription->id_classe,
            $bulletin->inscription->id_annee_scolaire
        );

        return response()->json([
            'success'  => true,
            'data'     => $bulletin,
            'effectif' => $effectif,
        ]);
    }

    /** DELETE /bulletins/{id} */
    public function destroy(int $id)
    {
        $bulletin = Bulletin::find($id);
        if (!$bulletin) {
            return response()->json(['success' => false, 'message' => 'Bulletin non trouvé'], 404);
        }

        DB::beginTransaction();
        try {
            DetailBulletins::where('bulletin_id', $bulletin->id)->delete();
            $bulletin->delete();
            DB::commit();
            return response()->json(['success' => true, 'message' => 'Bulletin supprimé avec succès']);
        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /** PUT /bulletins/{id}/appreciation */
    public function updateAppreciation(Request $request, int $id)
    {
        $v = Validator::make($request->all(), ['appreciation' => 'required|string|max:255']);
        if ($v->fails()) return response()->json(['errors' => $v->errors()], 422);

        $bulletin = Bulletin::find($id);
        if (!$bulletin) {
            return response()->json(['success' => false, 'message' => 'Bulletin non trouvé'], 404);
        }

        $bulletin->update(['appreciation' => $request->appreciation]);
        return response()->json(['success' => true, 'data' => $bulletin]);
    }

    // =========================================================================
    // BULLETINS ANNUELS
    // =========================================================================

    /** POST /bulletins-annuels/generate */
    public function generateAnnuel(Request $request)
    {
        $v = Validator::make($request->all(), [
            'classe_id'        => 'required|exists:classes,id',
            'annee_scolaire_id'=> 'required|exists:annee_scolaires,id',
        ]);
        if ($v->fails()) return response()->json(['errors' => $v->errors()], 422);

        try {
            $resultats    = $this->bulletinService->genererBulletinsAnnuels(
                (int)$request->classe_id,
                (int)$request->annee_scolaire_id
            );
            $successCount = count(array_filter($resultats, fn($r) => $r['success']));
            return response()->json([
                'success' => true,
                'message' => "$successCount bulletin(s) annuel(s) généré(s)",
                'data'    => $resultats,
            ]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /** POST /bulletins-annuels/generate-individual */
    public function generateAnnuelIndividuel(Request $request)
    {
        $v = Validator::make($request->all(), [
            'inscription_id' => 'required|exists:inscriptions,id',
        ]);
        if ($v->fails()) return response()->json(['errors' => $v->errors()], 422);

        try {
            $ba = $this->bulletinService->genererBulletinAnnuel((int)$request->inscription_id);
            return response()->json(['success' => true, 'data' => $ba]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /** GET /bulletins-annuels/classe */
    public function getAnnuelByClass(Request $request)
    {
        $v = Validator::make($request->all(), [
            'classe_id'        => 'required|exists:classes,id',
            'annee_scolaire_id'=> 'required|exists:annee_scolaires,id',
        ]);
        if ($v->fails()) return response()->json(['errors' => $v->errors()], 422);

        $bulletins = BulletinAnnuel::whereHas('inscription', function ($q) use ($request) {
            $q->where('id_classe', $request->classe_id)
              ->where('id_annee_scolaire', $request->annee_scolaire_id);
        })
            ->with(['inscription.eleve', 'inscription.classe'])
            ->orderBy('rang_annuel', 'asc')
            ->get();

        $effectif     = $bulletins->count();
        $admis        = $bulletins->where('decision', 'ADMIS')->count();
        $redoublants  = $bulletins->where('decision', 'REDOUBLANT')->count();
        $nonEvalues   = $bulletins->where('decision', 'NON_EVALUE')->count();

        return response()->json([
            'success' => true,
            'data'    => $bulletins,
            'stats'   => [
                'effectif'     => $effectif,
                'admis'        => $admis,
                'redoublants'  => $redoublants,
                'non_evalues'  => $nonEvalues,
                'taux_reussite'=> $effectif > 0 ? round(($admis / $effectif) * 100, 1) . '%' : '0%',
            ],
        ]);
    }

    /** GET /bulletins-annuels/eleve/{inscriptionId} */
    public function getAnnuelByEleve(int $inscriptionId)
    {
        $ba = BulletinAnnuel::where('inscription_id', $inscriptionId)
            ->with(['inscription.eleve', 'inscription.classe.niveau'])
            ->first();

        if (!$ba) {
            return response()->json(['success' => false, 'message' => 'Bulletin annuel non trouvé'], 404);
        }

        return response()->json(['success' => true, 'data' => $ba]);
    }

    /** GET /bulletins-annuels/{id}/pdf */
    public function exportAnnuelPDF(int $id)
    {
        $ba = BulletinAnnuel::with([
            'inscription.eleve',
            'inscription.classe.niveau',
        ])->find($id);

        if (!$ba) {
            return response()->json(['success' => false, 'message' => 'Bulletin annuel non trouvé'], 404);
        }

        $effectif = $this->bulletinService->getEffectifClasse(
            $ba->inscription->id_classe,
            $ba->inscription->id_annee_scolaire
        );

        return response()->json(['success' => true, 'data' => $ba, 'effectif' => $effectif]);
    }

    /** GET /bulletins/recapitulatif/pdf */
    public function exportRecapitulatifPDF(Request $request)
    {
        $v = Validator::make($request->all(), [
            'classe_id'        => 'required|exists:classes,id',
            'periode'          => 'required|string',
            'annee_scolaire_id'=> 'required|exists:annee_scolaires,id',
        ]);
        if ($v->fails()) return response()->json(['errors' => $v->errors()], 422);

        $bulletins = Bulletin::whereHas('inscription', function ($q) use ($request) {
            $q->where('id_classe', $request->classe_id)
              ->where('id_annee_scolaire', $request->annee_scolaire_id);
        })
            ->where('periode', $request->periode)
            ->with(['inscription.eleve', 'detailBulletins.matiere'])
            ->orderBy('rang', 'asc')
            ->get();

        $effectif      = $bulletins->count();
        $moyenneClasse = $effectif > 0 ? round($bulletins->avg('moyenne_eleve'), 2) : 0;

        return response()->json([
            'success'       => true,
            'data'          => $bulletins,
            'effectif'      => $effectif,
            'moyenne_classe'=> $moyenneClasse,
        ]);
    }
}
