<?php

namespace App\Swagger;

use OpenApi\Attributes as OA;

class NoteBulletinSwagger
{
    // ===================== NOTES =====================
    
    #[OA\Get(
        path: '/api/notes',
        security: [['bearerAuth' => []]],
        tags: ['Notes'],
        summary: 'Lister les notes',
        description: 'Retourne la liste des notes pour une inscription et une période donnée',
        parameters: [
            new OA\Parameter(
                name: 'eleve_id',
                in: 'query',
                required: true,
                description: 'ID de l\'élève',
                schema: new OA\Schema(type: 'integer', example: 1)
            ),
            new OA\Parameter(
                name: 'periode',
                in: 'query',
                required: false,
                description: 'Période (TRIMESTRE_1, TRIMESTRE_2, TRIMESTRE_3, SEMESTRE_1, SEMESTRE_2)',
                schema: new OA\Schema(type: 'string', example: 'TRIMESTRE_1')
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
                                    new OA\Property(property: 'id', type: 'integer', example: 1),
                                    new OA\Property(property: 'inscription_id', type: 'integer', example: 1),
                                    new OA\Property(property: 'matiere_id', type: 'integer', example: 1),
                                    new OA\Property(property: 'valeur', type: 'number', format: 'float', example: 15.5),
                                    new OA\Property(property: 'periode', type: 'string', example: 'TRIMESTRE_1'),
                                    new OA\Property(property: 'date', type: 'string', format: 'date', example: '2026-04-20'),
                                    new OA\Property(property: 'type', type: 'string', example: 'COMPOSITION'),
                                    new OA\Property(property: 'appreciation', type: 'string', example: 'Très bon travail'),
                                    new OA\Property(
                                        property: 'matiere',
                                        properties: [
                                            new OA\Property(property: 'id', type: 'integer'),
                                            new OA\Property(property: 'nom', type: 'string'),
                                            new OA\Property(property: 'coefficient', type: 'integer'),
                                            new OA\Property(property: 'cycle', type: 'string'),
                                            new OA\Property(property: 'niveau_classe', type: 'string'),
                                        ]
                                    ),
                                ]
                            )
                        ),
                    ]
                )
            ),
            new OA\Response(response: 422, description: 'Erreur de validation')
        ]
    )]
    public function indexNotes() {}

    #[OA\Get(
        path: '/api/notes/periodes',
        security: [['bearerAuth' => []]],
        tags: ['Notes'],
        summary: 'Lister les périodes/trimestres',
        description: 'Retourne la liste des trimestres ou semestres valides',
        responses: [
            new OA\Response(
                response: 200,
                description: 'Liste des périodes',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(
                            property: 'data',
                            type: 'array',
                            items: new OA\Items(
                                properties: [
                                    new OA\Property(property: 'id', type: 'string', example: 'TRIMESTRE_1'),
                                    new OA\Property(property: 'nom', type: 'string', example: '1er Trimestre'),
                                ]
                            )
                        )
                    ]
                )
            )
        ]
    )]
    public function getPeriodes() {}

    #[OA\Get(
        path: '/api/notes/statistiques',
        security: [['bearerAuth' => []]],
        tags: ['Notes'],
        summary: 'Statistiques globales des notes',
        description: 'Retourne les statistiques (total élèves, total notes, etc.)',
        parameters: [
            new OA\Parameter(
                name: 'classe_id',
                in: 'query',
                required: false,
                description: 'ID de la classe',
                schema: new OA\Schema(type: 'integer', example: 1)
            ),
            new OA\Parameter(
                name: 'periode',
                in: 'query',
                required: false,
                description: 'Période (TRIMESTRE_1, etc.)',
                schema: new OA\Schema(type: 'string', example: 'TRIMESTRE_1')
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
                                new OA\Property(property: 'total_eleves', type: 'integer'),
                                new OA\Property(property: 'total_matieres', type: 'integer'),
                                new OA\Property(property: 'total_notes', type: 'integer'),
                                new OA\Property(property: 'total_bulletins', type: 'integer'),
                                new OA\Property(
                                    property: 'distribution_notes',
                                    type: 'object',
                                    properties: [
                                        new OA\Property(property: '0-9', type: 'integer'),
                                        new OA\Property(property: '10-11', type: 'integer'),
                                        new OA\Property(property: '12-13', type: 'integer'),
                                        new OA\Property(property: '14-15', type: 'integer'),
                                        new OA\Property(property: '16-20', type: 'integer'),
                                    ]
                                ),
                            ]
                        )
                    ]
                )
            )
        ]
    )]
    public function getStatistiques() {}

    #[OA\Get(
        path: '/api/notes/activites-recentes',
        security: [['bearerAuth' => []]],
        tags: ['Notes'],
        summary: 'Activités récentes des notes et bulletins',
        description: 'Retourne les activités récentes avec limit (défaut 10)',
        parameters: [
            new OA\Parameter(
                name: 'limit',
                in: 'query',
                required: false,
                description: 'Nombre de résultats (défaut 10)',
                schema: new OA\Schema(type: 'integer', example: 10)
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
                                    new OA\Property(property: 'id', type: 'string'),
                                    new OA\Property(property: 'action', type: 'string'),
                                    new OA\Property(property: 'details', type: 'string'),
                                    new OA\Property(property: 'concerne', type: 'string'),
                                    new OA\Property(property: 'date', type: 'string', format: 'date-time'),
                                    new OA\Property(property: 'status', type: 'string'),
                                    new OA\Property(property: 'type', type: 'string'),
                                ]
                            )
                        )
                    ]
                )
            )
        ]
    )]
    public function getActivitesRecentes() {}

    #[OA\Post(
        path: '/api/notes',
        security: [['bearerAuth' => []]],
        tags: ['Notes'],
        summary: 'Ajouter une note',
        description: 'Crée une nouvelle note pour un élève',
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['eleve_id', 'matiere_id', 'valeur', 'periode', 'date', 'type'],
                properties: [
                    new OA\Property(property: 'eleve_id', type: 'integer', example: 1),
                    new OA\Property(property: 'matiere_id', type: 'integer', example: 1),
                    new OA\Property(property: 'valeur', type: 'number', format: 'float', example: 15.5),
                    new OA\Property(property: 'periode', type: 'string', enum: ['TRIMESTRE_1', 'TRIMESTRE_2', 'TRIMESTRE_3', 'SEMESTRE_1', 'SEMESTRE_2'], example: 'TRIMESTRE_1'),
                    new OA\Property(property: 'date', type: 'string', format: 'date', example: '2026-04-20'),
                    new OA\Property(property: 'type', type: 'string', enum: ['DEVOIR', 'COMPOSITION', 'EXAMEN'], example: 'COMPOSITION'),
                    new OA\Property(property: 'appreciation', type: 'string', nullable: true, example: 'Très bonne compréhension'),
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 201,
                description: 'Note créée avec succès',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(property: 'message', type: 'string', example: 'Note ajoutée avec succès'),
                        new OA\Property(
                            property: 'data',
                            properties: [
                                new OA\Property(property: 'id', type: 'integer'),
                                new OA\Property(property: 'inscription_id', type: 'integer'),
                                new OA\Property(property: 'matiere_id', type: 'integer'),
                                new OA\Property(property: 'valeur', type: 'number'),
                                new OA\Property(property: 'periode', type: 'string'),
                                new OA\Property(property: 'date', type: 'string'),
                                new OA\Property(property: 'type', type: 'string'),
                                new OA\Property(property: 'appreciation', type: 'string'),
                                new OA\Property(
                                    property: 'matiere',
                                    properties: [
                                        new OA\Property(property: 'id', type: 'integer'),
                                        new OA\Property(property: 'nom', type: 'string'),
                                        new OA\Property(property: 'coefficient', type: 'integer'),
                                    ]
                                ),
                            ]
                        ),
                    ]
                )
            ),
            new OA\Response(response: 422, description: 'Erreur de validation'),
            new OA\Response(response: 500, description: 'Erreur serveur')
        ]
    )]
    public function storeNote() {}

    #[OA\Get(
        path: '/api/notes/{id}',
        security: [['bearerAuth' => []]],
        tags: ['Notes'],
        summary: 'Afficher une note',
        description: 'Retourne les détails d\'une note spécifique',
        parameters: [
            new OA\Parameter(
                name: 'id',
                in: 'path',
                required: true,
                description: 'ID de la note',
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
                                new OA\Property(property: 'inscription_id', type: 'integer'),
                                new OA\Property(property: 'matiere_id', type: 'integer'),
                                new OA\Property(property: 'valeur', type: 'number'),
                                new OA\Property(property: 'periode', type: 'string'),
                                new OA\Property(property: 'date', type: 'string'),
                                new OA\Property(property: 'type', type: 'string'),
                                new OA\Property(property: 'appreciation', type: 'string'),
                                new OA\Property(
                                    property: 'matiere',
                                    properties: [
                                        new OA\Property(property: 'id', type: 'integer'),
                                        new OA\Property(property: 'nom', type: 'string'),
                                        new OA\Property(property: 'coefficient', type: 'integer'),
                                    ]
                                ),
                            ]
                        ),
                    ]
                )
            ),
            new OA\Response(response: 404, description: 'Note non trouvée')
        ]
    )]
    public function showNote() {}

    #[OA\Put(
        path: '/api/notes/{id}',
        security: [['bearerAuth' => []]],
        tags: ['Notes'],
        summary: 'Modifier une note',
        description: 'Met à jour une note existante',
        parameters: [
            new OA\Parameter(
                name: 'id',
                in: 'path',
                required: true,
                description: 'ID de la note',
                schema: new OA\Schema(type: 'integer', example: 1)
            )
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'valeur', type: 'number', format: 'float', example: 16.0),
                    new OA\Property(property: 'date', type: 'string', format: 'date', example: '2026-04-21'),
                    new OA\Property(property: 'appreciation', type: 'string', example: 'Excellent travail !'),
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Note modifiée avec succès',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(property: 'message', type: 'string', example: 'Note modifiée avec succès'),
                        new OA\Property(
                            property: 'data',
                            properties: [
                                new OA\Property(property: 'id', type: 'integer'),
                                new OA\Property(property: 'valeur', type: 'number'),
                                new OA\Property(property: 'appreciation', type: 'string'),
                                new OA\Property(
                                    property: 'matiere',
                                    properties: [
                                        new OA\Property(property: 'id', type: 'integer'),
                                        new OA\Property(property: 'nom', type: 'string'),
                                        new OA\Property(property: 'coefficient', type: 'integer'),
                                        new OA\Property(property: 'cycle', type: 'string'),
                                        new OA\Property(property: 'niveau_classe', type: 'string'),
                                    ]
                                ),
                            ]
                        ),
                    ]
                )
            ),
            new OA\Response(response: 404, description: 'Note non trouvée'),
            new OA\Response(response: 422, description: 'Erreur de validation')
        ]
    )]
    public function updateNote() {}

    #[OA\Delete(
        path: '/api/notes/{id}',
        security: [['bearerAuth' => []]],
        tags: ['Notes'],
        summary: 'Supprimer une note',
        description: 'Supprime une note existante',
        parameters: [
            new OA\Parameter(
                name: 'id',
                in: 'path',
                required: true,
                description: 'ID de la note',
                schema: new OA\Schema(type: 'integer', example: 1)
            )
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Note supprimée avec succès',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(property: 'message', type: 'string', example: 'Note supprimée avec succès'),
                    ]
                )
            ),
            new OA\Response(response: 404, description: 'Note non trouvée'),
            new OA\Response(response: 500, description: 'Erreur serveur')
        ]
    )]
    public function destroyNote() {}

    // ===================== BULLETINS =====================

    #[OA\Post(
        path: '/api/bulletins/generate',
        security: [['bearerAuth' => []]],
        tags: ['Bulletins'],
        summary: 'Générer un bulletin',
        description: 'Génère un bulletin pour un élève pour une période donnée',
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['inscription_id', 'periode'],
                properties: [
                    new OA\Property(property: 'inscription_id', type: 'integer', example: 1),
                    new OA\Property(property: 'periode', type: 'string', enum: ['TRIMESTRE_1', 'TRIMESTRE_2', 'TRIMESTRE_3', 'SEMESTRE_1', 'SEMESTRE_2'], example: 'TRIMESTRE_1'),
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Bulletin généré avec succès',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(property: 'message', type: 'string', example: 'Bulletin généré avec succès'),
                        new OA\Property(
                            property: 'data',
                            properties: [
                                new OA\Property(property: 'id', type: 'integer'),
                                new OA\Property(property: 'inscription_id', type: 'integer'),
                                new OA\Property(property: 'moyenne_eleve', type: 'number', format: 'float'),
                                new OA\Property(property: 'moyenne_classe', type: 'number', format: 'float'),
                                new OA\Property(property: 'rang', type: 'integer'),
                                new OA\Property(property: 'periode', type: 'string'),
                                new OA\Property(property: 'decision', type: 'string', enum: ['ADMIS', 'REPRISE', 'REDOUBLANT']),
                                new OA\Property(property: 'appreciation', type: 'string'),
                                new OA\Property(
                                    property: 'detail_bulletins',
                                    type: 'array',
                                    items: new OA\Items(
                                        properties: [
                                            new OA\Property(property: 'id', type: 'integer'),
                                            new OA\Property(property: 'matiere_id', type: 'integer'),
                                            new OA\Property(property: 'moyenne_matiere', type: 'number'),
                                            new OA\Property(property: 'rang_matiere', type: 'integer'),
                                            new OA\Property(property: 'appreciation', type: 'string'),
                                            new OA\Property(
                                                property: 'matiere',
                                                properties: [
                                                    new OA\Property(property: 'nom', type: 'string'),
                                                    new OA\Property(property: 'coefficient', type: 'integer'),
                                                ]
                                            ),
                                        ]
                                    )
                                ),
                            ]
                        ),
                    ]
                )
            ),
            new OA\Response(response: 422, description: 'Erreur de validation'),
            new OA\Response(response: 500, description: 'Erreur serveur')
        ]
    )]
    public function generateBulletin() {}

    #[OA\Post(
        path: '/api/bulletins/generate-class',
        security: [['bearerAuth' => []]],
        tags: ['Bulletins'],
        summary: 'Générer les bulletins d\'une classe',
        description: 'Génère les bulletins pour tous les élèves d\'une classe',
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['classe_id', 'periode', 'annee_scolaire_id'],
                properties: [
                    new OA\Property(property: 'classe_id', type: 'integer', example: 1),
                    new OA\Property(property: 'periode', type: 'string', example: 'TRIMESTRE_1'),
                    new OA\Property(property: 'annee_scolaire_id', type: 'integer', example: 1),
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Bulletins générés',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(property: 'message', type: 'string', example: '15 bulletins générés avec succès'),
                        new OA\Property(
                            property: 'data',
                            type: 'object',
                            additionalProperties: new OA\AdditionalProperties(
                                properties: [
                                    new OA\Property(property: 'success', type: 'boolean'),
                                    new OA\Property(property: 'bulletin', type: 'object'),
                                    new OA\Property(property: 'error', type: 'string', nullable: true),
                                ]
                            )
                        ),
                    ]
                )
            ),
            new OA\Response(response: 422, description: 'Erreur de validation')
        ]
    )]
    public function generateClassBulletins() {}

    #[OA\Get(
        path: '/api/bulletins/eleve/{inscriptionId}',
        security: [['bearerAuth' => []]],
        tags: ['Bulletins'],
        summary: 'Bulletins d\'un élève',
        description: 'Retourne tous les bulletins d\'un élève',
        parameters: [
            new OA\Parameter(
                name: 'inscriptionId',
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
                            type: 'array',
                            items: new OA\Items(
                                properties: [
                                    new OA\Property(property: 'id', type: 'integer'),
                                    new OA\Property(property: 'periode', type: 'string'),
                                    new OA\Property(property: 'moyenne_eleve', type: 'number'),
                                    new OA\Property(property: 'decision', type: 'string'),
                                ]
                            )
                        ),
                    ]
                )
            ),
            new OA\Response(response: 404, description: 'Inscription non trouvée')
        ]
    )]
    public function getBulletinsByEleve() {}

    #[OA\Get(
        path: '/api/bulletins/{id}',
        security: [['bearerAuth' => []]],
        tags: ['Bulletins'],
        summary: 'Afficher un bulletin',
        description: 'Retourne les détails complets d\'un bulletin',
        parameters: [
            new OA\Parameter(
                name: 'id',
                in: 'path',
                required: true,
                description: 'ID du bulletin',
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
                                new OA\Property(property: 'moyenne_eleve', type: 'number'),
                                new OA\Property(property: 'moyenne_classe', type: 'number'),
                                new OA\Property(property: 'rang', type: 'integer'),
                                new OA\Property(property: 'decision', type: 'string'),
                                new OA\Property(property: 'appreciation', type: 'string'),
                                new OA\Property(
                                    property: 'inscription',
                                    properties: [
                                        new OA\Property(
                                            property: 'eleve',
                                            properties: [
                                                new OA\Property(property: 'nom', type: 'string'),
                                                new OA\Property(property: 'prenom', type: 'string'),
                                                new OA\Property(property: 'matricule', type: 'string'),
                                            ]
                                        ),
                                        new OA\Property(
                                            property: 'classe',
                                            properties: [
                                                new OA\Property(property: 'nom_classe', type: 'string'),
                                                new OA\Property(
                                                    property: 'niveau',
                                                    properties: [
                                                        new OA\Property(property: 'nom_niveau', type: 'string'),
                                                        new OA\Property(property: 'cycle', type: 'string'),
                                                    ]
                                                ),
                                            ]
                                        ),
                                    ]
                                ),
                                new OA\Property(
                                    property: 'detail_bulletins',
                                    type: 'array',
                                    items: new OA\Items(
                                        properties: [
                                            new OA\Property(property: 'moyenne_matiere', type: 'number'),
                                            new OA\Property(property: 'rang_matiere', type: 'integer'),
                                            new OA\Property(property: 'appreciation', type: 'string'),
                                            new OA\Property(
                                                property: 'matiere',
                                                properties: [
                                                    new OA\Property(property: 'nom', type: 'string'),
                                                    new OA\Property(property: 'coefficient', type: 'integer'),
                                                ]
                                            ),
                                        ]
                                    )
                                ),
                            ]
                        ),
                    ]
                )
            ),
            new OA\Response(response: 404, description: 'Bulletin non trouvé')
        ]
    )]
    public function showBulletin() {}

    #[OA\Put(
        path: '/api/bulletins/{id}/appreciation',
        security: [['bearerAuth' => []]],
        tags: ['Bulletins'],
        summary: 'Modifier l\'appréciation',
        description: 'Met à jour l\'appréciation d\'un bulletin',
        parameters: [
            new OA\Parameter(
                name: 'id',
                in: 'path',
                required: true,
                description: 'ID du bulletin',
                schema: new OA\Schema(type: 'integer', example: 1)
            )
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['appreciation'],
                properties: [
                    new OA\Property(property: 'appreciation', type: 'string', maxLength: 255, example: 'Félicitations ! Excellent travail ce trimestre.'),
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Appréciation mise à jour',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(property: 'message', type: 'string', example: 'Appréciation mise à jour avec succès'),
                        new OA\Property(
                            property: 'data',
                            properties: [
                                new OA\Property(property: 'id', type: 'integer'),
                                new OA\Property(property: 'appreciation', type: 'string'),
                            ]
                        ),
                    ]
                )
            ),
            new OA\Response(response: 404, description: 'Bulletin non trouvé'),
            new OA\Response(response: 422, description: 'Erreur de validation')
        ]
    )]
    public function updateAppreciation() {}

    #[OA\Get(
        path: '/api/bulletins/classe',
        security: [['bearerAuth' => []]],
        tags: ['Bulletins'],
        summary: 'Bulletins par classe',
        description: 'Retourne la liste des bulletins d\'une classe pour une période',
        parameters: [
            new OA\Parameter(
                name: 'classe_id',
                in: 'query',
                required: true,
                description: 'ID de la classe',
                schema: new OA\Schema(type: 'integer', example: 1)
            ),
            new OA\Parameter(
                name: 'periode',
                in: 'query',
                required: true,
                description: 'Période',
                schema: new OA\Schema(type: 'string', example: 'TRIMESTRE_1')
            ),
            new OA\Parameter(
                name: 'annee_scolaire_id',
                in: 'query',
                required: true,
                description: 'ID de l\'année scolaire',
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
                            type: 'array',
                            items: new OA\Items(
                                properties: [
                                    new OA\Property(property: 'id', type: 'integer'),
                                    new OA\Property(property: 'moyenne_eleve', type: 'number'),
                                    new OA\Property(property: 'rang', type: 'integer'),
                                    new OA\Property(property: 'decision', type: 'string'),
                                    new OA\Property(
                                        property: 'inscription',
                                        properties: [
                                            new OA\Property(
                                                property: 'eleve',
                                                properties: [
                                                    new OA\Property(property: 'nom', type: 'string'),
                                                    new OA\Property(property: 'prenom', type: 'string'),
                                                ]
                                            ),
                                        ]
                                    ),
                                ]
                            )
                        ),
                    ]
                )
            ),
            new OA\Response(response: 422, description: 'Erreur de validation')
        ]
    )]
    public function getBulletinsByClass() {}


    #[OA\Get(
        path: '/api/bulletins/{id}/pdf',
        security: [['bearerAuth' => []]],
        tags: ['Bulletins'],
        summary: 'Exporter en PDF',
        description: 'Génère et télécharge le bulletin au format PDF',
        parameters: [
            new OA\Parameter(
                name: 'id',
                in: 'path',
                required: true,
                description: 'ID du bulletin',
                schema: new OA\Schema(type: 'integer', example: 1)
            )
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Fichier PDF',
                content: new OA\MediaType(mediaType: 'application/pdf')
            ),
            new OA\Response(response: 404, description: 'Bulletin non trouvé')
        ]
    )]
    public function exportPDF() {}
}
