<?php

namespace App\Http\Controllers\Inscription;

use App\Http\Controllers\Controller;
use App\Services\Inscription\ClasseService;
use Illuminate\Http\JsonResponse;

class CycleController extends Controller
{
    protected $classeService;

    public function __construct(ClasseService $classeService)
    {
        $this->classeService = $classeService;
   }
   // Endpoint pour récupérer les cycles disponibles, en vérifiant que des cycles sont définis dans le système
    public function index(): JsonResponse
    {
        $cycles = $this->classeService->getCycles();

        return response()->json([
            'success' => true,
            'data' => $cycles
        ]);
    }
}