<?php

namespace App\Services\Paiement;

use App\Models\Inscription\Inscription;
use App\Models\Inscription\Echeance;
use App\Models\Inscription\TypeFrais;
use Carbon\Carbon;

class EcheanceGenerator
{
    /**
     * Point d'entrée principal: Générer toutes les échéances après inscription
     */
    public function genererToutesEcheances(Inscription $inscription)
    {
        // Récupérer l'année scolaire de l'inscription
        $anneeScolaire = $inscription->anneeScolaire;

        if (!$anneeScolaire) {
            throw new \Exception("Année scolaire non trouvée pour cette inscription");
        }

        // Vérifier que l'année est valide
        if (!$anneeScolaire->date_debut || !$anneeScolaire->date_fin) {
            throw new \Exception("Dates de l'année scolaire non définies");
        }

        $dateDebut = Carbon::parse($anneeScolaire->date_debut);
        $dateFin = Carbon::parse($anneeScolaire->date_fin);
        $niveau = $inscription->classe->niveau;

        // 1. Frais d'inscription
        $this->genererEcheanceFraisInscription($inscription, $dateDebut);

        // 2. Frais technologiques
        $this->genererEcheanceFraisTechno($inscription, $dateDebut);

        // 3. Scolarité (mensuelle)
        $this->genererEcheancesScolarite($inscription, $niveau, $dateDebut, $dateFin);

        // 4. Parascolaire (si choisi)
        if ($inscription->parascolaire) {
            $this->genererEcheanceParascolaire($inscription, $dateDebut);
        }

        // 5. Cantine (si choisie) - SANS DÉTAILS
        if ($inscription->cantine) {
            $this->genererEcheancesCantine($inscription, $dateDebut, $dateFin);
        }
    }

    /**
     * 1. Frais d'inscription (unique)
     */
    private function genererEcheanceFraisInscription(Inscription $inscription, Carbon $dateDebut)
    {
        $typeFrais = TypeFrais::where('libelle', 'Inscription')->first();

        if (!$typeFrais) return;

        Echeance::create([
            'inscription_id' => $inscription->id,
            'type_frais_id' => $typeFrais->id,
            'libelle' => $typeFrais->libelle,
            'montant' => $typeFrais->montant,
            'mois' => null,
            'annee' => $dateDebut->year,
            'date_echeance' => $dateDebut->copy()->addDays(15),
            'statut' => 'impaye',
            'montant_paye' => 0,
            'montant_restant' => $typeFrais->montant,
            'a_details' => false
        ]);
    }

    /**
     * 2. Frais technologiques (unique)
     */
    private function genererEcheanceFraisTechno(Inscription $inscription, Carbon $dateDebut)
    {
        $typeFrais = TypeFrais::where('libelle', 'Frais technologiques')->first();

        if (!$typeFrais) return;

        Echeance::create([
            'inscription_id' => $inscription->id,
            'type_frais_id' => $typeFrais->id,
            'libelle' => $typeFrais->libelle,
            'montant' => $typeFrais->montant,
            'mois' => null,
            'annee' => $dateDebut->year,
            'date_echeance' => $dateDebut->copy()->addDays(30),
            'statut' => 'impaye',
            'montant_paye' => 0,
            'montant_restant' => $typeFrais->montant,
            'a_details' => false
        ]);
    }

    /**
     * 3. Scolarité (mensuelle)
     */
    private function genererEcheancesScolarite(Inscription $inscription, $niveau, Carbon $dateDebut, Carbon $dateFin)
    {
        $libelleScolarite = match($niveau->cycle) {
            'primaire' => 'Scolarité - Primaire',
            'college' => 'Scolarité - Collège',
            'lycee' => 'Scolarité - Lycée',
            default => 'Scolarité'
        };

        $typeFrais = TypeFrais::where('libelle', $libelleScolarite)->first();

        if (!$typeFrais) return;

        $currentDate = $dateDebut->copy();

        while ($currentDate <= $dateFin) {
            Echeance::create([
                'inscription_id' => $inscription->id,
                'type_frais_id' => $typeFrais->id,
                'libelle' => $typeFrais->libelle . ' - ' . $currentDate->translatedFormat('F Y'),
                'montant' => $typeFrais->montant,
                'mois' => $currentDate->month,
                'annee' => $currentDate->year,
                'date_echeance' => $currentDate->copy()->endOfMonth(),
                'statut' => 'impaye',
                'montant_paye' => 0,
                'montant_restant' => $typeFrais->montant,
                'a_details' => false
            ]);

            $currentDate->addMonth();
        }
    }

    /**
     * 4. Parascolaire (unique, si choisi)
     */
    private function genererEcheanceParascolaire(Inscription $inscription, Carbon $dateDebut)
    {
        $typeFrais = TypeFrais::where('libelle', 'Parascolaire')->first();

        if (!$typeFrais) return;

        Echeance::create([
            'inscription_id' => $inscription->id,
            'type_frais_id' => $typeFrais->id,
            'libelle' => $typeFrais->libelle,
            'montant' => $typeFrais->montant,
            'mois' => null,
            'annee' => $dateDebut->year,
            'date_echeance' => $dateDebut->copy()->addDays(15),
            'statut' => 'impaye',
            'montant_paye' => 0,
            'montant_restant' => $typeFrais->montant,
            'a_details' => false
        ]);
    }

    /**
     * 5. Cantine (mensuelle) - SANS DÉTAILS
     * Les détails sont gérés par presences_cantine
     */
    private function genererEcheancesCantine(Inscription $inscription, Carbon $dateDebut, Carbon $dateFin)
    {
        $typeFrais = TypeFrais::where('libelle', 'Cantine')->first();

        if (!$typeFrais) return;

        $prixJournalier = $typeFrais->montant;
        $currentDate = $dateDebut->copy();

        while ($currentDate <= $dateFin) {
            // Calculer le nombre de jours scolaires dans ce mois
            $nbJoursScolaires = $this->compterJoursScolaires($currentDate->month, $currentDate->year);
            $montantMensuel = $nbJoursScolaires * $prixJournalier;

            // Créer UNIQUEMENT l'échéance, pas de détails
            Echeance::create([
                'inscription_id' => $inscription->id,
                'type_frais_id' => $typeFrais->id,
                'libelle' => $typeFrais->libelle . ' - ' . $currentDate->translatedFormat('F Y'),
                'montant' => $montantMensuel,
                'mois' => $currentDate->month,
                'annee' => $currentDate->year,
                'date_echeance' => $currentDate->copy()->endOfMonth(),
                'statut' => 'impaye',
                'montant_paye' => 0,
                'montant_restant' => $montantMensuel,
                'a_details' => false  // ← Pas de détails
            ]);

            $currentDate->addMonth();
        }
    }

    /**
     * Compter le nombre de jours scolaires dans un mois (Lundi à Vendredi)
     */
    private function compterJoursScolaires($mois, $annee)
    {
        $date = Carbon::create($annee, $mois, 1);
        $finMois = $date->copy()->endOfMonth();
        $compteur = 0;

        while ($date <= $finMois) {
            if (!$date->isWeekend()) {
                $compteur++;
            }
            $date->addDay();
        }

        return $compteur;
    }
}