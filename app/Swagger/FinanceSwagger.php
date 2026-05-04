<?php

namespace App\Swagger;

use OpenApi\Attributes as OA;

class FinanceSwagger
{
    #[OA\Get(
        path: '/api/finance/overview',
        tags: ['Gestion Financière'],
        summary: 'Récupérer le solde actuel (ADMIN UNIQUEMENT)',
        security: [['bearerAuth' => []]],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Aperçu financier récupéré avec succès',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(property: 'data', type: 'object', properties: [
                            new OA\Property(property: 'solde_actuel', type: 'number', format: 'float', example: 1500000.50),
                            new OA\Property(property: 'total_entrees', type: 'number', format: 'float', example: 2000000.00),
                            new OA\Property(property: 'total_sorties', type: 'number', format: 'float', example: 499999.50),
                            new OA\Property(property: 'annee_scolaire', type: 'string', example: '2025-2026')
                        ])
                    ]
                )
            ),
            new OA\Response(response: 401, description: 'Non authentifié'),
            new OA\Response(response: 404, description: 'Aucune année scolaire active')
        ]
    )]
    public function overview() {}

    #[OA\Get(
        path: '/api/finance/categories',
        tags: ['Gestion Financière'],
        summary: 'Lister les catégories d\'entrées et de sorties',
        security: [['bearerAuth' => []]],
        responses: [
            new OA\Response(response: 200, description: 'Catégories récupérées')
        ]
    )]
    public function categories() {}

    #[OA\Post(
        path: '/api/finance/entrees',
        tags: ['Gestion Financière'],
        summary: 'Enregistrer une nouvelle entrée d\'argent (Don, Subvention, etc.)',
        security: [['bearerAuth' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['montant', 'date_entree', 'type_entree_id'],
                properties: [
                    new OA\Property(property: 'montant', type: 'number', format: 'float', example: 500000),
                    new OA\Property(property: 'date_entree', type: 'string', format: 'date', example: '2026-05-04'),
                    new OA\Property(property: 'type_entree_id', type: 'integer', example: 5),
                    new OA\Property(property: 'donneur_id', type: 'integer', example: 1, nullable: true),
                    new OA\Property(property: 'inscription_id', type: 'integer', example: 10, nullable: true),
                    new OA\Property(property: 'description', type: 'string', example: 'Don généreux pour la bibliothèque')
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Entrée enregistrée et caisse mise à jour'),
            new OA\Response(response: 422, description: 'Erreur de validation')
        ]
    )]
    public function storeEntree() {}

    #[OA\Post(
        path: '/api/finance/sorties',
        tags: ['Gestion Financière'],
        summary: 'Enregistrer une sortie d\'argent (Processus en une seule étape)',
        description: 'Enregistre la sortie, met à jour le solde de la caisse et définit le statut à "paye". Si le type contient "Salaire", staff_id est obligatoire.',
        security: [['bearerAuth' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['montant', 'date_sortie', 'type_sortie_id'],
                properties: [
                    new OA\Property(property: 'montant', type: 'number', format: 'float', example: 100000),
                    new OA\Property(property: 'date_sortie', type: 'string', format: 'date', example: '2026-05-04'),
                    new OA\Property(property: 'type_sortie_id', type: 'integer', example: 2),
                    new OA\Property(property: 'staff_id', type: 'integer', example: 3, description: 'Obligatoire si type est Salaire'),
                    new OA\Property(property: 'description', type: 'string', example: 'Paiement salaire mensuel')
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Sortie enregistrée et payée, caisse mise à jour'),
            new OA\Response(response: 422, description: 'Erreur de validation ou solde insuffisant')
        ]
    )]
    public function storeSortie() {}

    #[OA\Get(
        path: '/api/finance/historique',
        tags: ['Gestion Financière'],
        summary: 'Consulter l\'historique global (ADMIN UNIQUEMENT)',
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'annee', in: 'query', description: 'Libellé de l\'année (ex: 2025-2026) ou "Tous"', required: false, schema: new OA\Schema(type: 'string', example: '2025-2026'))
        ],
        responses: [
            new OA\Response(response: 200, description: 'Historique récupéré')
        ]
    )]
    public function historique() {}

    #[OA\Post(
        path: '/api/finance/archiver/{anneeId}',
        tags: ['Gestion Financière'],
        summary: 'Archiver les données financières d\'une année scolaire',
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'anneeId', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))
        ],
        responses: [
            new OA\Response(response: 200, description: 'Données archivées avec succès'),
            new OA\Response(response: 403, description: 'Accès interdit (Admin requis)')
        ]
    )]
    public function archive() {}
}
