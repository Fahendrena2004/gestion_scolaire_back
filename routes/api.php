<?php

use App\Http\Controllers\Auth\LoginController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\Gestion_note\GestionNoteController;

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

Route::prefix('notes')->group(function () {
    Route::get('/', [GestionNoteController::class, 'index']);
    Route::post('/', [GestionNoteController::class, 'create']);
    Route::get('/{id}', [GestionNoteController::class, 'show']);
    Route::put('/{id}', [GestionNoteController::class, 'update']);
    Route::delete('/{id}', [GestionNoteController::class, 'destroy']);
    
    // Routes supplémentaires
    Route::get('/par-matiere', [GestionNoteController::class, 'notesParMatiere']);
    Route::get('/statistiques/classe', [GestionNoteController::class, 'statistiquesClasse']);
});