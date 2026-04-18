<?php

namespace App\Services\Inscription;

use App\Models\Inscription\Eleve;
use App\Models\Inscription\AutreInformation;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\QueryException;

class EleveService
{
    /**
     * Vérifier si un élève existe déjà
     */
    public function eleveExiste(array $data): ?Eleve
    {
        return Eleve::where('nom', $data['nom'])
                    ->where('prenom', $data['prenom'])
                    ->where('date_naissance', $data['date_naissance'])
                    ->where('lieu_naissance', $data['lieu_naissance'])
                    ->first();
    }

    /**
     * Générer un matricule unique par niveau
     */
    public function genererMatricule(string $cycle): string
    {
        $codeNiveau = match($cycle) {
            'primaire' => 'PR',
            'college' => 'CL',
            'lycee' => 'LY',
            default => 'XX'
        };

        $annee = date('Y');

        $count = DB::table('eleves')
            ->join('inscriptions', 'inscriptions.id_eleve', '=', 'eleves.id')
            ->join('classes', 'classes.id', '=', 'inscriptions.id_classe')
            ->join('niveaux', 'niveaux.id', '=', 'classes.niveau_id')
            ->where('niveaux.cycle', $cycle)
            ->whereYear('inscriptions.date_inscription', $annee)
            ->count();

        $numero = str_pad($count + 1, 4, '0', STR_PAD_LEFT);

        return "RG-{$codeNiveau}-{$annee}-{$numero}";
    }

    /**
     * Créer ou récupérer un élève (Évite les doublons)
     */
    public function trouverOuCreerEleve(array $fixedData, string $cycle): Eleve
    {
        // 1. Vérifier si l'élève existe déjà
        $existant = $this->eleveExiste($fixedData);

        if ($existant) {
            return $existant;
        }

        // 2. Créer un nouvel élève
        $matricule = $this->genererMatricule($cycle);

        try {
            return Eleve::create([
                'nom' => $fixedData['nom'],
                'prenom' => $fixedData['prenom'],
                'date_naissance' => $fixedData['date_naissance'],
                'lieu_naissance' => $fixedData['lieu_naissance'],
                'sexe' => $fixedData['sexe'],
                'adresse' => $fixedData['adresse'] ?? null,
                'matricule' => $matricule,
            ]);
        } catch (QueryException $e) {
            if ($e->errorInfo[1] == 1062) {
                // Doublon détecté, on récupère l'existant
                return $this->eleveExiste($fixedData);
            }
            throw $e;
        }
    }

    public function ajouterInformationsDynamiques(Eleve $eleve, array $dynamicFields): void
    {
        foreach ($dynamicFields as $nomChamp => $valeur) {
            if (!empty($valeur)) {
                // Vérifier si l'info existe déjà
                $existant = AutreInformation::where('id_eleve', $eleve->id)
                    ->where('nom_champ', $nomChamp)
                    ->first();

                if (!$existant) {
                    AutreInformation::create([
                        'id_eleve' => $eleve->id,
                        'nom_champ' => $nomChamp,
                        'valeur_champ' => $valeur,
                    ]);
                }
            }
        }
    }

    public function getInformationsDynamiques(Eleve $eleve): array
    {
        $informations = [];

        foreach ($eleve->autresInformations as $info) {
            $informations[$info->nom_champ] = $info->valeur_champ;
        }

        return $informations;
    }
}