<?php

namespace App\Models\Paiement;

use App\Models\Inscription\Inscription;
use Illuminate\Database\Eloquent\Model;

class ResumePaiement extends Model
{
    protected $table = 'resume_paiements';

    protected $fillable = [
        'inscription_id',
        'total_du',
        'total_paye',
        'total_restant',
    ];

    protected $casts = [
        'total_du' => 'decimal:2',
        'total_paye' => 'decimal:2',
        'total_restant' => 'decimal:2',
    ];

    public function inscription()
    {
        return $this->belongsTo(Inscription::class, 'inscription_id');
    }

    public function paiementsMensuels()
    {
        return $this->hasMany(PaiementMensuel::class, 'resume_id');
    }
}
