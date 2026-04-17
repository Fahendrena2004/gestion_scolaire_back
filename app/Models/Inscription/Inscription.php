<?php

namespace App\Models\Inscription;

use Illuminate\Database\Eloquent\Model;
use App\Models\Utilisateur;
class Inscription extends Model
{
    //
    protected $table = 'inscriptions';

    protected $fillable = [
        'annee_scolaire_id',
        'classe_id',
        'montant_total',
        'montant_net',
        'parascolaire',
        'cantine',
        'date_inscription',
        'utilisateur_id',
        'eleve_id'
    ];

    public function AnneeScolaire()
    {
        return $this->belongsTo(AnneeScolaire::class);
    }

    public function Classe()
    {
        return $this->belongsTo(Classe::class);
    }

     public function Eleve()
    {
        return $this->belongsTo(Eleve::class);
    }

    public function Utilisateur(){
        return $this->belongsTo(Utilisateur::class);
    }


}
