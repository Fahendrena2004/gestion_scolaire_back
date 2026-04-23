<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\Gestion_note\Bulletin;
use App\Models\Gestion_note\Notes;
use App\Models\Inscription\AnneeScolaire;
use App\Models\Inscription\Inscription;
use App\Models\Inscription\Paiement;
use App\Models\Paiement\ResumePaiement;
use Carbon\Carbon;

class CaissierController extends Controller
{
    public function index()
    {
        $now = Carbon::now();

        $nombreElevesInscrits = $this->getNombreElevesInscrits();
        $totalPrevu = $this->getTotalPrevuAnnuelRestant();
        $totalEntreeMois = $this->getTotalEntreeMois($now->month, $now->year);
        $soldeNet = $this->getSoldeNet();
        $transactionsRecentes = $this->getTransactionsRecentes(10);
        $historiqueRecent = $this->getHistoriqueRecent(12);
        $tauxRecouvrement = $this->getTauxRecouvrement();

        return response()->json([
            'success' => true,
            'data' => [
                'statistiques' => [
                    'nombre_eleves_inscrits' => $nombreElevesInscrits,
                    'total_prevu' => $totalPrevu,
                    'total_prevu_formatte' => number_format($totalPrevu, 0, ',', ' ') . ' Ar',
                    'total_prevu_mois' => $totalPrevu,
                    'total_prevu_mois_formatte' => number_format($totalPrevu, 0, ',', ' ') . ' Ar',
                    'total_entree_mois' => $totalEntreeMois,
                    'total_entree_mois_formatte' => number_format($totalEntreeMois, 0, ',', ' ') . ' Ar',
                    'solde_net' => $soldeNet,
                    'solde_net_formatte' => number_format($soldeNet, 0, ',', ' ') . ' Ar',
                    'taux_recouvrement' => $tauxRecouvrement,
                    'taux_recouvrement_formatte' => $tauxRecouvrement . '%',
                ],
                'transactions_recentes' => $transactionsRecentes,
                'historique_recent' => $historiqueRecent,
                'date_rappel' => $now->format('d/m/Y H:i:s'),
            ],
        ]);
    }

    private function getNombreElevesInscrits(): int
    {
        $anneeActive = $this->getAnneeActive();

        if (!$anneeActive) {
            return 0;
        }

        return Inscription::where('id_annee_scolaire', $anneeActive->id)->count();
    }

    private function getTotalPrevuAnnuelRestant(): float
    {
        $anneeActive = $this->getAnneeActive();

        if (!$anneeActive) {
            return 0;
        }

        $resumeQuery = ResumePaiement::whereHas('inscription', function ($query) use ($anneeActive) {
            $query->where('id_annee_scolaire', $anneeActive->id);
        });

        $totalDu = (float) $resumeQuery->sum('total_du');
        $totalPaye = (float) ResumePaiement::whereHas('inscription', function ($query) use ($anneeActive) {
            $query->where('id_annee_scolaire', $anneeActive->id);
        })->sum('total_paye');

        return max($totalDu - $totalPaye, 0);
    }

    private function getTotalEntreeMois(int $mois, int $annee): float
    {
        $anneeActive = $this->getAnneeActive();

        if (!$anneeActive) {
            return 0;
        }

        return (float) Paiement::whereHas('inscription', function ($query) use ($anneeActive) {
            $query->where('id_annee_scolaire', $anneeActive->id);
        })
            ->whereMonth('date_paiement', $mois)
            ->whereYear('date_paiement', $annee)
            ->sum('montant');
    }

    private function getSoldeNet(): float
    {
        return $this->getTotalPrevuAnnuelRestant();
    }

    private function getTransactionsRecentes(int $limit = 10)
    {
        $anneeActive = $this->getAnneeActive();

        if (!$anneeActive) {
            return collect();
        }

        return Paiement::with(['inscription.eleve', 'typeFrais'])
            ->whereHas('inscription', function ($query) use ($anneeActive) {
                $query->where('id_annee_scolaire', $anneeActive->id);
            })
            ->orderBy('date_paiement', 'desc')
            ->limit($limit)
            ->get()
            ->map(function ($paiement) {
                return [
                    'id' => $paiement->id,
                    'reference' => $paiement->reference,
                    'date_paiement' => $paiement->date_paiement?->format('d/m/Y'),
                    'montant' => (float) $paiement->montant,
                    'montant_formatte' => number_format((float) $paiement->montant, 0, ',', ' ') . ' Ar',
                    'eleve' => $paiement->inscription && $paiement->inscription->eleve
                        ? trim($paiement->inscription->eleve->nom . ' ' . $paiement->inscription->eleve->prenom)
                        : 'Inconnu',
                    'matricule' => $paiement->inscription && $paiement->inscription->eleve
                        ? $paiement->inscription->eleve->matricule
                        : null,
                    'libelle' => $paiement->libelle ?? ($paiement->typeFrais?->libelle ?? 'Paiement divers'),
                    'type' => $paiement->type ?? 'autre',
                ];
            });
    }

    private function getHistoriqueRecent(int $limit = 12): array
    {
        $anneeActive = $this->getAnneeActive();

        if (!$anneeActive) {
            return [];
        }

        $paiements = Paiement::with(['inscription.eleve'])
            ->whereHas('inscription', function ($query) use ($anneeActive) {
                $query->where('id_annee_scolaire', $anneeActive->id);
            })
            ->latest('created_at')
            ->limit($limit)
            ->get()
            ->map(function ($paiement) {
                return [
                    'type_evenement' => 'paiement',
                    'date_evenement' => optional($paiement->created_at)->toIso8601String(),
                    'titre' => 'Paiement enregistre',
                    'description' => trim(($paiement->inscription?->eleve?->prenom ?? '') . ' ' . ($paiement->inscription?->eleve?->nom ?? '')),
                    'reference_id' => $paiement->id,
                ];
            });

        $inscriptions = Inscription::with(['eleve', 'classe'])
            ->where('id_annee_scolaire', $anneeActive->id)
            ->latest('created_at')
            ->limit($limit)
            ->get()
            ->map(function ($inscription) {
                return [
                    'type_evenement' => 'inscription',
                    'date_evenement' => optional($inscription->created_at)->toIso8601String(),
                    'titre' => 'Nouvel eleve inscrit',
                    'description' => trim(($inscription->eleve?->prenom ?? '') . ' ' . ($inscription->eleve?->nom ?? '')) . ' - ' . ($inscription->classe?->nom_classe ?? ''),
                    'reference_id' => $inscription->id,
                ];
            });

        $notes = Notes::with(['inscription.eleve', 'matiere'])
            ->whereHas('inscription', function ($query) use ($anneeActive) {
                $query->where('id_annee_scolaire', $anneeActive->id);
            })
            ->latest('created_at')
            ->limit($limit)
            ->get()
            ->map(function ($note) {
                return [
                    'type_evenement' => 'note',
                    'date_evenement' => optional($note->created_at)->toIso8601String(),
                    'titre' => 'Nouvelle note saisie',
                    'description' => trim(($note->inscription?->eleve?->prenom ?? '') . ' ' . ($note->inscription?->eleve?->nom ?? '')) . ' - ' . ($note->matiere?->nom ?? 'Matiere'),
                    'reference_id' => $note->id,
                ];
            });

        $bulletins = Bulletin::with(['inscription.eleve'])
            ->whereHas('inscription', function ($query) use ($anneeActive) {
                $query->where('id_annee_scolaire', $anneeActive->id);
            })
            ->latest('created_at')
            ->limit($limit)
            ->get()
            ->map(function ($bulletin) {
                return [
                    'type_evenement' => 'bulletin',
                    'date_evenement' => optional($bulletin->created_at)->toIso8601String(),
                    'titre' => 'Bulletin genere',
                    'description' => trim(($bulletin->inscription?->eleve?->prenom ?? '') . ' ' . ($bulletin->inscription?->eleve?->nom ?? '')) . ' - ' . ($bulletin->periode ?? ''),
                    'reference_id' => $bulletin->id,
                ];
            });

        return $paiements
            ->concat($inscriptions)
            ->concat($notes)
            ->concat($bulletins)
            ->sortByDesc('date_evenement')
            ->take($limit)
            ->values()
            ->all();
    }

    private function getTauxRecouvrement(): float
    {
        $anneeActive = $this->getAnneeActive();

        if (!$anneeActive) {
            return 0;
        }

        $totalPaye = (float) Paiement::whereHas('inscription', function ($query) use ($anneeActive) {
            $query->where('id_annee_scolaire', $anneeActive->id);
        })->sum('montant');

        $totalDu = (float) ResumePaiement::whereHas('inscription', function ($query) use ($anneeActive) {
            $query->where('id_annee_scolaire', $anneeActive->id);
        })->sum('total_du');

        if ($totalDu <= 0) {
            return 0;
        }

        return round(($totalPaye / $totalDu) * 100, 2);
    }

    private function getAnneeActive(): ?AnneeScolaire
    {
        return AnneeScolaire::where('statut', 'en_cours')->first();
    }
}
