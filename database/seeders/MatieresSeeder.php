<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Gestion_note\Matieres;

use Illuminate\Support\Facades\DB;
use App\Models\Inscription\Classe;

class MatieresSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Truncate to prevent unique constraint failures
        DB::statement('SET FOREIGN_KEY_CHECKS=0;');
        DB::table('matieres')->truncate();
        DB::statement('SET FOREIGN_KEY_CHECKS=1;');

        // On récupère toutes les classes avec leur niveau associé
        $classes = Classe::with('niveau')->get();

        foreach ($classes as $classe) {
            $cycle = $classe->niveau ? $classe->niveau->cycle : 'primaire';
            $niveauNom = $classe->niveau ? $classe->niveau->nom_niveau : '';
            $division = $classe->code_division; // ex: 'A', 'B', 'C', 'D'

            $matieres = [];

            if ($cycle === 'primaire') {
                $matieres = [
                    ['nom' => 'Mathématiques', 'coefficient' => 2],
                    ['nom' => 'Français', 'coefficient' => 3],
                    ['nom' => 'Éveil Scientifique', 'coefficient' => 1],
                    ['nom' => 'Éducation Civique', 'coefficient' => 1],
                    ['nom' => 'Éducation Physique et Sportive', 'coefficient' => 1],
                ];
            } elseif ($cycle === 'college') {
                $matieres = [
                    ['nom' => 'Mathématiques', 'coefficient' => 4],
                    ['nom' => 'Français', 'coefficient' => 4],
                    ['nom' => 'Anglais', 'coefficient' => 3],
                    ['nom' => 'Histoire-Géographie', 'coefficient' => 2],
                    ['nom' => 'Sciences Physiques', 'coefficient' => 2],
                    ['nom' => 'SVT', 'coefficient' => 2],
                    ['nom' => 'Éducation Physique et Sportive', 'coefficient' => 1],
                ];
            } elseif ($cycle === 'lycee') {
                if (in_array($niveauNom, ['Premiere', 'Première', 'Terminale'])) {
                    // Les divisions A et B sont souvent Littéraires, C et D Scientifiques
                    if (in_array($division, ['A', 'B'])) {
                        // Profil Littéraire
                        $matieres = [
                            ['nom' => 'Mathématiques', 'coefficient' => 2],
                            ['nom' => 'Français', 'coefficient' => 5],
                            ['nom' => 'Philosophie', 'coefficient' => 5],
                            ['nom' => 'Anglais', 'coefficient' => 4],
                            ['nom' => 'Histoire-Géographie', 'coefficient' => 4],
                            ['nom' => 'SVT', 'coefficient' => 2],
                            ['nom' => 'Éducation Physique et Sportive', 'coefficient' => 2],
                        ];
                    } else {
                        // Profil Scientifique (C, D)
                        $matieres = [
                            ['nom' => 'Mathématiques', 'coefficient' => 6],
                            ['nom' => 'Sciences Physiques', 'coefficient' => 5],
                            ['nom' => 'SVT', 'coefficient' => 5],
                            ['nom' => 'Français', 'coefficient' => 3],
                            ['nom' => 'Philosophie', 'coefficient' => 2],
                            ['nom' => 'Anglais', 'coefficient' => 2],
                            ['nom' => 'Histoire-Géographie', 'coefficient' => 2],
                            ['nom' => 'Éducation Physique et Sportive', 'coefficient' => 2],
                        ];
                    }
                } else {
                    // Seconde (Tronc commun)
                    $matieres = [
                        ['nom' => 'Mathématiques', 'coefficient' => 4],
                        ['nom' => 'Français', 'coefficient' => 4],
                        ['nom' => 'Sciences Physiques', 'coefficient' => 3],
                        ['nom' => 'SVT', 'coefficient' => 3],
                        ['nom' => 'Anglais', 'coefficient' => 3],
                        ['nom' => 'Histoire-Géographie', 'coefficient' => 3],
                        ['nom' => 'Éducation Physique et Sportive', 'coefficient' => 2],
                    ];
                }
            } else {
                $matieres = [
                    ['nom' => 'Matière Générale', 'coefficient' => 1],
                ];
            }

            // Création des matières spécifiques pour cette classe
            foreach ($matieres as $matiere) {
                Matieres::create([
                    'nom' => $matiere['nom'],
                    'coefficient' => $matiere['coefficient'],
                    'classe_id' => $classe->id,
                ]);
            }
        }
    }
}