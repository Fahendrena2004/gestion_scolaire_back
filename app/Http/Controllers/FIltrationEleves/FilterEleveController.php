<?php

namespace App\Http\Controllers\FIltrationEleves;

use App\Models\Inscription\Eleve;
use App\Models\Inscription\Inscription;
use App\Models\Inscription\Classe;
use App\Models\Inscription\AnneeScolaire;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;

class FilterEleveController extends Controller
{
    /**
     * Filtrer les élèves par matricule (IMEI) ou nom
     * GET /api/eleves/filter?imei=REG-2025-0001&nom=Rakoto
     */
    public function filter(Request $request)
    {
        $query = Eleve::query();

        // Filtre par matricule (IMEI)
        if ($request->filled('imei')) {
            $query->where('matricule', 'like', '%' . $request->imei . '%');
        }

        // Filtre par nom
        if ($request->filled('nom')) {
            $query->where(function($q) use ($request) {
                $q->where('nom', 'like', '%' . $request->nom . '%')
                  ->orWhere('prenom', 'like', '%' . $request->nom . '%');
            });
        }

        // Filtre par classe
        if ($request->filled('classe_id')) {
            $query->whereHas('inscriptions', function($q) use ($request) {
                $q->where('id_classe', $request->classe_id);
            });
        }

        // Filtre par année scolaire
        if ($request->filled('annee_scolaire_id')) {
            $query->whereHas('inscriptions', function($q) use ($request) {
                $q->where('id_annee_scolaire', $request->annee_scolaire_id);
            });
        }

        // Filtre par cycle
        if ($request->filled('cycle')) {
            $query->whereHas('inscriptions.classe.niveau', function($q) use ($request) {
                $q->where('cycle', $request->cycle);
            });
        }

        $eleves = $query->with([
            'inscriptions' => function($q) {
                $q->with(['classe.niveau', 'anneeScolaire']);
            },
            'autresInformations'
        ])->paginate(20);

        if ($eleves->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => 'Aucun élève trouvé',
                'filtres' => $request->all()
            ], 404);
        }

        return response()->json([
            'success' => true,
            'total' => $eleves->total(),
            'current_page' => $eleves->currentPage(),
            'per_page' => $eleves->perPage(),
            'data' => $eleves->map(function($eleve) {
                return $this->formatEleve($eleve);
            })
        ]);
    }

    /**
     * Formater les données d'un élève
     */
    private function formatEleve($eleve)
    {
        $inscriptionEnCours = $eleve->inscriptions->first(function($inscription) {
            return $inscription->anneeScolaire && $inscription->anneeScolaire->statut === 'en_cours';
        });

        $infosDynamiques = [];
        foreach ($eleve->autresInformations as $info) {
            $infosDynamiques[$info->nom_champ] = $info->valeur_champ;
        }

        return [
            'id' => $eleve->id,
            'matricule' => $eleve->matricule,
            'nom' => $eleve->nom,
            'prenom' => $eleve->prenom,
            'nom_complet' => $eleve->prenom . ' ' . $eleve->nom,
            'date_naissance' => $eleve->date_naissance,
            'lieu_naissance' => $eleve->lieu_naissance,
            'sexe' => $eleve->sexe == 'M' ? 'Masculin' : 'Féminin',
            'adresse' => $eleve->adresse,
            'inscription_en_cours' => $inscriptionEnCours ? [
                'id' => $inscriptionEnCours->id,
                'date_inscription' => $inscriptionEnCours->date_inscription,
                'classe' => $inscriptionEnCours->classe->nom_classe ?? null,
                'niveau' => $inscriptionEnCours->classe->niveau->nom_niveau ?? null,
                'cycle' => $inscriptionEnCours->classe->niveau->cycle ?? null,
                'annee_scolaire' => $inscriptionEnCours->anneeScolaire->libelle ?? null,
                'parascolaire' => $inscriptionEnCours->parascolaire,
                'cantine' => $inscriptionEnCours->cantine,
                'montant_total' => $inscriptionEnCours->montant_total,
                'reste_a_payer' => $inscriptionEnCours->reste_a_payer,
            ] : null,
            'informations' => $infosDynamiques
        ];
    }
}
