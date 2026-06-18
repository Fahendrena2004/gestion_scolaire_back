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
            'interro1' => 'nullable|numeric|min:0|max:20',
            'interro2' => 'nullable|numeric|min:0|max:20',
            'examen' => 'nullable|numeric|min:0|max:20',
            'periode' => 'required|string|in:' . implode(',', self::PERIODES_VALIDES),
            'date' => 'required|date',
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

            $updateData = [];
            if ($request->has('interro1')) $updateData['interro1'] = $request->interro1;
            if ($request->has('interro2')) $updateData['interro2'] = $request->interro2;
            if ($request->has('examen')) $updateData['examen'] = $request->examen;
            if ($request->has('date')) $updateData['date'] = $request->date;
            if ($request->has('appreciation')) $updateData['appreciation'] = $request->appreciation;

            if (empty($updateData['appreciation']) && count($updateData) > 0) {
                $total = 0;
                $count = 0;
                if (isset($updateData['interro1'])) { $total += $updateData['interro1']; $count++; }
                if (isset($updateData['interro2'])) { $total += $updateData['interro2']; $count++; }
                if (isset($updateData['examen'])) { $total += $updateData['examen']; $count++; }
                
                if ($count > 0) {
                    $updateData['appreciation'] = $this->calculerAppreciation($total / $count);
                }
            }

            $note = Notes::updateOrCreate(
                [
                    'inscription_id' => $inscription->id,
                    'matiere_id' => $matiere->id,
                    'periode' => $request->periode,
                ],
                $updateData
            );

            $noteWithMatiere = Notes::with('matiere')->find($note->id);

            return response()->json([
                'success' => true,
                'message' => 'Note ajoutee/mise a jour avec succes',
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
            'interro1' => 'sometimes|numeric|min:0|max:20',
            'interro2' => 'sometimes|numeric|min:0|max:20',
            'examen' => 'sometimes|numeric|min:0|max:20',
            'date' => 'sometimes|date',
            'appreciation' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        try {
            $updateData = $request->only(['interro1', 'interro2', 'examen', 'date', 'appreciation']);
            if (empty($updateData['appreciation']) && (isset($updateData['interro1']) || isset($updateData['interro2']) || isset($updateData['examen']))) {
                $total = 0;
                $count = 0;
                
                $i1 = $updateData['interro1'] ?? $note->interro1;
                $i2 = $updateData['interro2'] ?? $note->interro2;
                $ex = $updateData['examen'] ?? $note->examen;
                
                if ($i1 !== null) { $total += $i1; $count++; }
                if ($i2 !== null) { $total += $i2; $count++; }
                if ($ex !== null) { $total += $ex; $count++; }

                if ($count > 0) {
                    $updateData['appreciation'] = $this->calculerAppreciation($total / $count);
                }
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

    private function calculerAppreciation(float $valeur): string
    {
        if ($valeur >= 17) return 'Très Bien';
        if ($valeur >= 15) return 'Bien';
        if ($valeur >= 12) return 'Assez Bien';
        if ($valeur >= 10) return 'Passable';
        if ($valeur >= 6)  return 'Insuffisant';
        return 'Faible';
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
            $total = 0;
            $count = 0;
            if ($note->interro1 !== null) { $total += $note->interro1; $count++; }
            if ($note->interro2 !== null) { $total += $note->interro2; $count++; }
            if ($note->examen !== null) { $total += $note->examen; $count++; }
            
            if ($count === 0) continue;
            
            $v = (float) ($total / $count);
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

        // Top 5 élèves par moyenne sur la période sélectionnée
        $topQuery = Inscription::query()->with(['eleve', 'classe']);
        if ($classe_id) {
            $topQuery->where('id_classe', $classe_id);
        }
        $allInsForTop = $topQuery->get();

        $topStudents = $allInsForTop->map(function ($ins) use ($periode) {
            $bQuery = Bulletin::where('inscription_id', $ins->id);
            if ($periode) {
                $bQuery->where('periode', $periode);
            }
            $moy = $bQuery->avg('moyenne_eleve') ?? 0;
            return [
                'name'    => ($ins->eleve->nom ?? '') . ' ' . ($ins->eleve->prenom ?? ''),
                'moyenne' => round((float)$moy, 2),
                'rang'    => 0,
                'classe'  => $ins->classe->nom_classe ?? '',
            ];
        })
        ->filter(fn($s) => $s['moyenne'] > 0)
        ->sortByDesc('moyenne')
        ->values()
        ->take(5)
        ->map(function ($s, $i) { $s['rang'] = $i + 1; return $s; });

        return response()->json([
            'success' => true,
            'data' => [
                'total_eleves'       => $total_eleves,
                'total_matieres'     => $total_matieres,
                'total_notes'        => $total_notes,
                'total_bulletins'    => $total_bulletins,
                'distribution_notes' => $distribution,
                'top_students'       => $topStudents,
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
                    'details' => 'Notes (I1: ' . ($note->interro1 ?? '-') . ', I2: ' . ($note->interro2 ?? '-') . ', Ex: ' . ($note->examen ?? '-') . ') en ' . ($note->matiere ? $note->matiere->nom : 'Matière inconnue'),
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
