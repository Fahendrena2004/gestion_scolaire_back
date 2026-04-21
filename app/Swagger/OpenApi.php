<?php

namespace App\Swagger;

use OpenApi\Attributes as OA;

#[OA\Info(
    title: 'Gestion Sco API',
    version: '1.0.0',
    description: 'Documentation API Laravel avec authentification Sanctum'
)]
#[OA\Server(
    url: 'http://localhost:8000',
    description: 'Serveur local'
)]
#[OA\SecurityScheme(
    securityScheme: 'bearerAuth',
    type: 'http',
    scheme: 'bearer',
    bearerFormat: 'Token'
)]
class OpenApi
{
    // la configuration globale Swagger
}
