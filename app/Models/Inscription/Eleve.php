<?php

namespace App\Models\Inscription;

use Illuminate\Database\Eloquent\Model;
use App\Models\Inscription\AutreInformation;
class Eleve extends Model
{
    protected $table = 'eleves';
    protected $fillable = [
        'nom',
        'prenom',
        'date_naissance',
        'lieu_naissance',
        'sexe',
        'adresse',
        'matricule'
    ];

    public function NomComplet()
    {
        return $this->prenom . ' ' . $this->nom;
    }

    public function DateLieuNaissance()
    {
        return $this->date_naissance . ' à ' . $this->lieu_naissance;
    }

    public function sexe()
    {
        return $this->sexe == 'M' ? 'Masculin' : 'Féminin';
    }

    public function autresInformations()
    {
        return $this->hasMany(AutreInformation::class);
    }


    public function infosDynamiques()
    {
        return $this->hasMany(AutreInformation::class, 'id_eleve');
    }

    public function inscriptions()
    {
        return $this->hasMany(Inscription::class, 'id_eleve');
    }

}
