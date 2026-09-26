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
                'date_debut_inscription' => '2025-08-01',
                'date_fin_inscription' => '2025-10-31',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            DB::table('annee_scolaires')->insert([
                'date_debut' => '2024-09-01',
                'date_fin' => '2025-06-30',
                'statut' => 'termine',
                'date_debut_inscription' => '2024-08-01',
                'date_fin_inscription' => '2024-10-31',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            DB::table('annee_scolaires')->insert([
                'date_debut' => '2026-09-01',
                'date_fin' => '2027-06-30',
                'statut' => 'planifie',
                'date_debut_inscription' => '2026-08-01',
                'date_fin_inscription' => '2026-10-31',
                'created_at' => now(),
                'updated_at' => now(),
            ]);


    }
}
