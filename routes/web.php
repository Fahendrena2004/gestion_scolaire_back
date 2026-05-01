<?php


use Illuminate\Support\Facades\Route;

use App\Http\Controllers\Auth\ResetpasswordController;

Route::get('/', function () {
    return view('welcome');
});

Route::get('password/reset', [ResetpasswordController::class, 'showResetForm'])->name('password.reset');
Route::post('password/reset', [ResetpasswordController::class, 'reset'])->name('password.update');
