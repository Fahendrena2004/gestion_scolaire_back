<?php

namespace App\Swagger;

use OpenApi\Attributes as OA;

class RouteCoverageSwagger
{
    #[OA\Get(
        path: '/api/test',
        tags: ['System'],
        summary: 'Verifier que l API fonctionne',
        responses: [
            new OA\Response(
                response: 200,
                description: 'API disponible',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'status', type: 'string', example: 'ok'),
                        new OA\Property(property: 'message', type: 'string', example: 'API fonctionne'),
                    ]
                )
            ),
        ]
    )]
    public function healthcheck() {}

    #[OA\Post(
        path: '/api/setup/annee-scolaire',
        tags: ['Setup'],
        summary: 'Creer l annee scolaire initiale',
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['date_debut', 'date_fin', 'statut'],
                properties: [
                    new OA\Property(property: 'date_debut', type: 'string', format: 'date', example: '2026-09-01'),
                    new OA\Property(property: 'date_fin', type: 'string', format: 'date', example: '2027-06-30'),
                    new OA\Property(property: 'statut', type: 'string', example: 'en_cours'),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Annee scolaire creee'),
            new OA\Response(response: 422, description: 'Erreur de validation'),
        ]
    )]
    public function setupAnneeScolaire() {}

    #[OA\Post(
        path: '/api/setup/niveaux',
        tags: ['Setup'],
        summary: 'Creer les niveaux par defaut',
        responses: [
            new OA\Response(response: 200, description: 'Niveaux crees ou deja existants'),
        ]
    )]
    public function setupNiveaux() {}

    #[OA\Post(
        path: '/api/setup/classes',
        tags: ['Setup'],
        summary: 'Generer automatiquement les classes',
        responses: [
            new OA\Response(response: 200, description: 'Classes generees ou deja existantes'),
            new OA\Response(response: 400, description: 'Aucune annee scolaire active'),
        ]
    )]
    public function setupClasses() {}

    #[OA\Post(
        path: '/api/setup/frais',
        tags: ['Setup'],
        summary: 'Creer les types de frais par defaut',
        responses: [
            new OA\Response(response: 200, description: 'Types de frais crees ou deja existants'),
        ]
    )]
    public function setupFrais() {}

    #[OA\Get(
        path: '/api/setup/status',
        tags: ['Setup'],
        summary: 'Consulter l etat de l installation',
        responses: [
            new OA\Response(response: 200, description: 'Etat de l installation'),
        ]
    )]
    public function setupStatus() {}

    #[OA\Post(
        path: '/api/setup/reset',
        tags: ['Setup'],
        summary: 'Reinitialiser les donnees d installation',
        security: [['bearerAuth' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['confirmation'],
                properties: [
                    new OA\Property(property: 'confirmation', type: 'string', example: 'RESET'),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Donnees reinitialisees'),
            new OA\Response(response: 422, description: 'Confirmation invalide'),
            new OA\Response(response: 401, description: 'Non authentifie'),
        ]
    )]
    public function setupReset() {}

    #[OA\Get(
        path: '/api/inscription/annee-scolaire',
        tags: ['Annees Scolaires'],
        summary: 'Lister les annees scolaires',
        security: [['bearerAuth' => []]],
        responses: [new OA\Response(response: 200, description: 'Liste des annees scolaires')]
    )]
    public function anneeIndex() {}

    #[OA\Get(
        path: '/api/inscription/annee-scolaire/active',
        tags: ['Annees Scolaires'],
        summary: 'Recuperer l annee scolaire active',
        description: 'Retourne l année scolaire avec le statut "en_cours". Retourne une erreur 404 si aucune année n est active ou si la période d inscription est fermée.',
        security: [['bearerAuth' => []]],
        responses: [
            new OA\Response(
                response: 200, 
                description: 'Annee scolaire active et periode d inscription ouverte',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(property: 'data', type: 'object', properties: [
                            new OA\Property(property: 'id', type: 'integer', example: 1),
                            new OA\Property(property: 'statut', type: 'string', example: 'en_cours'),
                            new OA\Property(property: 'est_inscription_ouverte', type: 'boolean', example: true),
                            new OA\Property(property: 'libelle', type: 'string', example: '2026-09-01 - 2027-06-30'),
                        ])
                    ]
                )
            ),
            new OA\Response(
                response: 404, 
                description: 'Inscription non trouvee (active ou periode fermee)',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: false),
                        new OA\Property(property: 'message', type: 'string', example: 'Inscription non trouvée (la période d\'inscription est fermée pour cette année scolaire)'),
                    ]
                )
            )
        ]
    )]
    public function anneeActive() {}

    #[OA\Post(
        path: '/api/inscription/annee-scolaire',
        tags: ['Annees Scolaires'],
        summary: 'Creer une annee scolaire',
        security: [['bearerAuth' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['date_debut', 'date_fin', 'statut'],
                properties: [
                    new OA\Property(property: 'date_debut', type: 'string', format: 'date'),
                    new OA\Property(property: 'date_fin', type: 'string', format: 'date'),
                    new OA\Property(property: 'statut', type: 'string', enum: ['en_cours', 'termine', 'planifie', 'actif'], description: 'Le statut "actif" est converti automatiquement en "en_cours"', example: 'en_cours'),
                    new OA\Property(property: 'date_debut_inscription', type: 'string', format: 'date', nullable: true),
                    new OA\Property(property: 'date_fin_inscription', type: 'string', format: 'date', nullable: true),
                    new OA\Property(
                        property: 'classes',
                        type: 'array',
                        nullable: true,
                        items: new OA\Items(
                            properties: [
                                new OA\Property(property: 'id', type: 'integer', example: 12),
                                new OA\Property(property: 'effectif', type: 'integer', example: 35),
                            ]
                        )
                    ),
                    new OA\Property(
                        property: 'calendrier',
                        type: 'array',
                        nullable: true,
                        items: new OA\Items(
                            properties: [
                                new OA\Property(property: 'type', type: 'string', enum: ['examen', 'vacance', 'autre'], example: 'examen'),
                                new OA\Property(property: 'titre', type: 'string', example: 'Examen du premier trimestre'),
                                new OA\Property(property: 'date_debut', type: 'string', format: 'date', example: '2026-12-10'),
                                new OA\Property(property: 'date_fin', type: 'string', format: 'date', example: '2026-12-12'),
                                new OA\Property(property: 'description', type: 'string', nullable: true, example: 'Examen commun'),
                            ]
                        )
                    ),
                    new OA\Property(
                        property: 'frais',
                        type: 'array',
                        nullable: true,
                        items: new OA\Items(
                            properties: [
                                new OA\Property(property: 'libelle', type: 'string', example: 'Inscription'),
                                new OA\Property(property: 'montant', type: 'number', format: 'float', example: 75000),
                                new OA\Property(property: 'est_obligatoire', type: 'boolean', example: true),
                            ]
                        )
                    ),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 201, description: 'Annee scolaire creee'),
            new OA\Response(response: 422, description: 'Erreur de validation'),
        ]
    )]
    public function anneeStore() {}

    #[OA\Get(
        path: '/api/inscription/annee-scolaire/{id}',
        tags: ['Annees Scolaires'],
        summary: 'Afficher une annee scolaire',
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer', example: 1)),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Detail de l annee scolaire'),
            new OA\Response(
                response: 404, 
                description: 'Annee non trouvee',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: false),
                        new OA\Property(property: 'message', type: 'string', example: 'Année scolaire non trouvée'),
                    ]
                )
            ),
        ]
    )]
    public function anneeShow() {}

    #[OA\Put(
        path: '/api/inscription/annee-scolaire/{id}',
        tags: ['Annees Scolaires'],
        summary: 'Modifier une annee scolaire',
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer', example: 1)),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'date_debut', type: 'string', format: 'date'),
                    new OA\Property(property: 'date_fin', type: 'string', format: 'date'),
                    new OA\Property(property: 'statut', type: 'string', enum: ['en_cours', 'termine', 'planifie', 'actif'], example: 'planifie'),
                    new OA\Property(property: 'date_debut_inscription', type: 'string', format: 'date', nullable: true),
                    new OA\Property(property: 'date_fin_inscription', type: 'string', format: 'date', nullable: true),
                    new OA\Property(
                        property: 'classes',
                        type: 'array',
                        nullable: true,
                        items: new OA\Items(
                            properties: [
                                new OA\Property(property: 'id', type: 'integer', example: 12),
                                new OA\Property(property: 'effectif', type: 'integer', example: 38),
                            ]
                        )
                    ),
                    new OA\Property(
                        property: 'calendrier',
                        type: 'array',
                        nullable: true,
                        items: new OA\Items(
                            properties: [
                                new OA\Property(property: 'type', type: 'string', enum: ['examen', 'vacance', 'autre'], example: 'vacance'),
                                new OA\Property(property: 'titre', type: 'string', example: 'Petite vacance'),
                                new OA\Property(property: 'date_debut', type: 'string', format: 'date', example: '2026-11-02'),
                                new OA\Property(property: 'date_fin', type: 'string', format: 'date', example: '2026-11-06'),
                                new OA\Property(property: 'description', type: 'string', nullable: true, example: 'Pause de petite vacance'),
                            ]
                        )
                    ),
                    new OA\Property(
                        property: 'frais',
                        type: 'array',
                        nullable: true,
                        items: new OA\Items(
                            properties: [
                                new OA\Property(property: 'id', type: 'integer', nullable: true, example: 1),
                                new OA\Property(property: 'libelle', type: 'string', example: 'Cantine'),
                                new OA\Property(property: 'montant', type: 'number', format: 'float', example: 80000),
                                new OA\Property(property: 'est_obligatoire', type: 'boolean', example: false),
                            ]
                        )
                    ),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Annee scolaire mise a jour'),
            new OA\Response(response: 422, description: 'Erreur de validation'),
        ]
    )]
    public function anneeUpdate() {}

    #[OA\Delete(
        path: '/api/inscription/annee-scolaire/{id}',
        tags: ['Annees Scolaires'],
        summary: 'Supprimer une annee scolaire',
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer', example: 1)),
        ],
        responses: [new OA\Response(response: 200, description: 'Annee scolaire supprimee')]
    )]
    public function anneeDestroy() {}

    #[OA\Get(
        path: '/api/inscription/niveaux',
        tags: ['Niveaux'],
        summary: 'Lister les niveaux',
        security: [['bearerAuth' => []]],
        responses: [new OA\Response(response: 200, description: 'Liste des niveaux')]
    )]
    public function niveauIndex() {}

    #[OA\Post(
        path: '/api/inscription/niveaux',
        tags: ['Niveaux'],
        summary: 'Creer un niveau',
        security: [['bearerAuth' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['cycle', 'nom_niveau'],
                properties: [
                    new OA\Property(property: 'cycle', type: 'string', example: 'college'),
                    new OA\Property(property: 'nom_niveau', type: 'string', example: '5eme'),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 201, description: 'Niveau cree'),
            new OA\Response(response: 422, description: 'Erreur de validation'),
        ]
    )]
    public function niveauStore() {}

    #[OA\Get(
        path: '/api/inscription/niveaux/{id}',
        tags: ['Niveaux'],
        summary: 'Afficher un niveau',
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer', example: 1)),
        ],
        responses: [new OA\Response(response: 200, description: 'Detail du niveau')]
    )]
    public function niveauShow() {}

    #[OA\Put(
        path: '/api/inscription/niveaux/{id}',
        tags: ['Niveaux'],
        summary: 'Modifier un niveau',
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer', example: 1)),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'cycle', type: 'string', example: 'lycee'),
                    new OA\Property(property: 'nom_niveau', type: 'string', example: 'Terminale'),
                ]
            )
        ),
        responses: [new OA\Response(response: 200, description: 'Niveau mis a jour')]
    )]
    public function niveauUpdate() {}

    #[OA\Delete(
        path: '/api/inscription/niveaux/{id}',
        tags: ['Niveaux'],
        summary: 'Supprimer un niveau',
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer', example: 1)),
        ],
        responses: [new OA\Response(response: 200, description: 'Niveau supprime')]
    )]
    public function niveauDestroy() {}

    #[OA\Get(
        path: '/api/inscription/classes',
        tags: ['Classes'],
        summary: 'Lister les classes',
        security: [['bearerAuth' => []]],
        responses: [new OA\Response(response: 200, description: 'Liste des classes')]
    )]
    public function classeIndex() {}

    #[OA\Get(
        path: '/api/inscription/classes/cycle/{cycle}',
        tags: ['Classes'],
        summary: 'Lister les classes par cycle',
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'cycle', in: 'path', required: true, schema: new OA\Schema(type: 'string', example: 'college')),
        ],
        responses: [new OA\Response(response: 200, description: 'Liste des classes du cycle')]
    )]
    public function classeByCycle() {}

    #[OA\Post(
        path: '/api/inscription/classes',
        tags: ['Classes'],
        summary: 'Creer une classe',
        security: [['bearerAuth' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['nom_classe', 'niveau_id', 'code_division', 'anneeScolaire_id'],
                properties: [
                    new OA\Property(property: 'nom_classe', type: 'string', example: '6eme A'),
                    new OA\Property(property: 'niveau_id', type: 'integer', example: 6),
                    new OA\Property(property: 'code_division', type: 'string', example: 'A'),
                    new OA\Property(property: 'anneeScolaire_id', type: 'integer', example: 1),
                ]
            )
        ),
        responses: [new OA\Response(response: 201, description: 'Classe creee')]
    )]
    public function classeStore() {}

    #[OA\Get(
        path: '/api/inscription/classes/{id}',
        tags: ['Classes'],
        summary: 'Afficher une classe',
        security: [['bearerAuth' => []]],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer', example: 1))],
        responses: [new OA\Response(response: 200, description: 'Detail de la classe')]
    )]
    public function classeShow() {}

    #[OA\Put(
        path: '/api/inscription/classes/{id}',
        tags: ['Classes'],
        summary: 'Modifier une classe',
        security: [['bearerAuth' => []]],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer', example: 1))],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'nom_classe', type: 'string'),
                    new OA\Property(property: 'niveau_id', type: 'integer'),
                    new OA\Property(property: 'code_division', type: 'string'),
                    new OA\Property(property: 'effectif', type: 'integer'),
                ]
            )
        ),
        responses: [new OA\Response(response: 200, description: 'Classe mise a jour')]
    )]
    public function classeUpdate() {}

    #[OA\Delete(
        path: '/api/inscription/classes/{id}',
        tags: ['Classes'],
        summary: 'Supprimer une classe',
        security: [['bearerAuth' => []]],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer', example: 1))],
        responses: [new OA\Response(response: 200, description: 'Classe supprimee')]
    )]
    public function classeDestroy() {}

    #[OA\Get(
        path: '/api/inscription/frais/types',
        tags: ['Types de Frais'],
        summary: 'Lister les types de frais',
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'annee_scolaire_id', in: 'query', required: false, schema: new OA\Schema(type: 'integer', example: 2)),
        ],
        responses: [new OA\Response(response: 200, description: 'Liste des types de frais')]
    )]
    public function fraisTypeIndex() {}

    #[OA\Post(
        path: '/api/inscription/frais/types',
        tags: ['Types de Frais'],
        summary: 'Creer un type de frais',
        security: [['bearerAuth' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['annee_scolaire_id', 'libelle', 'montant', 'est_obligatoire'],
                properties: [
                    new OA\Property(property: 'annee_scolaire_id', type: 'integer', example: 2),
                    new OA\Property(property: 'libelle', type: 'string', example: 'Cantine'),
                    new OA\Property(property: 'montant', type: 'number', format: 'float', example: 75000),
                    new OA\Property(property: 'est_obligatoire', type: 'boolean', example: false),
                ]
            )
        ),
        responses: [new OA\Response(response: 201, description: 'Type de frais cree')]
    )]
    public function fraisTypeStore() {}

    #[OA\Put(
        path: '/api/inscription/frais/types/{id}',
        tags: ['Types de Frais'],
        summary: 'Modifier un type de frais',
        security: [['bearerAuth' => []]],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer', example: 1))],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'annee_scolaire_id', type: 'integer', example: 2),
                    new OA\Property(property: 'libelle', type: 'string'),
                    new OA\Property(property: 'montant', type: 'number', format: 'float'),
                    new OA\Property(property: 'est_obligatoire', type: 'boolean'),
                ]
            )
        ),
        responses: [new OA\Response(response: 200, description: 'Type de frais mis a jour')]
    )]
    public function fraisTypeUpdate() {}

    #[OA\Delete(
        path: '/api/inscription/frais/types/{id}',
        tags: ['Types de Frais'],
        summary: 'Supprimer un type de frais',
        security: [['bearerAuth' => []]],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer', example: 1))],
        responses: [new OA\Response(response: 200, description: 'Type de frais supprime')]
    )]
    public function fraisTypeDestroy() {}

    #[OA\Get(
        path: '/api/inscription',
        tags: ['Inscription'],
        summary: 'Lister les inscriptions (Repertoire)',
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'annee_scolaire_id', in: 'query', required: false, schema: new OA\Schema(type: 'integer', example: 2)),
            new OA\Parameter(name: 'classe_id', in: 'query', required: false, schema: new OA\Schema(type: 'integer', example: 12)),
            new OA\Parameter(name: 'niveau_id', in: 'query', required: false, schema: new OA\Schema(type: 'integer', example: 6)),
            new OA\Parameter(name: 'statut_paiement', in: 'query', required: false, schema: new OA\Schema(type: 'string', enum: ['paye', 'non_paye'], example: 'non_paye')),
        ],
        responses: [new OA\Response(response: 200, description: 'Liste des inscriptions')]
    )]
    public function inscriptionIndex() {}

    #[OA\Get(
        path: '/api/inscription/{id}/infos-dynamiques',
        tags: ['Inscription'],
        summary: 'Recuperer les informations dynamiques d une inscription',
        security: [['bearerAuth' => []]],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer', example: 1))],
        responses: [
            new OA\Response(response: 200, description: 'Informations dynamiques'),
            new OA\Response(response: 404, description: 'Inscription non trouvee'),
        ]
    )]
    public function inscriptionDynamicInfos() {}

    #[OA\Get(
        path: '/api/inscription/{inscriptionId}/paiements',
        tags: ['Paiements'],
        summary: 'Lister les paiements d une inscription',
        security: [['bearerAuth' => []]],
        parameters: [new OA\Parameter(name: 'inscriptionId', in: 'path', required: true, schema: new OA\Schema(type: 'integer', example: 1))],
        responses: [new OA\Response(response: 200, description: 'Liste des paiements')]
    )]
    public function paiementIndex() {}

    #[OA\Post(
        path: '/api/inscription/{inscriptionId}/paiements',
        tags: ['Paiements'],
        summary: 'Enregistrer un paiement',
        security: [['bearerAuth' => []]],
        parameters: [new OA\Parameter(name: 'inscriptionId', in: 'path', required: true, schema: new OA\Schema(type: 'integer', example: 1))],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['montant', 'date_paiement'],
                properties: [
                    new OA\Property(property: 'montant', type: 'number', format: 'float', example: 150000),
                    new OA\Property(property: 'date_paiement', type: 'string', format: 'date', example: '2026-04-21'),
                    new OA\Property(property: 'reference', type: 'string', example: 'PAY-123456'),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 201, description: 'Paiement enregistre'),
            new OA\Response(response: 422, description: 'Erreur de validation'),
        ]
    )]
    public function paiementStore() {}

    #[OA\Get(
        path: '/api/inscription/paiements/{id}',
        tags: ['Paiements'],
        summary: 'Afficher un paiement',
        security: [['bearerAuth' => []]],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer', example: 1))],
        responses: [new OA\Response(response: 200, description: 'Detail du paiement')]
    )]
    public function paiementShow() {}

    #[OA\Delete(
        path: '/api/inscription/paiements/{id}',
        tags: ['Paiements'],
        summary: 'Supprimer un paiement',
        security: [['bearerAuth' => []]],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer', example: 1))],
        responses: [new OA\Response(response: 200, description: 'Paiement supprime')]
    )]
    public function paiementDestroy() {}

    #[OA\Get(
        path: '/api/matieres',
        tags: ['Matieres'],
        summary: 'Lister les matieres',
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'classe_id', in: 'query', required: false, schema: new OA\Schema(type: 'integer', example: 12)),
            new OA\Parameter(name: 'niveau_id', in: 'query', required: false, schema: new OA\Schema(type: 'integer', example: 6)),
            new OA\Parameter(name: 'cycle', in: 'query', required: false, schema: new OA\Schema(type: 'string', example: 'college')),
        ],
        responses: [new OA\Response(response: 200, description: 'Liste des matieres')]
    )]
    public function matiereIndex() {}

    #[OA\Post(
        path: '/api/matieres',
        tags: ['Matieres'],
        summary: 'Creer une matiere',
        security: [['bearerAuth' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['nom', 'coefficient', 'classe_id'],
                properties: [
                    new OA\Property(property: 'nom', type: 'string', example: 'Mathematiques'),
                    new OA\Property(property: 'coefficient', type: 'integer', example: 4),
                    new OA\Property(property: 'classe_id', type: 'integer', example: 12),
                ]
            )
        ),
        responses: [new OA\Response(response: 201, description: 'Matiere creee')]
    )]
    public function matiereStore() {}

    #[OA\Post(
        path: '/api/matieres/multiple',
        tags: ['Matieres'],
        summary: 'Creer plusieurs matieres',
        security: [['bearerAuth' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['matieres'],
                properties: [
                    new OA\Property(
                        property: 'matieres',
                        type: 'array',
                        items: new OA\Items(
                            properties: [
                                new OA\Property(property: 'nom', type: 'string', example: 'Francais'),
                                new OA\Property(property: 'coefficient', type: 'integer', example: 4),
                                new OA\Property(property: 'classe_id', type: 'integer', example: 12),
                            ]
                        )
                    ),
                ]
            )
        ),
        responses: [new OA\Response(response: 200, description: 'Matieres ajoutees')]
    )]
    public function matiereStoreMultiple() {}

    #[OA\Get(
        path: '/api/matieres/{id}',
        tags: ['Matieres'],
        summary: 'Afficher une matiere',
        security: [['bearerAuth' => []]],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer', example: 1))],
        responses: [
            new OA\Response(response: 200, description: 'Detail de la matiere'),
            new OA\Response(response: 404, description: 'Matiere non trouvee'),
        ]
    )]
    public function matiereShow() {}

    #[OA\Put(
        path: '/api/matieres/{id}',
        tags: ['Matieres'],
        summary: 'Modifier une matiere',
        security: [['bearerAuth' => []]],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer', example: 1))],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'nom', type: 'string'),
                    new OA\Property(property: 'coefficient', type: 'integer'),
                    new OA\Property(property: 'classe_id', type: 'integer'),
                ]
            )
        ),
        responses: [new OA\Response(response: 200, description: 'Matiere mise a jour')]
    )]
    public function matiereUpdate() {}

    #[OA\Delete(
        path: '/api/matieres/{id}',
        tags: ['Matieres'],
        summary: 'Supprimer une matiere',
        security: [['bearerAuth' => []]],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer', example: 1))],
        responses: [new OA\Response(response: 200, description: 'Matiere supprimee')]
    )]
    public function matiereDestroy() {}

    #[OA\Get(
        path: '/api/matieres/suggestions/{cycle}',
        tags: ['Matieres'],
        summary: 'Recuperer les suggestions de matieres par cycle',
        security: [['bearerAuth' => []]],
        parameters: [new OA\Parameter(name: 'cycle', in: 'path', required: true, schema: new OA\Schema(type: 'string', example: 'college'))],
        responses: [
            new OA\Response(response: 200, description: 'Suggestions disponibles'),
            new OA\Response(response: 400, description: 'Cycle invalide'),
        ]
    )]
    public function matiereSuggestions() {}

    #[OA\Get(
        path: '/api/notes/moyenne/{inscriptionId}/{periode}',
        tags: ['Notes'],
        summary: 'Calculer la moyenne generale',
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'inscriptionId', in: 'path', required: true, schema: new OA\Schema(type: 'integer', example: 1)),
            new OA\Parameter(name: 'periode', in: 'path', required: true, schema: new OA\Schema(type: 'string', example: 'TRIMESTRE_1')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Moyenne calculee'),
            new OA\Response(response: 404, description: 'Inscription non trouvee'),
        ]
    )]
    public function noteMoyenne() {}

    #[OA\Get(
        path: '/api/detail-bulletins/bulletin/{bulletinId}',
        tags: ['Detail Bulletins'],
        summary: 'Lister les details d un bulletin',
        security: [['bearerAuth' => []]],
        parameters: [new OA\Parameter(name: 'bulletinId', in: 'path', required: true, schema: new OA\Schema(type: 'integer', example: 1))],
        responses: [new OA\Response(response: 200, description: 'Details du bulletin')]
    )]
    public function detailBulletinIndex() {}

    #[OA\Put(
        path: '/api/detail-bulletins/{id}',
        tags: ['Detail Bulletins'],
        summary: 'Modifier un detail de bulletin',
        security: [['bearerAuth' => []]],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer', example: 1))],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'moyenne_matiere', type: 'number', format: 'float', example: 14.5),
                    new OA\Property(property: 'rang_matiere', type: 'integer', example: 3),
                    new OA\Property(property: 'appreciation', type: 'string', example: 'Bon travail'),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Detail du bulletin mis a jour'),
            new OA\Response(response: 422, description: 'Erreur de validation'),
        ]
    )]
    public function detailBulletinUpdate() {}

}
