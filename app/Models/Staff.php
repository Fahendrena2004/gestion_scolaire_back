<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Staff extends Model
{
    //
    protected $table = 'staffs';

    protected $fillable = [
        'nom',
        'prenom',
        'telephone',
        'matricule',
        'email',
        'fonction',
        'salaire',
        'adresse',
        'sexe',
        'date_naissance',
        'lieu_naissance',
        'utilisateur_id',
    ];

    protected $appends = ['autres_donnees'];
    
    public function utilisateur()
    {
        return $this->belongsTo(Utilisateur::class, 'utilisateur_id');
    }

    public function infosDynamiques()
    {
        return $this->hasMany(AutreInformationStaff::class, 'staff_id');
    }

    /**
     * Reconstitue la structure JSON attendue par le frontend à partir des lignes de la table liée.
     */
    public function getAutresDonneesAttribute()
    {
        $infos = $this->infosDynamiques;
        
        $personnelles = [];
        $professionnelles = [];
        $notes = '';

        foreach ($infos as $info) {
            if ($info->nom_champ === 'Notes') {
                $notes = $info->valeur_champ;
            } elseif (in_array($info->nom_champ, ['Expérience', 'Diplôme', 'Contrat', 'Prime'])) {
                $professionnelles[] = [
                    'id' => $info->id,
                    'label' => $info->nom_champ,
                    'value' => $info->valeur_champ
                ];
            } else {
                $personnelles[] = [
                    'id' => $info->id,
                    'label' => $info->nom_champ,
                    'value' => $info->valeur_champ
                ];
            }
        }

        return [
            'informations_personnelles' => $personnelles,
            'informations_professionnelles' => $professionnelles,
            'notes' => $notes
        ];
    }
}
