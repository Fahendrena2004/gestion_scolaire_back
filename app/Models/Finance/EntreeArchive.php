<?php

namespace App\Models\Finance;

use Illuminate\Database\Eloquent\Model;

class EntreeArchive extends Model
{
    protected $table = 'entrees_archive';
    protected $fillable = [
        'reference', 'montant', 'date_entree', 'type_entree_nom', 
        'source_nom', 'annee_scolaire_libelle', 'description'
    ];
}
