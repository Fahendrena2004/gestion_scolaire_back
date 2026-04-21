<?php

namespace App\Models\Inscription;

use Illuminate\Database\Eloquent\Model;

class AnneeScolaire extends Model
{
    protected $table='annee_scolaires';

    protected $fillable = [
        'date_debut',
        'date_fin',
        'statut',
    ];

    public function Libelle(){
        return $this->date_debut . ' - ' . $this->date_fin;
    }

    public function getLibelleAttribute()
    {
        return $this->Libelle();
    }



}
