<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Utilisateur extends Authenticatable
{
    use HasFactory, Notifiable;

    
    protected $table = 'utilisateurs';

    
    protected $fillable = [
        'nom',
        'prenom',
        'telephone',
        'email',
        'password',
        'role',
        'status',
    ];

    
    protected $hidden = [
        'password',
        'remember_token',
    ];

    

    
    public function setPasswordAttribute($value)
    {
        if (!empty($value)) {
            $this->attributes['password'] = bcrypt($value);
        }
    }

    /**
     * 🧠 Accesseur : nom complet
     * Utilisation : $user->full_name
     */
    public function getFullNameAttribute()
    {
        return $this->nom . ' ' . $this->prenom;
    }

    
    public function isAdmin()
    {
        return $this->role === 'admin';
    }

   
    public function isCaissier()
    {
        return $this->role === 'caissier';
    }

    
    public function isActif()
    {
        return $this->status === 'actif';
    }

    
    public function isInactif()
    {
        return $this->status === 'inactif';
    }
}