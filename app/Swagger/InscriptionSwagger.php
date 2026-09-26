<?php

namespace App\Swagger;

use OpenApi\Attributes as OA;

class InscriptionSwagger
{
    #[OA\Get(
        path: '/api/inscription/cycles',
        security: [['bearerAuth' => []]],
        tags: ['Cycles'],
        summary: 'Recuperer tous les cycles',
        responses: [new OA\Response(response: 200, description: 'Succes')]
    )]
    public function cycles() {}

    #[OA\Get(
        path: '/api/inscription/niveaux/{cycle}',
        security: [['bearerAuth' => []]],
        tags: ['Cycles'],
        summary: 'Recuperer les niveaux par cycle',
        parameters: [
            new OA\Parameter(name: 'cycle', in: 'path', required: true, schema: new OA\Schema(type: 'string', example: 'college')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Succes'),
            new OA\Response(response: 404, description: 'Cycle non trouve'),
        ]
    )]
    public function niveaux() {}

    #[OA\Get(
        path: '/api/inscription/classes/niveau/{niveauId}',
        security: [['bearerAuth' => []]],
        tags: ['Cycles'],
        summary: 'Recuperer les classes par niveau',
        parameters: [
            new OA\Parameter(name: 'niveauId', in: 'path', required: true, schema: new OA\Schema(type: 'integer', example: 6)),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Succes'),
            new OA\Response(response: 404, description: 'Niveau non trouve'),
        ]
    )]
    public function classes() {}

    #[OA\Get(
        path: '/api/inscription/frais/calcul',
        security: [['bearerAuth' => []]],
        tags: ['Frais'],
        summary: 'Calculer les frais de l annee scolaire',
        parameters: [
            new OA\Parameter(name: 'cycle', in: 'query', required: true, schema: new OA\Schema(type: 'string', example: 'college')),
            new OA\Parameter(name: 'annee_scolaire_id', in: 'query', required: false, schema: new OA\Schema(type: 'integer', example: 2)),
            new OA\Parameter(name: 'parascolaire', in: 'query', required: false, schema: new OA\Schema(type: 'boolean', example: true)),
            new OA\Parameter(name: 'cantine', in: 'query', required: false, schema: new OA\Schema(type: 'boolean', example: false)),
        ],
        responses: [new OA\Response(response: 200, description: 'Succes')]
    )]
    public function calculFrais() {}

    #[OA\Post(
        path: '/api/inscription',
        security: [['bearerAuth' => []]],
        tags: ['Inscription'],
        summary: 'Soumettre une inscription',
        description: 'L inscription utilise les frais configures pour l annee scolaire en cours, avec fallback sur les frais globaux si besoin.',
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['nom', 'prenom', 'date_naissance', 'lieu_naissance', 'sexe', 'niveau_id', 'classe_id'],
                properties: [
                    new OA\Property(property: 'nom', type: 'string', example: 'Rakoto'),
                    new OA\Property(property: 'prenom', type: 'string', example: 'Fara'),
                    new OA\Property(property: 'date_naissance', type: 'string', format: 'date', example: '2015-03-20'),
                    new OA\Property(property: 'lieu_naissance', type: 'string', example: 'Antananarivo'),
                    new OA\Property(property: 'sexe', type: 'string', enum: ['M', 'F'], example: 'F'),
                    new OA\Property(property: 'adresse', type: 'string', nullable: true, example: 'Lot IV 123 Bis'),
                    new OA\Property(property: 'niveau_id', type: 'integer', example: 6),
                    new OA\Property(property: 'classe_id', type: 'integer', example: 12),
                    new OA\Property(property: 'parascolaire', type: 'boolean', example: true),
                    new OA\Property(property: 'cantine', type: 'boolean', example: false),
                    new OA\Property(property: 'montant_verse', type: 'integer', example: 200000),
                    new OA\Property(property: 'responsable_nom', type: 'string', example: 'Rakoto Jean'),
                    new OA\Property(property: 'responsable_telephone', type: 'string', example: '0321234567'),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 201, description: 'Inscription reussie'),
            new OA\Response(response: 422, description: 'Erreur de validation'),
            new OA\Response(response: 500, description: 'Erreur serveur'),
        ]
    )]
    public function inscription() {}

    #[OA\Get(
        path: '/api/inscription/{id}',
        security: [['bearerAuth' => []]],
        tags: ['Inscription'],
        summary: 'Details d une inscription',
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer', example: 1)),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Succes'),
            new OA\Response(response: 404, description: 'Inscription non trouvee'),
        ]
    )]
    public function detail() {}
}
