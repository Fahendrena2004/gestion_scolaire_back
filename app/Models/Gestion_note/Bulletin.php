<?php

namespace App\Models\Gestion_note;

use App\Models\Inscription\Inscription;
use Illuminate\Database\Eloquent\Model;

class Bulletin extends Model
{
    protected $table = 'bulletins';

    protected $fillable = [
        'inscription_id',
        'moyenne_eleve',
        'moyenne_classe',
        'rang',
        'periode',
        'decision',
        'appreciation',
    ];

    public function inscription()
    {
        return $this->belongsTo(Inscription::class, 'inscription_id');
    }
    
    public function detailBulletins()
    {
        return $this->hasMany(DetailBulletins::class, 'bulletin_id');
    }
}