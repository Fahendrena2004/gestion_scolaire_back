<?php

namespace App\Models\Paiement;

use App\Models\Inscription\Paiement;
use Illuminate\Database\Eloquent\Model;

class PaiementMensuel extends Model
{
    protected $table = 'paiements_mensuels';

    protected $fillable = [
        'resume_id',
        'mois',
        'annee',
        'montant',
        'paiement_id',
    ];

    protected $casts = [
        'montant' => 'decimal:2',
    ];

    public function resume()
    {
        return $this->belongsTo(ResumePaiement::class, 'resume_id');
    }

    public function paiement()
    {
        return $this->belongsTo(Paiement::class, 'paiement_id');
    }

    public function getEstPayeAttribute(): bool
    {
        return ! is_null($this->paiement_id);
    }
}
