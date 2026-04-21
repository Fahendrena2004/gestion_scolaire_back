<?php

namespace App\Http\Controllers\Inscription;

use App\Http\Controllers\Controller;
use App\Models\Inscription\Eleve;
use App\Models\Inscription\Reinscription;
use App\Models\Inscription\Classe;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class ReinscriptionController extends Controller
{
    /**
     * Rechercher un élève par MATRICULE pour réinscription
     */
    public function rechercherParMatricule(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'matricule' => 'required|string',
            'annee_scolaire_id' => 'required|exists:annee_scolaires,id'
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        // RECHERCHE PAR MATRICULE
        $eleve = Eleve::where('matricule', $request->matricule)
            ->with(['inscriptions' => function($q) {
                $q->with(['classe.niveau', 'anneeScolaire']);
            }])
            ->first();

        if (!$eleve) {
            return response()->json([
                'message' => 'Aucun élève trouvé avec ce matricule',
                'matricule_recherche' => $request->matricule
            ], 404);
        }

        // Vérifier si déjà réinscrit pour cette année
        $dejaReinscrit = Reinscription::where('eleve_id', $eleve->id)
            ->where('annee_scolaire_id', $request->annee_scolaire_id)
            ->exists();

        // Récupérer la dernière inscription
        $derniereInscription = $eleve->inscriptions()
            ->with(['classe.niveau', 'anneeScolaire'])
            ->latest('date_inscription')
            ->first();

        if (!$derniereInscription) {
            return response()->json([
                'message' => 'Cet élève n\'a pas d\'inscription antérieure',
                'eleve' => [
                    'id' => $eleve->id,
                    'matricule' => $eleve->matricule,
                    'nom' => $eleve->nom,
                    'prenom' => $eleve->prenom,
                ]
            ], 400);
        }

        // Proposer la classe supérieure
        $classeSuperieure = null;
        if ($derniereInscription->classe) {
            $classeSuperieure = Classe::where('niveau_id', $derniereInscription->classe->niveau_id + 1)
                ->first();
        }

        return response()->json([
            'eleve' => [
                'id' => $eleve->id,
                'matricule' => $eleve->matricule,
                'nom' => $eleve->nom,
                'prenom' => $eleve->prenom,
                'sexe' => $eleve->sexe ?? null,
                'date_naissance' => $eleve->date_naissance ?? null,
                'lieu_naissance' => $eleve->lieu_naissance ?? null,
            ],
            'derniere_inscription' => $derniereInscription ? [
                'id' => $derniereInscription->id,
                'annee_scolaire' => $derniereInscription->anneeScolaire->libelle ?? null,
                'classe' => $derniereInscription->classe->nom_classe ?? null,
                'montant_total' => $derniereInscription->montant_total ?? null,
                'date_inscription' => $derniereInscription->date_inscription ?? null,
            ] : null,
            'classe_superieure_proposee' => $classeSuperieure ? [
                'id' => $classeSuperieure->id,
                'nom' => $classeSuperieure->nom,
            ] : null,
            'deja_reinscrit' => $dejaReinscrit,
        ]);
    }

    /**
     * Créer une réinscription
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'matricule' => 'required|string|exists:eleves,matricule',
            'inscription_id' => 'required|exists:inscriptions,id',
            'annee_scolaire_id' => 'required|exists:annee_scolaires,id',
            'classe_id' => 'required|exists:classes,id',
            'montant_reinscription' => 'required|numeric|min:0',
            'parascolaire' => 'nullable|numeric|min:0',
            'cantine' => 'nullable|numeric|min:0',
            'date_reinscription' => 'required|date',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        // Récupérer l'élève par son MATRICULE
        $eleve = Eleve::where('matricule', $request->matricule)->first();
        
        if (!$eleve) {
            return response()->json([
                'message' => 'Élève non trouvé avec le matricule : ' . $request->matricule
            ], 404);
        }

        // Vérifier si déjà réinscrit pour cette année
        $existe = Reinscription::where('eleve_id', $eleve->id)
            ->where('annee_scolaire_id', $request->annee_scolaire_id)
            ->exists();

        if ($existe) {
            return response()->json([
                'message' => 'Cet élève est déjà réinscrit pour l\'année scolaire choisie'
            ], 409);
        }

        DB::beginTransaction();

        try {
            $reinscription = Reinscription::create([
                'inscription_id' => $request->inscription_id,
                'eleve_id' => $eleve->id,
                'annee_scolaire_id' => $request->annee_scolaire_id,
                'classe_id' => $request->classe_id,
                'montant_reinscription' => $request->montant_reinscription,
                'parascolaire' => $request->parascolaire ?? 0,
                'cantine' => $request->cantine ?? 0,
                'est_paye' => false,
                'date_reinscription' => $request->date_reinscription,
                'utilisateur_id' => Auth::id(),
            ]);

            DB::commit();

            return response()->json([
                'message' => 'Réinscription enregistrée avec succès',
                'reinscription' => [
                    'id' => $reinscription->id,
                    'matricule_eleve' => $eleve->matricule,
                    'eleve_nom' => $eleve->nom,
                    'eleve_prenom' => $eleve->prenom,
                    'classe_id' => $reinscription->classe_id,
                    'annee_scolaire_id' => $reinscription->annee_scolaire_id,
                    'montant_reinscription' => $reinscription->montant_reinscription,
                    'date_reinscription' => $reinscription->date_reinscription,
                ]
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Erreur lors de l\'enregistrement',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Lister les réinscriptions
     */
    public function index(Request $request)
    {
        $query = Reinscription::with(['eleve', 'classe', 'anneeScolaire']);

        if ($request->has('annee_scolaire_id')) {
            $query->where('annee_scolaire_id', $request->annee_scolaire_id);
        }

        if ($request->has('classe_id')) {
            $query->where('classe_id', $request->classe_id);
        }

        // Recherche par matricule
        if ($request->has('matricule')) {
            $query->whereHas('eleve', function($q) use ($request) {
                $q->where('matricule', 'like', '%' . $request->matricule . '%');
            });
        }

        $reinscriptions = $query->orderBy('created_at', 'desc')->paginate(20);

        return response()->json($reinscriptions);
    }

    /**
     * Voir une réinscription
     */
    public function show($id)
    {
        $reinscription = Reinscription::with([
            'eleve',
            'classe.niveau',
            'anneeScolaire',
            'inscription',
            'utilisateur'
        ])->find($id);

        if (!$reinscription) {
            return response()->json(['message' => 'Réinscription non trouvée'], 404);
        }

        return response()->json($reinscription);
    }

    /**
     * Mettre à jour le statut de paiement
     */
    public function updatePaiement($id)
    {
        $reinscription = Reinscription::find($id);
        
        if (!$reinscription) {
            return response()->json(['message' => 'Réinscription non trouvée'], 404);
        }
        
        $reinscription->update(['est_paye' => true]);

        return response()->json([
            'message' => 'Statut de paiement mis à jour',
            'est_paye' => true
        ]);
    }

    /**
     * Supprimer une réinscription
     */
    public function destroy($id)
    {
        $reinscription = Reinscription::find($id);
        
        if (!$reinscription) {
            return response()->json(['message' => 'Réinscription non trouvée'], 404);
        }
        
        $reinscription->delete();

        return response()->json([
            'message' => 'Réinscription supprimée avec succès'
        ]);
    }
}
