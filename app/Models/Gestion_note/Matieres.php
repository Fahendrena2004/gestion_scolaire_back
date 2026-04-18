<?php

namespace App\Models\Gestion_note;

use Illuminate\Database\Eloquent\Model;

class Matieres extends Model
{
    //
    protected $table = 'matieres';

    protected $fillable = [
        'nom',
        'coefficient',
    ];
}
