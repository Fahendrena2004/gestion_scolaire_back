<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\Finance\Entree;
use App\Models\Finance\Sortie;
use App\Models\Inscription\AnneeScolaire;
use App\Models\Inscription\Inscription;
use App\Models\Inscription\Niveau;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AdminController extends Controller
{
    /**
     * Récupère les statistiques globales pour le dashboard Admin
     */
    public function getStats()
    {
        // Vérifier les retards de paiement
        \App\Http\Controllers\NotificationController::checkLatePayments();

        // 0. Année scolaire en cours
        $currentYear = AnneeScolaire::where('statut', 'en_cours')->first();
        $yearId = $currentYear ? $currentYear->id : null;

        // 1. Total Entrées (Encaissement)
        $totalEntree = Entree::when($yearId, function($q) use ($yearId) {
            return $q->where('annee_scolaire_id', $yearId);
        })->sum('montant');

        // 2. Total Sorties (Décaissement)
        $totalSortie = Sortie::when($yearId, function($q) use ($yearId) {
            return $q->where('annee_scolaire_id', $yearId);
        })->sum('montant');

        // 3. Solde Actuel (Actuel)
        $soldeActuel = $totalEntree - $totalSortie;

        // 4. Nombre transactions effectuées
        $countEntree = Entree::when($yearId, function($q) use ($yearId) {
            return $q->where('annee_scolaire_id', $yearId);
        })->count();
        $countSortie = Sortie::when($yearId, function($q) use ($yearId) {
            return $q->where('annee_scolaire_id', $yearId);
        })->count();
        $totalTransactions = $countEntree + $countSortie;

        // 5. Évolution par mois
        $evolutionMensuelle = $this->getEvolutionMensuelle($yearId);

        // 6. Répartition par cycle
        $repartitionCycle = Inscription::when($yearId, function($q) use ($yearId) {
            return $q->where('inscriptions.id_annee_scolaire', $yearId);
        })
        ->join('classes', 'inscriptions.id_classe', '=', 'classes.id')
        ->join('niveaux', 'classes.niveau_id', '=', 'niveaux.id')
        ->select('niveaux.cycle', DB::raw('count(*) as total'))
        ->groupBy('niveaux.cycle')
        ->get();

        // 7. Activités récentes
        $activitesRecentes = $this->getActivitesRecentes($yearId);

        return response()->json([
            'success' => true,
            'data' => [
                'stats_globales' => [
                    'total_entree' => $totalEntree, // Total prix entreer / encaissement
                    'total_sortie' => $totalSortie, // Sortie / decaissement
                    'solde_actuel' => $soldeActuel, // Actuel / solde actuel
                    'nombre_transactions' => $totalTransactions,
                ],
                'evolution_mensuelle' => $evolutionMensuelle, // suive evolution par mois
                'repartition_cycle' => $repartitionCycle, // repartition par cycle
                'activites_recentes' => $activitesRecentes, // activie recente (paie frais, salaire, etc.)
                'annee_scolaire' => $currentYear ? $currentYear->libelle : 'Toutes les années',
            ]
        ]);
    }

    /**
     * Calcul de l'évolution financière sur les 6 derniers mois
     */
    private function getEvolutionMensuelle($yearId)
    {
        $months = collect();
        // On prend les 6 derniers mois à partir d'aujourd'hui
        for ($i = 5; $i >= 0; $i--) {
            $months->push(now()->subMonths($i)->format('Y-m'));
        }

        return $months->map(function($monthStr) use ($yearId) {
            $date = Carbon::parse($monthStr . '-01');
            
            $entree = Entree::when($yearId, function($q) use ($yearId) {
                return $q->where('annee_scolaire_id', $yearId);
            })
            ->whereYear('date_entree', $date->year)
            ->whereMonth('date_entree', $date->month)
            ->sum('montant');

            $sortie = Sortie::when($yearId, function($q) use ($yearId) {
                return $q->where('annee_scolaire_id', $yearId);
            })
            ->whereYear('date_sortie', $date->year)
            ->whereMonth('date_sortie', $date->month)
            ->sum('montant');

            return [
                'label' => $date->translatedFormat('M Y'),
                'mois' => $date->month,
                'annee' => $date->year,
                'entree' => (float)$entree,
                'sortie' => (float)$sortie,
                'solde' => (float)($entree - $sortie)
            ];
        });
    }

    /**
     * Récupère les 10 dernières activités financières
     */
    private function getActivitesRecentes($yearId)
    {
        $entrees = Entree::with(['type', 'inscription.eleve'])
            ->when($yearId, function($q) use ($yearId) {
                return $q->where('annee_scolaire_id', $yearId);
            })
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->get()
            ->map(function($item) {
                return [
                    'id' => $item->id,
                    'type_activite' => 'Entrée',
                    'categorie' => $item->type ? $item->type->nom : 'Divers',
                    'description' => $item->description,
                    'montant' => $item->montant,
                    'date' => $item->created_at->format('Y-m-d H:i:s'),
                    'concerne' => $item->inscription && $item->inscription->eleve ? 
                                $item->inscription->eleve->nom . ' ' . $item->inscription->eleve->prenom : 'N/A',
                    'reference' => $item->reference,
                    'color' => 'success'
                ];
            });

        $sorties = Sortie::with(['type', 'staff'])
            ->when($yearId, function($q) use ($yearId) {
                return $q->where('annee_scolaire_id', $yearId);
            })
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->get()
            ->map(function($item) {
                return [
                    'id' => $item->id,
                    'type_activite' => 'Sortie',
                    'categorie' => $item->type ? $item->type->nom : 'Divers',
                    'description' => $item->description,
                    'montant' => $item->montant,
                    'date' => $item->created_at->format('Y-m-d H:i:s'),
                    'concerne' => $item->staff ? $item->staff->nom . ' ' . $item->staff->prenom : 'N/A',
                    'reference' => $item->reference,
                    'color' => 'danger'
                ];
            });

        return $entrees->concat($sorties)
            ->sortByDesc('date')
            ->values()
            ->take(10);
    }
}
