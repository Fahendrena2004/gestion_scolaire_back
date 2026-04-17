<?php

namespace App\Models\Inscription;

use Illuminate\Database\Eloquent\Model;

class AntecedantScolaire extends Model
{
    //
    protected $table = 'antecedant_scolaires';

    protected $fillable = [
        'etablissement',
        'periode',
        'diplome_obtenu'
    ];
}
