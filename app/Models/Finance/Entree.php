<?php

namespace App\Models\Finance;

use Illuminate\Database\Eloquent\Model;

use App\Models\Inscription\AnneeScolaire;
use App\Models\Inscription\Inscription;
use App\Models\Utilisateur;

class Entree extends Model
{
    protected $fillable = [
        'reference', 'montant', 'date_entree', 'type_entree_id', 
        'inscription_id', 'donneur_id', 'annee_scolaire_id', 
        'description', 'created_by'
    ];

    public function type()
    {
        return $this->belongsTo(CategorieEntree::class, 'type_entree_id');
    }

    public function inscription()
    {
        return $this->belongsTo(Inscription::class);
    }

    public function donneur()
    {
        return $this->belongsTo(Donneur::class);
    }

    public function anneeScolaire()
    {
        return $this->belongsTo(AnneeScolaire::class);
    }

    public function creator()
    {
        return $this->belongsTo(Utilisateur::class, 'created_by');
    }
}
