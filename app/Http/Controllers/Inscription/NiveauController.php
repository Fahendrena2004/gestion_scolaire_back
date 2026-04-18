<?php

namespace App\Http\Controllers\Inscription;

use App\Http\Controllers\Controller;
use App\Services\Inscription\ClasseService;
use Illuminate\Http\JsonResponse;

class NiveauController extends Controller
{
    // Injecter le service ClasseService pour gérer la logique métier liée aux niveaux et aux cycles
    protected $classeService;

    public function __construct(ClasseService $classeService)
    {
        $this->classeService = $classeService;
    }

    // Endpoint pour récupérer les niveaux d'un cycle donné, en vérifiant que le cycle existe
    public function index(string $cycle): JsonResponse
    {
        // Vérifier que le cycle est valide
        $niveaux = $this->classeService->getNiveauxByCycle($cycle);

        if ($niveaux->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => 'Aucun niveau trouvé pour ce cycle'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $niveaux
        ]);
    }
}