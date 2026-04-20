<?php

namespace App\Http\Controllers\Inscription;

use App\Http\Controllers\Controller;
use App\Services\Inscription\FraisService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class FraisController extends Controller
{
    /// Injecter le service FraisService pour gérer la logique métier liée aux frais d'inscription
    protected $fraisService;


    ///     * Constructeur pour injecter le service FraisService


    public function __construct(FraisService $fraisService)
    {
        $this->fraisService = $fraisService;
    }

    /// Endpoint pour calculer le montant total des frais d'inscription en fonction du cycle et des options sélectionnées
    public function calcul(Request $request): JsonResponse
    {
        $request->validate([
            'cycle' => 'required|string|in:primaire,college,lycee',
            'parascolaire' => 'boolean',
            'cantine' => 'boolean',
        ]);

        $result = $this->fraisService->calculerMontantTotal(
            $request->cycle,
            [
                'parascolaire' => $request->has('parascolaire'),
                'cantine' => $request->has('cantine'),
            ]
        );

        return response()->json([
            'success' => true,
            'data' => $result
        ]);
    }
}