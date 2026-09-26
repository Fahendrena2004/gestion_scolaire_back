<?php

namespace App\Swagger;

use OpenApi\Attributes as OA;

class ReinscriptionSwagger
{
    #[OA\Get(
        path: '/api/reinscriptions/rechercher',
        tags: ['Reinscription'],
        summary: 'Rechercher un eleve par matricule pour la reinscription',
        description: 'Retourne les informations de l eleve, sa derniere inscription, sa classe actuelle, une classe superieure proposee et le statut de reinscription pour l annee scolaire demandee.',
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(
                name: 'matricule',
                in: 'query',
                required: true,
                description: 'Matricule de l eleve',
                schema: new OA\Schema(type: 'string', example: 'REG-2025-0001')
            ),
            new OA\Parameter(
                name: 'annee_scolaire_id',
                in: 'query',
                required: true,
                description: 'ID de l annee scolaire cible',
                schema: new OA\Schema(type: 'integer', example: 2)
            ),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Eleve trouve',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(
                            property: 'eleve',
                            properties: [
                                new OA\Property(property: 'id', type: 'integer', example: 1),
                                new OA\Property(property: 'matricule', type: 'string', example: 'REG-2025-0001'),
                                new OA\Property(property: 'nom', type: 'string', example: 'Rakoto'),
                                new OA\Property(property: 'prenom', type: 'string', example: 'Fara'),
                                new OA\Property(property: 'sexe', type: 'string', nullable: true, example: 'F'),
                                new OA\Property(property: 'date_naissance', type: 'string', format: 'date', nullable: true, example: '2015-03-20'),
                                new OA\Property(property: 'lieu_naissance', type: 'string', nullable: true, example: 'Antananarivo'),
                            ]
                        ),
                        new OA\Property(
                            property: 'derniere_inscription',
                            nullable: true,
                            properties: [
                                new OA\Property(property: 'id', type: 'integer', example: 8),
                                new OA\Property(property: 'annee_scolaire', type: 'string', nullable: true, example: '2025-09-01 - 2026-06-30'),
                                new OA\Property(property: 'classe', type: 'string', nullable: true, example: '6eme A'),
                                new OA\Property(property: 'classe_id', type: 'integer', nullable: true, example: 12),
                                new OA\Property(property: 'montant_total', type: 'number', format: 'float', nullable: true, example: 540000),
                                new OA\Property(property: 'date_inscription', type: 'string', format: 'date', nullable: true, example: '2025-09-03'),
                            ]
                        ),
                        new OA\Property(
                            property: 'classe_actuelle',
                            nullable: true,
                            properties: [
                                new OA\Property(property: 'id', type: 'integer', example: 12),
                                new OA\Property(property: 'nom', type: 'string', example: '6eme A'),
                                new OA\Property(property: 'niveau_id', type: 'integer', example: 6),
                                new OA\Property(property: 'niveau', type: 'string', nullable: true, example: '6eme'),
                            ]
                        ),
                        new OA\Property(
                            property: 'classe_superieure_proposee',
                            nullable: true,
                            properties: [
                                new OA\Property(property: 'id', type: 'integer', example: 14),
                                new OA\Property(property: 'nom', type: 'string', example: '5eme A'),
                            ]
                        ),
                        new OA\Property(property: 'deja_reinscrit', type: 'boolean', example: false),
                    ]
                )
            ),
            new OA\Response(response: 404, description: 'Eleve non trouve'),
            new OA\Response(response: 422, description: 'Erreur de validation'),
        ]
    )]
    public function rechercher() {}

    #[OA\Get(
        path: '/api/reinscriptions',
        tags: ['Reinscription'],
        summary: 'Lister les reinscriptions',
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'annee_scolaire_id', in: 'query', required: false, schema: new OA\Schema(type: 'integer', example: 2)),
            new OA\Parameter(name: 'classe_id', in: 'query', required: false, schema: new OA\Schema(type: 'integer', example: 14)),
            new OA\Parameter(name: 'statut', in: 'query', required: false, schema: new OA\Schema(type: 'string', enum: ['Passant', 'Redoublant'], example: 'Passant')),
            new OA\Parameter(name: 'matricule', in: 'query', required: false, schema: new OA\Schema(type: 'string', example: 'REG-2025-0001')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Liste paginee des reinscriptions'),
            new OA\Response(response: 401, description: 'Non authentifie'),
        ]
    )]
    public function index() {}

    #[OA\Post(
        path: '/api/reinscriptions',
        tags: ['Reinscription'],
        summary: 'Creer une reinscription',
        description: 'La reinscription cree egalement une nouvelle inscription pour la nouvelle annee scolaire. Les notes, paiements, frais appliques et resume de paiement se gerent ensuite via nouvelle_inscription_id comme une inscription normale.',
        security: [['bearerAuth' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['matricule', 'inscription_id', 'annee_scolaire_id', 'classe_id', 'statut', 'date_reinscription'],
                properties: [
                    new OA\Property(property: 'matricule', type: 'string', example: 'REG-2025-0001'),
                    new OA\Property(property: 'inscription_id', type: 'integer', example: 8),
                    new OA\Property(property: 'annee_scolaire_id', type: 'integer', example: 2),
                    new OA\Property(property: 'classe_id', type: 'integer', example: 14),
                    new OA\Property(property: 'statut', type: 'string', enum: ['Passant', 'Redoublant'], example: 'Passant'),
                    new OA\Property(property: 'parascolaire', type: 'boolean', nullable: true, example: true),
                    new OA\Property(property: 'cantine', type: 'boolean', nullable: true, example: false),
                    new OA\Property(property: 'montant_verse', type: 'number', format: 'float', nullable: true, example: 150000),
                    new OA\Property(property: 'date_reinscription', type: 'string', format: 'date', example: '2026-09-05'),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 201, description: 'Reinscription enregistree'),
            new OA\Response(response: 409, description: 'Eleve deja reinscrit pour cette annee scolaire'),
            new OA\Response(response: 422, description: 'Erreur de validation'),
            new OA\Response(response: 500, description: 'Erreur serveur'),
        ]
    )]
    public function store() {}

    #[OA\Get(
        path: '/api/reinscriptions/{id}',
        tags: ['Reinscription'],
        summary: 'Afficher une reinscription',
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer', example: 1)),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Detail de la reinscription'),
            new OA\Response(response: 404, description: 'Reinscription non trouvee'),
        ]
    )]
    public function show() {}

    #[OA\Put(
        path: '/api/reinscriptions/{id}/paiement',
        tags: ['Reinscription'],
        summary: 'Mettre a jour le statut de paiement d une reinscription',
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer', example: 1)),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Statut de paiement mis a jour'),
            new OA\Response(response: 404, description: 'Reinscription non trouvee'),
        ]
    )]
    public function updatePaiement() {}

    #[OA\Delete(
        path: '/api/reinscriptions/{id}',
        tags: ['Reinscription'],
        summary: 'Supprimer une reinscription',
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer', example: 1)),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Reinscription supprimee'),
            new OA\Response(response: 404, description: 'Reinscription non trouvee'),
        ]
    )]
    public function destroy() {}
}
