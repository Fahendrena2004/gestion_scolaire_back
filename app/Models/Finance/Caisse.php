<?php

namespace App\Models\Finance;

use Illuminate\Database\Eloquent\Model;

use App\Models\Inscription\AnneeScolaire;

class Caisse extends Model
{
    protected $fillable = ['nom', 'solde', 'annee_scolaire_id'];

    public function anneeScolaire()
    {
        return $this->belongsTo(AnneeScolaire::class);
    }
}
