<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\Inscription\FraisApplique;
use App\Models\Inscription\Inscription;
use App\Models\Inscription\Paiement;
use App\Models\Paiement\PaiementMensuel;
use App\Models\Paiement\PresenceCantine;
use App\Models\Paiement\ResumePaiement;
use Carbon\Carbon;

class CaissierController extends Controller
{
    public function index()
    {
        $now = Carbon::now();
        $moisActuel = $now->month;
        $anneeActuelle = $now->year;

        $nombreElevesInscrits = $this->getNombreElevesInscrits();
        $totalPrevuMois = $this->getTotalPrevuMois($moisActuel, $anneeActuelle);
        $totalEntreeMois = $this->getTotalEntreeMois($moisActuel, $anneeActuelle);
        $soldeNet = $this->getSoldeNet();
        $transactionsRecentes = $this->getTransactionsRecentes(10);
        $tauxRecouvrement = $this->getTauxRecouvrement();

        return response()->json([
            'success' => true,
            'data' => [
                'statistiques' => [
                    'nombre_eleves_inscrits' => $nombreElevesInscrits,
                    'total_prevu_mois' => $totalPrevuMois,
                    'total_prevu_mois_formatte' => number_format($totalPrevuMois, 0, ',', ' ') . ' Ar',
                    'total_entree_mois' => $totalEntreeMois,
                    'total_entree_mois_formatte' => number_format($totalEntreeMois, 0, ',', ' ') . ' Ar',
                    'solde_net' => $soldeNet,
                    'solde_net_formatte' => number_format($soldeNet, 0, ',', ' ') . ' Ar',
                    'taux_recouvrement' => $tauxRecouvrement,
                    'taux_recouvrement_formatte' => $tauxRecouvrement . '%',
                ],
                'transactions_recentes' => $transactionsRecentes,
                'date_rappel' => $now->format('d/m/Y H:i:s'),
            ],
        ]);
    }

    private function getNombreElevesInscrits()
    {
        $anneeActive = $this->getAnneeActive();

        if (!$anneeActive) {
            return 0;
        }

        return Inscription::where('id_annee_scolaire', $anneeActive->id)->count();
    }

    private function getTotalPrevuMois($mois, $annee)
    {
        $totalScolarite = (float) PaiementMensuel::where('mois', $mois)
            ->where('annee', $annee)
            ->sum('montant');

        $totalCantine = (float) PresenceCantine::whereMonth('date_presence', $mois)
            ->whereYear('date_presence', $annee)
            ->sum('montant');

        $totalAutresFrais = (float) FraisApplique::whereHas('inscription', function ($query) use ($mois, $annee) {
            $query->whereMonth('date_inscription', $mois)
                ->whereYear('date_inscription', $annee);
        })->whereHas('typeFrais', function ($query) {
            $query->where('libelle', '!=', 'Cantine')
                ->where('libelle', 'not like', 'Scolarité%');
        })->sum('montant');

        return $totalScolarite + $totalCantine + $totalAutresFrais;
    }

    private function getTotalEntreeMois($mois, $annee)
    {
        return (float) Paiement::whereMonth('date_paiement', $mois)
            ->whereYear('date_paiement', $annee)
            ->sum('montant');
    }

    private function getSoldeNet()
    {
        $totalPaye = (float) Paiement::sum('montant');
        $totalDu = (float) ResumePaiement::sum('total_du');

        return $totalPaye - $totalDu;
    }

    private function getTransactionsRecentes($limit = 10)
    {
        return Paiement::with(['inscription.eleve', 'typeFrais'])
            ->orderBy('date_paiement', 'desc')
            ->limit($limit)
            ->get()
            ->map(function ($paiement) {
                return [
                    'id' => $paiement->id,
                    'reference' => $paiement->reference,
                    'date_paiement' => $paiement->date_paiement->format('d/m/Y'),
                    'montant' => $paiement->montant,
                    'montant_formatte' => number_format($paiement->montant, 0, ',', ' ') . ' Ar',
                    'eleve' => $paiement->inscription && $paiement->inscription->eleve
                        ? $paiement->inscription->eleve->nom . ' ' . $paiement->inscription->eleve->prenom
                        : 'Inconnu',
                    'matricule' => $paiement->inscription && $paiement->inscription->eleve
                        ? $paiement->inscription->eleve->matricule
                        : null,
                    'libelle' => $paiement->libelle ?? ($paiement->typeFrais?->libelle ?? 'Paiement divers'),
                    'type' => $paiement->type ?? 'autre',
                ];
            });
    }

    private function getTauxRecouvrement()
    {
        $totalPaye = (float) Paiement::sum('montant');
        $totalDu = (float) ResumePaiement::sum('total_du');

        if ($totalDu <= 0) {
            return 0;
        }

        return round(($totalPaye / $totalDu) * 100, 2);
    }

    private function getAnneeActive()
    {
        return \App\Models\Inscription\AnneeScolaire::where('statut', 'en_cours')->first();
    }
}
