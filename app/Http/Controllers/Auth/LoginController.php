<?php

namespace App\Http\Controllers\Auth;

use App\Models\Utilisateur;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;


class LoginController extends Controller
{
    //
        /*
            fonction responsable de la connexion de l'utilisateur
        */
        public function login(Request $request)
        {
            //Validation des données d'entrée
            $validator = Validator::make($request->all(), [
                'email' => 'required|email',
                'password' => 'required|string|min:6',
            ]
            );

            //Validation des données d'entrée
            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Erreur de validation',
                    'errors' => $validator->errors()
                ], 422);
            }

            /*
                Rechercher l'utilisateur par email
            */
            $user = Utilisateur::where('email', $request->email)->first();


            /*
                Vérifier si l'utilisateur existe
            */
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'Authentification échouée',
                    'errors' => [
                        'email' => 'Cet email n\'existe pas dans notre système'
                    ]
                ], 401);
            }


            /*
                Vérifier le mot de passe
            */
            if (!Hash::check($request->password, $user->password)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Mot de passe incorrect'
                ], 401);
            }


         /*
            Authentifier l'utilisateur et créer une session
        */
        Auth::login($user, $request->filled('remember'));


        //Sauvegarder le fichier Session
        session()->save();


        // RÉCUPÉRER LE PAYLOAD
        $payload = base64_encode(serialize(session()->all()));

        //  RÉCUPÉRER L'ID DE LA SESSION
        $sessionId = session()->getId();

        /*
        METTRE À JOUR LA SESSION
        */
        try {
            // Vérifier si la session existe
            $sessionExists = DB::table('sessions')->where('id', $sessionId)->exists();

            if ($sessionExists) {

                // Mettre à jour la session existante
                $updated = DB::table('sessions')
                    ->where('id', $sessionId)
                    ->update([
                        'user_id' => $user->id,
                        'ip_address' => $request->ip(),
                        'user_agent' => $request->userAgent(),
                        'last_activity' => time(),
                    ]);

            } else {
                // Créer une nouvelle session si elle n'existe pas
                DB::table('sessions')->insert([
                    'id' => $sessionId,
                    'user_id' => $user->id,
                    'ip_address' => $request->ip(),
                    'user_agent' => $request->userAgent(),
                    'payload' => $payload,
                    'last_activity' => time(),
                ]);

            }
        } catch (\Exception $e) {
            Log::error('Erreur session: ' . $e->getMessage());
        }




        //RÉPONSE JSON DE SUCCÈS (AJOUTER LES DÉTAILS DE L'UTILISATEUR ET LA SESSION)
        return response()->json([
            'success' => true,
            'message' => 'Connexion réussie',
            'user' => [
                'id' => $user->id,
                'nom' => $user->nom,
                'prenom' => $user->prenom,
                'email' => $user->email,
                'role' => $user->role,
                'status' => $user->status
            ],
            'session_id' => $sessionId
        ], 200);


        }

        /*
            fonction responsable de la deconnexion de l'utilisateur
        */
        public function logout(Request $request)
        {
            try {


                // Récupérer les infos
                $userId = Auth::id();
                $sessionId = session()->getId();
                $userEmail = Auth::user() ? Auth::user()->email : null;

                // Supprimer de la base de données session
                if ($sessionId) {
                    DB::table('sessions')->where('id', $sessionId)->delete();
                }

                //  Déconnecter
                Auth::logout();



                return response()->json([
                    'success' => true,
                    'message' => 'Déconnexion réussie',
                    'data' => [
                        'user_id' => $userId,
                        'user_email' => $userEmail,
                        'session_id' => $sessionId
                    ]
                ], 200);

            } catch (\Exception $e) {
                Log::error('Erreur déconnexion: ' . $e->getMessage());
                return response()->json([
                    'success' => false,
                    'message' => 'Erreur lors de la déconnexion',
                    'error' => $e->getMessage()
                ], 500);
            }
        }


}
