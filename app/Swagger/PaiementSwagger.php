<?php

namespace App\Swagger;

use OpenApi\Attributes as OA;

class PaiementSwagger
{
    #[OA\Get(
        path: '/api/cantine/filtres',
        tags: ['Paiements - Cantine'],
        summary: 'Recuperer les filtres de paiement cantine',
        security: [['bearerAuth' => []]],
        responses: [
            new OA\Response(response: 200, description: 'Filtres recuperes avec succes'),
            new OA\Response(response: 401, description: 'Non authentifie'),
        ]
    )]
    public function cantineFiltres() {}

    #[OA\Get(
        path: '/api/cantine/eleves',
        tags: ['Paiements - Cantine'],
        summary: 'Filtrer les eleves pour le paiement',
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'classe_id', in: 'query', required: false, schema: new OA\Schema(type: 'integer', example: 12)),
            new OA\Parameter(name: 'niveau_id', in: 'query', required: false, schema: new OA\Schema(type: 'integer', example: 6)),
            new OA\Parameter(name: 'nom', in: 'query', required: false, schema: new OA\Schema(type: 'string', example: 'Rakoto')),
            new OA\Parameter(name: 'matricule', in: 'query', required: false, schema: new OA\Schema(type: 'string', example: 'REG-2025-0001')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Resultats de filtrage'),
            new OA\Response(response: 401, description: 'Non authentifie'),
        ]
    )]
    public function cantineEleves() {}

    #[OA\Post(
        path: '/api/cantine/presence',
        tags: ['Paiements - Cantine'],
        summary: 'Marquer la presence cantine',
        security: [['bearerAuth' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['inscription_id', 'date', 'est_present'],
                properties: [
                    new OA\Property(property: 'inscription_id', type: 'integer', example: 1),
                    new OA\Property(property: 'date', type: 'string', format: 'date', example: '2026-04-21'),
                    new OA\Property(property: 'est_present', type: 'boolean', example: true),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Presence enregistree'),
            new OA\Response(response: 422, description: 'Erreur de validation'),
        ]
    )]
    public function cantinePresence() {}

    #[OA\Get(
        path: '/api/cantine/mois-disponibles/{inscriptionId}',
        tags: ['Paiements - Cantine'],
        summary: 'Lister les mois de cantine disponibles',
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'inscriptionId', in: 'path', required: true, schema: new OA\Schema(type: 'integer', example: 1)),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Mois disponibles recuperes'),
        ]
    )]
    public function cantineMoisDisponibles() {}

    #[OA\Get(
        path: '/api/cantine/jours/{inscriptionId}',
        tags: ['Paiements - Cantine'],
        summary: 'Recuperer les jours de cantine',
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'inscriptionId', in: 'path', required: true, schema: new OA\Schema(type: 'integer', example: 1)),
            new OA\Parameter(name: 'mois', in: 'query', required: false, schema: new OA\Schema(type: 'integer', example: 9)),
            new OA\Parameter(name: 'annee', in: 'query', required: false, schema: new OA\Schema(type: 'integer', example: 2026)),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Jours recuperes'),
        ]
    )]
    public function cantineJours() {}

    #[OA\Post(
        path: '/api/cantine/payer',
        tags: ['Paiements - Cantine'],
        summary: 'Payer des jours de cantine',
        security: [['bearerAuth' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['inscription_id', 'dates'],
                properties: [
                    new OA\Property(property: 'inscription_id', type: 'integer', example: 1),
                    new OA\Property(property: 'dates', type: 'array', items: new OA\Items(type: 'string', format: 'date', example: '2026-04-21')),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Paiement effectue'),
            new OA\Response(response: 422, description: 'Aucun jour valide selectionne'),
        ]
    )]
    public function cantinePayer() {}

    #[OA\Get(
        path: '/api/autres-frais/{inscriptionId}',
        tags: ['Paiements - Autres Frais'],
        summary: 'Recuperer les autres frais a payer',
        description: 'Retourne les frais appliques hors cantine et hors scolarite avec leurs montants payes/restants.',
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'inscriptionId', in: 'path', required: true, schema: new OA\Schema(type: 'integer', example: 1)),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Frais recuperes'),
            new OA\Response(response: 404, description: 'Inscription non trouvee'),
        ]
    )]
    public function autresFraisListe() {}

    #[OA\Post(
        path: '/api/autres-frais/payer',
        tags: ['Paiements - Autres Frais'],
        summary: 'Payer des frais selectionnes',
        security: [['bearerAuth' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['inscription_id', 'frais_ids'],
                properties: [
                    new OA\Property(property: 'inscription_id', type: 'integer', example: 1),
                    new OA\Property(property: 'frais_ids', type: 'array', items: new OA\Items(type: 'integer', example: 25)),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Paiement effectue'),
            new OA\Response(response: 422, description: 'Aucun frais valide selectionne'),
        ]
    )]
    public function autresFraisPayer() {}

    #[OA\Get(
        path: '/api/scolarite/mois/{inscriptionId}',
        tags: ['Paiements - Scolarite'],
        summary: 'Recuperer les mois de scolarite',
        description: 'Retourne les mois generes dans paiements_mensuels avec leur statut paye/non paye.',
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'inscriptionId', in: 'path', required: true, schema: new OA\Schema(type: 'integer', example: 1)),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Mois recuperes'),
            new OA\Response(response: 404, description: 'Inscription non trouvee'),
        ]
    )]
    public function scolariteMois() {}

    #[OA\Post(
        path: '/api/scolarite/payer',
        tags: ['Paiements - Scolarite'],
        summary: 'Payer des mois de scolarite selectionnes',
        security: [['bearerAuth' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['inscription_id', 'mois'],
                properties: [
                    new OA\Property(property: 'inscription_id', type: 'integer', example: 1),
                    new OA\Property(
                        property: 'mois',
                        type: 'array',
                        items: new OA\Items(
                            properties: [
                                new OA\Property(property: 'mois', type: 'integer', example: 11),
                                new OA\Property(property: 'annee', type: 'integer', example: 2026),
                            ]
                        )
                    ),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Paiement enregistre'),
            new OA\Response(response: 422, description: 'Aucun mois valide selectionne'),
        ]
    )]
    public function scolaritePayer() {}

    #[OA\Post(
        path: '/api/scolarite/payer-tout/{inscriptionId}',
        tags: ['Paiements - Scolarite'],
        summary: 'Payer tous les mois restants de scolarite',
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'inscriptionId', in: 'path', required: true, schema: new OA\Schema(type: 'integer', example: 1)),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Paiement total effectue'),
            new OA\Response(response: 422, description: 'Aucun mois valide selectionne'),
        ]
    )]
    public function scolaritePayerTout() {}

    #[OA\Get(
        path: '/api/scolarite/historique/{inscriptionId}',
        tags: ['Paiements - Scolarite'],
        summary: 'Consulter l historique des paiements de scolarite',
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'inscriptionId', in: 'path', required: true, schema: new OA\Schema(type: 'integer', example: 1)),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Historique recupere'),
        ]
    )]
    public function scolariteHistorique() {}
}
