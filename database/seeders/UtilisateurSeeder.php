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
        Utilisateur::create([
            'nom' => 'Admin',
            'prenom' => 'Principal',
            'telephone' => '0321234567',
            'email' => 'admin@gmail.com',
            'password' => 'admin123',
            'role' => 'admin',
            'status' => 'actif',
        ]);

        Utilisateur::create([
            'nom' => 'Cassier',
            'prenom' => 'Principal',
            'telephone' => '0381234567',
            'email' => 'Cassier@gmail.com',
            'password' => 'cassier123',
            'role' => 'Cassier',
            'status' => 'actif',
        ]);
    }
}
