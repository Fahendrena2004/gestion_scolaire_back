<?php

namespace App\Models\Gestion_note;

use Illuminate\Database\Eloquent\Model;
use App\Models\Inscription\Classe;

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

    public function classe()
    {
        return $this->belongsTo(Classe::class, 'classe_id');
    }

    public function niveau()
    {
        return $this->belongsTo(\App\Models\Inscription\Niveau::class, 'niveau_id');
    }

    protected $appends = ['cycle', 'niveau_classe'];

    public function getCycleAttribute()
    {
        if ($this->niveau) {
            return $this->niveau->cycle;
        }
        if ($this->classe && $this->classe->niveau) {
            return $this->classe->niveau->cycle;
        }
        return null;
    }

    public function getNiveauClasseAttribute()
    {
        if ($this->niveau) {
            return $this->niveau->nom_niveau;
        }
        if ($this->classe && $this->classe->niveau) {
            return $this->classe->niveau->nom_niveau;
        }
        return null;
    }
}
