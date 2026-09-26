<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StaffPointage extends Model
{
    protected $fillable = [
        'staff_id',
        'date_pointage',
        'heure_entree',
        'heure_sortie',
        'statut',
        'commentaire',
        'created_by'
    ];

    public function staff()
    {
        return $this->belongsTo(Staff::class, 'staff_id');
    }

    public function creator()
    {
        return $this->belongsTo(Utilisateur::class, 'created_by');
    }
}
