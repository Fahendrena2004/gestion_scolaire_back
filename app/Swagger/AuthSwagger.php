<?php

namespace App\Swagger;

use OpenApi\Attributes as OA;

class AuthSwagger
{
    // ===================== LOGIN =====================
    #[OA\Post(
        path: '/api/login',
        tags: ['Auth'],
        summary: 'Connexion utilisateur',
        description: 'Connexion et création de session Laravel',
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
                description: 'Connexion réussie',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(property: 'message', type: 'string', example: 'Connexion réussie'),
                        new OA\Property(
                            property: 'user',
                            type: 'object',
                            properties: [
                                new OA\Property(property: 'id', type: 'integer'),
                                new OA\Property(property: 'nom', type: 'string'),
                                new OA\Property(property: 'prenom', type: 'string'),
                                new OA\Property(property: 'email', type: 'string'),
                                new OA\Property(property: 'role', type: 'string'),
                                new OA\Property(property: 'status', type: 'string'),
                            ]
                        ),
                        new OA\Property(property: 'session_id', type: 'string'),
                    ]
                )
            ),
            new OA\Response(response: 401, description: 'Erreur authentification'),
            new OA\Response(response: 422, description: 'Erreur validation'),
        ]
    )]
    public function login() {}



    // ===================== REGISTER =====================
    #[OA\Post(
        path: '/api/register',
        tags: ['Auth'],
        summary: 'Inscription utilisateur',
        description: 'Créer un nouvel utilisateur',
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
                description: 'Utilisateur créé',
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



    // ===================== LOGOUT =====================
    #[OA\Post(
        path: '/api/logout',
        tags: ['Auth'],
        summary: 'Déconnexion utilisateur',
        description: 'Suppression de la session utilisateur',
        responses: [
            new OA\Response(
                response: 200,
                description: 'Déconnexion réussie',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(property: 'message', type: 'string', example: 'Déconnexion réussie'),
                        new OA\Property(
                            property: 'data',
                            type: 'object',
                            properties: [
                                new OA\Property(property: 'user_id', type: 'integer'),
                                new OA\Property(property: 'user_email', type: 'string'),
                                new OA\Property(property: 'session_id', type: 'string'),
                            ]
                        ),
                    ]
                )
            ),
            new OA\Response(response: 500, description: 'Erreur serveur'),
        ]
    )]
    public function logout() {}
}