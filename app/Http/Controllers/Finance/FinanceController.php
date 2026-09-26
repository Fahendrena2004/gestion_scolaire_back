<?php

namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;
use App\Models\Finance\Caisse;
use App\Models\Finance\CategorieEntree;
use App\Models\Finance\CategorieSortie;
use App\Models\Finance\Entree;
use App\Models\Finance\EntreeArchive;
use App\Models\Finance\Sortie;
use App\Models\Finance\SortieArchive;
use App\Models\Inscription\AnneeScolaire;
use App\Models\Inscription\Inscription;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class FinanceController extends Controller
{
    public function index()
    {
        $anneeScolaire = AnneeScolaire::where('statut', 'en_cours')->first();
        if (!$anneeScolaire) {
            $anneeScolaire = AnneeScolaire::latest()->first();
        }

        if (!$anneeScolaire) {
            return response()->json(['success' => false, 'message' => 'Aucune année scolaire trouvée.'], 404);
        }

        $caisse = Caisse::firstOrCreate(
            ['annee_scolaire_id' => $anneeScolaire->id],
            ['nom' => 'Caisse Principale', 'solde' => 0]
        );

        $totalEntrees = Entree::where('annee_scolaire_id', $anneeScolaire->id)->sum('montant');
        $totalSorties = Sortie::where('annee_scolaire_id', $anneeScolaire->id)
            ->where('statut', 'paye')
            ->sum('montant');

        $totalInscriptions = Inscription::where('id_annee_scolaire', $anneeScolaire->id)->count();

        // Donations (type_entree_id qui correspond à 'Donation')
        $totalDonations = Entree::where('annee_scolaire_id', $anneeScolaire->id)
            ->whereHas('type', function($q) {
                $q->where('nom', 'LIKE', '%donation%');
            })->sum('montant');

        return response()->json([
            'success' => true,
            'data' => [
                'solde_actuel' => $caisse->solde,
                'solde' => $caisse->solde,
                'total_entrees' => $totalEntrees,
                'total_sorties' => $totalSorties,
                'total_inscriptions' => $totalInscriptions,
                'total_donations' => $totalDonations,
                'factures_impayees' => 0, // À implémenter si besoin
                'caisse' => $caisse,
                'annee_scolaire' => $anneeScolaire->libelle
            ]
        ]);
    }

    public function getCategories()
    {
        return response()->json([
            'success' => true,
            'data' => [
                'entrees' => CategorieEntree::all(),
                'sorties' => CategorieSortie::all()
            ]
        ]);
    }

    public function storeEntree(Request $request)
    {
        // Support des noms de champs frontend
        if ($request->has('categorie_id') && !$request->has('type_entree_id')) {
            $request->merge(['type_entree_id' => $request->categorie_id]);
        }
        if ($request->has('libelle') && !$request->has('description')) {
            $request->merge(['description' => $request->libelle]);
        }

        $validator = Validator::make($request->all(), [
            'montant' => 'required|numeric|min:0',
            'date_entree' => 'required|date',
            'type_entree_id' => 'required|exists:categories_entree,id',
            'inscription_id' => 'nullable|exists:inscriptions,id',
            'donneur_id' => 'nullable|exists:donneurs,id',
            'description' => 'nullable|string'
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        $anneeScolaire = AnneeScolaire::where('statut', 'en_cours')->first();
        
        DB::beginTransaction();
        try {
            $entree = Entree::create([
                'reference' => 'ENT-' . time(),
                'montant' => $request->montant,
                'date_entree' => $request->date_entree,
                'type_entree_id' => $request->type_entree_id,
                'inscription_id' => $request->inscription_id,
                'donneur_id' => $request->donneur_id,
                'annee_scolaire_id' => $anneeScolaire->id,
                'description' => $request->description,
                'created_by' => auth()->id()
            ]);

            // Mise à jour caisse
            $caisse = Caisse::where('annee_scolaire_id', $anneeScolaire->id)->first();
            $caisse->increment('solde', $request->montant);

            DB::commit();
            return response()->json(['success' => true, 'data' => $entree]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    public function storeSortie(Request $request)
    {
        // Support des noms de champs frontend
        if ($request->has('categorie_id') && !$request->has('type_sortie_id')) {
            $request->merge(['type_sortie_id' => $request->categorie_id]);
        }
        if ($request->has('libelle') && !$request->has('description')) {
            $request->merge(['description' => $request->libelle]);
        }

        $validator = Validator::make($request->all(), [
            'montant' => 'required|numeric|min:0',
            'date_sortie' => 'required|date',
            'type_sortie_id' => 'required|exists:categories_sortie,id',
            'staff_id' => 'nullable|exists:staffs,id',
            'description' => 'nullable|string'
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        $categorie = CategorieSortie::find($request->type_sortie_id);
        
        // Vérification salaire
        if (str_contains(strtolower($categorie->nom), 'salaire') && !$request->staff_id) {
            return response()->json([
                'success' => false, 
                'message' => 'Le personnel (staff_id) est obligatoire pour une sortie de type Salaire.'
            ], 422);
        }

        $anneeScolaire = AnneeScolaire::where('statut', 'en_cours')->first();
        $caisse = Caisse::where('annee_scolaire_id', $anneeScolaire->id)->first();

        if ($caisse->solde < $request->montant) {
            return response()->json(['success' => false, 'message' => 'Solde insuffisant dans la caisse.'], 422);
        }

        DB::beginTransaction();
        try {
            $sortie = Sortie::create([
                'reference' => 'SOR-' . time(),
                'montant' => $request->montant,
                'date_sortie' => $request->date_sortie,
                'type_sortie_id' => $request->type_sortie_id,
                'staff_id' => $request->staff_id,
                'statut' => 'paye', // Directement payé
                'annee_scolaire_id' => $anneeScolaire->id,
                'description' => $request->description,
                'created_by' => auth()->id(),
                'paid_by' => auth()->id()
            ]);

            $caisse->decrement('solde', $request->montant);

            DB::commit();
            return response()->json(['success' => true, 'data' => $sortie]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    public function getHistorique(Request $request)
    {
        $anneeLibelle = $request->query('annee'); // ex: 2025-2026

        $queryEntrees = Entree::with(['type', 'inscription.eleve', 'donneur', 'anneeScolaire']);
        $querySorties = Sortie::with(['type', 'staff', 'anneeScolaire'])->where('statut', 'paye');

        if ($anneeLibelle && $anneeLibelle != 'Tous') {
            $queryEntrees->whereHas('anneeScolaire', function($q) use ($anneeLibelle) {
                $q->where('libelle', $anneeLibelle);
            });
            $querySorties->whereHas('anneeScolaire', function($q) use ($anneeLibelle) {
                $q->where('libelle', $anneeLibelle);
            });
        }

        $entrees = $queryEntrees->get()->map(function($e) {
            return [
                'annee' => $e->anneeScolaire->libelle,
                'date' => $e->date_entree,
                'reference' => $e->reference,
                'montant' => $e->montant,
                'type' => $e->type->nom,
                'source' => $e->inscription ? $e->inscription->eleve->nom . ' ' . $e->inscription->eleve->prenom : ($e->donneur ? $e->donneur->nom : 'N/A'),
                'is_entree' => true
            ];
        });

        $sorties = $querySorties->get()->map(function($s) {
            return [
                'annee' => $s->anneeScolaire->libelle,
                'date' => $s->date_sortie,
                'reference' => $s->reference,
                'montant' => $s->montant,
                'type' => $s->type->nom,
                'source' => $s->staff ? $s->staff->nom . ' ' . $s->staff->prenom : 'Interne',
                'is_entree' => false
            ];
        });

        // Archives
        $queryArchivedEntrees = EntreeArchive::query();
        $queryArchivedSorties = SortieArchive::query();

        if ($anneeLibelle && $anneeLibelle != 'Tous') {
            $queryArchivedEntrees->where('anneeScolaire_libelle', $anneeLibelle);
            $queryArchivedSorties->where('anneeScolaire_libelle', $anneeLibelle);
        }

        $archivedEntrees = $queryArchivedEntrees->get()->map(function($e) {
            return [
                'annee' => $e->annee_scolaire_libelle,
                'date' => $e->date_entree,
                'reference' => $e->reference,
                'montant' => $e->montant,
                'type' => $e->type_entree_nom,
                'source' => $e->source_nom,
                'is_entree' => true
            ];
        });

        $archivedSorties = $queryArchivedSorties->get()->map(function($s) {
            return [
                'annee' => $s->annee_scolaire_libelle,
                'date' => $s->date_sortie,
                'reference' => $s->reference,
                'montant' => $s->montant,
                'type' => $s->type_sortie_nom,
                'source' => $s->beneficiaire_nom,
                'is_entree' => false
            ];
        });

        $merged = $entrees->concat($sorties)->concat($archivedEntrees)->concat($archivedSorties)
            ->sortByDesc('date')->values();

        return response()->json(['success' => true, 'data' => $merged]);
    }

    public function archiveYear($anneeId)
    {
        $annee = AnneeScolaire::findOrFail($anneeId);
        
        DB::beginTransaction();
        try {
            // Archiver Entrees
            $entrees = Entree::where('annee_scolaire_id', $anneeId)->get();
            foreach ($entrees as $e) {
                EntreeArchive::create([
                    'reference' => $e->reference,
                    'montant' => $e->montant,
                    'date_entree' => $e->date_entree,
                    'type_entree_nom' => $e->type->nom,
                    'source_nom' => $e->inscription ? $e->inscription->eleve->nom . ' ' . $e->inscription->eleve->prenom : ($e->donneur ? $e->donneur->nom : 'N/A'),
                    'annee_scolaire_libelle' => $annee->libelle,
                    'description' => $e->description
                ]);
                $e->delete();
            }

            // Archiver Sorties
            $sorties = Sortie::where('annee_scolaire_id', $anneeId)->where('statut', 'paye')->get();
            foreach ($sorties as $s) {
                SortieArchive::create([
                    'reference' => $s->reference,
                    'montant' => $s->montant,
                    'date_sortie' => $s->date_sortie,
                    'type_sortie_nom' => $s->type->nom,
                    'beneficiaire_nom' => $s->staff ? $s->staff->nom . ' ' . $s->staff->prenom : 'Interne',
                    'annee_scolaire_libelle' => $annee->libelle,
                    'description' => $s->description
                ]);
                $s->delete();
            }

            DB::commit();
            return response()->json(['success' => true, 'message' => 'Archivage effectué avec succès.']);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }
}
