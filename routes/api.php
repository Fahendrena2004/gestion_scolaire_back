<?php

use App\Http\Controllers\Auth\LoginController;
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

<<<<<<< HEAD
//Routes pour l'inscription utilisateur
Route::post('/register', [RegisterController::class, 'register']);

=======
>>>>>>> 0d718204741f11a6f4f8f62f054cce3db069840d
// Routes pour Authentification
Route::post('/register', [RegisterController::class, 'register']);
Route::post('/login', [LoginController::class, 'login']);
Route::post('/logout', [LoginController::class, 'logout']);