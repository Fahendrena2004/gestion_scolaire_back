<?php

namespace App\Swagger;

use OpenApi\Attributes as OA;

class InscriptionSwagger {



       // ==============Recuperation CYCLES=====================/
    #[OA\Get(
        path: '/api/inscription/cycles',
        tags: ['Cycles'],
        summary: 'Récupérer tous les cycles',
        description: 'Retourne la liste des cycles disponibles (primaire, college, lycee)',
        responses: [
            new OA\Response(
                response: 200,
                description: 'Succès',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(
                            property: 'data',
                            type: 'array',
                            items: new OA\Items(type: 'string'),
                            example: ['primaire', 'college', 'lycee']
                        ),
                    ]
                )
            )
        ]
    )]
    public function cycles() {}

    //=======NIVEAUX PAR CYCLE===============//
    #[OA\Get(
        path: '/api/inscription/niveaux/{cycle}',
        tags: ['Cycles'],
        summary: 'Récupérer les niveaux par cycle',
        description: 'Retourne la liste des niveaux pour un cycle donné',
        parameters: [
            new OA\Parameter(
                name: 'cycle',
                in: 'path',
                required: true,
                description: 'Cycle (primaire, college, lycee)',
                schema: new OA\Schema(type: 'string', example: 'college')
            )
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Succès',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(
                            property: 'data',
                            type: 'array',
                            items: new OA\Items(
                                properties: [
                                    new OA\Property(property: 'id', type: 'integer', example: 6),
                                    new OA\Property(property: 'nom_niveau', type: 'string', example: '6ème'),
                                ]
                            )
                        ),
                    ]
                )
            ),
            new OA\Response(response: 404, description: 'Cycle non trouvé')
        ]
    )]
    public function niveaux() {}


    //===============Recuperation classe par Niveau============//
    #[OA\Get(
        path: '/api/inscription/classes/{niveauId}',
        tags: ['Cycles'],
        summary: 'Récupérer les classes par niveau',
        description: 'Retourne la liste des classes pour un niveau donné',
        parameters: [
            new OA\Parameter(
                name: 'niveauId',
                in: 'path',
                required: true,
                description: 'ID du niveau',
                schema: new OA\Schema(type: 'integer', example: 6)
            )
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Succès',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(
                            property: 'data',
                            type: 'array',
                            items: new OA\Items(
                                properties: [
                                    new OA\Property(property: 'id', type: 'integer', example: 12),
                                    new OA\Property(property: 'nom_classe', type: 'string', example: '6ème A'),
                                ]
                            )
                        ),
                    ]
                )
            ),
            new OA\Response(response: 404, description: 'Niveau non trouvé')
        ]
    )]
    public function classes() {}


    //=============calcul frais selon classe =============//
    #[OA\Get(
        path: '/api/inscription/frais/calcul',
        tags: ['Frais'],
        summary: 'Calculer les frais de scolarité',
        description: 'Calcule le montant total des frais selon le cycle et les options',
        parameters: [
            new OA\Parameter(
                name: 'cycle',
                in: 'query',
                required: true,
                description: 'Cycle (primaire, college, lycee)',
                schema: new OA\Schema(type: 'string', example: 'college')
            ),
            new OA\Parameter(
                name: 'parascolaire',
                in: 'query',
                required: false,
                description: 'Option parascolaire',
                schema: new OA\Schema(type: 'boolean', example: true)
            ),
            new OA\Parameter(
                name: 'cantine',
                in: 'query',
                required: false,
                description: 'Option cantine',
                schema: new OA\Schema(type: 'boolean', example: false)
            )
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Succès',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(
                            property: 'data',
                            properties: [
                                new OA\Property(property: 'total', type: 'integer', example: 540000),
                                new OA\Property(
                                    property: 'details',
                                    type: 'array',
                                    items: new OA\Items(
                                        properties: [
                                            new OA\Property(property: 'libelle', type: 'string', example: 'Inscription'),
                                            new OA\Property(property: 'montant', type: 'integer', example: 75000),
                                            new OA\Property(property: 'type', type: 'string', example: 'obligatoire'),
                                        ]
                                    )
                                ),
                            ]
                        ),
                    ]
                )
            )
        ]
    )]
    public function calculFrais() {}

//======================Inscription des eleves========================/
    #[OA\Post(
        path: '/api/inscription',
        tags: ['Inscription'],
        summary: 'Soumettre une inscription',
        description: 'Enregistre une nouvelle inscription',

        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['nom', 'prenom', 'date_naissance', 'lieu_naissance', 'sexe', 'cycle', 'niveau_id', 'classe_id'],
                properties: [
                    // Identité
                    new OA\Property(property: 'nom', type: 'string', example: 'Rakoto'),
                    new OA\Property(property: 'prenom', type: 'string', example: 'Fara'),
                    new OA\Property(property: 'date_naissance', type: 'string', format: 'date', example: '2015-03-20'),
                    new OA\Property(property: 'lieu_naissance', type: 'string', example: 'Antananarivo'),
                    new OA\Property(property: 'sexe', type: 'string', enum: ['M', 'F'], example: 'F'),
                    new OA\Property(property: 'adresse', type: 'string', nullable: true, example: 'Lot IV 123 Bis'),

                    // Choix classe
                    new OA\Property(property: 'cycle', type: 'string', enum: ['primaire', 'college', 'lycee'], example: 'college'),
                    new OA\Property(property: 'niveau_id', type: 'integer', example: 6),
                    new OA\Property(property: 'classe_id', type: 'integer', example: 12),

                    // Options
                    new OA\Property(property: 'parascolaire', type: 'boolean', example: true),
                    new OA\Property(property: 'cantine', type: 'boolean', example: false),

                    // Paiement
                    new OA\Property(property: 'montant_verse', type: 'integer', example: 200000),

                    // Champs dynamiques (responsable)
                    new OA\Property(property: 'responsable_nom', type: 'string', example: 'Rakoto Jean'),
                    new OA\Property(property: 'responsable_telephone', type: 'string', example: '0321234567'),
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 201,
                description: 'Inscription réussie',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(property: 'message', type: 'string', example: 'Inscription réussie'),
                        new OA\Property(
                            property: 'data',
                            properties: [
                                new OA\Property(property: 'matricule', type: 'string', example: 'RG-CL-2025-0001'),
                                new OA\Property(property: 'eleve_id', type: 'integer', example: 1),
                                new OA\Property(property: 'inscription_id', type: 'integer', example: 1),
                                new OA\Property(property: 'classe', type: 'string', example: '6ème B'),
                                new OA\Property(property: 'niveau', type: 'string', example: '6ème'),
                                new OA\Property(property: 'cycle', type: 'string', example: 'college'),
                                new OA\Property(property: 'annee_scolaire', type: 'string', example: '2025-09-01 - 2026-06-30'),
                                new OA\Property(property: 'montant_total', type: 'integer', example: 540000),
                                new OA\Property(property: 'montant_verse', type: 'integer', example: 200000),
                                new OA\Property(property: 'reste_a_payer', type: 'integer', example: 340000),
                                new OA\Property(property: 'est_paye', type: 'boolean', example: false),
                            ]
                        ),
                    ]
                )
            ),
            new OA\Response(
                response: 422,
                description: 'Erreur de validation',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: false),
                        new OA\Property(property: 'message', type: 'string', example: 'Erreur de validation'),
                    ]
                )
            ),
            new OA\Response(
                response: 500,
                description: 'Erreur serveur',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: false),
                        new OA\Property(property: 'message', type: 'string', example: 'Erreur lors de l\'inscription'),
                    ]
                )
            )
        ]
    )]
    public function inscription() {}


//====================Details_Inscription=======================///////
    #[OA\Get(
        path: '/api/inscription/{id}',
        tags: ['Inscription'],
        summary: 'Détails d\'une inscription',
        description: 'Retourne tous les détails d\'une inscription',
        parameters: [
            new OA\Parameter(
                name: 'id',
                in: 'path',
                required: true,
                description: 'ID de l\'inscription',
                schema: new OA\Schema(type: 'integer', example: 1)
            )
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Succès',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(
                            property: 'data',
                            properties: [
                                new OA\Property(property: 'id', type: 'integer'),
                                new OA\Property(property: 'date_inscription', type: 'string'),
                                new OA\Property(
                                    property: 'eleve',
                                    properties: [
                                        new OA\Property(property: 'id', type: 'integer'),
                                        new OA\Property(property: 'nom', type: 'string'),
                                        new OA\Property(property: 'prenom', type: 'string'),
                                        new OA\Property(property: 'matricule', type: 'string'),
                                    ]
                                ),
                                new OA\Property(
                                    property: 'classe',
                                    properties: [
                                        new OA\Property(property: 'id', type: 'integer'),
                                        new OA\Property(property: 'nom', type: 'string'),
                                    ]
                                ),
                                new OA\Property(property: 'montant_total', type: 'integer'),
                                new OA\Property(property: 'reste_a_payer', type: 'integer'),
                            ]
                        ),
                    ]
                )
            ),
            new OA\Response(response: 404, description: 'Inscription non trouvée')
        ]
    )]
    public function detail() {}


}