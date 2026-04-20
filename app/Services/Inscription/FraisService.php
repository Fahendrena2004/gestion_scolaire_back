<?php

namespace App\Services\Inscription;

use App\Models\Inscription\TypeFrais;
use App\Models\Inscription\FraisApplique;
use App\Models\Inscription\Inscription as InscriptionModel;

class FraisService
{
    public function getLibelleScolariteByCycle(string $cycle): string
    {
        return match($cycle) {
            'primaire' => 'Scolarité - Primaire',
            'college' => 'Scolarité - Collège',
            'lycee' => 'Scolarité - Lycée',
            default => 'Scolarité',
        };
    }

    public function calculerMontantTotal(string $cycle, array $options = []): array
    {
        $details = [];
        $total = 0;

        $fraisInscription = TypeFrais::where('libelle', 'Inscription')->first();
        if ($fraisInscription) {
            $total += $fraisInscription->montant;
            $details[] = [
                'libelle' => $fraisInscription->libelle,
                'montant' => $fraisInscription->montant,
                'type' => 'obligatoire',
            ];
        }

        $libelleScolarite = $this->getLibelleScolariteByCycle($cycle);
        $fraisScolarite = TypeFrais::where('libelle', $libelleScolarite)->first();
        if ($fraisScolarite) {
            $total += $fraisScolarite->montant;
            $details[] = [
                'libelle' => $fraisScolarite->libelle,
                'montant' => $fraisScolarite->montant,
                'type' => 'obligatoire',
            ];
        }

        $fraisTechno = TypeFrais::where('libelle', 'Frais technologiques')->first();
        if ($fraisTechno) {
            $total += $fraisTechno->montant;
            $details[] = [
                'libelle' => $fraisTechno->libelle,
                'montant' => $fraisTechno->montant,
                'type' => 'obligatoire',
            ];
        }

        if (isset($options['parascolaire']) && $options['parascolaire']) {
            $parascolaire = TypeFrais::where('libelle', 'Parascolaire')->first();
            if ($parascolaire) {
                $total += $parascolaire->montant;
                $details[] = [
                    'libelle' => $parascolaire->libelle,
                    'montant' => $parascolaire->montant,
                    'type' => 'option',
                ];
            }
        }

        if (isset($options['cantine']) && $options['cantine']) {
            $cantine = TypeFrais::where('libelle', 'Cantine')->first();
            if ($cantine) {
                $total += $cantine->montant;
                $details[] = [
                    'libelle' => $cantine->libelle,
                    'montant' => $cantine->montant,
                    'type' => 'option',
                ];
            }
        }

        return [
            'total' => $total,
            'details' => $details,
        ];
    }

    public function appliquerFrais(InscriptionModel $inscription, string $cycle, array $options = []): float
    {
        $montantTotal = 0;

        $montantTotal += $this->ajouterFrais($inscription, 'Inscription');
        $montantTotal += $this->ajouterFrais($inscription, $this->getLibelleScolariteByCycle($cycle));
        $montantTotal += $this->ajouterFrais($inscription, 'Frais technologiques');

        if (isset($options['parascolaire']) && $options['parascolaire']) {
            $montantTotal += $this->ajouterFrais($inscription, 'Parascolaire');
        }

        if (isset($options['cantine']) && $options['cantine']) {
            $montantTotal += $this->ajouterFrais($inscription, 'Cantine');
        }

        return $montantTotal;
    }

    // ✅ MÉTHODE CORRIGÉE
    private function ajouterFrais(InscriptionModel $inscription, string $libelle): float
    {
        $typeFrais = TypeFrais::where('libelle', $libelle)->first();

        if ($typeFrais) {
            FraisApplique::create([
                'id_frais' => $typeFrais->id,
                'id_inscription' => $inscription->id,  
                'montant' => $typeFrais->montant,
            ]);
            return $typeFrais->montant;
        }

        return 0;
    }
}