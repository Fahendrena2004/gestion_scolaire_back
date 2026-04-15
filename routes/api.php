<?php

use App\Http\Controllers\Auth\LoginController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');
Route::get('/teste', function () {
    return response()->json([
        'status' => 'ok',
    ]);
});

// Routes pour Authentification
Route::post('/login', [LoginController::class, 'login']);

