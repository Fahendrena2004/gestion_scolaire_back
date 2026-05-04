<?php

namespace App\Models\Finance;

use Illuminate\Database\Eloquent\Model;

class CategorieSortie extends Model
{
    protected $table = 'categories_sortie';
    protected $fillable = ['nom', 'description'];

    public function sorties()
    {
        return $this->hasMany(Sortie::class, 'type_sortie_id');
    }
}
