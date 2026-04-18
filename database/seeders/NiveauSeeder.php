<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class NiveauSeeder extends Seeder
{
    public function run()
    {
        $niveaux = [
            // Primaire
            ['cycle' => 'primaire', 'nom_niveau' => 'CP'],
            ['cycle' => 'primaire', 'nom_niveau' => 'CE1'],
            ['cycle' => 'primaire', 'nom_niveau' => 'CE2'],
            ['cycle' => 'primaire', 'nom_niveau' => 'CM1'],
            ['cycle' => 'primaire', 'nom_niveau' => 'CM2'],

            // Collège
            ['cycle' => 'college', 'nom_niveau' => '6ème'],
            ['cycle' => 'college', 'nom_niveau' => '5ème'],
            ['cycle' => 'college', 'nom_niveau' => '4ème'],
            ['cycle' => 'college', 'nom_niveau' => '3ème'],

            // Lycée
            ['cycle' => 'lycee', 'nom_niveau' => 'Seconde'],
            ['cycle' => 'lycee', 'nom_niveau' => 'Première'],
            ['cycle' => 'lycee', 'nom_niveau' => 'Terminale'],
        ];

        foreach ($niveaux as $niveau) {
            DB::table('niveaux')->insert([
                'cycle' => $niveau['cycle'],
                'nom_niveau' => $niveau['nom_niveau'],
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $this->command->info('✅ Niveaux créés : ' . count($niveaux));
    }
}