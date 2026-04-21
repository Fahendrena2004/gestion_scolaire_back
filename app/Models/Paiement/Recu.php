<?php

namespace App\Models\Paiement;

use Illuminate\Database\Eloquent\Model;
use App\Models\Inscription\Inscription;
use App\Models\Inscription\Paiement;
class Recu extends Model
{
    protected $table = 'recus';

    protected $fillable = [
        'numero',
        'paiement_id',
        'inscription_id',
        'montant',
        'date_emission',
        'libelle',
        'details'
    ];

    protected $casts = [
        'montant' => 'decimal:2',
        'date_emission' => 'date'
    ];

    public function paiement()
    {
        return $this->belongsTo(Paiement::class, 'paiement_id');
    }

    public function inscription()
    {
        return $this->belongsTo(Inscription::class, 'inscription_id');
    }

    public static function genererNumero()
    {
        $lastId = self::max('id') ?? 0;
        $numero = str_pad($lastId + 1, 6, '0', STR_PAD_LEFT);
        return 'REC-' . date('Y') . '-' . $numero;
    }
}