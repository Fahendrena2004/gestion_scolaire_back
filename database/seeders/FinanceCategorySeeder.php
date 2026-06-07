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
            ['nom' => 'Paiement Scolarité', 'description' => 'Paiement des frais de scolarité mensuels'],
            ['nom' => 'Frais d\'Inscription', 'description' => 'Frais d\'inscription annuelle'],
            ['nom' => 'Donation', 'description' => 'Dons de bienfaiteurs'],
            ['nom' => 'Partenariat', 'description' => 'Soutiens d\'organisations partenaires'],
            ['nom' => 'Partenaire', 'description' => 'Soutien provenant d\'un partenaire'],
            ['nom' => 'Association', 'description' => 'Soutien provenant d\'une association'],
            ['nom' => 'Ministere', 'description' => 'Soutien provenant du ministere'],
            ['nom' => 'Vente Fournitures', 'description' => 'Vente de matériel scolaire'],
        ];

        foreach ($categoriesEntree as $cat) {
            \App\Models\Finance\CategorieEntree::updateOrCreate(['nom' => $cat['nom']], $cat);
        }

        $categoriesSortie = [
            ['nom' => 'Salaire', 'description' => 'Paiement des salaires du personnel'],
            ['nom' => 'Prime', 'description' => 'Prime du personnel'],
            ['nom' => 'Bonus', 'description' => 'Bonus du personnel'],
            ['nom' => 'Avance', 'description' => 'Avance sur salaire'],
            ['nom' => 'Professeur', 'description' => 'Paiement des honoraires des professeurs'],
            ['nom' => 'Surveillance', 'description' => 'Paiement des surveillants'],
            ['nom' => 'Gardien', 'description' => 'Paiement du service de gardiennage'],
            ['nom' => 'Chauffeur', 'description' => 'Paiement des chauffeurs'],
            ['nom' => 'Coach', 'description' => 'Paiement des coachs sportifs'],
            ['nom' => 'Craie', 'description' => 'Achat de craies et petit matériel'],
            ['nom' => 'JIRAMA', 'description' => 'Factures d\'électricité et d\'eau'],
            ['nom' => 'Materielle', 'description' => 'Achat de materiels pour l\'ecole'],
            ['nom' => 'Aliment', 'description' => 'Achats alimentaires et restauration'],
            ['nom' => 'Cantine', 'description' => 'Achats pour la restauration'],
            ['nom' => 'Médical', 'description' => 'Achats de pharmacie et soins'],
            ['nom' => 'Maintenance', 'description' => 'Entretien des bâtiments'],
            ['nom' => 'Fournitures', 'description' => 'Fournitures administratives'],
            ['nom' => 'Transport', 'description' => 'Carburant et entretien véhicule'],
        ];

        foreach ($categoriesSortie as $cat) {
            \App\Models\Finance\CategorieSortie::updateOrCreate(['nom' => $cat['nom']], $cat);
        }
    }
}
