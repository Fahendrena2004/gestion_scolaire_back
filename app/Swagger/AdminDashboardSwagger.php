<?php

namespace App\Swagger;

use OpenApi\Attributes as OA;

class AdminDashboardSwagger
{
    #[OA\Get(
        path: "/api/admin/dashboard/stats",
        tags: ["Admin - Dashboard"],
        summary: "Tableau de bord de l'administrateur",
        description: "Retourne le total des entrees (prix encaissement), sorties (decaissement), solde actuel, nombre de transactions, evolution mensuelle, repartition par cycle et activites recentes.",
        security: [["bearerAuth" => []]],
        responses: [
            new OA\Response(
                response: 200,
                description: "Statistiques recuperees avec succes",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "success", type: "boolean", example: true),
                        new OA\Property(
                            property: "data",
                            type: "object",
                            properties: [
                                new OA\Property(
                                    property: "stats_globales",
                                    type: "object",
                                    properties: [
                                        new OA\Property(property: "total_entree", type: "number", description: "Total prix entree / encaissement", example: 1500000),
                                        new OA\Property(property: "total_sortie", type: "number", description: "Total sortie / decaissement", example: 500000),
                                        new OA\Property(property: "solde_actuel", type: "number", description: "Solde actuel", example: 1000000),
                                        new OA\Property(property: "nombre_transactions", type: "integer", description: "Nombre transactions effectuees", example: 42)
                                    ]
                                ),
                                new OA\Property(
                                    property: "evolution_mensuelle",
                                    type: "array",
                                    items: new OA\Items(type: "object"),
                                    description: "Suivi de l'evolution financiere sur les 6 derniers mois"
                                ),
                                new OA\Property(
                                    property: "repartition_cycle",
                                    type: "array",
                                    items: new OA\Items(type: "object"),
                                    description: "Nombre de repartition par cycle"
                                ),
                                new OA\Property(
                                    property: "activites_recentes",
                                    type: "array",
                                    items: new OA\Items(type: "object"),
                                    description: "Activites recentes (paie frais, salaire)"
                                ),
                                new OA\Property(property: "annee_scolaire", type: "string", example: "2023-2024")
                            ]
                        )
                    ]
                )
            ),
            new OA\Response(response: 401, description: "Non authentifie"),
            new OA\Response(response: 403, description: "Acces reserve a l admin")
        ]
    )]
    public function adminDashboardStats() {}
}
