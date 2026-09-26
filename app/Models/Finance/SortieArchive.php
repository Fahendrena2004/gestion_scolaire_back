<?php

namespace App\Models\Finance;

use Illuminate\Database\Eloquent\Model;

class SortieArchive extends Model
{
    protected $table = 'sorties_archive';
    protected $fillable = [
        'reference', 'montant', 'date_sortie', 'type_sortie_nom', 
        'beneficiaire_nom', 'annee_scolaire_libelle', 'description'
    ];
}
