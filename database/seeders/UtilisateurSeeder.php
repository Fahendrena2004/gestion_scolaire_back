<?php

namespace Database\Seeders;

use App\Models\Utilisateur;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class UtilisateurSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Utilisateur::firstOrCreate(
            ['email' => 'admin@gmail.com'],
            [
                'nom' => 'Admin',
                'prenom' => 'Principal',
                'telephone' => '0321234567',
                'password' => 'admin123',
                'role' => 'admin',
                'status' => 'actif',
            ]
        );

        Utilisateur::firstOrCreate(
            ['email' => 'caissier123@gmail.com'],
            [
                'nom' => 'Caissier',
                'prenom' => 'Principal',
                'telephone' => '0381234567',
                'password' => 'caissier123',
                'role' => 'caissier',
                'status' => 'actif',
            ]
        );
    }
}
