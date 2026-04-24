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
        'max_effectif',
        'anneeScolaire_id',
    ];

    protected $attributes = [
        'max_effectif' => 50,
        'effectif'     => 0,
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

    /**
     * Vérifie si la classe a atteint sa capacité maximale (basé sur le champ effectif).
     */
    public function estPleine(): bool
    {
        $max = $this->max_effectif ?? 50;
        return $this->effectif >= $max;
    }

    /**
     * Retourne le nombre d'élèves réellement inscrits dans cette classe.
     */
    public function effectifActuel(): int
    {
        return $this->inscriptions()->count();
    }
}
