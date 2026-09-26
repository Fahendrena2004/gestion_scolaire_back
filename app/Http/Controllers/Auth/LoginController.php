<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Utilisateur;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Laravel\Sanctum\PersonalAccessToken;

class LoginController extends Controller
{
    /*
        fonction responsable de la connexion de l'utilisateur
    */
    public function login(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email',
            'password' => 'required|string|min:6',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur de validation',
                'errors' => $validator->errors(),
            ], 422);
        }

        $user = Utilisateur::where('email', $request->email)->first();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Authentification echouee',
                'errors' => [
                    'email' => 'Cet email n\'existe pas dans notre systeme',
                ],
            ], 401);
        }

        if (!Hash::check($request->password, $user->password)) {
            return response()->json([
                'success' => false,
                'message' => 'Mot de passe incorrect',
            ], 401);
        }

        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'success' => true,
            'message' => 'Connexion reussie',
            'token' => $token,
            'token_type' => 'Bearer',
            'user' => [
                'id' => $user->id,
                'nom' => $user->nom,
                'prenom' => $user->prenom,
                'email' => $user->email,
                'telephone' => $user->telephone,
                'role' => $user->role,
                'status' => $user->status,
            ],
        ], 200);
    }

    /*
        fonction responsable de la recuperation de l'utilisateur connecte
    */
    public function User(Request $request)
    {
        $user = $request->user();

        return response()->json([
            'success' => true,
            'message' => 'Utilisateur connecte recupere avec succes',
            'user' => [
                'id' => $user->id,
                'nom' => $user->nom,
                'prenom' => $user->prenom,
                'email' => $user->email,
                'telephone' => $user->telephone,
                'role' => $user->role,
                'status' => $user->status,
                'full_name' => $user->full_name,
            ],
        ], 200);
    }

    /*
        fonction responsable de la deconnexion de l'utilisateur
    */
    public function logout(Request $request)
    {
        try {
            $user = $request->user();
            $userId = $user?->id;
            $userEmail = $user?->email;

            $accessToken = $user?->currentAccessToken();

            if ($accessToken instanceof PersonalAccessToken) {
                $accessToken->delete();
            }

            return response()->json([
                'success' => true,
                'message' => 'Deconnexion reussie',
                'data' => [
                    'user_id' => $userId,
                    'user_email' => $userEmail,
                ],
            ], 200);
        } catch (\Exception $e) {
            Log::error('Erreur deconnexion: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la deconnexion',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
