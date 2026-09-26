<?php

namespace App\Models\Inscription;

use Illuminate\Database\Eloquent\Model;
use App\Models\Utilisateur;
use App\Models\Inscription\TypeFrais;
use App\Models\Paiement\PaiementMensuel;
use App\Models\Paiement\PresenceCantine;
use App\Models\Paiement\Recu;

class Paiement extends Model
{
    protected $table = 'paiements';

    protected $appends = [
        'user_id',
    ];

    protected $fillable = [
        'reference',
        'inscription_id',
        'type_frais_id',
        'type',
        'libelle',
        'details',
        'montant',
        'date_paiement',
        'utilisateur_id'
    ];

    protected $casts = [
        'date_paiement' => 'date',
        'montant' => 'decimal:2',
        'details' => 'array',
    ];

    public function inscription()
    {
        return $this->belongsTo(Inscription::class, 'inscription_id');
    }

    public function typeFrais()
    {
        return $this->belongsTo(TypeFrais::class, 'type_frais_id');
    }

    public function utilisateur()
    {
        return $this->belongsTo(Utilisateur::class, 'utilisateur_id');
    }

    public function user()
    {
        return $this->utilisateur();
    }

    public function recu()
    {
        return $this->hasOne(Recu::class, 'paiement_id');
    }

    public function paiementsMensuels()
    {
        return $this->hasMany(PaiementMensuel::class, 'paiement_id');
    }

    public function presencesCantine()
    {
        return $this->hasMany(PresenceCantine::class, 'paiement_id');
    }

    public function getUserIdAttribute()
    {
        return $this->utilisateur_id;
    }

}
