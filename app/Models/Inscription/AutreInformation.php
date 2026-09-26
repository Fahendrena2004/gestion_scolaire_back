<?php

namespace App\Models\Inscription;

use Illuminate\Database\Eloquent\Model;
use App\Models\Inscription\Eleve;

class AutreInformation extends Model
{
    //
    protected $table = 'autre_information';

    protected $fillable = [
        'id_eleve',
        'nom_champ',
        'valeur_champ'
    ];

    public function eleve()
    {
        return $this->belongsTo(Eleve::class, 'id_eleve');
    }
}
