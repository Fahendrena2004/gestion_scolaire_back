<?php

namespace App\Models\Inscription;

use Illuminate\Database\Eloquent\Model;

class TypeFrais extends Model
{
    protected $table = 'type_frais';

    protected $fillable = [
        'annee_scolaire_id',
        'libelle',
        'montant',
        'est_obligatoire',
    ];

    protected $casts = [
        'montant' => 'decimal:2',
        'est_obligatoire' => 'boolean',
    ];

    public function anneeScolaire()
    {
        return $this->belongsTo(AnneeScolaire::class, 'annee_scolaire_id');
    }
}
