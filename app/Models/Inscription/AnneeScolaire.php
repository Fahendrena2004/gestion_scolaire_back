<?php

namespace App\Models\Inscription;

use Illuminate\Database\Eloquent\Model;

class AnneeScolaire extends Model
{
    protected $table = 'annee_scolaires';

    protected $fillable = [
        'date_debut',
        'date_fin',
        'statut',
        'date_debut_inscription',
        'date_fin_inscription',
    ];

    protected $casts = [
        'date_debut'             => 'date',
        'date_fin'               => 'date',
        'date_debut_inscription' => 'date',
        'date_fin_inscription'   => 'date',
    ];

    protected $appends = ['libelle', 'est_inscription_ouverte'];

    public function getLibelleAttribute(): string
    {
        return $this->date_debut?->format('Y-m-d') . ' - ' . $this->date_fin?->format('Y-m-d');
    }

    public function getEstInscriptionOuverteAttribute(): bool
    {
        if ($this->statut !== 'en_cours') {
            return false;
        }

        $today = now()->startOfDay();

        if ($this->date_debut_inscription && $today->lt($this->date_debut_inscription->startOfDay())) {
            return false;
        }

        if ($this->date_fin_inscription && $today->gt($this->date_fin_inscription->endOfDay())) {
            return false;
        }

        return true;
    }

    public function classes()
    {
        return $this->hasMany(Classe::class, 'anneeScolaire_id');
    }

    public function typeFrais()
    {
        return $this->hasMany(TypeFrais::class, 'annee_scolaire_id');
    }

    public function calendrierScolaire()
    {
        return $this->hasMany(CalendrierScolaire::class, 'annee_scolaire_id');
    }
}
