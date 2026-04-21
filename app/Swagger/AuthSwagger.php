<?php

namespace App\Swagger;

use OpenApi\Attributes as OA;

class AuthSwagger
{
    #[OA\Post(
        path: '/api/login',
        tags: ['Auth'],
        summary: 'Connexion utilisateur',
        description: 'Connexion utilisateur et generation d un token Sanctum',
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['email', 'password'],
                properties: [
                    new OA\Property(property: 'email', type: 'string', example: 'test@mail.com'),
                    new OA\Property(property: 'password', type: 'string', example: '123456'),
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Connexion reussie',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(property: 'message', type: 'string', example: 'Connexion reussie'),
                        new OA\Property(property: 'token', type: 'string', example: '1|sanctum_token_exemple'),
                        new OA\Property(property: 'token_type', type: 'string', example: 'Bearer'),
                        new OA\Property(
                            property: 'user',
                            type: 'object',
                            properties: [
                                new OA\Property(property: 'id', type: 'integer', example: 1),
                                new OA\Property(property: 'nom', type: 'string', example: 'Rakoto'),
                                new OA\Property(property: 'prenom', type: 'string', example: 'Jean'),
                                new OA\Property(property: 'email', type: 'string', example: 'jean@example.com'),
                                new OA\Property(property: 'telephone', type: 'string', nullable: true, example: '0340011223'),
                                new OA\Property(property: 'role', type: 'string', example: 'admin'),
                                new OA\Property(property: 'status', type: 'string', example: 'actif'),
                            ]
                        ),
                    ]
                )
            ),
            new OA\Response(response: 401, description: 'Erreur authentification'),
            new OA\Response(response: 422, description: 'Erreur validation'),
        ]
    )]
    public function login() {}

    #[OA\Post(
        path: '/api/register',
        tags: ['Auth'],
        summary: 'Inscription utilisateur',
        description: 'Creer un nouvel utilisateur',
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['nom', 'prenom', 'email', 'password', 'password_confirmation'],
                properties: [
                    new OA\Property(property: 'nom', type: 'string'),
                    new OA\Property(property: 'prenom', type: 'string'),
                    new OA\Property(property: 'telephone', type: 'string', nullable: true),
                    new OA\Property(property: 'email', type: 'string'),
                    new OA\Property(property: 'password', type: 'string'),
                    new OA\Property(property: 'password_confirmation', type: 'string'),
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 201,
                description: 'Utilisateur cree',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'message', type: 'string'),
                        new OA\Property(property: 'user', type: 'object'),
                    ]
                )
            ),
            new OA\Response(response: 422, description: 'Erreur validation'),
        ]
    )]
    public function register() {}

    #[OA\Get(
        path: '/api/user',
        tags: ['Auth'],
        summary: 'Recuperer l utilisateur connecte',
        description: 'Retourne les informations de l utilisateur authentifie avec Sanctum',
        security: [['bearerAuth' => []]],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Utilisateur connecte recupere avec succes',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(property: 'message', type: 'string', example: 'Utilisateur connecte recupere avec succes'),
                        new OA\Property(
                            property: 'user',
                            type: 'object',
                            properties: [
                                new OA\Property(property: 'id', type: 'integer', example: 1),
                                new OA\Property(property: 'nom', type: 'string', example: 'Rakoto'),
                                new OA\Property(property: 'prenom', type: 'string', example: 'Jean'),
                                new OA\Property(property: 'email', type: 'string', example: 'jean@example.com'),
                                new OA\Property(property: 'telephone', type: 'string', nullable: true, example: '0340011223'),
                                new OA\Property(property: 'role', type: 'string', example: 'admin'),
                                new OA\Property(property: 'status', type: 'string', example: 'actif'),
                                new OA\Property(property: 'full_name', type: 'string', example: 'Rakoto Jean'),
                            ]
                        ),
                    ]
                )
            ),
            new OA\Response(response: 401, description: 'Non authentifie'),
        ]
    )]
    public function user() {}

    #[OA\Post(
        path: '/api/logout',
        tags: ['Auth'],
        summary: 'Deconnexion utilisateur',
        description: 'Revocation du token Sanctum courant',
        security: [['bearerAuth' => []]],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Deconnexion reussie',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(property: 'message', type: 'string', example: 'Deconnexion reussie'),
                        new OA\Property(
                            property: 'data',
                            type: 'object',
                            properties: [
                                new OA\Property(property: 'user_id', type: 'integer', example: 1),
                                new OA\Property(property: 'user_email', type: 'string', example: 'jean@example.com'),
                            ]
                        ),
                    ]
                )
            ),
            new OA\Response(response: 401, description: 'Non authentifie'),
            new OA\Response(response: 500, description: 'Erreur serveur'),
        ]
    )]
    public function logout() {}
}
