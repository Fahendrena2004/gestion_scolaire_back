<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| IMPORTS CONTROLLERS
|--------------------------------------------------------------------------
*/
// Dashboard
use App\Http\Controllers\Dashboard\CaissierController;
use App\Http\Controllers\Dashboard\RecapitulatifAnneeScolaireController;

// AUTH
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\Admin\StaffController;
use App\Http\Controllers\Admin\UtilisateurController;

// SETUP
use App\Http\Controllers\Setup\InstallController;

// INSCRIPTION
use App\Http\Controllers\Inscription\CycleController;
use App\Http\Controllers\Inscription\AnneeScolaireController;
use App\Http\Controllers\Inscription\ClasseController;
use App\Http\Controllers\Inscription\NiveauController;
use App\Http\Controllers\Inscription\FraisController;
use App\Http\Controllers\Inscription\TypeFraisController;
use App\Http\Controllers\Inscription\InscriptionController;
use App\Http\Controllers\Inscription\PaiementController;
use App\Http\Controllers\Inscription\ReinscriptionController;

// NOTES
use App\Http\Controllers\Gestion_note\MatieresController;
use App\Http\Controllers\Gestion_note\NotesController;
use App\Http\Controllers\Gestion_note\BulletinController;
use App\Http\Controllers\Gestion_note\DetailBulletinController;

// PAIEMENTS
use App\Http\Controllers\Paiements\CantineController;
use App\Http\Controllers\Paiements\ScolariteController;
use App\Http\Controllers\Paiements\AutresFraisController;
use App\Http\Controllers\Paiements\FiltrationPaiement;

/*
|--------------------------------------------------------------------------
| ROUTES PUBLIQUES
|--------------------------------------------------------------------------
*/

Route::get('/test', function () {
    return response()->json(['status' => 'ok', 'message' => 'API fonctionne']);
});

// Auth
Route::post('/register', [RegisterController::class, 'register']);
Route::post('/login', [LoginController::class, 'login']);

/*
|--------------------------------------------------------------------------
| ROUTES PROTEGEES (SANCTUM)
|--------------------------------------------------------------------------
*/

Route::middleware('auth:sanctum')->group(function () {

    // Auth utilisateur
    Route::get('/user', [LoginController::class, 'user']);
    Route::post('/logout', [LoginController::class, 'logout']);

    // Dashboard caissier — vue globale financière
    Route::get('/caissier/dashboard', [CaissierController::class, 'index']);

    /*
    |--------------------------------------------------------------------------
    | ADMIN UNIQUEMENT
    |--------------------------------------------------------------------------
    */
    Route::middleware('role:admin')->group(function () {
        Route::post('/register', [RegisterController::class, 'register']);
        Route::get('/admin/utilisateurs', [UtilisateurController::class, 'index']);

        Route::prefix('inscription')->group(function () {
            // Années scolaires (CRUD admin)
            Route::get('/annees-scolaires', [AnneeScolaireController::class, 'index']);
            Route::get('/annees-scolaires/active', [AnneeScolaireController::class, 'getActive']);
            Route::post('/annees-scolaires', [AnneeScolaireController::class, 'store']);
            Route::get('/annees-scolaires/{id}', [AnneeScolaireController::class, 'show']);
            Route::put('/annees-scolaires/{id}', [AnneeScolaireController::class, 'update']);
            Route::delete('/annees-scolaires/{id}', [AnneeScolaireController::class, 'destroy']);

            // Types de frais (CRUD admin)
            Route::get('/frais/types', [TypeFraisController::class, 'index']);
            Route::post('/frais/types', [TypeFraisController::class, 'store']);
            Route::put('/frais/types/{id}', [TypeFraisController::class, 'update']);
            Route::delete('/frais/types/{id}', [TypeFraisController::class, 'destroy']);
        });

        // Staff admin
        Route::prefix('admin/staffs')->group(function () {
            Route::get('/', [StaffController::class, 'index']);
            Route::post('/', [StaffController::class, 'store']);
            Route::get('/statistiques', [StaffController::class, 'statistiques']);
            Route::get('/export', [StaffController::class, 'export']);
            Route::get('/{id}', [StaffController::class, 'show']);
            Route::put('/{id}', [StaffController::class, 'update']);
            Route::delete('/{id}', [StaffController::class, 'destroy']);
        });
    });

    /*
    |--------------------------------------------------------------------------
    | ADMIN ET CAISSIER
    |--------------------------------------------------------------------------
    */
    Route::middleware('role:admin,caissier')->group(function () {

        // Dashboard récapitulatif par année
        Route::prefix('dashboard/recapitulatif')->group(function () {
            Route::get('/annees-scolaires', [RecapitulatifAnneeScolaireController::class, 'anneesScolaires']);
            Route::get('/annees-scolaires/{anneeId}/classes', [RecapitulatifAnneeScolaireController::class, 'classesParAnnee']);
            Route::get('/annees-scolaires/{anneeId}/classes/{classeId}', [RecapitulatifAnneeScolaireController::class, 'detailClasse']);
            Route::get('/annees-scolaires/{anneeId}/statistiques', [RecapitulatifAnneeScolaireController::class, 'statistiques']);
            Route::get('/annees-scolaires/{anneeId}/finance', [RecapitulatifAnneeScolaireController::class, 'finance']);
            Route::get('/annees-scolaires/{anneeId}/journal-caisse', [RecapitulatifAnneeScolaireController::class, 'journalCaisse']);
        });

        // ─── INSCRIPTION ──────────────────────────────────────────────────────
        Route::prefix('inscription')->group(function () {

            Route::get('/cycles', [CycleController::class, 'index']);

            // Niveaux
            Route::get('/niveaux', [NiveauController::class, 'index']);
            Route::get('/niveaux/{cycle}', [NiveauController::class, 'getByCycle']);
            Route::post('/niveaux', [NiveauController::class, 'store']);
            Route::get('/niveaux/{id}', [NiveauController::class, 'show']);
            Route::put('/niveaux/{id}', [NiveauController::class, 'update']);
            Route::delete('/niveaux/{id}', [NiveauController::class, 'destroy']);

            // Classes
            Route::get('/classes', [ClasseController::class, 'index']);
            Route::get('/classes/niveau/{niveauId}', [ClasseController::class, 'getByNiveau']);
            Route::get('/classes/niveau/{niveauId}/disponibles', [ClasseController::class, 'getDisponibles']);
            Route::get('/classes/cycle/{cycle}', [ClasseController::class, 'getByCycle']);
            Route::post('/classes', [ClasseController::class, 'store']);
            Route::get('/classes/{id}', [ClasseController::class, 'show']);
            Route::put('/classes/{id}', [ClasseController::class, 'update']);
            Route::delete('/classes/{id}', [ClasseController::class, 'destroy']);

            // Frais calcul
            Route::get('/frais/calcul', [FraisController::class, 'calcul']);

            // Attribution automatique de classe pour un niveau donné
            Route::get('/auto-classe', [InscriptionController::class, 'getClasseAuto']);

            // Inscriptions
            Route::get('/', [InscriptionController::class, 'index']);
            Route::post('/', [InscriptionController::class, 'store']);
            Route::get('/{id}', [InscriptionController::class, 'show']);
            Route::get('/{id}/infos-dynamiques', [InscriptionController::class, 'getDynamicInfos']);

            // Paiements d'une inscription
            Route::get('/{inscriptionId}/paiements', [PaiementController::class, 'index']);
            Route::post('/{inscriptionId}/paiements', [PaiementController::class, 'store']);
            Route::get('/paiements/{id}', [PaiementController::class, 'show']);
            Route::delete('/paiements/{id}', [PaiementController::class, 'destroy']);
        });

        // ─── RÉINSCRIPTIONS ───────────────────────────────────────────────────
        Route::prefix('reinscriptions')->group(function () {
            Route::get('/rechercher', [ReinscriptionController::class, 'rechercherParMatricule']);
            Route::get('/', [ReinscriptionController::class, 'index']);
            Route::post('/', [ReinscriptionController::class, 'store']);
            Route::get('/{id}', [ReinscriptionController::class, 'show']);
            Route::delete('/{id}', [ReinscriptionController::class, 'destroy']);
            Route::put('/{id}/paiement', [ReinscriptionController::class, 'updatePaiement']);
        });

        // ─── MATIÈRES ─────────────────────────────────────────────────────────
        Route::prefix('matieres')->group(function () {
            Route::get('/', [MatieresController::class, 'index']);
            Route::post('/', [MatieresController::class, 'store']);
            Route::post('/multiple', [MatieresController::class, 'storeMultiple']);
            Route::get('/suggestions/{cycle}', [MatieresController::class, 'suggestions']);
            Route::get('/{id}', [MatieresController::class, 'show']);
            Route::put('/{id}', [MatieresController::class, 'update']);
            Route::delete('/{id}', [MatieresController::class, 'destroy']);
        });

        // ─── NOTES ────────────────────────────────────────────────────────────
        Route::prefix('notes')->group(function () {
            Route::get('/', [NotesController::class, 'index']);
            Route::post('/', [NotesController::class, 'store']);
            Route::get('/moyenne/{inscriptionId}/{periode}', [NotesController::class, 'getMoyenne']);
            Route::get('/{id}', [NotesController::class, 'show']);
            Route::put('/{id}', [NotesController::class, 'update']);
            Route::delete('/{id}', [NotesController::class, 'destroy']);
        });

        // ─── BULLETINS ────────────────────────────────────────────────────────
        Route::prefix('bulletins')->group(function () {
            Route::post('/generate', [BulletinController::class, 'generate']);
            Route::post('/generate-class', [BulletinController::class, 'generateForClass']);
            Route::get('/eleve/{inscriptionId}', [BulletinController::class, 'getByEleve']);
            Route::get('/classe', [BulletinController::class, 'getByClass']);
            Route::get('/{id}', [BulletinController::class, 'show']);
            Route::put('/{id}/appreciation', [BulletinController::class, 'updateAppreciation']);
            Route::delete('/{id}', [BulletinController::class, 'destroy']);
            Route::get('/{id}/pdf', [BulletinController::class, 'exportPDF']);
        });

        // ─── DÉTAILS BULLETINS ────────────────────────────────────────────────
        Route::prefix('detail-bulletins')->group(function () {
            Route::get('/bulletin/{bulletinId}', [DetailBulletinController::class, 'getByBulletin']);
            Route::put('/{id}', [DetailBulletinController::class, 'update']);
        });

        // ─── CANTINE ─────────────────────────────────────────────────────────
        Route::prefix('cantine')->group(function () {
            Route::get('/filtres', [FiltrationPaiement::class, 'getFiltres']);
            Route::get('/eleves', [FiltrationPaiement::class, 'filtrerEleves']);
            Route::post('/presence', [CantineController::class, 'marquerPresence']);
            Route::get('/mois-disponibles/{inscriptionId}', [CantineController::class, 'getMoisDisponibles']);
            Route::get('/jours/{inscriptionId}', [CantineController::class, 'getJours']);
            Route::post('/payer', [CantineController::class, 'payerJours']);
        });

        // ─── AUTRES FRAIS ─────────────────────────────────────────────────────
        Route::prefix('autres-frais')->group(function () {
            Route::get('/{inscriptionId}', [AutresFraisController::class, 'getFraisAPayer']);
            Route::post('/payer', [AutresFraisController::class, 'payer']);
        });

        // ─── SCOLARITÉ ────────────────────────────────────────────────────────
        Route::prefix('scolarite')->group(function () {
            Route::get('/mois/{inscriptionId}', [ScolariteController::class, 'getMoisAPayer']);
            Route::post('/payer', [ScolariteController::class, 'payerMois']);
            Route::post('/payer-tout/{inscriptionId}', [ScolariteController::class, 'payerTout']);
            Route::get('/historique/{inscriptionId}', [ScolariteController::class, 'getHistorique']);
        });
    });
});

/*
|--------------------------------------------------------------------------
| SETUP (admin uniquement)
|--------------------------------------------------------------------------
*/

Route::prefix('setup')->middleware(['auth:sanctum', 'role:admin'])->group(function () {
    Route::get('/status', [InstallController::class, 'getStatus']);
    Route::post('/annee-scolaire', [InstallController::class, 'createAnneeScolaire']);
    Route::post('/niveaux', [InstallController::class, 'createNiveaux']);
    Route::post('/classes', [InstallController::class, 'generateClasses']);
    Route::post('/frais', [InstallController::class, 'createTypeFrais']);
    Route::post('/reset', [InstallController::class, 'reset']);
});
