<?php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Inscription\ClasseController;
use App\Http\Controllers\Inscription\CycleController;
use App\Http\Controllers\Inscription\FraisController;
use App\Http\Controllers\Inscription\InscriptionController;
use App\Http\Controllers\Inscription\NiveauController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Auth\RegisterController;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::get('/teste', function () {
    return response()->json([
        'status' => 'ok',
    ]);
});

// Routes pour Authentification
Route::post('/register', [RegisterController::class, 'register']);
Route::post('/login', [LoginController::class, 'login']);
Route::post('/logout', [LoginController::class, 'logout']);


//Route pour faire l'inscription de l'eleve
Route::prefix('inscription')->group(function () {

    // Étape 1: Récupérer tous les cycles (primaire, college, lycee)
    Route::get('/cycles', [CycleController::class, 'index']);

    // Étape 2: Récupérer les niveaux par cycle
    Route::get('/niveaux/{cycle}', [NiveauController::class, 'index']);

    // Étape 3: Récupérer les classes par niveau
    Route::get('/classes/{niveauId}', [ClasseController::class, 'index']);

    // Étape 4: Calculer les frais (aperçu avant soumission)
    Route::get('/frais/calcul', [FraisController::class, 'calcul']);

    // Étape 5: Soumettre l'inscription complète (POST)
    Route::post('/', [InscriptionController::class, 'store']);

    // Étape 6: Voir les détails d'une inscription
    Route::get('/{id}', [InscriptionController::class, 'show']);

    // Extra: Récupérer les infos dynamiques d'un élève
    Route::get('/{id}/infos-dynamiques', [InscriptionController::class, 'getDynamicInfos']);
});
