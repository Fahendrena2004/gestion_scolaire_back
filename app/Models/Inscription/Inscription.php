<?php

namespace App\Models\Inscription;

use Illuminate\Database\Eloquent\Model;
use App\Models\Utilisateur;
class Inscription extends Model
{
    //
    protected $table = 'inscriptions';

    protected $fillable = [
        'id_eleve',
        'id_classe',
        'id_annee_scolaire',
        'montant_total',
        'montant_net',
        'parascolaire',
        'cantine',
        'date_inscription',
        'utilisateur_id',
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

    public function paiements()
    {
        return $this->hasMany(Paiement::class, 'inscription_id');
    }


    public function getResteAPayerAttribute()
    {
        $totalPaye = $this->paiements()->sum('montant') ?? 0;
        return $this->montant_net - $totalPaye;
    }


    public function getEstPayeAttribute()
    {
        return $this->reste_a_payer <= 0;
    }


}
