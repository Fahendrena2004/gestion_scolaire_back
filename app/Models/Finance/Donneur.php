<?php

namespace App\Models\Finance;

use Illuminate\Database\Eloquent\Model;

class Donneur extends Model
{
    protected $fillable = ['nom', 'contact'];

    public function entrees()
    {
        return $this->hasMany(Entree::class);
    }
}
