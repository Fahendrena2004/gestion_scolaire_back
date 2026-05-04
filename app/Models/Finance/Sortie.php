<?php

namespace App\Models\Finance;

use Illuminate\Database\Eloquent\Model;

use App\Models\Inscription\AnneeScolaire;
use App\Models\Staff;
use App\Models\Utilisateur;

class Sortie extends Model
{
    protected $fillable = [
        'reference', 'montant', 'date_sortie', 'type_sortie_id', 
        'staff_id', 'statut', 'annee_scolaire_id', 
        'description', 'created_by', 'validated_by', 'paid_by'
    ];

    public function type()
    {
        return $this->belongsTo(CategorieSortie::class, 'type_sortie_id');
    }

    public function staff()
    {
        return $this->belongsTo(Staff::class);
    }

    public function anneeScolaire()
    {
        return $this->belongsTo(AnneeScolaire::class);
    }

    public function creator()
    {
        return $this->belongsTo(Utilisateur::class, 'created_by');
    }

    public function validator()
    {
        return $this->belongsTo(Utilisateur::class, 'validated_by');
    }

    public function payer()
    {
        return $this->belongsTo(Utilisateur::class, 'paid_by');
    }
}
