<?php

namespace App\Http\Controllers;

use App\Models\Notification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class NotificationController extends Controller
{
    /**
     * Display a listing of notifications for the current user.
     */
    public function index()
    {
        $user = Auth::user();

        // Self-healing: Fix old broken links
        Notification::where('link', 'like', '%/admin/etudiant/%')
            ->get()
            ->each(function ($n) {
                // Link format is typically: /admin/etudiant/{id}/paiements
                $parts = explode('/', trim($n->link, '/'));
                // parts[0]=admin, parts[1]=etudiant, parts[2]=id, parts[3]=paiements
                if (isset($parts[2]) && $parts[1] === 'etudiant') {
                    $id = $parts[2];
                    $n->link = "/caissier/paiement?student_id={$id}";
                    $n->save();
                }
            });
        
        // Return notifications for this user OR general admin notifications (user_id is null)
        $notifications = Notification::where(function($query) use ($user) {
                $query->where('user_id', $user->id)
                      ->orWhereNull('user_id');
            })
            ->orderBy('created_at', 'desc')
            ->limit(20)
            ->get();

        return response()->json([
            'success' => true,
            'data' => $notifications,
            'unread_count' => Notification::where(function($query) use ($user) {
                    $query->where('user_id', $user->id)
                          ->orWhereNull('user_id');
                })
                ->whereNull('read_at')
                ->count()
        ]);
    }

    /**
     * Mark a notification as read.
     */
    public function markAsRead($id)
    {
        $notification = Notification::findOrFail($id);
        
        // Ensure user can only mark their own or general notifications
        if ($notification->user_id && $notification->user_id !== Auth::id()) {
            return response()->json(['success' => false, 'message' => 'Non autorisé'], 403);
        }

        $notification->update(['read_at' => now()]);

        return response()->json([
            'success' => true,
            'message' => 'Notification marquée comme lue'
        ]);
    }

    /**
     * Mark all notifications as read for the current user.
     */
    public function markAllAsRead()
    {
        $user = Auth::user();

        Notification::where(function($query) use ($user) {
                $query->where('user_id', $user->id)
                      ->orWhereNull('user_id');
            })
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        return response()->json([
            'success' => true,
            'message' => 'Toutes les notifications sont marquées comme lues'
        ]);
    }

    /**
     * Static helper to create a notification easily from other controllers
     */
    public static function push($titre, $message, $type = 'info', $userId = null, $link = null)
    {
        return Notification::create([
            'titre' => $titre,
            'message' => $message,
            'type' => $type,
            'user_id' => $userId,
            'link' => $link
        ]);
    }

    /**
     * Check for late payments and push notifications
     */
    public static function checkLatePayments()
    {
        $lateInscriptions = \App\Models\Paiement\ResumePaiement::with('inscription.eleve')
            ->where('total_restant', '>', 0)
            ->whereHas('inscription', function($q) {
                $q->whereHas('anneeScolaire', function($sq) {
                    $sq->where('statut', 'en_cours');
                });
            })
            ->limit(5)
            ->get();

        foreach ($lateInscriptions as $resume) {
            $eleve = $resume->inscription->eleve;
            $message = "Retard de paiement détecté : {$eleve->nom} {$eleve->prenom} (Reste: " . number_format($resume->total_restant, 0, ',', ' ') . " Ar)";
            
            // Avoid duplicates
            $exists = Notification::where('message', $message)
                ->whereNull('read_at')
                ->exists();

            if (!$exists) {
                self::push("Retard de Paiement", $message, 'warning', null, "/caissier/paiement?student_id={$resume->inscription_id}");
            }
        }
    }
}
