<?php

namespace App\Models\Inscription;

use Illuminate\Database\Eloquent\Model;

class Classe extends Model
{
    protected $table = "classes";

    protected $fillable = [
        'nom_classe',
        'niveau_id',
        'code_division',
        'effectif',
        'anneeScolaire_id',
    ];

    public function niveau()
    {
        return $this->belongsTo(Niveau::class, 'niveau_id');
    }

    public function anneeScolaire()
    {
        return $this->belongsTo(AnneeScolaire::class, 'anneeScolaire_id');
    }

    public function getNomAttribute()
    {
        return $this->nom_classe;
    }

    public function inscriptions()
    {
        return $this->hasMany(Inscription::class, 'id_classe');
    }
}
