<?php

namespace App\Http\Controllers\Inscription;

use App\Http\Controllers\Controller;
use App\Models\Inscription\AnneeScolaire;
use App\Models\Inscription\Classe;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class ClasseController extends Controller
{
    public function index()
    {
        $classes = Classe::with(['niveau', 'anneeScolaire'])->get();

        return response()->json([
            'success' => true,
            'data'    => $classes,
        ]);
    }

    public function getByNiveau($niveauId)
    {
        $classes = Classe::where('niveau_id', $niveauId)
            ->with('anneeScolaire')
            ->get()
            ->map(fn ($c) => $this->avecEffectifDisponible($c));

        return response()->json([
            'success' => true,
            'data'    => $classes,
        ]);
    }

    public function getByCycle($cycle)
    {
        $classes = Classe::whereHas('niveau', fn ($q) => $q->where('cycle', $cycle))
            ->with(['niveau', 'anneeScolaire'])
            ->get()
            ->map(fn ($c) => $this->avecEffectifDisponible($c));

        return response()->json([
            'success' => true,
            'data'    => $classes,
        ]);
    }

    /**
     * Retourne les classes non pleines pour un niveau (utile pour auto-attribution).
     */
    public function getDisponibles(Request $request, $niveauId)
    {
        $anneeActive = AnneeScolaire::where('statut', 'en_cours')->first();

        $query = Classe::where('niveau_id', $niveauId)
            ->with(['niveau', 'anneeScolaire'])
            ->orderBy('code_division', 'asc');

        if ($anneeActive) {
            $query->where('anneeScolaire_id', $anneeActive->id);
        }

        $classes = $query->get()->map(fn ($c) => $this->avecEffectifDisponible($c));

        return response()->json([
            'success'              => true,
            'annee_scolaire'       => $anneeActive,
            'data'                 => $classes,
            'classes_disponibles'  => $classes->where('est_pleine', false)->values(),
        ]);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'nom_classe'      => 'required|string|max:100',
            'niveau_id'       => 'required|exists:niveaux,id',
            'code_division'   => 'required|string|max:10',
            'anneeScolaire_id' => 'required|exists:annee_scolaires,id',
            'max_effectif'    => 'nullable|integer|min:1|max:200',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        $classe = Classe::create($request->only([
            'nom_classe', 'niveau_id', 'code_division', 'anneeScolaire_id', 'max_effectif',
        ]));

        return response()->json([
            'success' => true,
            'message' => 'Classe créée',
            'data'    => $this->avecEffectifDisponible($classe->load('niveau', 'anneeScolaire')),
        ], 201);
    }

    public function show($id)
    {
        $classe = Classe::with(['niveau', 'anneeScolaire'])->findOrFail($id);

        return response()->json([
            'success' => true,
            'data'    => $this->avecEffectifDisponible($classe),
        ]);
    }

    public function update(Request $request, $id)
    {
        $classe = Classe::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'nom_classe'    => 'sometimes|string|max:100',
            'niveau_id'     => 'sometimes|exists:niveaux,id',
            'code_division' => 'sometimes|string|max:10',
            'effectif'      => 'sometimes|integer|min:0',
            'max_effectif'  => 'sometimes|integer|min:1|max:200',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        $classe->update($request->only([
            'nom_classe', 'niveau_id', 'code_division', 'effectif', 'max_effectif',
        ]));

        return response()->json([
            'success' => true,
            'message' => 'Classe mise à jour',
            'data'    => $this->avecEffectifDisponible($classe->fresh()->load('niveau', 'anneeScolaire')),
        ]);
    }

    public function destroy($id)
    {
        Classe::findOrFail($id)->delete();

        return response()->json([
            'success' => true,
            'message' => 'Classe supprimée',
        ]);
    }

    /**
     * Ajoute les informations de disponibilité à une classe.
     */
    private function avecEffectifDisponible(Classe $classe): array
    {
        $max = $classe->max_effectif ?? 50;
        $data = $classe->toArray();
        $data['max_effectif']      = $max;
        $data['places_restantes']  = max($max - $classe->effectif, 0);
        $data['est_pleine']        = $classe->estPleine();
        return $data;
    }
}