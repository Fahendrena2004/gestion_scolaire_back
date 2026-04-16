<?php

namespace App\Swagger;

use OpenApi\Attributes as OA;

#[OA\Info(
    title: 'Gestion Sco API',
    version: '1.0.0',
    description: 'Documentation API Laravel - Auth session-based'
)]

#[OA\Server(
    url: 'http://localhost:8000',
    description: 'Serveur local'
)]

class OpenApi
{
    // Ce fichier est uniquement pour la configuration globale Swagger
}