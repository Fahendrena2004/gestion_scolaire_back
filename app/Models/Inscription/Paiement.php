<?php

namespace App\Models\Inscription;

use Illuminate\Database\Eloquent\Model;
use App\Models\Utilisateur;
class Paiement extends Model
{
    protected $table = 'paiements';

    protected $fillable = [
        'inscription_id',
        'montant',
        'date_paiement',
        'reference',
        'utilisateur_id'
    ];

    protected $casts = [
        'date_paiement' => 'date',
        'montant' => 'decimal:2',
    ];

    public function inscription()
    {
        return $this->belongsTo(Inscription::class, 'inscription_id');
    }

    public function utilisateur()
    {
        return $this->belongsTo(Utilisateur::class, 'utilisateur_id');
    }
}