<?php

namespace App\Http\Controllers\Inscription;

use App\Http\Controllers\Controller;
use App\Http\Requests\InscriptionRequest;
use App\Services\Inscription\InscriptionService;
use App\Services\Inscription\EleveService;
use Illuminate\Http\JsonResponse;

class InscriptionController extends Controller
{
    protected $inscriptionService;
    protected $eleveService;

    public function __construct(
        InscriptionService $inscriptionService,
        EleveService $eleveService
    ) {
        $this->inscriptionService = $inscriptionService;
        $this->eleveService = $eleveService;
    }


    //Endpoint de faire creer Inscription

    public function store(InscriptionRequest $request): JsonResponse
    {
        try {
            $fixedFields = $request->getFixedFields();
            $inscriptionFields = $request->getInscriptionFields();
            $dynamicFields = $request->getDynamicFields();

            $result = $this->inscriptionService->processInscription(
                $fixedFields,
                $inscriptionFields,
                $dynamicFields
            );

            return response()->json($result, 201);

        } catch (\Exception $e) {
            $errorMessage = $e->getMessage();
            $statusCode = 500;

            //Erreurs spécifiques classe
            if (str_contains($errorMessage, 'Classe non trouvée')) {
                $errorMessage = 'La classe sélectionnée n\'existe pas.';
                $statusCode = 404;
            //
            //Niveau selectionne est incorrect
            } elseif (str_contains($errorMessage, 'niveau')) {
                $errorMessage = 'Le niveau sélectionné n\'existe pas.';
                $statusCode = 404;
            //verification erreur annne
            } elseif (str_contains($errorMessage, 'année scolaire')) {
                $errorMessage = 'Aucune année scolaire active.';
                $statusCode = 404;

            //Verification doublons eleve
            } elseif (str_contains($errorMessage, 'déjà inscrit')) {
                $errorMessage = 'Cet élève est déjà inscrit pour cette année scolaire.';
                $statusCode = 409;
            }

            return response()->json([
                'success' => false,
                'message' => $errorMessage,
                'error' => $e->getMessage(),
                'code' => 'INSCRIPTION_ERROR'
            ], $statusCode);
        }
    }

    // Endpoint pour récupérer les détails d'une inscription, en vérifiant que l'inscription existe et en formatant les données de manière claire et structurée
    public function show(int $id): JsonResponse
    {
        $inscription = $this->inscriptionService->getInscription($id);

        if (!$inscription) {
            return response()->json([
                'success' => false,
                'message' => 'Inscription non trouvée'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $inscription->id,
                'date_inscription' => $inscription->date_inscription,
                'eleve' => [
                    'id' => $inscription->eleve->id,
                    'nom' => $inscription->eleve->nom,
                    'prenom' => $inscription->eleve->prenom,
                    'matricule' => $inscription->eleve->matricule,
                    'date_naissance' => $inscription->eleve->date_naissance,
                    'lieu_naissance' => $inscription->eleve->lieu_naissance,
                ],
                'classe' => [
                    'id' => $inscription->classe->id,
                    'nom' => $inscription->classe->nom_classe,
                    'niveau' => $inscription->classe->niveau->nom_niveau,
                    'cycle' => $inscription->classe->niveau->cycle,
                ],
                'options' => [
                    'parascolaire' => $inscription->parascolaire,
                    'cantine' => $inscription->cantine,
                ],
                'frais' => [
                    'total' => $inscription->montant_total,
                    'net' => $inscription->montant_net,
                    'details' => $inscription->fraisAppliques->map(function($frais) {
                        return [
                            'libelle' => $frais->typeFrais->libelle,
                            'montant' => $frais->montant,
                        ];
                    }),
                ],
                //  AJOUTER LES PAIEMENTS
                'paiements' => $inscription->paiements->map(function($paiement) {
                    return [
                        'id' => $paiement->id,
                        'montant' => $paiement->montant,
                        'date_paiement' => $paiement->date_paiement,
                        'reference' => $paiement->reference,
                    ];
                }),
                'reste_a_payer' => $inscription->reste_a_payer,
                'est_paye' => $inscription->est_paye,
            ]
        ]);
    }

    public function getDynamicInfos(int $id): JsonResponse
    {
        $inscription = $this->inscriptionService->getInscription($id);

        if (!$inscription) {
            return response()->json([
                'success' => false,
                'message' => 'Inscription non trouvée'
            ], 404);
        }

        $dynamicInfos = $this->eleveService->getInformationsDynamiques($inscription->eleve);

        return response()->json([
            'success' => true,
            'data' => $dynamicInfos
        ]);
    }
}