<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Staff;
use App\Models\StaffPointage;
use App\Http\Controllers\NotificationController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class StaffPointageController extends Controller
{
    /**
     * Get pointages for a specific date (defaults to today)
     */
    public function index(Request $request)
    {
        $date = $request->query('date', now()->toDateString());

        $pointages = StaffPointage::with('staff')
            ->whereDate('date_pointage', $date)
            ->get();

        return response()->json([
            'success' => true,
            'data' => $pointages
        ]);
    }

    /**
     * Mark presence/pointage for a staff member
     */
    public function store(Request $request)
    {
        $request->validate([
            'staff_id' => 'required|exists:staffs,id',
            'date_pointage' => 'required|date',
            'heure_entree' => 'nullable',
            'statut' => 'required|in:present,retard,absent',
            'commentaire' => 'nullable|string'
        ]);

        $pointage = StaffPointage::updateOrCreate(
            [
                'staff_id' => $request->staff_id,
                'date_pointage' => $request->date_pointage,
            ],
            [
                'heure_entree' => $request->heure_entree ?? now()->toTimeString(),
                'statut' => $request->statut,
                'commentaire' => $request->commentaire,
                'created_by' => Auth::id()
            ]
        );

        // Notification de pointage
        $staff = Staff::find($request->staff_id);
        NotificationController::push(
            "Pointage professeur : {$staff->nom} {$staff->prenom} marqué comme {$request->statut} à " . ($request->heure_entree ?? now()->format('H:i')),
            $request->statut === 'absent' ? 'warning' : 'info',
            null, // Pour tous les admins
            "/admin/staff"
        );

        return response()->json([
            'success' => true,
            'message' => 'Pointage enregistré avec succès',
            'data' => $pointage
        ]);
    }

    /**
     * Get history for a specific staff member
     */
    public function history($staffId)
    {
        $history = StaffPointage::where('staff_id', $staffId)
            ->orderBy('date_pointage', 'desc')
            ->limit(30)
            ->get();

        return response()->json([
            'success' => true,
            'data' => $history
        ]);
    }
}
