<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Utilisateur;
use App\Models\Staff;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class StaffController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $query = Staff::with(['utilisateur', 'infosDynamiques']);

        // Filtre par fonction
        if ($request->has('fonction')) {
            $query->where('fonction', 'like', '%' . $request->fonction . '%');
        }

        // Filtre par sexe
        if ($request->has('sexe')) {
            $query->where('sexe', '=', $request->sexe);
        }

        // Recherche
        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('nom', 'like', "%{$search}%")
                ->orWhere('prenom', 'like', "%{$search}%")
                ->orWhere('matricule', 'like', "%{$search}%")
                ->orWhere('fonction', 'like', "%{$search}%")
                ->orWhere('email', 'like', "%{$search}%");
            });
        }

        $staffs = $query->orderBy('created_at', 'desc')->paginate(20);

        return response()->json([
            'success' => true,
            'data' => $staffs,
            'total' => $staffs->total(),
            'per_page' => $staffs->perPage(),
            'current_page' => $staffs->currentPage(),
            'last_page' => $staffs->lastPage()
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'nom' => 'required|string|max:50',
            'prenom' => 'required|string|max:50',
            'telephone' => 'required|string|max:20',
            'matricule' => 'required|string|max:20|unique:staffs,matricule',
            'email' => 'required|email|max:50|unique:staffs,email',
            'fonction' => 'required|string|max:75',
            'salaire' => 'required|numeric|min:0',
            'adresse' => 'required|string|max:100',
            'sexe' => 'required|in:masculin,feminin',
            'date_naissance' => 'required|date',
            'lieu_naissance' => 'required|string|max:50',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $staff = Staff::create([
            'nom' => $request->nom,
            'prenom' => $request->prenom,
            'telephone' => $request->telephone,
            'matricule' => $request->matricule,
            'email' => $request->email,
            'fonction' => $request->fonction,
            'salaire' => $request->salaire,
            'adresse' => $request->adresse,
            'sexe' => $request->sexe,
            'date_naissance' => $request->date_naissance,
            'lieu_naissance' => $request->lieu_naissance,
            'utilisateur_id' => Auth::id(),
        ]);

        // Enregistrement des informations dynamiques
        if ($request->has('infos_dynamiques') && is_array($request->infos_dynamiques)) {
            foreach ($request->infos_dynamiques as $nom_champ => $valeur_champ) {
                if (!empty($nom_champ)) {
                    $staff->infosDynamiques()->create([
                        'nom_champ' => $nom_champ,
                        'valeur_champ' => $valeur_champ
                    ]);
                }
            }
        }

        return response()->json([
            'success' => true,
            'message' => 'Staff créé avec succès',
            'data' => $staff
        ], 201);
    }

    /**
     * Display the specified resource.
     */
    public function show($id)
    {
        $staff = Staff::with(['utilisateur', 'infosDynamiques'])->find($id);

        if (!$staff) {
            return response()->json([
                'success' => false,
                'message' => 'Staff non trouvé'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'Staff trouvé avec succès',
            'data' => $staff
        ], 200);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, $id)
    {
        $staff = Staff::findOrFail($id);

        if (!$staff) {
            return response()->json([
                'success' => false,
                'message' => 'Staff non trouvé'
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'nom' => 'sometimes|required|string|max:50',
            'prenom' => 'sometimes|required|string|max:50',
            'telephone' => 'sometimes|required|string|max:20',
            'matricule' => 'sometimes|required|string|max:20|unique:staffs,matricule,' . $id,
            'email' => 'sometimes|required|email|max:50|unique:staffs,email,' . $id,
            'fonction' => 'sometimes|required|string|max:75',
            'salaire' => 'sometimes|required|numeric|min:0',
            'adresse' => 'sometimes|required|string|max:100',
            'sexe' => 'sometimes|required|in:masculin,feminin',
            'date_naissance' => 'sometimes|required|date',
            'lieu_naissance' => 'sometimes|required|string|max:50',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $staff->update($request->only([
            'nom',
            'prenom',
            'telephone',
            'matricule',
            'email',
            'fonction',
            'salaire',
            'adresse',
            'sexe',
            'date_naissance',
            'lieu_naissance'
        ]));

        // Mise à jour des informations dynamiques
        if ($request->has('infos_dynamiques') && is_array($request->infos_dynamiques)) {
            // Optionnel : on peut choisir de supprimer les anciennes ou de mettre à jour au cas par cas
            // Ici, on va faire un updateOrCreate pour chaque champ
            foreach ($request->infos_dynamiques as $nom_champ => $valeur_champ) {
                if (!empty($nom_champ)) {
                    $staff->infosDynamiques()->updateOrCreate(
                        ['staff_id' => $staff->id, 'nom_champ' => $nom_champ],
                        ['valeur_champ' => $valeur_champ]
                    );
                }
            }
        }

        return response()->json([
            'success' => true,
            'message' => 'Staff modifié avec succès',
            'data' => $staff
        ], 200);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy($id)
    {
        $staff = Staff::findOrFail($id);

        if (!$staff) {
            return response()->json([
                'success' => false,
                'message' => 'Staff non trouvé'
            ], 404);
        }

        $staff->delete();

        return response()->json([
            'success' => true,
            'message' => 'Staff supprimé avec succès'
        ], 200);
    }

    /**
     * Get staff statistics
     */
    public function statistiques()
    {
        $stats = [
            'total' => Staff::count(),
            'par_fonction' => Staff::select('fonction',
            DB::raw('count(*) as total'))
                ->groupBy('fonction')
                ->get(),
            'par_sexe' => [
                'masculin' => Staff::where('sexe', 'masculin')->count(),
                'feminin' => Staff::where('sexe', 'feminin')->count(),
            ],
            'salaire_moyen' => round(Staff::avg('salaire') ?? 0, 2),
            'salaire_total' => round(Staff::sum('salaire') ?? 0, 2),
            'salaire_min' => round(Staff::min('salaire') ?? 0, 2),
            'salaire_max' => round(Staff::max('salaire') ?? 0, 2),
        ];

        return response()->json([
            'success' => true,
            'data' => $stats
        ]);
    }

    /**
     * Export staff list
     */
    public function export(Request $request)
    {
        $query = Staff::query();

        if ($request->has('fonction')) {
            $query->where('fonction', 'like', '%' . $request->fonction . '%');
        }

        $staffs = $query->orderBy('nom')->get([
            'matricule', 'nom', 'prenom', 'fonction',
            'telephone', 'email', 'salaire', 'sexe'
        ]);

        return response()->json([
            'success' => true,
            'data' => $staffs,
            'total' => $staffs->count()
        ]);
    }
}
