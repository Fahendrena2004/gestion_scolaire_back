<?php

namespace App\Models\Inscription;

use Illuminate\Database\Eloquent\Model;

class Echeance extends Model
{
    protected $table = 'echeances';

    protected $fillable = [
        'inscription_id', 'type_frais_id', 'libelle', 'montant',
        'mois', 'annee', 'date_echeance', 'statut',
        'montant_paye', 'montant_restant', 'a_details'
    ];

    protected $casts = [
        'montant' => 'decimal:2',
        'montant_paye' => 'decimal:2',
        'montant_restant' => 'decimal:2',
        'date_echeance' => 'date',
        'a_details' => 'boolean'
    ];

    public function inscription()
    {
        return $this->belongsTo(Inscription::class, 'inscription_id');
    }

    public function typeFrais()
    {
        return $this->belongsTo(TypeFrais::class, 'type_frais_id');
    }

    public function paiements()
    {
        return $this->hasMany(Paiement::class, 'echeance_id');
    }

    public function getEstPayeAttribute()
    {
        return $this->statut === 'paye';
    }

    public function getPourcentagePayeAttribute()
    {
        if ($this->montant <= 0) return 0;
        return round(($this->montant_paye / $this->montant) * 100, 2);
    }
}