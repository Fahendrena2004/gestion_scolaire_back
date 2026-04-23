<?php

namespace App\Models\Inscription;

use App\Models\Utilisateur;
use Illuminate\Database\Eloquent\Model;

class Reinscription extends Model
{
    protected $table = 'reinscriptions';

    protected $fillable = [
        'inscription_id',
        'nouvelle_inscription_id',
        'eleve_id',
        'annee_scolaire_id',
        'classe_id',
        'statut',
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
        'statut' => 'string',
        'montant_reinscription' => 'decimal:2',
        'parascolaire' => 'boolean',
        'cantine' => 'boolean',
    ];

    public function inscription()
    {
        return $this->belongsTo(Inscription::class, 'inscription_id');
    }

    public function nouvelleInscription()
    {
        return $this->belongsTo(Inscription::class, 'nouvelle_inscription_id');
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
