<?php

namespace App\Http\Controllers\Gestion_note;

use App\Http\Controllers\Controller;
use App\Models\Gestion_note\Matieres;
use App\Models\Gestion_note\Notes;
use App\Models\Inscription\Inscription;
use App\Services\BulletinService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class NotesController extends Controller
{
    protected $bulletinService;

    public function __construct(BulletinService $bulletinService)
    {
        $this->bulletinService = $bulletinService;
    }

    public function index(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'inscription_id' => 'required|exists:inscriptions,id',
            'periode' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $query = Notes::where('inscription_id', $request->inscription_id);

        if ($request->has('periode')) {
            $query->where('periode', $request->periode);
        }

        $notes = $query->with('matiere')->get();

        return response()->json([
            'success' => true,
            'data' => $notes,
        ]);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'inscription_id' => 'required|exists:inscriptions,id',
            'matiere_id' => 'required|exists:matieres,id',
            'valeur' => 'required|numeric|min:0|max:20',
            'periode' => 'required|string',
            'date' => 'required|date',
            'type' => 'required|string',
            'appreciation' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        try {
            $inscription = Inscription::find($request->inscription_id);
            $matiere     = Matieres::find($request->matiere_id);

            if (!$inscription || !$matiere) {
                return response()->json([
                    'success' => false,
                    'message' => 'Inscription ou matiere introuvable',
                ], 404);
            }

            if ((int) $matiere->classe_id !== (int) $inscription->id_classe) {
                return response()->json([
                    'success' => false,
                    'message' => 'La matiere selectionnee n appartient pas a la classe de cette inscription',
                ], 422);
            }

            $note = Notes::create($request->only([
                'inscription_id',
                'matiere_id',
                'valeur',
                'periode',
                'date',
                'type',
                'appreciation',
            ]));
            $noteWithMatiere = Notes::with('matiere')->find($note->id);

            return response()->json([
                'success' => true,
                'message' => 'Note ajoutee avec succes',
                'data' => $noteWithMatiere,
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de l ajout',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function show($id)
    {
        $note = Notes::with('matiere')->find($id);

        if (!$note) {
            return response()->json([
                'success' => false,
                'message' => 'Note non trouvee',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $note,
        ]);
    }

    public function update(Request $request, $id)
    {
        $note = Notes::find($id);

        if (!$note) {
            return response()->json([
                'success' => false,
                'message' => 'Note non trouvee',
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'valeur' => 'sometimes|numeric|min:0|max:20',
            'date' => 'sometimes|date',
            'appreciation' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        try {
            Notes::where('id', $id)->update($request->only(['valeur', 'date', 'appreciation']));
            $updatedNote = Notes::with('matiere')->find($id);

            return response()->json([
                'success' => true,
                'message' => 'Note modifiee avec succes',
                'data' => $updatedNote,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la modification',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function destroy($id)
    {
        $note = Notes::find($id);

        if (!$note) {
            return response()->json([
                'success' => false,
                'message' => 'Note non trouvee',
            ], 404);
        }

        try {
            Notes::where('id', $id)->delete();

            return response()->json([
                'success' => true,
                'message' => 'Note supprimee avec succes',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la suppression',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function getMoyenne($inscriptionId, $periode)
    {
        $inscription = Inscription::find($inscriptionId);

        if (!$inscription) {
            return response()->json([
                'success' => false,
                'message' => 'Inscription non trouvee',
            ], 404);
        }

        $moyenne = $this->bulletinService->calculerMoyenneGenerale($inscriptionId, $periode);

        return response()->json([
            'success' => true,
            'data' => [
                'inscription_id' => $inscriptionId,
                'periode' => $periode,
                'moyenne_generale' => $moyenne,
            ],
        ]);
    }
}
