<?php

namespace App\Models\Gestion_note;

use App\Models\Inscription\AnneeScolaire;
use App\Models\Inscription\Classe;
use App\Models\Inscription\Eleve;
use Illuminate\Database\Eloquent\Model;

class Bulletin extends Model
{
    //
    protected $table = 'bulletins';

    protected $fillable = [
        'eleve_id',
        'annee_scolaire_id',
        'classe_id',
        'moyenne_eleve',
        'moyenne_classe',
        'rang',
        'periode',
    ];

    public function AnneeScolaire(){
        return $this->belongsTo(AnneeScolaire::class);
    }

    public function Eleve(){
        return $this->belongsTo(Eleve::class);
    }

    public function classe(){
        return $this->belongsTo(Classe::class);
    }
}
