<?php

namespace App\Models\Gestion_note;

use Illuminate\Database\Eloquent\Model;
use App\Models\Inscription\Classe;
use App\Models\Inscription\Niveau;

class Matieres extends Model
{
    //
    protected $table = 'matieres';

    protected $fillable = [
        'nom',
        'coefficient',
        'classe_id',
        'niveau_id',
        'section',
    ];

    protected $appends = ['cycle', 'niveau_classe'];

    public function getCycleAttribute()
    {
        return $this->niveau?->cycle ?? $this->classe?->niveau?->cycle;
    }

    public function getNiveauClasseAttribute()
    {
        return $this->niveau?->nom_niveau ?? $this->classe?->niveau?->nom_niveau;
    }

    public function classe()
    {
        return $this->belongsTo(Classe::class, 'classe_id');
    }

    public function niveau()
    {
        return $this->belongsTo(Niveau::class, 'niveau_id');
    }
}
