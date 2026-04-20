<?php

namespace App\Services\Inscription;

use App\Models\Inscription\Classe;
use App\Models\Inscription\Niveau;
use App\Models\Inscription\AnneeScolaire;
use Illuminate\Support\Facades\Cache;

class ClasseService
{

    // Récupérer l'année scolaire active
    public function getAnneeActive(): ?AnneeScolaire
    {

            return AnneeScolaire::where('statut', 'en_cours')->first();

    }


    // Vérifier si une année scolaire active existe
    public function hasAnneeActive(): bool
    {
        return $this->getAnneeActive() !== null;
    }

    // Récupérer une classe avec son niveau associé
    public function getClasseWithNiveau(int $classeId): ?Classe
    {
        return Classe::with('niveau')->find($classeId);
    }

    // Récupérer un niveau par son ID
    public function getNiveau(int $niveauId): ?Niveau
    {
        return Niveau::find($niveauId);
    }

    // Récupérer tous les cycles disponibles
    public function getCycles(): array
    {
            return Niveau::select('cycle')->distinct()->get()->toArray();

    }


    // Récupérer les niveaux d'un cycle donné
    public function getNiveauxByCycle(string $cycle)
    {
            return Niveau::where('cycle', $cycle)->get();
    }


    // Récupérer les classes d'un niveau donné pour l'année scolaire active
    public function getClassesByNiveau(int $niveauId)
    {
        $anneeActive = $this->getAnneeActive();

        if (!$anneeActive) {
            return collect([]);
        }


        return Classe::where('niveau_id', $niveauId)
                     ->where('anneeScolaire_id', $anneeActive->id)
                     ->get(['id', 'nom_classe']);
    }

    // Valider l'appartenance d'une classe à un niveau
    public function validerAppartenanceClasse(int $classeId, int $niveauId): bool
    {
        return Classe::where('id', $classeId)
                     ->where('niveau_id', $niveauId)
                     ->exists();
    }
}