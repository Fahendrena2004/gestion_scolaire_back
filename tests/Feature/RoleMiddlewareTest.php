<?php

use App\Models\Utilisateur;
use Illuminate\Support\Facades\Route;
use Laravel\Sanctum\Sanctum;

function creerUtilisateur(string $role): Utilisateur
{
    $utilisateur = new Utilisateur([
        'nom' => ucfirst($role),
        'prenom' => 'Test',
        'telephone' => '0340000000',
        'email' => $role . '@test.local',
        'password' => 'password123',
        'role' => $role,
        'status' => 'actif',
    ]);

    $utilisateur->id = $role === 'admin' ? 1 : 2;

    return $utilisateur;
}

beforeEach(function () {
    Route::middleware(['auth:sanctum', 'role:admin'])->get('/api/test-admin-role', function () {
        return response()->json(['ok' => true]);
    });

    Route::middleware(['auth:sanctum', 'role:admin,caissier'])->get('/api/test-shared-role', function () {
        return response()->json(['ok' => true]);
    });
});

it('refuse une route admin a un caissier', function () {
    $caissier = creerUtilisateur('caissier');

    Sanctum::actingAs($caissier);

    $this->getJson('/api/test-admin-role')
        ->assertForbidden()
        ->assertJson([
            'success' => false,
            'user_role' => 'caissier',
        ]);
});

it('autorise un admin sur une route admin', function () {
    $admin = creerUtilisateur('admin');

    Sanctum::actingAs($admin);

    $this->getJson('/api/test-admin-role')
        ->assertOk()
        ->assertJson([
            'ok' => true,
        ]);
});

it('autorise un caissier sur une route partagee admin et caissier', function () {
    $caissier = creerUtilisateur('caissier');

    Sanctum::actingAs($caissier);

    $this->getJson('/api/test-shared-role')
        ->assertOk()
        ->assertJson([
            'ok' => true,
        ]);
});
