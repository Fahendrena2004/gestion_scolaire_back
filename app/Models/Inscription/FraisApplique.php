<?php

namespace App\Models\Inscription;

use Illuminate\Database\Eloquent\Model;

class FraisApplique extends Model
{
    //
    protected $table = 'frais_appliques';

    protected $fillable = [
        'id_frais',
        'id_inscription',
        'montant'
    ];

    public function typeFrais()
    {
        return $this->belongsTo(TypeFrais::class, 'id_frais');
    }

    public function inscription()
    {
        return $this->belongsTo(Inscription::class, 'id_inscription');
    }
}
