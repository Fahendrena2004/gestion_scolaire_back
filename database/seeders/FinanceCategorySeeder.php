<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class FinanceCategorySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $categoriesEntree = [
            ['nom' => 'Scolarité', 'description' => 'Paiement des frais de scolarité mensuels'],
            ['nom' => 'Inscription', 'description' => 'Frais d\'inscription annuelle'],
            ['nom' => 'Cantine', 'description' => 'Frais de restauration'],
            ['nom' => 'Parascolaire', 'description' => 'Activités extra-scolaires'],
            ['nom' => 'Don', 'description' => 'Dons de bienfaiteurs'],
            ['nom' => 'Subvention', 'description' => 'Aides de l\'état ou d\'organisations'],
        ];

        foreach ($categoriesEntree as $cat) {
            \App\Models\Finance\CategorieEntree::updateOrCreate(['nom' => $cat['nom']], $cat);
        }

        $categoriesSortie = [
            ['nom' => 'Salaire', 'description' => 'Paiement des salaires du personnel'],
            ['nom' => 'Fournitures', 'description' => 'Achat de fournitures scolaires et bureau'],
            ['nom' => 'Factures', 'description' => 'Eau, électricité, internet, etc.'],
            ['nom' => 'Entretien', 'description' => 'Maintenance des locaux'],
            ['nom' => 'Transport', 'description' => 'Frais de déplacement et carburant'],
        ];

        foreach ($categoriesSortie as $cat) {
            \App\Models\Finance\CategorieSortie::updateOrCreate(['nom' => $cat['nom']], $cat);
        }
    }
}
