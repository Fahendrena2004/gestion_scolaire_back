<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Utilisateur;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class UtilisateurController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $query = Utilisateur::query();

        // Filtrage par rôle
        if ($request->filled('role')) {
            $query->where('role', $request->role);
        }

        // Filtrage par statut
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        // Recherche par nom ou email
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('nom', 'like', "%{$search}%")
                  ->orWhere('prenom', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%");
            });
        }

        $utilisateurs = $query->orderBy('created_at', 'desc')->get();

        return response()->json([
            'success' => true,
            'data' => $utilisateurs,
            'count' => $utilisateurs->count(),
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validator = $request->validate([
            'nom' => 'required|string|max:100',
            'prenom' => 'required|string|max:100',
            'telephone' => 'nullable|string|max:20',
            'email' => 'required|email|unique:utilisateurs,email',
            'password' => 'required|string|min:6|max:255|regex:/[a-zA-Z]/|regex:/[0-9]/',
            'role' => ['required', Rule::in(['admin', 'caissier', 'professeur', 'secretaire'])],
            'status' => ['required', Rule::in(['actif', 'inactif'])],
        ]);

        $utilisateur = Utilisateur::create([
            'nom' => $validator['nom'],
            'prenom' => $validator['prenom'],
            'telephone' => $validator['telephone'] ?? null,
            'email' => $validator['email'],
            'password' => $validator['password'], // Le modèle Utilisateur gère le hachage
            'role' => $validator['role'],
            'status' => $validator['status'],
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Utilisateur créé avec succès',
            'data' => $utilisateur,
        ], 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        $utilisateur = Utilisateur::findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => $utilisateur,
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $utilisateur = Utilisateur::findOrFail($id);

        $validator = $request->validate([
            'nom' => 'sometimes|string|max:100',
            'prenom' => 'sometimes|string|max:100',
            'telephone' => 'nullable|string|max:20',
            'email' => ['sometimes', 'email', Rule::unique('utilisateurs')->ignore($utilisateur->id)],
            'password' => 'sometimes|string|min:6|max:255|regex:/[a-zA-Z]/|regex:/[0-9]/',
            'role' => ['sometimes', Rule::in(['admin', 'caissier', 'professeur', 'secretaire'])],
            'status' => ['sometimes', Rule::in(['actif', 'inactif'])],
        ]);

        // Mettre à jour uniquement les champs fournis
        $utilisateur->fill($validator);

        if (isset($validator['password'])) {
            $utilisateur->password = $validator['password']; // Le modèle Utilisateur gère le hachage
        }

        $utilisateur->save();

        return response()->json([
            'success' => true,
            'message' => 'Utilisateur mis à jour avec succès',
            'data' => $utilisateur,
        ]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $utilisateur = Utilisateur::findOrFail($id);

        // Empêcher la suppression de son propre compte
        $utilisateurId = (int) $utilisateur->id;
        $currentUserId = Auth::id();

        if ($utilisateurId === $currentUserId) {
            return response()->json([
                'success' => false,
                'message' => 'Vous ne pouvez pas supprimer votre propre compte',
            ], 403);
        }

        $utilisateur->delete();

        return response()->json([
            'success' => true,
            'message' => 'Utilisateur supprimé avec succès',
        ]);
    }

    /**
     * Obtenir les statistiques des utilisateurs
     */
    public function statistiques()
    {
        $stats = [
            'total' => Utilisateur::count(),
            'actifs' => Utilisateur::where('status', 'actif')->count(),
            'inactifs' => Utilisateur::where('status', 'inactif')->count(),
            'par_role' => Utilisateur::select('role')
                ->selectRaw('COUNT(*) as total')
                ->groupBy('role')
                ->pluck('total', 'role'),
        ];

        return response()->json([
            'success' => true,
            'data' => $stats,
        ]);
    }

    /**
     * Envoyer un code de vérification par email
     */
    public function sendVerificationCode(Request $request)
    {
        $request->validate([
            'email' => 'required|email|unique:utilisateurs,email',
        ]);

        // Générer un code à 6 chiffres
        $code = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        // Sauvegarder dans password_reset_tokens (on peut réutiliser cette table pour les vérifs)
        \DB::table('password_reset_tokens')->updateOrInsert(
            ['email' => $request->email],
            [
                'token' => \Hash::make($code),
                'created_at' => now()
            ]
        );

        // Envoyer la notification (on peut réutiliser ResetPasswordNotification ou en créer une spécifique)
        // Pour l'instant on utilise ResetPasswordNotification avec un message générique
        $notifiable = new \stdClass();
        $notifiable->email = $request->email;
        $notifiable->nom = "Utilisateur";
        
        \Notification::route('mail', $request->email)
            ->notify(new \App\Notifications\EmailVerificationNotification($code));

        return response()->json(['message' => 'Code de vérification envoyé.'], 200);
    }

    /**
     * Vérifier le code PIN
     */
    public function verifyCode(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'code' => 'required|string|size:6',
        ]);

        $record = \DB::table('password_reset_tokens')->where('email', $request->email)->first();

        if (!$record || \Carbon\Carbon::parse($record->created_at)->addMinutes(15)->isPast()) {
            return response()->json(['error' => 'Code expiré ou invalide.'], 400);
        }

        if (!\Hash::check($request->code, $record->token)) {
            return response()->json(['error' => 'Code de vérification incorrect.'], 400);
        }

        return response()->json(['success' => true, 'message' => 'Email vérifié avec succès.'], 200);
    }
}
