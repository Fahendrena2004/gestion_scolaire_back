<?php

namespace App\Models\Gestion_note;

use App\Models\Inscription\Inscription;
use Illuminate\Database\Eloquent\Model;

class BulletinAnnuel extends Model
{
    protected $table = 'bulletins_annuels';

    protected $fillable = [
        'inscription_id',
        'moyenne_t1',
        'moyenne_t2',
        'moyenne_t3',
        'moyenne_annuelle',
        'rang_annuel',
        'moyenne_classe_annuelle',
        'decision',
        'appreciation',
        'nb_trimestres',
        'est_complet',
    ];

    protected $casts = [
        'moyenne_t1'              => 'float',
        'moyenne_t2'              => 'float',
        'moyenne_t3'              => 'float',
        'moyenne_annuelle'        => 'float',
        'moyenne_classe_annuelle' => 'float',
        'nb_trimestres'           => 'integer',
        'rang_annuel'             => 'integer',
        'est_complet'             => 'boolean',
    ];

    public function inscription()
    {
        return $this->belongsTo(Inscription::class, 'inscription_id');
    }
}
