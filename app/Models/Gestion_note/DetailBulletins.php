<?php

namespace App\Models\Gestion_note;

use Illuminate\Database\Eloquent\Model;

class DetailBulletins extends Model
{
    protected $table = 'detail_bulletins';

    protected $fillable = [
        'bulletin_id',
        'matiere_id',
        'moyenne_matiere',
        'rang_matiere',
        'appreciation',
    ];

    public function bulletin()
    {
        return $this->belongsTo(Bulletin::class, 'bulletin_id');
    }

    
    public function matiere()
    {
        return $this->belongsTo(Matieres::class, 'matiere_id');
    }
}