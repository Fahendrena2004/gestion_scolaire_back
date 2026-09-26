<?php

namespace App\Models\Gestion_note;

use App\Models\Inscription\Inscription;
use Illuminate\Database\Eloquent\Model;

class Notes extends Model
{
    protected $table = 'notes';

    protected $fillable = [
        'inscription_id',
        'matiere_id',
        'valeur',
        'periode',
        'date',
        'type',
        'appreciation',
    ];

    
    public function matiere()
    {
        return $this->belongsTo(Matieres::class, 'matiere_id');
    }

    public function inscription()
    {
        return $this->belongsTo(Inscription::class, 'inscription_id');
    }
}