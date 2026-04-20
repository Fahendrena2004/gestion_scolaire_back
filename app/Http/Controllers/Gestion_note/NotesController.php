<?php

namespace App\Http\Controllers\Gestion_note;

use App\Http\Controllers\Controller;
use App\Models\Gestion_note\Notes;
use App\Models\Gestion_note\Matieres;
use App\Models\Inscription\Inscription;
use App\Services\BulletinService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;

class NotesController extends Controller
{
    protected $bulletinService;

    public function __construct(BulletinService $bulletinService)
    {
        $this->bulletinService = $bulletinService;
    }

    /**
     * Afficher toutes les notes d'une inscription
     */
    public function index(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'inscription_id' => 'required|exists:inscriptions,id',
            'periode' => 'nullable|string'
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
            'data' => $notes
        ]);
    }

    /**
     * Ajouter une note
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'inscription_id' => 'required|exists:inscriptions,id',
            'matiere_id' => 'required|exists:matieres,id',
            'valeur' => 'required|numeric|min:0|max:20',
            'periode' => 'required|string',
            'date' => 'required|date',
            'type' => 'required|string',
            'appreciation' => 'nullable|string'
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        try {
            $note = Notes::create($request->all());
            
            // Récupérer la note avec sa relation matière
            $noteWithMatiere = Notes::with('matiere')->find($note->id);

            return response()->json([
                'success' => true,
                'message' => 'Note ajoutée avec succès',
                'data' => $noteWithMatiere
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de l\'ajout',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Afficher une note
     */
    public function show($id)
    {
        $note = Notes::with('matiere')->find($id);

        if (!$note) {
            return response()->json([
                'success' => false,
                'message' => 'Note non trouvée'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $note
        ]);
    }

    /**
     * Modifier une note
     */
    public function update(Request $request, $id)
    {
        $note = Notes::find($id);

        if (!$note) {
            return response()->json([
                'success' => false,
                'message' => 'Note non trouvée'
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'valeur' => 'sometimes|numeric|min:0|max:20',
            'date' => 'sometimes|date',
            'appreciation' => 'nullable|string'
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        try {
            // Mettre à jour directement
            Notes::where('id', $id)->update($request->only(['valeur', 'date', 'appreciation']));
            
            // Récupérer la note mise à jour avec la relation
            $updatedNote = Notes::with('matiere')->find($id);

            return response()->json([
                'success' => true,
                'message' => 'Note modifiée avec succès',
                'data' => $updatedNote
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la modification',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Supprimer une note
     */
    public function destroy($id)
    {
        $note = Notes::find($id);

        if (!$note) {
            return response()->json([
                'success' => false,
                'message' => 'Note non trouvée'
            ], 404);
        }

        try {
            Notes::where('id', $id)->delete();

            return response()->json([
                'success' => true,
                'message' => 'Note supprimée avec succès'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la suppression',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}