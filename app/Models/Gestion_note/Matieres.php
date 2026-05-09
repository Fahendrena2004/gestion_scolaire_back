<?php

namespace App\Models\Gestion_note;

use Illuminate\Database\Eloquent\Model;
use App\Models\Inscription\Classe;

class Matieres extends Model
{
    //
    protected $table = 'matieres';

    protected $fillable = [
        'nom',
        'coefficient',
        'classe_id',
        'cycle',
        'niveau_classe',
        'section',
    ];

    public function classe()
    {
        return $this->belongsTo(Classe::class, 'classe_id');
    }
}
