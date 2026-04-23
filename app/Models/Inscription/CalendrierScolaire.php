<?php

namespace App\Models\Inscription;

use Illuminate\Database\Eloquent\Model;

class CalendrierScolaire extends Model
{
    protected $table = 'calendrier_scolaires';

    protected $fillable = [
        'annee_scolaire_id',
        'type',
        'titre',
        'date_debut',
        'date_fin',
        'description',
    ];

    protected $casts = [
        'date_debut' => 'date',
        'date_fin' => 'date',
    ];

    public function anneeScolaire()
    {
        return $this->belongsTo(AnneeScolaire::class, 'annee_scolaire_id');
    }
}
