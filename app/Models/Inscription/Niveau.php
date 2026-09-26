<?php

namespace App\Models\Inscription;

use Illuminate\Database\Eloquent\Model;
class Niveau extends Model
{
    //
    protected $table='niveaux';
    protected $fillable = [
        'cycle',
        'nom_niveau',
        'serie'
    ];




}
