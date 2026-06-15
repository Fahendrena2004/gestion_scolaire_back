<?php

namespace App\Http\Controllers\Inscription;

use App\Http\Controllers\Controller;
use App\Models\Inscription\AnneeScolaire;
use App\Models\Inscription\TypeFrais;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Carbon\Carbon;

class FraisController extends Controller
{
    public function calcul(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'cycle'   => 'required|string',
                'niveau'  => 'required|string',
                'serie'   => 'nullable|string',
                'options' => 'nullable|array',
            ]);

            if ($validator->fails()) {
                return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
            }

            $anneeScolaire = AnneeScolaire::where('statut', 'en_cours')->first();

            if (!$anneeScolaire) {
                return response()->json(['success' => false, 'message' => 'Aucune annee scolaire active.'], 404);
            }

            // Calcul du nombre de mois scolaires
            $dateDebut = Carbon::parse($anneeScolaire->date_debut);
            $dateFin = Carbon::parse($anneeScolaire->date_fin);
            $nombreMoisScolaires = $dateDebut->diffInMonths($dateFin);
            if ($nombreMoisScolaires <= 0) $nombreMoisScolaires = 10; // Fallback

            $cycle = strtolower($request->input('cycle'));
            $niveau = strtolower($request->input('niveau'));
            $options = $request->input('options', []); // ex: ['12' => true, '15' => false]

            // On recupere tous les frais de l'annee
            $allFrais = TypeFrais::where('annee_scolaire_id', $anneeScolaire->id)
                ->orderBy('ordre_affichage', 'asc')
                ->get();

            $fraisCibles = [];
            $totalFixe = 0;

            foreach ($allFrais as $frais) {
                $isTargeted = false;

                // Verifier le ciblage
                if ($frais->target_type === 'general') {
                    $isTargeted = true;
                } elseif ($frais->target_type === 'cycle' && strtolower($frais->target_value) === $cycle) {
                    $isTargeted = true;
                } elseif ($frais->target_type === 'niveau' && strtolower($frais->target_value) === $niveau) {
                    $isTargeted = true;
                }

                if ($isTargeted) {
                    $isSelectionne = $frais->est_obligatoire;

                    // Si c'est optionnel, verifier si l'utilisateur l'a coché
                    if (!$frais->est_obligatoire && is_array($options) && isset($options[$frais->id])) {
                        if ($options[$frais->id] == 1 || $options[$frais->id] === 'true' || $options[$frais->id] === true) {
                            $isSelectionne = true;
                        }
                    }

                    $montantTotal = $frais->montant;
                    if ($frais->frequence === 'mensuel') {
                        $montantTotal = $frais->montant * $nombreMoisScolaires;
                    }

                    $fraisCibles[] = [
                        'id' => $frais->id,
                        'libelle' => $frais->libelle,
                        'montant_base' => $frais->montant,
                        'frequence' => $frais->frequence,
                        'montant_total' => $montantTotal,
                        'categorie' => $frais->categorie,
                        'est_obligatoire' => $frais->est_obligatoire,
                        'est_applique' => $isSelectionne,
                        'original_frais' => $frais // Utile pour appliquerFrais
                    ];

                    if ($isSelectionne) {
                        $totalFixe += $montantTotal;
                    }
                }
            }

            return response()->json([
                'success' => true,
                'data'    => [
                    'details'      => $fraisCibles,
                    'total_fixe'   => $totalFixe,
                    'cycle'        => $cycle,
                    'annee_active' => $anneeScolaire->libelle ?? ($dateDebut->format('Y') . '-' . $dateFin->format('Y')),
                    'nombre_mois'  => $nombreMoisScolaires
                ],
                'message' => 'Calcul des frais effectue avec succes'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur Backend: ' . $e->getMessage()
            ], 500);
        }
    }

    public function getFraisByTarget(Request $request)
    {
        $anneeScolaire = AnneeScolaire::where('statut', 'en_cours')->first();
        if (!$anneeScolaire) {
            return response()->json(['success' => false, 'message' => 'Aucune annee scolaire active.'], 404);
        }

        $query = TypeFrais::where('annee_scolaire_id', $anneeScolaire->id);

        if ($request->has('cycle') && $request->input('cycle')) {
            $cycle = strtolower($request->input('cycle'));
            $query->where(function($q) use ($cycle) {
                $q->where('target_type', 'general')
                  ->orWhere(function($sub) use ($cycle) {
                      $sub->where('target_type', 'cycle')->where('target_value', 'like', "%$cycle%");
                  });
            });
        }

        if ($request->has('niveau') && $request->input('niveau')) {
            $niveau = strtolower($request->input('niveau'));
            $query->orWhere(function($sub) use ($niveau) {
                 $sub->where('target_type', 'niveau')->where('target_value', 'like', "%$niveau%");
            });
        }

        $frais = $query->orderBy('ordre_affichage', 'asc')->get();

        return response()->json([
            'success' => true,
            'data' => $frais
        ]);
    }
}
