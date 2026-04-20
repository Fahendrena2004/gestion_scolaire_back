<?php

namespace App\Models\Gestion_note;

use App\Models\Inscription\AnneeScolaire;
use App\Models\Gestion_note\Matieres;
use App\Models\Inscription\Eleve;
use Illuminate\Database\Eloquent\Model;

class Notes extends Model
{
    protected $table = 'notes';

    protected $fillable = [
        'id_eleve',
        'id_matiere',
        'id_annee_scolaire',
        'valeur',
        'periode',
        'date',
        'type',
    ];

    // Relations
    public function anneeScolaire()
    {
        return $this->belongsTo(AnneeScolaire::class, 'id_annee_scolaire');
    }

    public function matiere()
    {
        return $this->belongsTo(Matieres::class, 'id_matiere');
    }

    public function eleve()
    {
        return $this->belongsTo(Eleve::class, 'id_eleve');
    }
}