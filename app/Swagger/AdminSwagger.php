<?php

namespace App\Swagger;

use OpenApi\Attributes as OA;

class AdminSwagger
{
    #[OA\Get(
        path: '/api/admin/utilisateurs',
        tags: ['Admin - Utilisateurs'],
        summary: 'Lister les utilisateurs',
        description: 'Retourne la liste des utilisateurs avec filtres optionnels (role, status, search). Accessible uniquement a un admin authentifie via Sanctum.',
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'role', in: 'query', required: false, schema: new OA\Schema(type: 'string', enum: ['admin', 'caissier', 'professeur', 'secretaire'], example: 'admin')),
            new OA\Parameter(name: 'status', in: 'query', required: false, schema: new OA\Schema(type: 'string', enum: ['actif', 'inactif'], example: 'actif')),
            new OA\Parameter(name: 'search', in: 'query', required: false, schema: new OA\Schema(type: 'string', example: 'Jean')),
        ],
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
                                    new OA\Property(property: 'created_at', type: 'string', format: 'date-time', example: '2026-04-15T10:00:00Z'),
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

    #[OA\Post(
        path: '/api/admin/utilisateurs',
        tags: ['Admin - Utilisateurs'],
        summary: 'Creer un utilisateur',
        description: 'Cree un nouvel utilisateur. Accessible uniquement a un admin.',
        security: [['bearerAuth' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['nom', 'prenom', 'email', 'password', 'role', 'status'],
                properties: [
                    new OA\Property(property: 'nom', type: 'string', example: 'Rakoto'),
                    new OA\Property(property: 'prenom', type: 'string', example: 'Jean'),
                    new OA\Property(property: 'email', type: 'string', example: 'jean.rakoto@ecole.local'),
                    new OA\Property(property: 'password', type: 'string', format: 'password', example: 'password123'),
                    new OA\Property(property: 'telephone', type: 'string', nullable: true, example: '0340011223'),
                    new OA\Property(property: 'role', type: 'string', enum: ['admin', 'caissier', 'professeur', 'secretaire'], example: 'caissier'),
                    new OA\Property(property: 'status', type: 'string', enum: ['actif', 'inactif'], example: 'actif'),
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 201,
                description: 'Utilisateur cree avec succes',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(property: 'message', type: 'string', example: 'Utilisateur cree avec succes'),
                        new OA\Property(property: 'data', type: 'object'),
                    ]
                )
            ),
            new OA\Response(response: 422, description: 'Erreur de validation'),
            new OA\Response(response: 401, description: 'Non authentifie'),
            new OA\Response(response: 403, description: 'Acces reserve a l admin'),
        ]
    )]
    public function utilisateursStore() {}

    #[OA\Get(
        path: '/api/admin/utilisateurs/statistiques',
        tags: ['Admin - Utilisateurs'],
        summary: 'Consulter les statistiques des utilisateurs',
        description: 'Retourne les statistiques globales (total, actifs, inactifs, par role). Accessible uniquement a un admin.',
        security: [['bearerAuth' => []]],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Statistiques des utilisateurs',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(property: 'data', type: 'object', properties: [
                            new OA\Property(property: 'total', type: 'integer', example: 10),
                            new OA\Property(property: 'actifs', type: 'integer', example: 8),
                            new OA\Property(property: 'inactifs', type: 'integer', example: 2),
                            new OA\Property(property: 'par_role', type: 'object', example: ['admin' => 2, 'caissier' => 3, 'professeur' => 5]),
                        ]),
                    ]
                )
            ),
            new OA\Response(response: 401, description: 'Non authentifie'),
            new OA\Response(response: 403, description: 'Acces reserve a l admin'),
        ]
    )]
    public function utilisateursStatistiques() {}

    #[OA\Get(
        path: '/api/admin/utilisateurs/{id}',
        tags: ['Admin - Utilisateurs'],
        summary: 'Afficher un utilisateur',
        description: 'Retourne le detail d un utilisateur. Accessible uniquement a un admin.',
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer', example: 1)),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Detail de l utilisateur',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(property: 'data', type: 'object', properties: [
                            new OA\Property(property: 'id', type: 'integer', example: 1),
                            new OA\Property(property: 'nom', type: 'string', example: 'Rakoto'),
                            new OA\Property(property: 'prenom', type: 'string', example: 'Jean'),
                            new OA\Property(property: 'email', type: 'string', example: 'admin@gmail.com'),
                            new OA\Property(property: 'telephone', type: 'string', nullable: true),
                            new OA\Property(property: 'role', type: 'string', example: 'admin'),
                            new OA\Property(property: 'status', type: 'string', example: 'actif'),
                        ]),
                    ]
                )
            ),
            new OA\Response(response: 401, description: 'Non authentifie'),
            new OA\Response(response: 403, description: 'Acces reserve a l admin'),
            new OA\Response(response: 404, description: 'Utilisateur non trouve'),
        ]
    )]
    public function utilisateursShow() {}

    #[OA\Put(
        path: '/api/admin/utilisateurs/{id}',
        tags: ['Admin - Utilisateurs'],
        summary: 'Modifier un utilisateur',
        description: 'Met a jour un utilisateur existant. Accessible uniquement a un admin.',
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer', example: 1)),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'nom', type: 'string', example: 'Rakoto'),
                    new OA\Property(property: 'prenom', type: 'string', example: 'Jean'),
                    new OA\Property(property: 'email', type: 'string', example: 'jean.rakoto@ecole.local'),
                    new OA\Property(property: 'password', type: 'string', format: 'password', example: 'newpassword123'),
                    new OA\Property(property: 'telephone', type: 'string', nullable: true),
                    new OA\Property(property: 'role', type: 'string', enum: ['admin', 'caissier', 'professeur', 'secretaire']),
                    new OA\Property(property: 'status', type: 'string', enum: ['actif', 'inactif']),
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Utilisateur modifie avec succes',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(property: 'message', type: 'string', example: 'Utilisateur mis a jour avec succes'),
                        new OA\Property(property: 'data', type: 'object'),
                    ]
                )
            ),
            new OA\Response(response: 422, description: 'Erreur de validation'),
            new OA\Response(response: 401, description: 'Non authentifie'),
            new OA\Response(response: 403, description: 'Acces reserve a l admin'),
            new OA\Response(response: 404, description: 'Utilisateur non trouve'),
        ]
    )]
    public function utilisateursUpdate() {}

    #[OA\Delete(
        path: '/api/admin/utilisateurs/{id}',
        tags: ['Admin - Utilisateurs'],
        summary: 'Supprimer un utilisateur',
        description: 'Supprime un utilisateur existant. Accessible uniquement a un admin. Impossible de supprimer son propre compte.',
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer', example: 1)),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Utilisateur supprime avec succes',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(property: 'message', type: 'string', example: 'Utilisateur supprime avec succes'),
                    ]
                )
            ),
            new OA\Response(response: 401, description: 'Non authentifie'),
            new OA\Response(response: 403, description: 'Acces reserve a l admin ou suppression de son propre compte'),
            new OA\Response(response: 404, description: 'Utilisateur non trouve'),
        ]
    )]
    public function utilisateursDestroy() {}

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
            new OA\Response(
                response: 200,
                description: 'Liste des staffs',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(
                            property: 'data',
                            type: 'object',
                            properties: [
                                new OA\Property(property: 'current_page', type: 'integer', example: 1),
                                new OA\Property(
                                    property: 'data',
                                    type: 'array',
                                    items: new OA\Items(
                                        properties: [
                                            new OA\Property(property: 'id', type: 'integer', example: 1),
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
                                            new OA\Property(property: 'utilisateur_id', type: 'integer', example: 1),
                                            new OA\Property(property: 'created_at', type: 'string', format: 'date-time'),
                                            new OA\Property(property: 'updated_at', type: 'string', format: 'date-time'),
                                            new OA\Property(
                                                property: 'infos_dynamiques',
                                                type: 'array',
                                                items: new OA\Items(
                                                    properties: [
                                                        new OA\Property(property: 'id', type: 'integer', example: 1),
                                                        new OA\Property(property: 'staff_id', type: 'integer', example: 1),
                                                        new OA\Property(property: 'nom_champ', type: 'string', example: 'diplome'),
                                                        new OA\Property(property: 'valeur_champ', type: 'string', example: 'Master en Informatique'),
                                                        new OA\Property(property: 'created_at', type: 'string', format: 'date-time'),
                                                        new OA\Property(property: 'updated_at', type: 'string', format: 'date-time'),
                                                    ],
                                                    type: 'object'
                                                )
                                            ),
                                            new OA\Property(
                                                property: 'utilisateur',
                                                type: 'object',
                                                properties: [
                                                    new OA\Property(property: 'id', type: 'integer', example: 1),
                                                    new OA\Property(property: 'nom', type: 'string', example: 'Admin'),
                                                    new OA\Property(property: 'prenom', type: 'string', example: 'User'),
                                                    new OA\Property(property: 'email', type: 'string', example: 'admin@example.com'),
                                                ]
                                            ),
                                        ],
                                        type: 'object'
                                    )
                                ),
                                new OA\Property(property: 'first_page_url', type: 'string'),
                                new OA\Property(property: 'from', type: 'integer'),
                                new OA\Property(property: 'last_page', type: 'integer'),
                                new OA\Property(property: 'last_page_url', type: 'string'),
                                new OA\Property(property: 'next_page_url', type: 'string', nullable: true),
                                new OA\Property(property: 'path', type: 'string'),
                                new OA\Property(property: 'per_page', type: 'integer'),
                                new OA\Property(property: 'prev_page_url', type: 'string', nullable: true),
                                new OA\Property(property: 'to', type: 'integer'),
                                new OA\Property(property: 'total', type: 'integer'),
                            ]
                        ),
                        new OA\Property(property: 'total', type: 'integer', example: 10),
                        new OA\Property(property: 'per_page', type: 'integer', example: 20),
                        new OA\Property(property: 'current_page', type: 'integer', example: 1),
                        new OA\Property(property: 'last_page', type: 'integer', example: 1),
                    ]
                )
            ),
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
                    new OA\Property(
                        property: 'infos_dynamiques',
                        type: 'object',
                        description: 'Informations dynamiques supplementaires pour le staff',
                        example: ['diplome' => 'Master en Informatique', 'experience' => '5 ans']
                    ),
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
            new OA\Response(
                response: 200,
                description: 'Detail du staff',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(property: 'message', type: 'string', example: 'Staff trouve avec succes'),
                        new OA\Property(
                            property: 'data',
                            type: 'object',
                            properties: [
                                new OA\Property(property: 'id', type: 'integer', example: 1),
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
                                new OA\Property(property: 'utilisateur_id', type: 'integer', example: 1),
                                new OA\Property(property: 'created_at', type: 'string', format: 'date-time'),
                                new OA\Property(property: 'updated_at', type: 'string', format: 'date-time'),
                                new OA\Property(
                                    property: 'infos_dynamiques',
                                    type: 'array',
                                    items: new OA\Items(
                                        properties: [
                                            new OA\Property(property: 'id', type: 'integer', example: 1),
                                            new OA\Property(property: 'staff_id', type: 'integer', example: 1),
                                            new OA\Property(property: 'nom_champ', type: 'string', example: 'diplome'),
                                            new OA\Property(property: 'valeur_champ', type: 'string', example: 'Master en Informatique'),
                                            new OA\Property(property: 'created_at', type: 'string', format: 'date-time'),
                                            new OA\Property(property: 'updated_at', type: 'string', format: 'date-time'),
                                        ],
                                        type: 'object'
                                    )
                                ),
                                new OA\Property(
                                    property: 'utilisateur',
                                    type: 'object',
                                    properties: [
                                        new OA\Property(property: 'id', type: 'integer', example: 1),
                                        new OA\Property(property: 'nom', type: 'string', example: 'Admin'),
                                        new OA\Property(property: 'prenom', type: 'string', example: 'User'),
                                        new OA\Property(property: 'email', type: 'string', example: 'admin@example.com'),
                                    ]
                                ),
                            ]
                        ),
                    ]
                )
            ),
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
                    new OA\Property(
                        property: 'infos_dynamiques',
                        type: 'object',
                        description: 'Informations dynamiques supplementaires pour le staff',
                        example: ['diplome' => 'Master en Informatique', 'experience' => '5 ans']
                    ),
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
