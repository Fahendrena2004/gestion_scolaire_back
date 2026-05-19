<?php

namespace App\Http\Controllers\Gestion_note;

use App\Http\Controllers\Controller;
use App\Models\Gestion_note\Matieres;
use App\Models\Gestion_note\Notes;
use App\Models\Gestion_note\Bulletin;
use App\Models\Inscription\Inscription;
use App\Services\BulletinService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class NotesController extends Controller
{
    public const PERIODES_VALIDES = [
        'TRIMESTRE_1', 'TRIMESTRE_2', 'TRIMESTRE_3',
        'SEMESTRE_1', 'SEMESTRE_2'
    ];

    protected $bulletinService;

    public function __construct(BulletinService $bulletinService)
    {
        $this->bulletinService = $bulletinService;
    }

    public function index(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'eleve_id' => 'nullable|exists:eleves,id',
            'periode' => 'nullable|string|in:' . implode(',', self::PERIODES_VALIDES),
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $query = Notes::with(['matiere', 'inscription.eleve']);

        if ($request->has('eleve_id')) {
            $inscription = Inscription::where('id_eleve', $request->eleve_id)->latest('created_at')->first();
            if (!$inscription) {
                return response()->json([
                    'success' => true,
                    'data' => [],
                ]);
            }
            $query->where('inscription_id', $inscription->id);
        }

        if ($request->has('periode')) {
            $query->where('periode', $request->periode);
        }

        $notes = $query->orderBy('created_at', 'desc')->get();

        return response()->json([
            'success' => true,
            'data' => $notes,
        ]);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'eleve_id' => 'required|exists:eleves,id',
            'matiere_id' => 'required|exists:matieres,id',
            'valeur' => 'required|numeric|min:0|max:20',
            'periode' => 'required|string|in:' . implode(',', self::PERIODES_VALIDES),
            'date' => 'required|date',
            'type' => 'required|string',
            'appreciation' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        try {
            $inscription = Inscription::where('id_eleve', $request->eleve_id)->latest('created_at')->first();
            $matiere     = Matieres::find($request->matiere_id);

            if (!$inscription || !$matiere) {
                return response()->json([
                    'success' => false,
                    'message' => 'Inscription ou matiere introuvable',
                ], 404);
            }

            if ($matiere->classe_id !== null && (int) $matiere->classe_id !== (int) $inscription->id_classe) {
                return response()->json([
                    'success' => false,
                    'message' => 'La matiere selectionnee n appartient pas a la classe de cette inscription',
                ], 422);
            }

            $noteData = $request->only([
                'matiere_id',
                'valeur',
                'periode',
                'date',
                'type',
                'appreciation',
            ]);
            $noteData['inscription_id'] = $inscription->id;

            if (empty($noteData['appreciation'])) {
                $noteData['appreciation'] = $this->calculerAppreciation($noteData['valeur']);
            }

            $note = Notes::create($noteData);
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
            $updateData = $request->only(['valeur', 'date', 'appreciation']);
            if (isset($updateData['valeur']) && empty($updateData['appreciation'])) {
                $updateData['appreciation'] = $this->calculerAppreciation($updateData['valeur']);
            }
            Notes::where('id', $id)->update($updateData);
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

    private function calculerAppreciation($valeur)
    {
        $v = (float)$valeur;
        if ($v >= 0 && $v <= 5) {
            return 'balme';
        } elseif ($v >= 6 && $v <= 9) {
            return 'Insuffisant';
        } elseif ($v >= 10 && $v <= 12) {
            return 'passable';
        } elseif ($v >= 12 && $v <= 14) {
            return 'Assez-bien';
        } elseif ($v >= 15 && $v <= 16) {
            return 'bien';
        } else {
            return 'tres-bien';
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

    // ─── NOUVEAUX ENDPOINTS ───────────────────────────────────────────────────

    public function getPeriodes()
    {
        $periodes = [
            ['id' => 'TRIMESTRE_1', 'nom' => '1er Trimestre'],
            ['id' => 'TRIMESTRE_2', 'nom' => '2ème Trimestre'],
            ['id' => 'TRIMESTRE_3', 'nom' => '3ème Trimestre'],
            ['id' => 'SEMESTRE_1',  'nom' => '1er Semestre'],
            ['id' => 'SEMESTRE_2',  'nom' => '2ème Semestre'],
        ];

        return response()->json([
            'success' => true,
            'data' => $periodes
        ]);
    }

    public function getStatistiques(Request $request)
    {
        $classe_id = $request->input('classe_id');
        $periode = $request->input('periode');

        // Total eleves
        $elevesQuery = Inscription::query();
        if ($classe_id) {
            $elevesQuery->where('id_classe', $classe_id);
        }
        $total_eleves = $elevesQuery->count();

        // Total matieres
        $matieresQuery = Matieres::query();
        if ($classe_id) {
            $matieresQuery->where('classe_id', $classe_id);
        }
        $total_matieres = $matieresQuery->count();

        // Total notes
        $notesQuery = Notes::query();
        if ($periode) {
            $notesQuery->where('periode', $periode);
        }
        if ($classe_id) {
            $notesQuery->whereHas('inscription', function($q) use ($classe_id) {
                $q->where('id_classe', $classe_id);
            });
        }
        $total_notes = $notesQuery->count();

        // Distribution des notes
        $notesList = $notesQuery->get();
        $distribution = [
            '0-9' => 0,
            '10-11' => 0,
            '12-13' => 0,
            '14-15' => 0,
            '16-20' => 0,
        ];
        
        foreach ($notesList as $note) {
            $v = (float) $note->valeur;
            if ($v < 10) $distribution['0-9']++;
            elseif ($v < 12) $distribution['10-11']++;
            elseif ($v < 14) $distribution['12-13']++;
            elseif ($v < 16) $distribution['14-15']++;
            else $distribution['16-20']++;
        }

        // Total bulletins
        $bulletinsQuery = Bulletin::query();
        if ($periode) {
            $bulletinsQuery->where('periode', $periode);
        }
        if ($classe_id) {
            $bulletinsQuery->whereHas('inscription', function($q) use ($classe_id) {
                $q->where('id_classe', $classe_id);
            });
        }
        $total_bulletins = $bulletinsQuery->count();

        return response()->json([
            'success' => true,
            'data' => [
                'total_eleves' => $total_eleves,
                'total_matieres' => $total_matieres,
                'total_notes' => $total_notes,
                'total_bulletins' => $total_bulletins,
                'distribution_notes' => $distribution,
            ]
        ]);
    }

    public function getActivitesRecentes(Request $request)
    {
        $limit = $request->input('limit', 10);

        // Récupérer les notes les plus récentes
        $recentNotes = Notes::with(['matiere', 'inscription.eleve'])
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get()
            ->map(function ($note) {
                $eleve = $note->inscription && $note->inscription->eleve 
                    ? $note->inscription->eleve->nom . ' ' . $note->inscription->eleve->prenom 
                    : 'Élève inconnu';
                
                return [
                    'id' => 'note_' . $note->id,
                    'action' => 'Nouvelle note ajoutée',
                    'details' => $note->valeur . '/20 en ' . ($note->matiere ? $note->matiere->nom : 'Matière inconnue'),
                    'concerne' => $eleve,
                    'date' => $note->created_at->format('Y-m-d H:i:s'),
                    'status' => 'success',
                    'type' => 'note'
                ];
            });

        // Récupérer les bulletins les plus récents
        $recentBulletins = Bulletin::with(['inscription.eleve'])
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get()
            ->map(function ($bulletin) {
                $eleve = $bulletin->inscription && $bulletin->inscription->eleve 
                    ? $bulletin->inscription->eleve->nom . ' ' . $bulletin->inscription->eleve->prenom 
                    : 'Élève inconnu';

                return [
                    'id' => 'bulletin_' . $bulletin->id,
                    'action' => 'Bulletin généré',
                    'details' => 'Moyenne: ' . $bulletin->moyenne_eleve . ' (' . $bulletin->periode . ')',
                    'concerne' => $eleve,
                    'date' => $bulletin->created_at->format('Y-m-d H:i:s'),
                    'status' => 'info',
                    'type' => 'bulletin'
                ];
            });

        $activites = $recentNotes->concat($recentBulletins)
            ->sortByDesc('date')
            ->take($limit)
            ->values();

        return response()->json([
            'success' => true,
            'data' => $activites
        ]);
    }
}
