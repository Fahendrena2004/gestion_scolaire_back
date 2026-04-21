<?php

namespace App\Models\Inscription;

use Illuminate\Database\Eloquent\Model;
use App\Models\Utilisateur;
use App\Models\Inscription\Inscription;
use App\Models\Inscription\Eleve;
use App\Models\Inscription\AnneeScolaire;
use App\Models\Inscription\Classe;

class Reinscription extends Model
{
    protected $table = 'reinscriptions';

    protected $fillable = [
        'inscription_id',
        'eleve_id',
        'annee_scolaire_id',
        'classe_id',
        'montant_reinscription',
        'parascolaire',
        'cantine',
        'est_paye',
        'date_reinscription',
        'utilisateur_id',
    ];

    protected $casts = [
        'date_reinscription' => 'date',
        'est_paye' => 'boolean',
        'montant_reinscription' => 'decimal:2',
        'parascolaire' => 'decimal:2',
        'cantine' => 'decimal:2',
    ];

    // Relations
    public function inscription()
    {
        return $this->belongsTo(Inscription::class, 'inscription_id');
    }

    public function eleve()
    {
        return $this->belongsTo(Eleve::class, 'eleve_id');
    }

    public function anneeScolaire()
    {
        return $this->belongsTo(AnneeScolaire::class, 'annee_scolaire_id');
    }

    public function classe()
    {
        return $this->belongsTo(Classe::class, 'classe_id');
    }

    public function utilisateur()
    {
        return $this->belongsTo(Utilisateur::class, 'utilisateur_id');
    }
}