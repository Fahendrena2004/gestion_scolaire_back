<?php

namespace App\Swagger;

use OpenApi\Attributes as OA;

#[OA\Info(
    title: 'Gestion Sco',
    version: '1.0.0',
    description: 'Documentation API Laravel'
)]
#[OA\Server(url: 'http://localhost:8000')]
#[OA\SecurityScheme(
    securityScheme: 'bearerAuth',
    type: 'http',
    scheme: 'bearer',
    bearerFormat: 'Sanctum'
)]
class OpenApi
{
    #[OA\Get(
        path: '/api/teste',
        tags: ['System'],
        responses: [
            new OA\Response(response: 200, description: 'OK'),
        ]
    )]
   

    public function notificationSend(): void
    {
    }
}
