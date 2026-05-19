<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class NiveauSeeder extends Seeder
{
    public function run()
    {
        // Truncate to avoid duplicates on re-run
        DB::statement('SET FOREIGN_KEY_CHECKS=0;');
        DB::table('niveaux')->truncate();
        DB::statement('SET FOREIGN_KEY_CHECKS=1;');

        $niveaux = [
            // Primaire
            ['cycle' => 'primaire', 'nom_niveau' => 'CP',   'serie' => null],
            ['cycle' => 'primaire', 'nom_niveau' => 'CE1',  'serie' => null],
            ['cycle' => 'primaire', 'nom_niveau' => 'CE2',  'serie' => null],
            ['cycle' => 'primaire', 'nom_niveau' => 'CM1',  'serie' => null],
            ['cycle' => 'primaire', 'nom_niveau' => 'CM2',  'serie' => null],

            // College
            ['cycle' => 'college', 'nom_niveau' => '6eme', 'serie' => null],
            ['cycle' => 'college', 'nom_niveau' => '5eme', 'serie' => null],
            ['cycle' => 'college', 'nom_niveau' => '4eme', 'serie' => null],
            ['cycle' => 'college', 'nom_niveau' => '3eme', 'serie' => null],

            // Lycee - Seconde (pas de serie)
            ['cycle' => 'lycee', 'nom_niveau' => 'Seconde',   'serie' => null],

            // Lycee - Premiere (avec series)
            ['cycle' => 'lycee', 'nom_niveau' => 'Premiere', 'serie' => 'S'],
            ['cycle' => 'lycee', 'nom_niveau' => 'Premiere', 'serie' => 'OSE'],
            ['cycle' => 'lycee', 'nom_niveau' => 'Premiere', 'serie' => 'A1'],
            ['cycle' => 'lycee', 'nom_niveau' => 'Premiere', 'serie' => 'A2'],
            ['cycle' => 'lycee', 'nom_niveau' => 'Premiere', 'serie' => 'C'],
            ['cycle' => 'lycee', 'nom_niveau' => 'Premiere', 'serie' => 'D'],

            // Lycee - Terminale (avec series)
            ['cycle' => 'lycee', 'nom_niveau' => 'Terminale', 'serie' => 'S'],
            ['cycle' => 'lycee', 'nom_niveau' => 'Terminale', 'serie' => 'OSE'],
            ['cycle' => 'lycee', 'nom_niveau' => 'Terminale', 'serie' => 'A1'],
            ['cycle' => 'lycee', 'nom_niveau' => 'Terminale', 'serie' => 'A2'],
            ['cycle' => 'lycee', 'nom_niveau' => 'Terminale', 'serie' => 'C'],
            ['cycle' => 'lycee', 'nom_niveau' => 'Terminale', 'serie' => 'D'],
        ];

        $now = now();
        foreach ($niveaux as $niveau) {
            DB::table('niveaux')->insert([
                'cycle'      => $niveau['cycle'],
                'nom_niveau' => $niveau['nom_niveau'],
                'serie'      => $niveau['serie'],
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        $this->command->info('Niveaux reinitialises : ' . count($niveaux) . ' entrees propres.');
    }
}