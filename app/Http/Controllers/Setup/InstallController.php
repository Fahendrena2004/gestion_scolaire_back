<?php

namespace App\Http\Controllers\Setup;

use App\Http\Controllers\Controller;
use App\Models\Gestion_note\Matieres;
use App\Models\Inscription\AnneeScolaire;
use App\Models\Inscription\Classe;
use App\Models\Inscription\Niveau;
use App\Models\Inscription\TypeFrais;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class InstallController extends Controller
{
    /**
     * ÉTAPE 1 : Créer l'année scolaire (UNIQUEMENT)
     * POST /api/setup/annee-scolaire
     */
    public function createAnneeScolaire(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'date_debut' => 'required|date',
            'date_fin' => 'required|date|after:date_debut',
            'statut' => 'required|in:en_cours,termine,planifie',
            'date_debut_inscription' => 'nullable|date',
            'date_fin_inscription' => 'nullable|date|after_or_equal:date_debut_inscription',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        // Désactiver les autres années "en_cours"
        if ($request->statut === 'en_cours') {
            AnneeScolaire::where('statut', 'en_cours')->update(['statut' => 'termine']);
        }

        $annee = AnneeScolaire::create($request->only([
            'date_debut', 
            'date_fin', 
            'statut',
            'date_debut_inscription',
            'date_fin_inscription'
        ]));

        return response()->json([
            'success' => true,
            'message' => 'Année scolaire créée avec succès',
            'step' => 1,
            'next_action' => 'configure_niveaux', // ← direction l'interface admin
            'data' => [
                'id' => $annee->id,
                'libelle' => $annee->libelle,
                'date_debut' => $annee->date_debut,
                'date_fin' => $annee->date_fin,
                'statut' => $annee->statut,
                'est_inscription_ouverte' => $annee->est_inscription_ouverte,
            ],
        ], 201);
    }

    /**
     * Vérifier l'état de l'installation
     * GET /api/setup/status
     */
    public function getStatus()
    {
        $anneeActive = AnneeScolaire::where('statut', 'en_cours')->first();
        $anneeCount = AnneeScolaire::count();
        
        $hasNiveaux = Niveau::count() > 0;
        $hasClasses = Classe::count() > 0;
        $hasFrais = $anneeActive ? TypeFrais::where('annee_scolaire_id', $anneeActive->id)->count() > 0 : false;
        $hasMatieres = Matieres::count() > 0;

        // Déterminer l'étape
        if ($anneeCount === 0) {
            $step = 1;
            $message = "Aucune année scolaire. Veuillez en créer une.";
            $nextAction = "create_annee";
        } elseif (!$hasNiveaux) {
            $step = 2;
            $message = "Aucun niveau. Allez dans Configuration Scolaire → Structure pour ajouter des niveaux.";
            $nextAction = "configure_niveaux";
        } elseif (!$hasClasses) {
            $step = 3;
            $message = "Aucune classe. Allez dans Configuration Scolaire → Structure pour ajouter des classes.";
            $nextAction = "configure_classes";
        } elseif (!$hasFrais) {
            $step = 4;
            $message = "Aucun frais. Allez dans Configuration Scolaire → Frais pour configurer les tarifs.";
            $nextAction = "configure_frais";
        } elseif (!$hasMatieres) {
            $step = 5;
            $message = "Aucune matière. Allez dans Gestion des notes pour ajouter des matières.";
            $nextAction = "configure_matieres";
        } else {
            $step = 6;
            $message = "Installation terminée ! Vous pouvez utiliser l'application.";
            $nextAction = "complete";
        }

        return response()->json([
            'success' => true,
            'status' => [
                'step' => $step,
                'message' => $message,
                'next_action' => $nextAction,
                'complete' => $step === 6,
                'annee_scolaire' => [
                    'exists' => $anneeCount > 0,
                    'active' => $anneeActive,
                    'count' => $anneeCount,
                ],
                'niveaux' => [
                    'exists' => $hasNiveaux,
                    'count' => Niveau::count(),
                ],
                'classes' => [
                    'exists' => $hasClasses,
                    'count' => Classe::count(),
                ],
                'frais' => [
                    'exists' => $hasFrais,
                    'count' => $anneeActive ? TypeFrais::where('annee_scolaire_id', $anneeActive->id)->count() : 0,
                ],
                'matieres' => [
                    'exists' => $hasMatieres,
                    'count' => Matieres::count(),
                ],
            ],
        ]);
    }

    /**
     * Récupérer l'année active
     * GET /api/setup/active-annee
     */
    public function getActiveAnnee()
    {
        $annee = AnneeScolaire::where('statut', 'en_cours')->first();

        if (!$annee) {
            return response()->json([
                'success' => false,
                'message' => 'Aucune année scolaire active',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $annee->id,
                'libelle' => $annee->libelle,
                'date_debut' => $annee->date_debut,
                'date_fin' => $annee->date_fin,
                'statut' => $annee->statut,
                'date_debut_inscription' => $annee->date_debut_inscription,
                'date_fin_inscription' => $annee->date_fin_inscription,
                'est_inscription_ouverte' => $annee->est_inscription_ouverte,
            ],
        ]);
    }

    /**
     * RESET - Supprimer TOUTES les données
     * POST /api/setup/reset
     */
    public function reset(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'confirmation' => 'required|string|in:RESET',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Confirmation requise. Envoyez "confirmation": "RESET"',
            ], 422);
        }

        // Supprimer dans l'ordre inverse des dépendances
        Matieres::truncate();
        TypeFrais::truncate();
        Classe::truncate();
        Niveau::truncate();
        AnneeScolaire::truncate();

        return response()->json([
            'success' => true,
            'message' => 'Toutes les données ont été réinitialisées',
            'step' => 1,
            'next_action' => 'create_annee',
        ]);
    }
}
