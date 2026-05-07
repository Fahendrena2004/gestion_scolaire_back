<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Staff extends Model
{
    //
    protected $table = 'staffs';

    protected $fillable = [
        'nom',
        'prenom',
        'telephone',
        'matricule',
        'email',
        'fonction',
        'salaire',
        'adresse',
        'sexe',
        'date_naissance',
        'lieu_naissance',
        'utilisateur_id',
    ];
    
    public function utilisateur()
    {
        return $this->belongsTo(Utilisateur::class, 'utilisateur_id');
    }

    public function infosDynamiques()
    {
        return $this->hasMany(AutreInformationStaff::class, 'staff_id');
    }
}
