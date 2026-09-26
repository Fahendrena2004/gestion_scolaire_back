<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ClasseSeeder extends Seeder
{
    public function run()
    {
        // Annee scolaire active
        $anneeActive = DB::table('annee_scolaires')->where('statut', 'en_cours')->first();

        if (!$anneeActive) {
            $this->command->error('Aucune annee scolaire active trouvee !');
            $this->command->info('Veuillez d\'abord executer AnneeScolaireSeeder');
            return;
        }

        // Truncate classes (safe: FK checks off)
        DB::statement('SET FOREIGN_KEY_CHECKS=0;');
        DB::table('classes')->truncate();
        DB::statement('SET FOREIGN_KEY_CHECKS=1;');

        // Recuperer tous les niveaux frais
        $niveaux = DB::table('niveaux')->get();

        $lettres    = ['A', 'B', 'C', 'D'];
        $totalClasses = 0;
        $now        = now();

        foreach ($niveaux as $niveau) {
            $nbDivisions = $this->getNombreDivisions($niveau->cycle, $niveau->nom_niveau);

            for ($i = 0; $i < $nbDivisions; $i++) {
                $lettre = $lettres[$i];

                // Build class name:
                // - Lycee with serie  → "Premiere S A"  / "Terminale OSE B"
                // - Lycee no serie    → "Seconde A"
                // - College / Primaire → "6eme A" / "CP A"
                if (!empty($niveau->serie)) {
                    $nomClasse = $niveau->nom_niveau . ' ' . $niveau->serie . ' ' . $lettre;
                } else {
                    $nomClasse = $niveau->nom_niveau . ' ' . $lettre;
                }

                DB::table('classes')->insert([
                    'nom_classe'       => $nomClasse,
                    'niveau_id'        => $niveau->id,
                    'code_division'    => $lettre,
                    'effectif'         => 0,
                    'anneeScolaire_id' => $anneeActive->id,
                    'created_at'       => $now,
                    'updated_at'       => $now,
                ]);
                $totalClasses++;
            }
        }

        $this->command->info('Classes creees : ' . $totalClasses);
        $this->command->info('Annee scolaire : ' . $anneeActive->date_debut . ' - ' . $anneeActive->date_fin);
    }

    /**
     * Nombre de divisions (sections) par niveau.
     *
     * Primaire          : 2  (A, B)
     * College 6eme      : 4  (A, B, C, D)
     * College 5,4,3eme  : 3  (A, B, C)
     * Lycee Seconde     : 4  (A, B, C, D)
     * Lycee Premiere    : 2  (A, B)   — per serie
     * Lycee Terminale   : 2  (A, B)   — per serie
     */
    private function getNombreDivisions(string $cycle, string $nomNiveau): int
    {
        return match (true) {
            $cycle === 'primaire'          => 2,
            $nomNiveau === '6eme'          => 4,
            $cycle === 'college'           => 3,
            $nomNiveau === 'Seconde'       => 4,
            $nomNiveau === 'Premiere'      => 2,
            $nomNiveau === 'Terminale'     => 2,
            default                        => 2,
        };
    }
}