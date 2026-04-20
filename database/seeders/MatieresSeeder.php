<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Gestion_note\Matieres;

class MatieresSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $matieres = [
            // Matières principales
            ['nom' => 'Mathématiques', 'coefficient' => 4],
            ['nom' => 'Français', 'coefficient' => 4],
            ['nom' => 'Anglais', 'coefficient' => 3],
            ['nom' => 'Histoire-Géographie', 'coefficient' => 2],
            ['nom' => 'Sciences Physiques', 'coefficient' => 3],
            ['nom' => 'Sciences de la Vie et de la Terre', 'coefficient' => 3],
            ['nom' => 'Philosophie', 'coefficient' => 2],
            
            // Matières secondaires
            ['nom' => 'Éducation Physique et Sportive', 'coefficient' => 1],
            ['nom' => 'Arts Plastiques', 'coefficient' => 1],
            ['nom' => 'Musique', 'coefficient' => 1],
            ['nom' => 'Informatique', 'coefficient' => 1],
            
            // Langues
            ['nom' => 'Espagnol', 'coefficient' => 2],
            ['nom' => 'Allemand', 'coefficient' => 2],
            ['nom' => 'Latin', 'coefficient' => 1],
            
            // Matières spécifiques selon les filières
            ['nom' => 'Comptabilité', 'coefficient' => 3],
            ['nom' => 'Gestion', 'coefficient' => 3],
            ['nom' => 'Économie', 'coefficient' => 3],
            ['nom' => 'Droit', 'coefficient' => 2],
        ];

        foreach ($matieres as $matiere) {
            Matieres::create($matiere);
        }
    }
}