<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Staff;

class AutreInformationStaff extends Model
{
    protected $table = 'autre_information_staff';

    protected $fillable = [
        'staff_id',
        'nom_champ',
        'valeur_champ'
    ];

    public function staff()
    {
        return $this->belongsTo(Staff::class, 'staff_id');
    }
}
