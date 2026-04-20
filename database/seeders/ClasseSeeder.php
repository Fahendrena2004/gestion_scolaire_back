<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ClasseSeeder extends Seeder
{
    public function run()
    {
        // Récupérer l'année scolaire active (statut = 'en_cours')
        $anneeActive = DB::table('annee_scolaires')->where('statut', 'en_cours')->first();

        if (!$anneeActive) {
            $this->command->error('❌ Aucune année scolaire active trouvée !');
            $this->command->info('   Veuillez d\'abord exécuter AnneeScolaireSeeder');
            return;
        }

        // Récupérer tous les niveaux
        $niveaux = DB::table('niveaux')->get();

        $lettres = ['A', 'B', 'C', 'D'];
        $totalClasses = 0;

        foreach ($niveaux as $niveau) {
            // Déterminer le nombre de divisions selon le niveau
            $nbDivisions = $this->getNombreDivisions($niveau->cycle, $niveau->nom_niveau);

            for ($i = 0; $i < $nbDivisions; $i++) {
                DB::table('classes')->insert([
                    'nom_classe' => $niveau->nom_niveau . ' ' . $lettres[$i],
                    'niveau_id' => $niveau->id,
                    'code_division' => $lettres[$i],
                    'effectif' => 0,
                    'anneeScolaire_id' => $anneeActive->id,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
                $totalClasses++;
            }
        }

        $this->command->info('✅ Classes créées : ' . $totalClasses);
        $this->command->info('   📅 Année scolaire : ' . $anneeActive->date_debut . ' - ' . $anneeActive->date_fin);
    }

    /**
     * Déterminer le nombre de divisions selon le niveau
     */
    private function getNombreDivisions(string $cycle, string $nomNiveau): int
    {
        return match(true) {
            $cycle == 'primaire' => 2,           // CP à CM2 : A, B
            $nomNiveau == '6ème' => 4,           // 6ème : A, B, C, D
            $nomNiveau == 'Seconde' => 4,        // Seconde : A, B, C, D
            $cycle == 'college' => 3,            // 5ème, 4ème, 3ème : A, B, C
            $cycle == 'lycee' => 3,              // Première, Terminale : A, B, C
            default => 2,
        };
    }
}