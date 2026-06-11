<?php

namespace App\Http\Controllers\Inscription;

use App\Http\Controllers\Controller;
use App\Models\Inscription\AnneeScolaire;
use App\Models\Inscription\TypeFrais;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class FraisController extends Controller
{
    public function calcul(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'cycle'        => 'required|string',
                'parascolaire' => 'sometimes',
                'cantine'      => 'sometimes',
            ]);

            if ($validator->fails()) {
                return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
            }

            $anneeScolaire = AnneeScolaire::where('statut', 'en_cours')->first();

            if (!$anneeScolaire) {
                return response()->json(['success' => false, 'message' => 'Aucune année scolaire active.'], 404);
            }

            $cycle        = $request->input('cycle');
            $niveau       = $request->input('niveau', '');
            $parascolaire = $request->input('parascolaire') == 1 || $request->input('parascolaire') === 'true';
            $cantine      = $request->input('cantine') == 1 || $request->input('cantine') === 'true';

            $frais = [];

            // 1. Inscription
            $frais[] = $this->getTypeFrais('Inscription', $anneeScolaire->id, $cycle, $niveau);

            // 2. Scolarité (Recherche flexible)
            $libelleScolarite = 'Scolarité';
            if ($cycle == 'primaire') $libelleScolarite = 'Scolarité - Primaire';
            else if ($cycle == 'college') $libelleScolarite = 'Scolarité - Collège';
            else if ($cycle == 'lycee') $libelleScolarite = 'Scolarité - Lycée';

            $frais[] = $this->getTypeFrais($libelleScolarite, $anneeScolaire->id, $cycle, $niveau);

            // 3. Frais technologiques
            $frais[] = $this->getTypeFrais('Frais technologiques', $anneeScolaire->id, $cycle, $niveau);

            // 4. Options
            if ($parascolaire) $frais[] = $this->getTypeFrais('Parascolaire', $anneeScolaire->id, $cycle, $niveau);
            if ($cantine) $frais[] = $this->getTypeFrais('Cantine', $anneeScolaire->id, $cycle, $niveau);

            // 5. Autres frais actifs de l'année scolaire (ex: Transport) qui ne sont pas déjà inclus
            $allTypeFrais = TypeFrais::where('annee_scolaire_id', $anneeScolaire->id)
                ->orWhereNull('annee_scolaire_id')
                ->get();

            $includedIds = [];
            foreach ($frais as $f) {
                if ($f) $includedIds[] = $f->id;
            }

            foreach ($allTypeFrais as $tf) {
                if (!in_array($tf->id, $includedIds)) {
                    $low = strtolower($tf->libelle);

                    // On exclut les scolarités (déjà gérées)
                    if (str_contains($low, 'scolarit') || str_contains($low, 'ecolage') || str_contains($low, 'mensualit') || str_contains($low, 'mensuel') || str_contains($low, 'pension')) {
                        continue;
                    }

                    // On exclut les frais destinés spécifiquement à d'autres cycles
                    $otherCycles = ['maternelle', 'primaire', 'college', 'collège', 'lycee', 'lycée', 'creche', 'crèche', 'prescolaire', 'préscolaire'];

                    // Retirer le cycle actuel de la liste des mots à exclure
                    $currentCycleLow = strtolower($cycle);
                    // Normaliser pour la comparaison
                    $normalizedCycle = str_replace(['é', 'è'], ['e', 'e'], $currentCycleLow);

                    $shouldExclude = false;
                    foreach ($otherCycles as $other) {
                        $normalizedOther = str_replace(['é', 'è'], ['e', 'e'], $other);
                        if ($normalizedOther !== $normalizedCycle && str_contains($low, $other)) {
                            // S'il contient explicitement le nom d'un AUTRE cycle, on l'exclut !
                            $shouldExclude = true;
                            break;
                        }
                    }

                    // On exclut les frais destinés spécifiquement à un AUTRE niveau
                    if (!empty($niveau) && str_contains($low, ' - ')) {
                        $parts = array_map('trim', explode('-', strtolower($tf->libelle)));
                        if (count($parts) >= 3) {
                            $targetNiveau = end($parts);
                            // Si le niveau cible est différent du niveau actuel, on l'exclut
                            if (!str_contains(strtolower($niveau), $targetNiveau) && !str_contains($targetNiveau, strtolower($niveau))) {
                                $shouldExclude = true;
                            }
                        } else if (count($parts) == 2) {
                            // Si le format est "Droit d'inscription - CP1"
                            $targetNiveau = end($parts);
                            // On s'assure d'abord que le "targetNiveau" n'est pas simplement le nom du cycle ("Primaire")
                            if (!str_contains($normalizedCycle, str_replace(['é', 'è'], ['e', 'e'], $targetNiveau)) && !str_contains(str_replace(['é', 'è'], ['e', 'e'], $targetNiveau), $normalizedCycle)) {
                                if (!str_contains(strtolower($niveau), $targetNiveau) && !str_contains($targetNiveau, strtolower($niveau))) {
                                    $shouldExclude = true;
                                }
                            }
                        }
                    }

                    if ($shouldExclude) {
                        continue;
                    }

                    $frais[] = $tf;
                    $includedIds[] = $tf->id;
                }
            }

            // Nettoyage des résultats (filtre les null)
            $finalFrais = [];
            foreach ($frais as $f) {
                if ($f) $finalFrais[] = $f;
            }

            $total = 0;
            foreach ($finalFrais as $item) {
                $total += (float) $item->montant;
            }

            return response()->json([
                'success' => true,
                'data'    => [
                    'details'      => $finalFrais,
                    'total_fixe'   => $total,
                    'cycle'        => $cycle,
                    'annee_active' => $anneeScolaire->libelle,
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur Backend: ' . $e->getMessage()
            ], 500);
        }
    }

    private function getTypeFrais($libelle, $anneeId, $cycle = '', $niveau = '')
    {
        $query = TypeFrais::query();

        $query->where(function ($q) use ($libelle) {
            $q->where('libelle', $libelle)
              ->orWhere('libelle', 'like', '%' . $libelle . '%');

            if (str_contains($libelle, 'Scolarité')) {
                $base = trim(str_replace(['Scolarité', '-'], '', $libelle));

                $q->orWhere(function($sub) use ($base) {
                    $sub->where(function($s) {
                        $s->where('libelle', 'like', '%scolarit%')
                          ->orWhere('libelle', 'like', '%ecolage%')
                          ->orWhere('libelle', 'like', '%mensualit%')
                          ->orWhere('libelle', 'like', '%mensuel%')
                          ->orWhere('libelle', 'like', '%pension%');
                    });
                    if (!empty($base)) {
                        $sub->where('libelle', 'like', '%' . $base . '%');
                    }
                });
            }

            if (str_contains(strtolower($libelle), 'inscription')) {
                $q->orWhere('libelle', 'like', '%inscription%');
            }
            if (str_contains(strtolower($libelle), 'cantine')) {
                $q->orWhere('libelle', 'like', '%cantine%');
            }
            if (str_contains(strtolower($libelle), 'parascolaire')) {
                $q->orWhere('libelle', 'like', '%parascolaire%')
                  ->orWhere('libelle', 'like', '%para-scolaire%')
                  ->orWhere('libelle', 'like', '%para scolaire%');
            }
        });

        if ($anneeId) {
            $query->where(function ($q) use ($anneeId) {
                $q->where('annee_scolaire_id', $anneeId)
                  ->orWhereNull('annee_scolaire_id');
            });
        } else {
            $query->whereNull('annee_scolaire_id');
        }

        // Récupérer tous les résultats potentiels
        $results = $query->orderByRaw('CASE WHEN annee_scolaire_id IS NOT NULL THEN 1 ELSE 0 END')->get();

        // S'il y a plus d'un résultat, on filtre par cycle et niveau
        if ($results->count() > 1 && (!empty($cycle) || !empty($niveau))) {
            foreach ($results as $res) {
                $low = strtolower($res->libelle);
                $isTargetCycle = true;
                $isTargetNiveau = true;

                // Vérifier cycle
                if (!empty($cycle)) {
                    $normalizedCycle = str_replace(['é', 'è'], ['e', 'e'], strtolower($cycle));
                    $otherCycles = ['maternelle', 'primaire', 'college', 'collège', 'lycee', 'lycée', 'creche', 'crèche', 'prescolaire', 'préscolaire'];
                    foreach ($otherCycles as $other) {
                        $normalizedOther = str_replace(['é', 'è'], ['e', 'e'], $other);
                        if ($normalizedOther !== $normalizedCycle && str_contains($low, $other)) {
                            $isTargetCycle = false;
                            break;
                        }
                    }
                    if (str_contains($low, $normalizedCycle)) {
                        $isTargetCycle = true;
                    }
                }

                // Vérifier niveau
                if (!empty($niveau) && str_contains($low, ' - ')) {
                    $parts = array_map('trim', explode('-', $low));

                    if (count($parts) >= 3) {
                        $targetNiveau = end($parts);
                        $cleanTarget = trim(preg_replace('/\s*\([^)]*\)/', '', $targetNiveau));
                        if (!empty($cleanTarget) && !str_contains(strtolower($niveau), $cleanTarget) && !str_contains($cleanTarget, strtolower($niveau))) {
                            $isTargetNiveau = false;
                        }
                    } else if (count($parts) == 2) {
                        $targetNiveau = end($parts);
                        $cleanTarget = trim(preg_replace('/\s*\([^)]*\)/', '', $targetNiveau));

                        if (!str_contains($normalizedCycle, str_replace(['é', 'è'], ['e', 'e'], $cleanTarget)) && !str_contains(str_replace(['é', 'è'], ['e', 'e'], $cleanTarget), $normalizedCycle)) {
                            if (!str_contains(strtolower($niveau), $cleanTarget) && !str_contains($cleanTarget, strtolower($niveau))) {
                                $isTargetNiveau = false;
                            }
                        }
                    }
                }

                if ($isTargetCycle && $isTargetNiveau) {
                    return $res; // Le match parfait
                }
            }
        }

        return $results->first();
    }
}
