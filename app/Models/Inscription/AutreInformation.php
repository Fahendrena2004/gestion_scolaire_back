<?php

namespace App\Models\Inscription;

use Illuminate\Database\Eloquent\Model;
use App\Models\Inscription\Eleve;

class AutreInformation extends Model
{
    //
    protected $table = 'autres_informations';

    protected $fillable = [
        'eleve_id',
        'nom_champ',
        'valeur_champ'
    ];

    public function eleve()
    {
        return $this->belongsTo(Eleve::class);
    }
}
