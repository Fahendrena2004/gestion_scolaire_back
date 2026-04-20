<?php

namespace App\Http\Controllers\Gestion_note;

use App\Http\Controllers\Controller;
use App\Models\Gestion_note\Notes;
use App\Models\Inscription\Eleve;
use App\Services\BulletinService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;

class GestionNoteController extends Controller
{
    protected $bulletinService;

    public function __construct(BulletinService $bulletinService)
    {
        $this->bulletinService = $bulletinService;
    }

    /**
     * Afficher toutes les notes d'un élève
     */
    public function index(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'eleve_id' => 'required|exists:eleves,id',
            'periode' => 'nullable|string|in:trimestre1,trimestre2,trimestre3,semestre1,semestre2',
            'annee_scolaire_id' => 'required|exists:annee_scolaires,id'
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $query = Notes::where('id_eleve', $request->eleve_id)
            ->where('id_annee_scolaire', $request->annee_scolaire_id)
            ->with('matiere');

        if ($request->has('periode')) {
            $query->where('periode', $request->periode);
        }

        $notes = $query->get();

        // Calculer la moyenne
        $moyenne = $this->bulletinService->calculerMoyenneEleve(
            $request->eleve_id,
            $request->periode ?? 'all',
            $request->annee_scolaire_id
        );

        return response()->json([
            'notes' => $notes,
            'moyenne_generale' => round($moyenne, 2),
            'nombre_notes' => $notes->count()
        ]);
    }

    /**
     * Ajouter une note
     */
    public function create(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'eleve_id' => 'required|exists:eleves,id',
            'matiere_id' => 'required|exists:matieres,id',
            'annee_scolaire_id' => 'required|exists:annee_scolaires,id',
            'valeur' => 'required|numeric|min:0|max:20',
            'periode' => 'required|string|in:trimestre1,trimestre2,trimestre3,semestre1,semestre2',
            'date' => 'required|date',
            'type' => 'required|string|in:devoir,composition,examen'
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        // Vérifier si la note existe déjà
        $exists = Notes::where('id_eleve', $request->eleve_id)
            ->where('id_matiere', $request->matiere_id)
            ->where('periode', $request->periode)
            ->where('id_annee_scolaire', $request->annee_scolaire_id)
            ->where('type', $request->type)
            ->exists();

        if ($exists) {
            return response()->json([
                'message' => 'Une note existe déjà pour cet élève, cette matière, cette période et ce type'
            ], 409);
        }

        // Création de la note
        $note = Notes::create([
            'id_eleve' => $request->eleve_id,
            'id_matiere' => $request->matiere_id,
            'id_annee_scolaire' => $request->annee_scolaire_id,
            'valeur' => $request->valeur,
            'periode' => $request->periode,
            'date' => $request->date,
            'type' => $request->type,
        ]);

        // Recharger la note avec sa relation matière
        $note = Notes::with('matiere')->find($note->id);

        // Recalculer la moyenne
        $moyenne = $this->bulletinService->calculerMoyenneEleve(
            $request->eleve_id,
            $request->periode,
            $request->annee_scolaire_id
        );

        return response()->json([
            'message' => 'Note ajoutée avec succès',
            'note' => $note,
            'moyenne_eleve' => round($moyenne, 2)
        ], 201);
    }

    /**
     * Afficher une note spécifique
     */
    public function show($id)
    {
        $note = Notes::with(['matiere', 'eleve', 'anneeScolaire'])->find($id);

        if (!$note) {
            return response()->json(['message' => 'Note non trouvée'], 404);
        }

        return response()->json($note);
    }

    /**
     * Modifier une note
     */

/**
 * Modifier une note (Version DB)
 */
public function update(Request $request, $id)
{
    $validator = Validator::make($request->all(), [
        'valeur' => 'sometimes|numeric|min:0|max:20',
        'periode' => 'sometimes|string|in:trimestre1,trimestre2,trimestre3,semestre1,semestre2',
        'date' => 'sometimes|date',
        'type' => 'sometimes|string|in:devoir,composition,examen'
    ]);

    if ($validator->fails()) {
        return response()->json(['errors' => $validator->errors()], 422);
    }

    // Mise à jour directe
    DB::table('notes')
        ->where('id', $id)
        ->update($request->only(['valeur', 'periode', 'date', 'type']));

    // Récupérer la note mise à jour
    $note = DB::table('notes')->where('id', $id)->first();
    
    // Récupérer avec la relation matière
    $noteWithMatiere = Notes::with('matiere')->find($id);

    // Recalculer la moyenne
    $moyenne = $this->bulletinService->calculerMoyenneEleve(
        $note->id_eleve,
        $note->periode,
        $note->id_annee_scolaire
    );

    return response()->json([
        'message' => 'Note modifiée avec succès',
        'note' => $noteWithMatiere,
        'moyenne_eleve' => round($moyenne, 2)
    ]);
}

/**
 * Supprimer une note (Version DB)
 */
public function destroy($id)
{
    // Récupérer les infos avant suppression
    $note = DB::table('notes')->where('id', $id)->first();
    
    if (!$note) {
        return response()->json(['message' => 'Note non trouvée'], 404);
    }

    $eleveId = $note->id_eleve;
    $periode = $note->periode;
    $anneeId = $note->id_annee_scolaire;

    // Suppression directe
    DB::table('notes')->where('id', $id)->delete();

    // Recalculer la moyenne
    $moyenne = $this->bulletinService->calculerMoyenneEleve(
        $eleveId,
        $periode,
        $anneeId
    );

    return response()->json([
        'message' => 'Note supprimée avec succès',
        'moyenne_eleve' => round($moyenne, 2)
    ]);
}
    /**
     * Notes par matière pour une classe
     */
    public function notesParMatiere(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'classe_id' => 'required|exists:classes,id',
            'matiere_id' => 'required|exists:matieres,id',
            'periode' => 'required|string',
            'annee_scolaire_id' => 'required|exists:annee_scolaires,id'
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $eleves = Eleve::where('classe_id', $request->classe_id)->get();

        $resultats = [];
        foreach ($eleves as $eleve) {
            $note = Notes::where('id_eleve', $eleve->id)
                ->where('id_matiere', $request->matiere_id)
                ->where('periode', $request->periode)
                ->where('id_annee_scolaire', $request->annee_scolaire_id)
                ->first();

            $resultats[] = [
                'eleve' => ($eleve->nom ?? '') . ' ' . ($eleve->prenom ?? ''),
                'eleve_id' => $eleve->id,
                'note' => $note ? $note->valeur : null,
                'appreciation' => $note ? $this->getAppreciation($note->valeur) : 'Non noté'
            ];
        }

        return response()->json($resultats);
    }

    /**
     * Statistiques des notes d'une classe
     */
    public function statistiquesClasse(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'classe_id' => 'required|exists:classes,id',
            'periode' => 'required|string',
            'annee_scolaire_id' => 'required|exists:annee_scolaires,id'
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $moyenneClasse = $this->bulletinService->calculerMoyenneClasse(
            $request->classe_id,
            $request->periode,
            $request->annee_scolaire_id
        );

        $eleves = Eleve::where('classe_id', $request->classe_id)->get();
        $moyennesEleves = [];

        foreach ($eleves as $eleve) {
            $moyennesEleves[] = $this->bulletinService->calculerMoyenneEleve(
                $eleve->id,
                $request->periode,
                $request->annee_scolaire_id
            );
        }

        return response()->json([
            'moyenne_classe' => round($moyenneClasse, 2),
            'meilleure_moyenne' => !empty($moyennesEleves) ? round(max($moyennesEleves), 2) : 0,
            'plus_petite_moyenne' => !empty($moyennesEleves) ? round(min($moyennesEleves), 2) : 0,
            'nombre_eleves' => count($eleves),
            'taux_reussite' => round($this->calculerTauxReussite($moyennesEleves), 2)
        ]);
    }

    /**
     * Obtenir l'appréciation selon la note
     */
    private function getAppreciation($note)
    {
        if ($note >= 16) return 'Excellent';
        if ($note >= 14) return 'Très bien';
        if ($note >= 12) return 'Bien';
        if ($note >= 10) return 'Assez bien';
        if ($note >= 8) return 'Passable';
        return 'Insuffisant';
    }

    /**
     * Calculer le taux de réussite (moyenne >= 10)
     */
    private function calculerTauxReussite($moyennes)
    {
        if (empty($moyennes)) return 0;

        $reussis = count(array_filter($moyennes, function($m) {
            return $m >= 10;
        }));

        return ($reussis / count($moyennes)) * 100;
    }
}