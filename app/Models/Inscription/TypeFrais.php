<?php

namespace App\Models\Inscription;

use Illuminate\Database\Eloquent\Model;

class TypeFrais extends Model
{
    //
    protected $table = 'type_frais';

    protected $fillable = [
        'libelle',
        'montant',
        'est_obligatoire'
    ];

}
