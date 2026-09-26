<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\Gestion_note\Bulletin;
use App\Models\Gestion_note\Notes;
use App\Models\Inscription\AnneeScolaire;
use App\Models\Inscription\Inscription;
use App\Models\Inscription\Paiement;
use App\Models\Paiement\ResumePaiement;
use App\Models\Paiement\Recu;
use App\Models\Finance\Entree;
use App\Models\Finance\Sortie;
use Carbon\Carbon;

class CaissierController extends Controller
{
    public function index()
    {
        // Vérifier les retards de paiement
        \App\Http\Controllers\NotificationController::checkLatePayments();

        $now         = Carbon::now();
        $anneeActive = $this->getAnneeActive();

        // ── Encaissements ────────────────────────────────────────────────────
        // Total de tous les paiements reçus (année scolaire en cours)
        $totalEncaissements = $this->getTotalEncaissements($anneeActive);

        // Total encaissé ce mois-ci
        $totalEncaissementsMois = $this->getTotalEntreeMois($now->month, $now->year, $anneeActive);

        // ── Décaissements ────────────────────────────────────────────────────
        // Montant total dû (attendu) pour l'année = total_du de tous les résumés
        $totalDu = $this->getTotalDu($anneeActive);

        // Total encore impayé (= montant attendu non reçu)
        $totalRestant = $this->getTotalRestant($anneeActive);

        // -- Sorties (Décaissements réels) --
        $totalSorties = $this->getTotalSorties($anneeActive);

        // ── Solde Net ────────────────────────────────────────────────────────
        // Solde net = Total encaissé - Total décaissé
        $soldeNet = $totalEncaissements - $totalSorties;

        // ── Autres indicateurs ───────────────────────────────────────────────
        $nombreElevesInscrits  = $this->getNombreElevesInscrits($anneeActive);
        $tauxRecouvrement      = $this->getTauxRecouvrement($anneeActive, $totalDu, $totalEncaissements);
        $transactionsRecentes  = $this->getTransactionsRecentes(10, $anneeActive);
        $historiqueRecent      = $this->getHistoriqueRecent(12, $anneeActive);

        return response()->json([
            'success' => true,
            'data'    => [
                'statistique' => [
                    // Encaissements
                    'total_encaissements'          => $totalEncaissements,
                    'total_encaissements_formatte'  => $this->formatterMontant($totalEncaissements),
                    'total_encaissements_mois'      => $totalEncaissementsMois,
                    'total_encaissements_mois_formatte' => $this->formatterMontant($totalEncaissementsMois),

                    // Décaissements (sorties réelles)
                    'total_sorties'                 => $totalSorties,
                    'total_sorties_formatte'        => $this->formatterMontant($totalSorties),
                    
                    // Aliases pour le frontend
                    'total_collecte'                => $totalEncaissements,
                    'total_decaissements'           => $totalSorties,
                    'nombre_recus'                  => $this->getNombreRecus($anneeActive),

                    // Montants attendus / Impayés
                    'total_restant_du'              => $totalRestant,
                    'total_restant_du_formatte'     => $this->formatterMontant($totalRestant),
                    'total_du'                      => $totalDu,
                    'total_du_formatte'             => $this->formatterMontant($totalDu),

                    // Solde net = encaissements - sorties
                    'solde_net'                     => $soldeNet,
                    'solde_net_formatte'            => $this->formatterMontant($soldeNet),

                    // Élèves
                    'nombre_eleves_inscrits'        => $nombreElevesInscrits,

                    // Taux de recouvrement
                    'taux_recouvrement'             => $tauxRecouvrement,
                    'taux_recouvrement_formatte'    => $tauxRecouvrement . '%',

                    // Compatibilité anciens champs
                    'total_prevu'                   => $totalDu,
                    'total_prevu_formatte'          => $this->formatterMontant($totalDu),
                    'total_prevu_mois'              => $totalEncaissementsMois,
                    'total_prevu_mois_formatte'     => $this->formatterMontant($totalEncaissementsMois),
                    'total_entree_mois'             => $totalEncaissementsMois,
                    'total_entree_mois_formatte'    => $this->formatterMontant($totalEncaissementsMois),
                ],
                'evolution_mensuelle'   => $this->getEvolutionMensuelle($anneeActive),
                'repartition_classe'    => $this->getRepartitionParClasse($anneeActive),
                'transactions_recentes' => $transactionsRecentes,
                'historique_recent'     => $historiqueRecent,
                'date_rappel'           => $now->format('d/m/Y H:i:s'),
                'annee_scolaire'        => $anneeActive ? [
                    'id'      => $anneeActive->id,
                    'libelle' => $anneeActive->libelle,
                    'statut'  => $anneeActive->statut,
                ] : null,
            ],
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // CALCULS FINANCIERS
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Total de tous les paiements effectivement reçus (encaissements réels).
     */
    private function getTotalEncaissements(?AnneeScolaire $anneeActive): float
    {
        if (!$anneeActive) return 0.0;

        return (float) Entree::where('annee_scolaire_id', $anneeActive->id)->sum('montant');
    }

    /**
     * Total de tous les décaissements (sorties réelles).
     */
    private function getTotalSorties(?AnneeScolaire $anneeActive): float
    {
        if (!$anneeActive) return 0.0;

        return (float) Sortie::where('annee_scolaire_id', $anneeActive->id)->sum('montant');
    }

    /**
     * Total prévu (somme de tous les montants dus) pour l'année.
     */
    private function getTotalDu(?AnneeScolaire $anneeActive): float
    {
        if (!$anneeActive) return 0.0;

        return (float) ResumePaiement::whereHas('inscription', function ($q) use ($anneeActive) {
            $q->where('id_annee_scolaire', $anneeActive->id);
        })->sum('total_du');
    }

    /**
     * Total restant à payer (impayés = décaissements attendus non reçus).
     */
    private function getTotalRestant(?AnneeScolaire $anneeActive): float
    {
        if (!$anneeActive) return 0.0;

        return (float) ResumePaiement::whereHas('inscription', function ($q) use ($anneeActive) {
            $q->where('id_annee_scolaire', $anneeActive->id);
        })->sum('total_restant');
    }

    /**
     * Encaissements du mois en cours.
     */
    private function getTotalEntreeMois(int $mois, int $annee, ?AnneeScolaire $anneeActive): float
    {
        if (!$anneeActive) return 0.0;

        return (float) Entree::where('annee_scolaire_id', $anneeActive->id)
            ->whereMonth('date_entree', $mois)
            ->whereYear('date_entree', $annee)
            ->sum('montant');
    }

    /**
     * Nombre d'élèves inscrits cette année.
     */
    private function getNombreElevesInscrits(?AnneeScolaire $anneeActive): int
    {
        if (!$anneeActive) return 0;

        return Inscription::where('id_annee_scolaire', $anneeActive->id)->count();
    }

    /**
     * Taux de recouvrement = (total encaissé / total dû) * 100.
     */
    private function getTauxRecouvrement(?AnneeScolaire $anneeActive, float $totalDu, float $totalEncaissements): float
    {
        if (!$anneeActive || $totalDu <= 0) return 0.0;

        return round(($totalEncaissements / $totalDu) * 100, 2);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // TRANSACTIONS ET HISTORIQUE
    // ─────────────────────────────────────────────────────────────────────────

    private function getTransactionsRecentes(int $limit, ?AnneeScolaire $anneeActive)
    {
        if (!$anneeActive) return collect();

        return Paiement::with(['inscription.eleve', 'typeFrais'])
            ->whereHas('inscription', function ($q) use ($anneeActive) {
                $q->where('id_annee_scolaire', $anneeActive->id);
            })
            ->orderBy('date_paiement', 'desc')
            ->limit($limit)
            ->get()
            ->map(fn ($p) => [
                'id'               => $p->id,
                'reference'        => $p->reference,
                'date_paiement'    => $p->date_paiement?->format('d/m/Y'),
                'montant'          => (float) $p->montant,
                'montant_formatte' => $this->formatterMontant((float) $p->montant),
                'eleve'            => $p->inscription?->eleve
                    ? trim($p->inscription->eleve->nom . ' ' . $p->inscription->eleve->prenom)
                    : 'Inconnu',
                'matricule'        => $p->inscription?->eleve?->matricule,
                'libelle'          => $p->libelle ?? ($p->typeFrais?->libelle ?? 'Paiement divers'),
                'type'             => $p->type ?? 'autre',
            ]);
    }

    private function getHistoriqueRecent(int $limit, ?AnneeScolaire $anneeActive): array
    {
        if (!$anneeActive) return [];

        $paiements = Paiement::with(['inscription.eleve'])
            ->whereHas('inscription', fn ($q) => $q->where('id_annee_scolaire', $anneeActive->id))
            ->latest('created_at')->limit($limit)->get()
            ->map(fn ($p) => [
                'type_evenement' => 'paiement',
                'date_evenement' => optional($p->created_at)->toIso8601String(),
                'titre'          => 'Paiement enregistre',
                'description'    => trim(($p->inscription?->eleve?->prenom ?? '') . ' ' . ($p->inscription?->eleve?->nom ?? '')),
                'montant'        => (float) $p->montant,
                'reference_id'   => $p->id,
            ]);

        $inscriptions = Inscription::with(['eleve', 'classe'])
            ->where('id_annee_scolaire', $anneeActive->id)
            ->latest('created_at')->limit($limit)->get()
            ->map(fn ($i) => [
                'type_evenement' => 'inscription',
                'date_evenement' => optional($i->created_at)->toIso8601String(),
                'titre'          => 'Nouvel eleve inscrit',
                'description'    => trim(($i->eleve?->prenom ?? '') . ' ' . ($i->eleve?->nom ?? '')) . ' - ' . ($i->classe?->nom_classe ?? ''),
                'reference_id'   => $i->id,
            ]);

        $notes = Notes::with(['inscription.eleve', 'matiere'])
            ->whereHas('inscription', fn ($q) => $q->where('id_annee_scolaire', $anneeActive->id))
            ->latest('created_at')->limit($limit)->get()
            ->map(fn ($n) => [
                'type_evenement' => 'note',
                'date_evenement' => optional($n->created_at)->toIso8601String(),
                'titre'          => 'Nouvelle note saisie',
                'description'    => trim(($n->inscription?->eleve?->prenom ?? '') . ' ' . ($n->inscription?->eleve?->nom ?? '')) . ' - ' . ($n->matiere?->nom ?? 'Matiere'),
                'reference_id'   => $n->id,
            ]);

        $bulletins = Bulletin::with(['inscription.eleve'])
            ->whereHas('inscription', fn ($q) => $q->where('id_annee_scolaire', $anneeActive->id))
            ->latest('created_at')->limit($limit)->get()
            ->map(fn ($b) => [
                'type_evenement' => 'bulletin',
                'date_evenement' => optional($b->created_at)->toIso8601String(),
                'titre'          => 'Bulletin genere',
                'description'    => trim(($b->inscription?->eleve?->prenom ?? '') . ' ' . ($b->inscription?->eleve?->nom ?? '')) . ' - ' . ($b->periode ?? ''),
                'reference_id'   => $b->id,
            ]);

        return $paiements
            ->concat($inscriptions)
            ->concat($notes)
            ->concat($bulletins)
            ->sortByDesc('date_evenement')
            ->take($limit)
            ->values()
            ->all();
    }

    // ─────────────────────────────────────────────────────────────────────────
    // UTILITAIRES
    // ─────────────────────────────────────────────────────────────────────────

    private function getAnneeActive(): ?AnneeScolaire
    {
        return AnneeScolaire::where('statut', 'en_cours')->first();
    }

    private function formatterMontant(float $montant): string
    {
        if ($montant >= 1_000_000) {
            return 'Ar ' . number_format($montant / 1_000_000, 1, '.', '') . 'M';
        }
        if ($montant >= 1_000) {
            return 'Ar ' . number_format($montant / 1_000, 1, '.', '') . 'K';
        }
        return 'Ar ' . number_format($montant, 0, ',', ' ');
    }

    /**
     * Retourne le nombre total de reçus émis pour l'année active.
     */
    private function getNombreRecus(?AnneeScolaire $anneeActive): int
    {
        if (!$anneeActive) return 0;

        return (int) Recu::whereHas('paiement.inscription', function ($q) use ($anneeActive) {
            $q->where('id_annee_scolaire', $anneeActive->id);
        })->count();
    }

    /**
     * Évolution mensuelle des encaissements et décaissements sur les 6 derniers mois.
     */
    private function getEvolutionMensuelle(?AnneeScolaire $anneeActive): array
    {
        if (!$anneeActive) return [];

        $data = [];
        for ($i = 5; $i >= 0; $i--) {
            $date = Carbon::now()->subMonths($i);
            $mois = $date->month;
            $an = $date->year;

            $encaissements = (float) Entree::where('annee_scolaire_id', $anneeActive->id)
                ->whereMonth('date_entree', $mois)
                ->whereYear('date_entree', $an)
                ->sum('montant');

            $decaissements = (float) Sortie::where('annee_scolaire_id', $anneeActive->id)
                ->whereMonth('date_sortie', $mois)
                ->whereYear('date_sortie', $an)
                ->sum('montant');

            $data[] = [
                'month'         => strtoupper($date->translatedFormat('M')),
                'encaissements' => $encaissements,
                'decaissements' => $decaissements,
            ];
        }

        return $data;
    }

    /**
     * Répartition des paiements par cycle (Maternelle, Primaire, Collège, Lycée).
     */
    private function getRepartitionParClasse(?AnneeScolaire $anneeActive): array
    {
        if (!$anneeActive) return [];

        $cycles = ['Maternelle', 'Primaire', 'Collège', 'Lycée'];
        $colors = ['#8B5CF6', '#F59E0B', '#10B981', '#3B82F6'];
        $data = [];

        foreach ($cycles as $index => $cycle) {
            $total = (float) Paiement::whereHas('inscription', function ($q) use ($anneeActive, $cycle) {
                $q->where('id_annee_scolaire', $anneeActive->id)
                  ->whereHas('classe.niveau', function ($sq) use ($cycle) {
                      $sq->where('cycle', $cycle);
                  });
            })->sum('montant');

            $data[] = [
                'name'  => $cycle,
                'value' => $total,
                'color' => $colors[$index],
            ];
        }

        // Calculer les pourcentages
        $totalGeneral = array_sum(array_column($data, 'value'));
        if ($totalGeneral > 0) {
            foreach ($data as &$item) {
                $item['value'] = round(($item['value'] / $totalGeneral) * 100, 1);
            }
        }

        return $data;
    }
}
