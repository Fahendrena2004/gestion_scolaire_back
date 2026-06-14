<?php

namespace App\Models\Inscription;

use Illuminate\Database\Eloquent\Model;

class ResteAvancement extends Model
{
    protected $table = 'reste_avancements';

    protected $fillable = [
        'inscription_id',
        'montant_rest',
        'statut',
    ];

    /**
     * Relationship back to the inscription.
     */
    public function inscription()
    {
        return $this->belongsTo(Inscription::class, 'inscription_id');
    }
}
