<?php

namespace App\Swagger;

use OpenApi\Attributes as OA;

class DashboardSwagger
{
    #[OA\Get(
        path: "/api/caissier/dashboard",
        tags: ["Dashboard"],
        summary: "Tableau de bord du caissier",
        description: "Retourne les statistiques du dashboard, les transactions recentes et un historique recent incluant inscriptions, notes et bulletins.",
        security: [["bearerAuth" => []]],
        responses: [
            new OA\Response(response: 200, description: "Succes"),
            new OA\Response(response: 401, description: "Non authentifie")
        ]
    )]
    public function dashboard() {}

    #[OA\Get(
        path: "/api/dashboard/recapitulatif/annees-scolaires",
        tags: ["Dashboard"],
        summary: "Lister les annees scolaires pour le recapitulatif",
        security: [["bearerAuth" => []]],
        responses: [
            new OA\Response(response: 200, description: "Liste des annees scolaires")
        ]
    )]
    public function recapAnneesScolaires() {}

    #[OA\Get(
        path: "/api/dashboard/recapitulatif/annees-scolaires/{anneeId}/classes",
        tags: ["Dashboard"],
        summary: "Lister les classes d une annee scolaire",
        security: [["bearerAuth" => []]],
        parameters: [
            new OA\Parameter(name: "anneeId", in: "path", required: true, schema: new OA\Schema(type: "integer", example: 1)),
            new OA\Parameter(name: "niveau_id", in: "query", required: false, schema: new OA\Schema(type: "integer", example: 2))
        ],
        responses: [
            new OA\Response(response: 200, description: "Classes recuperees")
        ]
    )]
    public function recapClasses() {}

    #[OA\Get(
        path: "/api/dashboard/recapitulatif/annees-scolaires/{anneeId}/classes/{classeId}",
        tags: ["Dashboard"],
        summary: "Afficher le detail d une classe pour une annee scolaire",
        security: [["bearerAuth" => []]],
        parameters: [
            new OA\Parameter(name: "anneeId", in: "path", required: true, schema: new OA\Schema(type: "integer", example: 1)),
            new OA\Parameter(name: "classeId", in: "path", required: true, schema: new OA\Schema(type: "integer", example: 3))
        ],
        responses: [
            new OA\Response(response: 200, description: "Detail de la classe")
        ]
    )]
    public function recapClasseDetail() {}

    #[OA\Get(
        path: "/api/dashboard/recapitulatif/annees-scolaires/{anneeId}/statistiques",
        tags: ["Dashboard"],
        summary: "Consulter les statistiques d une annee scolaire",
        security: [["bearerAuth" => []]],
        parameters: [
            new OA\Parameter(name: "anneeId", in: "path", required: true, schema: new OA\Schema(type: "integer", example: 1)),
            new OA\Parameter(name: "niveau_id", in: "query", required: false, schema: new OA\Schema(type: "integer")),
            new OA\Parameter(name: "classe_id", in: "query", required: false, schema: new OA\Schema(type: "integer"))
        ],
        responses: [
            new OA\Response(response: 200, description: "Statistiques recuperees")
        ]
    )]
    public function recapStatistiques() {}

    #[OA\Get(
        path: "/api/dashboard/recapitulatif/annees-scolaires/{anneeId}/finance",
        tags: ["Dashboard"],
        summary: "Consulter le recapitulatif financier d une annee scolaire",
        security: [["bearerAuth" => []]],
        parameters: [
            new OA\Parameter(name: "anneeId", in: "path", required: true, schema: new OA\Schema(type: "integer", example: 1))
        ],
        responses: [
            new OA\Response(response: 200, description: "Recapitulatif financier recupere")
        ]
    )]
    public function recapFinance() {}

    #[OA\Get(
        path: "/api/dashboard/recapitulatif/annees-scolaires/{anneeId}/journal-caisse",
        tags: ["Dashboard"],
        summary: "Consulter le journal de caisse d une journee",
        security: [["bearerAuth" => []]],
        parameters: [
            new OA\Parameter(name: "anneeId", in: "path", required: true, schema: new OA\Schema(type: "integer", example: 1)),
            new OA\Parameter(name: "date", in: "query", required: false, schema: new OA\Schema(type: "string", format: "date", example: "2026-04-23")),
            new OA\Parameter(name: "nom", in: "query", required: false, schema: new OA\Schema(type: "string", example: "Rakoto")),
            new OA\Parameter(name: "email", in: "query", required: false, schema: new OA\Schema(type: "string", example: "caissier@example.com"))
        ],
        responses: [
            new OA\Response(response: 200, description: "Journal de caisse recupere")
        ]
    )]
    public function recapJournalCaisse() {}

    #[OA\Get(
        path: "/api/dashboard/recapitulatif/annees-scolaires/{anneeId}/recherche-etudiant",
        tags: ["Dashboard"],
        summary: "Rechercher un etudiant dans une annee scolaire",
        security: [["bearerAuth" => []]],
        parameters: [
            new OA\Parameter(name: "anneeId", in: "path", required: true, schema: new OA\Schema(type: "integer", example: 1)),
            new OA\Parameter(name: "nom", in: "query", required: false, schema: new OA\Schema(type: "string", example: "Rakoto")),
            new OA\Parameter(name: "matricule", in: "query", required: false, schema: new OA\Schema(type: "string", example: "ETU001"))
        ],
        responses: [
            new OA\Response(response: 200, description: "Liste des etudiants trouves")
        ]
    )]
    public function recapRechercheEtudiant() {}
}
