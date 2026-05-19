<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| IMPORTS CONTROLLERS
|--------------------------------------------------------------------------
*/
// Dashboard
use App\Http\Controllers\Dashboard\AdminController;
use App\Http\Controllers\Dashboard\CaissierController;
use App\Http\Controllers\Dashboard\RecapitulatifAnneeScolaireController;
// Finance
use App\Http\Controllers\Finance\FinanceController;

// AUTH
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\Auth\ResetpasswordController;
use App\Http\Controllers\Admin\StaffController;
use App\Http\Controllers\Admin\StaffPointageController;
use App\Http\Controllers\Admin\UtilisateurController;
use App\Http\Controllers\NotificationController;

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
Route::post('/forgot-password', [ResetpasswordController::class, 'sendResetLinkEmail']);
Route::post('/reset-password', [ResetpasswordController::class, 'reset']);

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

    // Notifications
    Route::prefix('notifications')->group(function () {
        Route::get('/', [NotificationController::class, 'index']);
        Route::put('/{id}/read', [NotificationController::class, 'markAsRead']);
        Route::put('/read-all', [NotificationController::class, 'markAllAsRead']);
    });

    /*
    |--------------------------------------------------------------------------
    | ADMIN UNIQUEMENT
    |--------------------------------------------------------------------------
    */
    Route::middleware('role:admin')->group(function () {
        Route::post('/register', [RegisterController::class, 'register']);

        // Gestion des utilisateurs (CRUD complet)
        Route::prefix('admin/utilisateurs')->group(function () {
            Route::get('/', [UtilisateurController::class, 'index']);
            Route::post('/', [UtilisateurController::class, 'store']);
            Route::get('/statistiques', [UtilisateurController::class, 'statistiques']);
            Route::post('/send-code', [UtilisateurController::class, 'sendVerificationCode']);
            Route::post('/verify-code', [UtilisateurController::class, 'verifyCode']);
            Route::get('/{id}', [UtilisateurController::class, 'show']);
            Route::put('/{id}', [UtilisateurController::class, 'update']);
            Route::delete('/{id}', [UtilisateurController::class, 'destroy']);
        });

        Route::prefix('inscription')->group(function () {
            // Années scolaires (CRUD admin)
            Route::get('/annees-scolaires', [AnneeScolaireController::class, 'index']);
            Route::get('/annee-scolaire', [AnneeScolaireController::class, 'index']); // Alias
            
            Route::get('/annees-scolaires/active', [AnneeScolaireController::class, 'getActive']);
            Route::get('/annee-scolaire/active', [AnneeScolaireController::class, 'getActive']); // Alias
            
            Route::post('/annees-scolaires', [AnneeScolaireController::class, 'store']);
            Route::post('/annee-scolaire', [AnneeScolaireController::class, 'store']); // Alias
            
            Route::get('/annees-scolaires/{id}', [AnneeScolaireController::class, 'show'])->whereNumber('id');
            Route::get('/annee-scolaire/{id}', [AnneeScolaireController::class, 'show'])->whereNumber('id'); // Alias
            
            Route::put('/annees-scolaires/{id}', [AnneeScolaireController::class, 'update'])->whereNumber('id');
            Route::put('/annee-scolaire/{id}', [AnneeScolaireController::class, 'update'])->whereNumber('id'); // Alias
            Route::delete('/annees-scolaires/{id}', [AnneeScolaireController::class, 'destroy'])->whereNumber('id');
            Route::delete('/annee-scolaire/{id}', [AnneeScolaireController::class, 'destroy'])->whereNumber('id'); // Alias

            // Types de frais (CRUD admin - MODIFICATION UNIQUEMENT)
            Route::post('/frais/types', [TypeFraisController::class, 'store']);
            Route::post('/frais/type', [TypeFraisController::class, 'store']); // Alias
            Route::put('/frais/types/{id}', [TypeFraisController::class, 'update'])->whereNumber('id');
            Route::put('/frais/type/{id}', [TypeFraisController::class, 'update'])->whereNumber('id'); // Alias
            Route::delete('/frais/types/{id}', [TypeFraisController::class, 'destroy'])->whereNumber('id');
            Route::delete('/frais/type/{id}', [TypeFraisController::class, 'destroy'])->whereNumber('id'); // Alias
        });

        // Dashboard Admin
        Route::get('admin/dashboard/stats', [AdminController::class, 'getStats']);
    });

    /*
    |--------------------------------------------------------------------------
    | ADMIN ET CAISSIER
    |--------------------------------------------------------------------------
    */
    Route::middleware('role:admin,caissier')->group(function () {

        // ─── GESTION STAFF (Shared) ──────────────────────────────────────────
        Route::prefix('admin/staffs')->group(function () {
            Route::get('/', [StaffController::class, 'index']);
            Route::get('/{id}', [StaffController::class, 'show'])->whereNumber('id');
            
            // Pointage (Accessible to Caissier for daily tracking)
            Route::get('/pointage', [StaffPointageController::class, 'index']);
            Route::post('/pointage', [StaffPointageController::class, 'store']);
            Route::get('/{id}/pointage-history', [StaffPointageController::class, 'history']);

            // Operations reserved for ADMIN
            Route::middleware('role:admin')->group(function () {
                Route::post('/', [StaffController::class, 'store']);
                Route::put('/{id}', [StaffController::class, 'update']);
                Route::delete('/{id}', [StaffController::class, 'destroy']);
                Route::get('/statistiques', [StaffController::class, 'statistiques']);
                Route::get('/export', [StaffController::class, 'export']);
            });
        });

        // Dashboard récapitulatif par année
        Route::prefix('dashboard/recapitulatif')->group(function () {
            Route::get('/annees-scolaires', [RecapitulatifAnneeScolaireController::class, 'anneesScolaires']);
            Route::get('/annees-scolaires/{anneeId}/classes', [RecapitulatifAnneeScolaireController::class, 'classesParAnnee']);
            Route::get('/annees-scolaires/{anneeId}/classes/{classeId}', [RecapitulatifAnneeScolaireController::class, 'detailClasse']);
            Route::get('/annees-scolaires/{anneeId}/statistiques', [RecapitulatifAnneeScolaireController::class, 'statistiques']);
            Route::get('/annees-scolaires/{anneeId}/finance', [RecapitulatifAnneeScolaireController::class, 'finance']);
            Route::get('/annees-scolaires/{anneeId}/journal-caisse', [RecapitulatifAnneeScolaireController::class, 'journalCaisse']);
            Route::get('/annees-scolaires/{anneeId}/recherche-etudiant', [RecapitulatifAnneeScolaireController::class, 'rechercherEtudiant']);
        });

        // ─── INSCRIPTION ──────────────────────────────────────────────────────
        Route::prefix('inscription')->group(function () {

            Route::get('/cycles', [CycleController::class, 'index']);

            // Années scolaires (Lecture accessible aux caissiers)
            Route::get('/annees-scolaires', [AnneeScolaireController::class, 'index']);
            Route::get('/annee-scolaire', [AnneeScolaireController::class, 'index']); // Alias
            Route::get('/annees-scolaires/active', [AnneeScolaireController::class, 'getActive']);
            Route::get('/annee-scolaire/active', [AnneeScolaireController::class, 'getActive']); // Alias
            Route::get('/annees-scolaires/{id}', [AnneeScolaireController::class, 'show'])->whereNumber('id');
            Route::get('/annee-scolaire/{id}', [AnneeScolaireController::class, 'show'])->whereNumber('id'); // Alias

            // Types de frais (Lecture accessible aux caissiers)
            Route::get('/frais/types', [TypeFraisController::class, 'index']);
            Route::get('/frais/type', [TypeFraisController::class, 'index']); // Alias

            // Niveaux
            Route::get('/niveaux', [NiveauController::class, 'index']);
            Route::get('/niveaux/{cycle}', [NiveauController::class, 'getByCycle']);
            Route::post('/niveaux', [NiveauController::class, 'store']);
            Route::get('/niveaux/{id}', [NiveauController::class, 'show']);
            Route::put('/niveaux/{id}', [NiveauController::class, 'update']);
            Route::delete('/niveaux/{id}', [NiveauController::class, 'destroy']);

            // Classes
            Route::get('/classes', [ClasseController::class, 'index']);
            Route::get('/classes/par-nom', [ClasseController::class, 'getByNomNiveau']);
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
            Route::put('/{id}', [InscriptionController::class, 'update']);
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
        });



    /*
    |--------------------------------------------------------------------------
    | GESTION DES NOTES (ADMIN ET CAISSIER)
    |--------------------------------------------------------------------------
    */
    Route::middleware('role:admin,caissier')->group(function () {
        
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
            Route::get('/periodes', [NotesController::class, 'getPeriodes']);
            Route::get('/statistiques', [NotesController::class, 'getStatistiques']);
            Route::get('/activites-recentes', [NotesController::class, 'getActivitesRecentes']);

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

        // ─── GESTION FINANCIÈRE (FINANCE) ─────────────────────────────────────
        Route::prefix('finance')->group(function () {
            // Routes accessibles à l'Admin ET au Caissier
            Route::get('/categories', [FinanceController::class, 'getCategories']);
            Route::post('/entrees', [FinanceController::class, 'storeEntree']);
            Route::post('/sorties', [FinanceController::class, 'storeSortie']);

            // Routes réservées à l'ADMIN uniquement (Inventaire, Archivage)
            Route::middleware('role:admin')->group(function () {
                Route::get('/overview', [FinanceController::class, 'index']);
                Route::get('/historique', [FinanceController::class, 'getHistorique']);
                Route::post('/archiver/{anneeId}', [FinanceController::class, 'archiveYear']);
            });
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