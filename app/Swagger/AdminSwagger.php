<?php

namespace App\Swagger;

use OpenApi\Attributes as OA;

class AdminSwagger
{
    #[OA\Get(
        path: '/api/admin/utilisateurs',
        tags: ['Admin - Utilisateurs'],
        summary: 'Lister les utilisateurs',
        description: 'Retourne la liste des utilisateurs. Accessible uniquement a un admin authentifie via Sanctum.',
        security: [['bearerAuth' => []]],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Liste des utilisateurs',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(
                            property: 'data',
                            type: 'array',
                            items: new OA\Items(
                                properties: [
                                    new OA\Property(property: 'id', type: 'integer', example: 1),
                                    new OA\Property(property: 'nom', type: 'string', example: 'Rakoto'),
                                    new OA\Property(property: 'prenom', type: 'string', example: 'Jean'),
                                    new OA\Property(property: 'email', type: 'string', example: 'admin@gmail.com'),
                                    new OA\Property(property: 'telephone', type: 'string', nullable: true, example: '0340011223'),
                                    new OA\Property(property: 'role', type: 'string', example: 'admin'),
                                    new OA\Property(property: 'status', type: 'string', example: 'actif'),
                                ],
                                type: 'object'
                            )
                        ),
                        new OA\Property(property: 'count', type: 'integer', example: 2),
                    ]
                )
            ),
            new OA\Response(response: 401, description: 'Non authentifie'),
            new OA\Response(response: 403, description: 'Acces reserve a l admin'),
        ]
    )]
    public function utilisateursIndex() {}

    #[OA\Get(
        path: '/api/admin/staffs',
        tags: ['Admin - Staffs'],
        summary: 'Lister les staffs',
        description: 'Liste paginee des staffs avec filtres optionnels. Accessible uniquement a un admin.',
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'fonction', in: 'query', required: false, schema: new OA\Schema(type: 'string', example: 'Surveillant')),
            new OA\Parameter(name: 'sexe', in: 'query', required: false, schema: new OA\Schema(type: 'string', enum: ['masculin', 'feminin'], example: 'masculin')),
            new OA\Parameter(name: 'search', in: 'query', required: false, schema: new OA\Schema(type: 'string', example: 'Jean')),
            new OA\Parameter(name: 'page', in: 'query', required: false, schema: new OA\Schema(type: 'integer', example: 1)),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Liste des staffs'),
            new OA\Response(response: 401, description: 'Non authentifie'),
            new OA\Response(response: 403, description: 'Acces reserve a l admin'),
        ]
    )]
    public function staffsIndex() {}

    #[OA\Post(
        path: '/api/admin/staffs',
        tags: ['Admin - Staffs'],
        summary: 'Creer un staff',
        description: 'Ajoute un nouveau membre du staff. Accessible uniquement a un admin.',
        security: [['bearerAuth' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['nom', 'prenom', 'telephone', 'matricule', 'email', 'fonction', 'salaire', 'adresse', 'sexe', 'date_naissance', 'lieu_naissance'],
                properties: [
                    new OA\Property(property: 'nom', type: 'string', example: 'Rabe'),
                    new OA\Property(property: 'prenom', type: 'string', example: 'Marie'),
                    new OA\Property(property: 'telephone', type: 'string', example: '0340011223'),
                    new OA\Property(property: 'matricule', type: 'string', example: 'STF-0001'),
                    new OA\Property(property: 'email', type: 'string', example: 'marie.rabe@ecole.local'),
                    new OA\Property(property: 'fonction', type: 'string', example: 'Secretaire'),
                    new OA\Property(property: 'salaire', type: 'number', format: 'float', example: 450000),
                    new OA\Property(property: 'adresse', type: 'string', example: 'Lot II A 45 Antananarivo'),
                    new OA\Property(property: 'sexe', type: 'string', enum: ['masculin', 'feminin'], example: 'feminin'),
                    new OA\Property(property: 'date_naissance', type: 'string', format: 'date', example: '1995-08-12'),
                    new OA\Property(property: 'lieu_naissance', type: 'string', example: 'Antsirabe'),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 201, description: 'Staff cree avec succes'),
            new OA\Response(response: 422, description: 'Erreur de validation'),
            new OA\Response(response: 401, description: 'Non authentifie'),
            new OA\Response(response: 403, description: 'Acces reserve a l admin'),
        ]
    )]
    public function staffsStore() {}

    #[OA\Get(
        path: '/api/admin/staffs/statistiques',
        tags: ['Admin - Staffs'],
        summary: 'Consulter les statistiques des staffs',
        description: 'Retourne les statistiques globales des staffs. Accessible uniquement a un admin.',
        security: [['bearerAuth' => []]],
        responses: [
            new OA\Response(response: 200, description: 'Statistiques des staffs'),
            new OA\Response(response: 401, description: 'Non authentifie'),
            new OA\Response(response: 403, description: 'Acces reserve a l admin'),
        ]
    )]
    public function staffsStatistiques() {}

    #[OA\Get(
        path: '/api/admin/staffs/export',
        tags: ['Admin - Staffs'],
        summary: 'Exporter la liste des staffs',
        description: 'Retourne la liste exportable des staffs. Accessible uniquement a un admin.',
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'fonction', in: 'query', required: false, schema: new OA\Schema(type: 'string', example: 'Secretaire')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Export des staffs'),
            new OA\Response(response: 401, description: 'Non authentifie'),
            new OA\Response(response: 403, description: 'Acces reserve a l admin'),
        ]
    )]
    public function staffsExport() {}

    #[OA\Get(
        path: '/api/admin/staffs/{id}',
        tags: ['Admin - Staffs'],
        summary: 'Afficher un staff',
        description: 'Retourne le detail d un staff. Accessible uniquement a un admin.',
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer', example: 1)),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Detail du staff'),
            new OA\Response(response: 401, description: 'Non authentifie'),
            new OA\Response(response: 403, description: 'Acces reserve a l admin'),
            new OA\Response(response: 404, description: 'Staff non trouve'),
        ]
    )]
    public function staffsShow() {}

    #[OA\Put(
        path: '/api/admin/staffs/{id}',
        tags: ['Admin - Staffs'],
        summary: 'Modifier un staff',
        description: 'Met a jour un staff existant. Accessible uniquement a un admin.',
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer', example: 1)),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'nom', type: 'string', example: 'Rabe'),
                    new OA\Property(property: 'prenom', type: 'string', example: 'Marie'),
                    new OA\Property(property: 'telephone', type: 'string', example: '0340011223'),
                    new OA\Property(property: 'matricule', type: 'string', example: 'STF-0001'),
                    new OA\Property(property: 'email', type: 'string', example: 'marie.rabe@ecole.local'),
                    new OA\Property(property: 'fonction', type: 'string', example: 'Comptable'),
                    new OA\Property(property: 'salaire', type: 'number', format: 'float', example: 500000),
                    new OA\Property(property: 'adresse', type: 'string', example: 'Lot II A 45 Antananarivo'),
                    new OA\Property(property: 'sexe', type: 'string', enum: ['masculin', 'feminin'], example: 'feminin'),
                    new OA\Property(property: 'date_naissance', type: 'string', format: 'date', example: '1995-08-12'),
                    new OA\Property(property: 'lieu_naissance', type: 'string', example: 'Antsirabe'),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Staff modifie avec succes'),
            new OA\Response(response: 422, description: 'Erreur de validation'),
            new OA\Response(response: 401, description: 'Non authentifie'),
            new OA\Response(response: 403, description: 'Acces reserve a l admin'),
            new OA\Response(response: 404, description: 'Staff non trouve'),
        ]
    )]
    public function staffsUpdate() {}

    #[OA\Delete(
        path: '/api/admin/staffs/{id}',
        tags: ['Admin - Staffs'],
        summary: 'Supprimer un staff',
        description: 'Supprime un staff existant. Accessible uniquement a un admin.',
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer', example: 1)),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Staff supprime avec succes'),
            new OA\Response(response: 401, description: 'Non authentifie'),
            new OA\Response(response: 403, description: 'Acces reserve a l admin'),
            new OA\Response(response: 404, description: 'Staff non trouve'),
        ]
    )]
    public function staffsDestroy() {}
}
