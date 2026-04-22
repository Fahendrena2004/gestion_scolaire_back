<?php

namespace App\Models\Inscription;

use Illuminate\Database\Eloquent\Model;
use App\Models\Utilisateur;
use App\Models\Paiement\PresenceCantine;
use App\Models\Paiement\ResumePaiement;
class Inscription extends Model
{
    protected $table = 'inscriptions';

    protected $appends = [
        'user_id',
    ];

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

    public function anneeScolaire()
    {
        return $this->belongsTo(AnneeScolaire::class, 'id_annee_scolaire', 'id');
    }

    public function classe()
    {
        return $this->belongsTo(Classe::class, 'id_classe', 'id');
    }

     public function eleve()
    {
        return $this->belongsTo(Eleve::class, 'id_eleve', 'id');
    }

    public function utilisateur(){
        return $this->belongsTo(Utilisateur::class, 'utilisateur_id', 'id');
    }

    public function user()
    {
        return $this->utilisateur();
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

    public function fraisAppliques()
    {
        return $this->hasMany(FraisApplique::class, 'id_inscription');
    }

    public function resumePaiement()
    {
        return $this->hasOne(ResumePaiement::class, 'inscription_id');
    }

    public function presencesCantine()
    {
        return $this->hasMany(PresenceCantine::class, 'inscription_id');
    }

    public function getUserIdAttribute()
    {
        return $this->utilisateur_id;
    }

}
