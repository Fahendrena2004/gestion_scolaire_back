<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\Factories\HasFactory;



class Utilisateur extends Authenticatable{
    use HasFactory, Notifiable;

    protected $table = 'utilisateurs';


    protected $fillable =[
        'nom',
        'prenom',
        'telephone',
        'email',
        'password',
        'role',
        'status',
        'remember_token',
        'created_at',
        'update_at'
    ];

    protected $hidden = [
        'password',
        'remember_token'
    ];




    public function setPasswordAttribute($value){
        if (!empty($value)) {
        $this->attributes['password'] = bcrypt($value);
        };
    }


    public function setFullName(){
        return $this->nom.' '.$this->prenom;
    }


    public function isAdmin(){
        return $this->role === 'admin';
    }


    public function isCaissier(){
        return $this->role === 'caissier';
    }


    public function isActif(){
        return $this->status === 'actif';
    }


    public function isInactif(){
        return $this->status === 'inactif';
    }


}
