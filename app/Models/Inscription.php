<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Inscription extends Model
{
    protected $table = "inscriptions";
    
    protected $fillable = [
        'nom',
        'prenom',
        'sexe',
        'classe',
        'annee_scolaire',
        'date_naissane',
        'lieu_naissance',
        'date_inscription',
        'frais_scolarite',
    ];
}
