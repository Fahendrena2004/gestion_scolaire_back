<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Utilisateur;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Auth\Events\PasswordReset;

class ResetpasswordController extends Controller
{
    /**
     * Display the password reset view.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Contracts\View\Factory|\Illuminate\View\View
     */
    public function showResetForm(Request $request)
    {
        return view('auth.reset-password')->with(
            ['email' => $request->email]
        );
    }

    /**
     * Send a reset code to the given user.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function sendResetLinkEmail(Request $request)
    {
        $request->validate(['email' => 'required|email']);

        $user = Utilisateur::where('email', $request->email)->first();

        if (!$user) {
            return response()->json(['error' => 'Utilisateur non trouvé'], 404);
        }

        // Générer un code à 6 chiffres
        $code = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        // Sauvegarder le code dans la table password_reset_tokens
        \DB::table('password_reset_tokens')->updateOrInsert(
            ['email' => $request->email],
            [
                'token' => \Hash::make($code),
                'created_at' => now()
            ]
        );

        // Envoyer la notification
        $user->notify(new \App\Notifications\ResetPasswordNotification($code));

        return response()->json(['message' => 'Code de vérification envoyé à votre adresse email.'], 200);
    }

    /**
     * Reset the given user's password using the verification code.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\RedirectResponse|\Illuminate\Http\JsonResponse
     */
    public function reset(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'code' => 'required|string|size:6',
            'password' => 'required|confirmed|min:8',
        ]);

        $record = \DB::table('password_reset_tokens')->where('email', $request->email)->first();

        if (!$record || \Carbon\Carbon::parse($record->created_at)->addMinutes(config('auth.passwords.users.expire'))->isPast()) {
            return response()->json(['error' => 'Code expiré ou invalide.'], 400);
        }

        if (!\Hash::check($request->code, $record->token)) {
            return response()->json(['error' => 'Code de vérification incorrect.'], 400);
        }

        $user = Utilisateur::where('email', $request->email)->first();
        
        if (!$user) {
            return response()->json(['error' => 'Utilisateur introuvable.'], 404);
        }

        $user->password = $request->password;
        $user->save();

        // Supprimer le code utilisé
        \DB::table('password_reset_tokens')->where('email', $request->email)->delete();

        if ($request->wantsJson()) {
            return response()->json(['message' => 'Votre mot de passe a été réinitialisé avec succès.'], 200);
        }

        return view('auth.reset-success');
    }
}
