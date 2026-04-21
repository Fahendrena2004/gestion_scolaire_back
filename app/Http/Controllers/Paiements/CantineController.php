<?php

namespace App\Http\Controllers\Paiements;

use App\Http\Controllers\Controller;
use App\Services\Paiement\CantinePaiementService;
use Illuminate\Http\Request;

class CantineController extends Controller
{
    protected $cantineService;

    public function __construct(CantinePaiementService $cantineService)
    {
        $this->cantineService = $cantineService;
    }

    /**
     * 1. Marquer la présence (Cantinier)
     * POST /api/cantine/presence
     */
    public function marquerPresence(Request $request)
    {
        $request->validate([
            'inscription_id' => 'required|exists:inscriptions,id',
            'date' => 'required|date',
            'est_present' => 'required|boolean'
        ]);

        $result = $this->cantineService->marquerPresence(
            $request->inscription_id,
            $request->date,
            $request->est_present
        );

        return response()->json($result, $result['success'] ? 200 : 500);
    }

    /**
     * 2. Récupérer la liste des mois disponibles
     * GET /api/cantine/mois-disponibles/{inscriptionId}
     */
    public function getMoisDisponibles($inscriptionId)
    {
        $mois = $this->cantineService->getMoisDisponibles($inscriptionId);

        return response()->json([
            'success' => true,
            'data' => $mois
        ]);
    }

    /**
     * 3. Récupérer les jours d'un mois
     * GET /api/cantine/jours/{inscriptionId}?mois=9&annee=2025
     */
    public function getJours($inscriptionId, Request $request)
    {
        $mois = $request->query('mois');
        $annee = $request->query('annee');

        // Si mois et année non fournis, prendre le premier mois disponible
        if (!$mois || !$annee) {
            $moisDisponibles = $this->cantineService->getMoisDisponibles($inscriptionId);
            if (!empty($moisDisponibles)) {
                $premierMois = $moisDisponibles[0];
                $mois = $premierMois['mois'];
                $annee = $premierMois['annee'];
            }
        }

        $data = $this->cantineService->getJoursMois($inscriptionId, $mois, $annee);

        if (!$data) {
            return response()->json([
                'success' => false,
                'message' => 'Aucune donnée trouvée'
            ], 404);
        }

        if (isset($data['error'])) {
            return response()->json([
                'success' => false,
                'message' => $data['message']
            ], 400);
        }

        return response()->json([
            'success' => true,
            'data' => $data
        ]);
    }

    /**
     * 4. Payer des jours sélectionnés
     * POST /api/cantine/payer
     */
    public function payerJours(Request $request)
    {
        $request->validate([
            'inscription_id' => 'required|exists:inscriptions,id',
            'dates' => 'required|array|min:1',
            'dates.*' => 'required|date_format:Y-m-d'
        ]);

        $result = $this->cantineService->payerJours(
            $request->inscription_id,
            $request->dates
        );

        return response()->json($result, $result['success'] ? 200 : 422);
    }
}