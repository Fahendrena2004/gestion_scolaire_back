<?php

namespace App\Models\Finance;

use Illuminate\Database\Eloquent\Model;

class CategorieEntree extends Model
{
    protected $table = 'categories_entree';
    protected $fillable = ['nom', 'description'];

    public function entrees()
    {
        return $this->hasMany(Entree::class, 'type_entree_id');
    }
}
