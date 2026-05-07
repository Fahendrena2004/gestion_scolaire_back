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
        try {

            // 1. Validation des données d'entrée
            $request->validate([
                'nom' => 'required|string|max:100',
                'prenom' => 'required|string|max:150',
                'telephone' => 'nullable|string|max:20',
                'email' => 'required|email|unique:utilisateurs,email',
                'password' => 'required|min:6|confirmed',
                'role' => 'required|in:caissier,admin'
            ],[
                "email.unique" => "Cet email est déjà utilisé.",
                "role.in" => "Le rôle doit être soit 'caissier' soit 'admin'.",
                "password.min" => "Le mot de passe doit comporter au moins 6 caractères.",
                "password.confirmed" => "Le champ de confirmation du mot de passe ne correspond pas."
            ]);


            // 2. Création de l'utilisateur avec mot de passe haché
            $user = Utilisateur::create([
                'nom' => $request->nom,
                'prenom' => $request->prenom,
                'telephone' => $request->telephone,
                'email' => $request->email,
                'password' => $request->password, // Le modèle Utilisateur gère le hachage
                'role' => $request->role,
                'status' => 'actif'
            ]);


            // 3. Connexion (Note: Auth::login est pour le Web. Pour une API pure, on génère souvent un token)
            Auth::login($user);

            return response()->json([
                'message' => 'Utilisateur cree avec succes',
                'user' => $user
            ], 201);


            // 4. Gestion des exceptions
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
