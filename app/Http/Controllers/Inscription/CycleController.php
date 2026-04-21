<?php

namespace App\Http\Controllers\Inscription;

use App\Http\Controllers\Controller;

class CycleController extends Controller
{
    public function index()
    {
        $cycles = ['primaire', 'college', 'lycee'];
        
        return response()->json([
            'success' => true,
            'data' => $cycles
        ]);
    }
}