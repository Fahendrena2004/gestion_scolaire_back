<?php

namespace App\Models\Paiement;

use Illuminate\Database\Eloquent\Model;
use App\Models\Inscription\Inscription;
use App\Models\Inscription\Paiement;
class PresenceCantine extends Model
{
    protected $table = 'presences_cantine';

    protected $fillable = [
        'inscription_id',
        'date_presence',
        'montant',
        'est_paye',
        'paiement_id'
    ];

    protected $casts = [
        'date_presence' => 'date',
        'montant' => 'decimal:2',
        'est_paye' => 'boolean'
    ];

    /**
     * Relation avec l'inscription
     */
    public function inscription()
    {
        return $this->belongsTo(Inscription::class, 'inscription_id');
    }

    /**
     * Relation avec le paiement
     */
    public function paiement()
    {
        return $this->belongsTo(Paiement::class, 'paiement_id');
    }

    /**
     * Vérifier si la présence a été payée
     */
    public function getEstPayeAttribute($value)
    {
        return (bool) $value;
    }

    /**
     * Scope: Présences non payées
     */
    public function scopeNonPaye($query)
    {
        return $query->where('est_paye', false);
    }

    /**
     * Scope: Présences payées
     */
    public function scopePaye($query)
    {
        return $query->where('est_paye', true);
    }

    /**
     * Scope: Pour un mois donné
     */
    public function scopePourMois($query, $mois, $annee)
    {
        return $query->whereMonth('date_presence', $mois)
                     ->whereYear('date_presence', $annee);
    }
}
