<?php

namespace App\Services;

use App\Models\Gestion_note\Notes;
use App\Models\Inscription\Eleve;

class BulletinService
{
    public function calculerMoyenneEleve($eleveId, $periode, $anneeId)
    {
        $notes = Notes::where('eleve_id', $eleveId)
            ->where('periode', $periode)
            ->where('annee_scolaire_id', $anneeId)
            ->with('matiere')
            ->get();

        $total = 0;
        $coefTotal = 0;

        foreach ($notes as $note) {
            $coef = $note->matiere->coefficient;
            $total += $note->valeur * $coef;
            $coefTotal += $coef;
        }

        return $coefTotal > 0 ? $total / $coefTotal : 0;
    }

    public function calculerMoyenneClasse($classeId, $periode, $anneeId)
    {
        $eleves = Eleve::where('classe_id', $classeId)->get();

        $total = 0;
        $count = 0;

        foreach ($eleves as $eleve) {
            $moyenne = $this->calculerMoyenneEleve($eleve->id, $periode, $anneeId);
            $total += $moyenne;
            $count++;
        }

        return $count > 0 ? $total / $count : 0;
    }
}