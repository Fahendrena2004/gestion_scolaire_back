<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class TypeFraisSeeder extends Seeder
{
    public function run()
    {
        $frais = [
            // Frais obligatoires
            [
                'libelle' => 'Inscription',
                'montant' => 75000,
                'est_obligatoire' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'libelle' => 'Scolarité - Primaire',
                'montant' => 350000,
                'est_obligatoire' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'libelle' => 'Scolarité - Collège',
                'montant' => 450000,
                'est_obligatoire' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'libelle' => 'Scolarité - Lycée',
                'montant' => 550000,
                'est_obligatoire' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'libelle' => 'Frais technologiques',
                'montant' => 15000,
                'est_obligatoire' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],

            // Activités optionnelles
            [
                'libelle' => 'Parascolaire',
                'montant' => 50000,
                'est_obligatoire' => false,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'libelle' => 'Sports',
                'montant' => 45000,
                'est_obligatoire' => false,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'libelle' => 'Cantine',
                'montant' => 75000,
                'est_obligatoire' => false,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ];

        foreach ($frais as $f) {
            DB::table('type_frais')->insert($f);
        }
    }
}