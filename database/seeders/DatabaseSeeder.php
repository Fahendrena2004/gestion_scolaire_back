<?php

namespace Database\Seeders;

use App\Models\Utilisateur;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // User::factory(10)->create();

        $this->call(AnneeScolaireSeeder::class);
        $this->call(NiveauSeeder::class);
        $this->call(TypeFraisSeeder::class);
        $this->call(ClasseSeeder::class);
        $this->call(UtilisateurSeeder::class);
        $this->call([MatieresSeeder::class,]);
        $this->call(FinanceCategorySeeder::class);
        
}
}