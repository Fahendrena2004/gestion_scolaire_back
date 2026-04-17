<?php

namespace App\Models\Inscription;

use Illuminate\Database\Eloquent\Model;

class Classe extends Model
{
    protected $table="classe";
    //
    protected $fillable = [
        'nom_classe',
        'niveau_id',
        'code_division',
        'effectif',
        'anneeScolaire_id'
    ];

    public function niveau()
    {
        return $this->belongsTo(Niveau::class);
    }

     public function anneeScolaire()
    {
        return $this->belongsTo(AnneeScolaire::class);
    }
}
