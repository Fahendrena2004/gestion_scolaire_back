<?php

namespace App\Http\Controllers\Paiements;

use App\Http\Controllers\Controller;
use App\Models\Inscription\Inscription;
use App\Models\Inscription\Classe;
use App\Models\Inscription\Niveau;
use Illuminate\Http\Request;

class FiltrationPaiement extends Controller
{
    /**
     * 1. Filtrer les élèves (par classe, niveau, nom, matricule)
     * GET /api/paiement/eleves?classe_id=12&niveau_id=6&nom=Rakoto&matricule=REG-2025-0001
     */
    public function filtrerEleves(Request $request)
    {
        $query = Inscription::with(['eleve', 'classe.niveau', 'anneeScolaire'])
            ->whereHas('anneeScolaire', function($q) {
                $q->where('statut', 'en_cours');
            });

        if ($request->filled('classe_id')) {
            $query->where('id_classe', $request->classe_id);
        }

        if ($request->filled('niveau_id')) {
            $query->whereHas('classe.niveau', function($q) use ($request) {
                $val = $request->niveau_id;
                if (is_numeric($val)) {
                    $q->where('id', $val);
                } else {
                    $q->where('nom_niveau', $val);
                }
            });
        }

        if ($request->filled('nom')) {
            $query->whereHas('eleve', function($q) use ($request) {
                $q->where('nom', 'like', '%' . $request->nom . '%')
                  ->orWhere('prenom', 'like', '%' . $request->nom . '%');
            });
        }

        if ($request->filled('matricule')) {
            $query->whereHas('eleve', function($q) use ($request) {
                $q->where('matricule', 'like', '%' . $request->matricule . '%');
            });
        }

        $inscriptions = $query->paginate(20);

        $resultats = [];
        foreach ($inscriptions as $inscription) {
            $resultats[] = [
                'inscription_id' => $inscription->id,
                'eleve' => [
                    'id' => $inscription->eleve->id,
                    'nom' => $inscription->eleve->nom,
                    'prenom' => $inscription->eleve->prenom,
                    'matricule' => $inscription->eleve->matricule,
                ],
                'classe' => [
                    'id' => $inscription->classe->id,
                    'nom' => $inscription->classe->nom_classe,
                    'niveau' => $inscription->classe->niveau->nom_niveau,
                ]
            ];
        }

        return response()->json([
            'success' => true,
            'data' => $resultats,
            'pagination' => [
                'total' => $inscriptions->total(),
                'current_page' => $inscriptions->currentPage(),
                'per_page' => $inscriptions->perPage(),
            ]
        ]);
    }

    /**
     * 2. Récupérer les filtres disponibles (classes, niveaux)
     * GET /api/paiement/filtres
     */
    public function getFiltres()
    {
        $classes = Classe::with('niveau')->get();
        $niveaux = Niveau::all();

        return response()->json([
            'success' => true,
            'data' => [
                'classes' => $classes->map(function($classe) {
                    return [
                        'id' => $classe->id,
                        'nom' => $classe->nom_classe,
                        'niveau' => $classe->niveau->nom_niveau
                    ];
                }),
                'niveaux' => $niveaux
            ]
        ]);
    }
}