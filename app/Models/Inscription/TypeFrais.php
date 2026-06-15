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
        'target_type',
        'target_value',
        'frequence',
        'categorie',
        'ordre_affichage',
    ];

    protected $casts = [
        'montant' => 'decimal:2',
        'est_obligatoire' => 'boolean',
        'ordre_affichage' => 'integer',
    ];

    public function anneeScolaire()
    {
        return $this->belongsTo(AnneeScolaire::class, 'annee_scolaire_id');
    }
}
