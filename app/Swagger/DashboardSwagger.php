<?php

namespace App\Swagger;

use OpenApi\Attributes as OA;

class DashboardSwagger
{
    #[OA\Get(
        path: "/api/caissier/dashboard",
        tags: ["Dashboard"],
        summary: "Tableau de bord du caissier",
        security: [["bearerAuth" => []]],
        responses: [
            new OA\Response(response: 200, description: "Succès"),
            new OA\Response(response: 401, description: "Non authentifié")
        ]
    )]
    public function dashboard() {}
}
