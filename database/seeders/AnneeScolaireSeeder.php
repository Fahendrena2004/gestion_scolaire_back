<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class AnneeScolaireSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        //

            DB::table('annee_scolaires')->insert([
                'date_debut' => '2025-09-01',
                'date_fin' => '2026-06-30',
                'statut' => 'en_cours',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            DB::table('annee_scolaires')->insert([
                'date_debut' => '2024-09-01',
                'date_fin' => '2025-06-30',
                'statut' => 'termine',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            DB::table('annee_scolaires')->insert([
                'date_debut' => '2026-09-01',
                'date_fin' => '2027-06-30',
                'statut' => 'planifie',
                'created_at' => now(),
                'updated_at' => now(),
            ]);


    }
}
