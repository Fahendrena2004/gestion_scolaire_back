<?php

namespace App\Http\Controllers\Inscription;

use App\Http\Controllers\Controller;
use App\Services\Inscription\ClasseService;
use Illuminate\Http\JsonResponse;

class ClasseController extends Controller
{
    protected $classeService;


    //
    public function __construct(ClasseService $classeService)
    {
        $this->classeService = $classeService;
    }

    // Endpoint pour récupérer les classes d'un niveau donné, en vérifiant que le niveau existe et qu'une année scolaire active est disponible
    public function index(int $niveauId): JsonResponse
    {
        $niveau = $this->classeService->getNiveau($niveauId);

        if (!$niveau) {
            return response()->json([
                'success' => false,
                'message' => 'Niveau non trouvé'
            ], 404);
        }

        if (!$this->classeService->hasAnneeActive()) {
            return response()->json([
                'success' => false,
                'message' => 'Aucune année scolaire active'
            ], 404);
        }

        // Récupérer les classes associées au niveau pour l'année scolaire active
        $classes = $this->classeService->getClassesByNiveau($niveauId);

        if ($classes->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => 'Aucune classe trouvée pour ce niveau'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $classes
        ]);
    }
}