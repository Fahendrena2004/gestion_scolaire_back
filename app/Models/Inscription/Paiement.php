<?php

namespace App\Models\Inscription;

use Illuminate\Database\Eloquent\Model;
use App\Models\Utilisateur;
use App\Models\Paiement\Recu;

class Paiement extends Model
{
    protected $table = 'paiements';

    protected $fillable = [
        'reference',
        'inscription_id',
        'echeance_id',
        'montant',
        'date_paiement',
        'utilisateur_id'
    ];

    protected $casts = [
        'date_paiement' => 'date',
        'montant' => 'decimal:2'
    ];

    public function inscription()
    {
        return $this->belongsTo(Inscription::class, 'inscription_id');
    }

    public function echeance()
    {
        return $this->belongsTo(Echeance::class, 'echeance_id');
    }

    public function utilisateur()
    {
        return $this->belongsTo(Utilisateur::class, 'utilisateur_id');
    }

    public function recu()
    {
        return $this->hasOne(Recu::class, 'paiement_id');
    }

}