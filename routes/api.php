<?php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\Gestion_note\BulletinController;
use App\Http\Controllers\Gestion_note\DetailBulletinController;
use App\Http\Controllers\Gestion_note\MatieresController;
use App\Http\Controllers\Gestion_note\NotesController;
use App\Http\Controllers\Inscription\AnneeScolaireController;
use App\Http\Controllers\Inscription\ClasseController;
use App\Http\Controllers\Inscription\CycleController;
use App\Http\Controllers\Inscription\FraisController;
use App\Http\Controllers\Inscription\InscriptionController;
use App\Http\Controllers\Inscription\NiveauController;
use App\Http\Controllers\Inscription\PaiementController;
use App\Http\Controllers\Inscription\TypeFraisController;
use App\Http\Controllers\Setup\InstallController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Routes publiques (non authentifiees)
|--------------------------------------------------------------------------
*/
Route::get('/teste', function () {
    return response()->json(['status' => 'ok', 'message' => 'API fonctionne']);
});

Route::post('/register', [RegisterController::class, 'register']);
Route::post('/login', [LoginController::class, 'login']);

Route::prefix('setup')->group(function () {
    Route::post('/annee-scolaire', [InstallController::class, 'createAnneeScolaire']);
    Route::post('/niveaux', [InstallController::class, 'createNiveaux']);
    Route::post('/classes', [InstallController::class, 'generateClasses']);
    Route::post('/frais', [InstallController::class, 'createTypeFrais']);
    Route::get('/status', [InstallController::class, 'getStatus']);
    Route::post('/reset', [InstallController::class, 'reset'])->middleware('auth:sanctum');
});

/*
|--------------------------------------------------------------------------
| Routes protegees par Sanctum (authentification requise)
|--------------------------------------------------------------------------
*/
Route::middleware(['auth:sanctum'])->group(function () {
    Route::get('/user', [LoginController::class, 'User']);
    Route::post('/logout', [LoginController::class, 'logout']);

    Route::prefix('inscription')->group(function () {
        Route::get('/cycles', [CycleController::class, 'index']);

        Route::get('/annee-scolaires', [AnneeScolaireController::class, 'index']);
        Route::get('/annee-scolaires/active', [AnneeScolaireController::class, 'getActive']);
        Route::post('/annee-scolaires', [AnneeScolaireController::class, 'store']);
        Route::get('/annee-scolaires/{id}', [AnneeScolaireController::class, 'show']);
        Route::put('/annee-scolaires/{id}', [AnneeScolaireController::class, 'update']);
        Route::delete('/annee-scolaires/{id}', [AnneeScolaireController::class, 'destroy']);

        Route::get('/niveaux', [NiveauController::class, 'index']);
        Route::get('/niveaux/{cycle}', [NiveauController::class, 'getByCycle'])
            ->where('cycle', 'primaire|college|lycee');
        Route::post('/niveaux', [NiveauController::class, 'store']);
        Route::get('/niveaux/{id}', [NiveauController::class, 'show'])->whereNumber('id');
        Route::put('/niveaux/{id}', [NiveauController::class, 'update'])->whereNumber('id');
        Route::delete('/niveaux/{id}', [NiveauController::class, 'destroy'])->whereNumber('id');

        Route::get('/classes', [ClasseController::class, 'index']);
        Route::get('/classes/niveau/{niveauId}', [ClasseController::class, 'getByNiveau']);
        Route::get('/classes/cycle/{cycle}', [ClasseController::class, 'getByCycle']);
        Route::post('/classes', [ClasseController::class, 'store']);
        Route::get('/classes/{id}', [ClasseController::class, 'show']);
        Route::put('/classes/{id}', [ClasseController::class, 'update']);
        Route::delete('/classes/{id}', [ClasseController::class, 'destroy']);

        Route::get('/frais/types', [TypeFraisController::class, 'index']);
        Route::post('/frais/types', [TypeFraisController::class, 'store']);
        Route::put('/frais/types/{id}', [TypeFraisController::class, 'update']);
        Route::delete('/frais/types/{id}', [TypeFraisController::class, 'destroy']);

        Route::get('/frais/calcul', [FraisController::class, 'calcul']);

        Route::get('/', [InscriptionController::class, 'index']);
        Route::post('/', [InscriptionController::class, 'store']);
        Route::get('/{id}', [InscriptionController::class, 'show']);
        Route::get('/{id}/infos-dynamiques', [InscriptionController::class, 'getDynamicInfos']);

        Route::get('/{inscriptionId}/paiements', [PaiementController::class, 'index']);
        Route::post('/{inscriptionId}/paiements', [PaiementController::class, 'store']);
        Route::get('/paiements/{id}', [PaiementController::class, 'show']);
        Route::delete('/paiements/{id}', [PaiementController::class, 'destroy']);
    });

    Route::prefix('matieres')->group(function () {
        Route::get('/', [MatieresController::class, 'index']);
        Route::post('/', [MatieresController::class, 'store']);
        Route::post('/multiple', [MatieresController::class, 'storeMultiple']);
        Route::get('/{id}', [MatieresController::class, 'show']);
        Route::put('/{id}', [MatieresController::class, 'update']);
        Route::delete('/{id}', [MatieresController::class, 'destroy']);
        Route::get('/suggestions/{cycle}', [MatieresController::class, 'suggestions']);
    });

    Route::prefix('notes')->group(function () {
        Route::get('/', [NotesController::class, 'index']);
        Route::post('/', [NotesController::class, 'store']);
        Route::get('/moyenne/{inscriptionId}/{periode}', [NotesController::class, 'getMoyenne']);
        Route::get('/{id}', [NotesController::class, 'show']);
        Route::put('/{id}', [NotesController::class, 'update']);
        Route::delete('/{id}', [NotesController::class, 'destroy']);
    });

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

    Route::prefix('detail-bulletins')->group(function () {
        Route::get('/bulletin/{bulletinId}', [DetailBulletinController::class, 'getByBulletin']);
        Route::put('/{id}', [DetailBulletinController::class, 'update']);
    });
});
