<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Utilisateur;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

class RegisterController extends Controller
{
    public function register(Request $request)
    {
        
        $request->validate([
            'nom' => 'required|string|max:100',
            'prenom' => 'required|string|max:150',
            'telephone' => 'nullable|string|max:20',
            'email' => 'required|email|unique:utilisateurs,email',
            'password' => 'required|min:6|confirmed'
        ]);

        // 2. Création de l'utilisateur avec mot de passe haché
        $user = Utilisateur::create([
            'nom' => $request->nom,
            'prenom' => $request->prenom,
            'telephone' => $request->telephone,
            'email' => $request->email,
            'password' => $request->password,
            'role' => 'caissier',
            'status' => 'actif'
        ]);

        // 3. Connexion (Note: Auth::login est pour le Web. Pour une API pure, on génère souvent un token)
        Auth::login($user);

        return response()->json([
            'message' => 'Utilisateur cree avec succes',
            'user' => $user
        ], 201);
    }
}