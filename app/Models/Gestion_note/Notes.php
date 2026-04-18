<?php

namespace App\Models\Gestion_note;

use App\Models\Inscription\AnneeScolaire;
use App\Models\Gestion_note\Matieres;
use App\Models\Inscription\Eleve;

use Illuminate\Database\Eloquent\Model;

class Notes extends Model
{
    //
    protected $table = 'notes';

    protected $fillable = [
        'eleve_id',
        'matiere_id',
        'annee_scolaire_id',
        'valeur',
        'periode',
        'date',
        'type',
    ];

    public function AnneeScolaire(){
        return $this->belongsTo(AnneeScolaire::class, );
    }

    public function Matiere(){
        return $this->belongsTo(Matieres::class);
    }

    public function Eleve(){
        return $this->belongsTo(Eleve::class);
    }
}
