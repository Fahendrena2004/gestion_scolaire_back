<?php

namespace App\Services\Paiement;

use App\Models\Inscription\Echeance;
use App\Models\Inscription\Inscription;
use App\Models\Inscription\Paiement;
use App\Models\Paiement\Recu;
use App\Models\Inscription\TypeFrais;
use App\Models\Paiement\PresenceCantine;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CantinePaiementService
{
    private function getPrixJournalier()
    {
        $typeFrais = TypeFrais::where('libelle', 'Cantine')->first();
        return $typeFrais ? $typeFrais->montant : 0;
    }

    /**
     * Récupérer l'année scolaire d'une inscription
     */
    private function getAnneeScolaire($inscriptionId)
    {
        $inscription = Inscription::with('anneeScolaire')->find($inscriptionId);

        if (!$inscription || !$inscription->anneeScolaire) {
            return null;
        }
        //  Convertir en objets Carbon des annee
        $dateDebut = $inscription->anneeScolaire->date_debut instanceof Carbon
            ? $inscription->anneeScolaire->date_debut
            : Carbon::parse($inscription->anneeScolaire->date_debut);

        $dateFin = $inscription->anneeScolaire->date_fin instanceof Carbon
            ? $inscription->anneeScolaire->date_fin
            : Carbon::parse($inscription->anneeScolaire->date_fin);

        return [
            'date_debut' => $dateDebut,
            'date_fin' => $dateFin,
            'libelle' => $dateDebut->format('Y') . ' - ' . $dateFin->format('Y')
        ];

    }

    /**
     * Vérifier si un mois/année est dans l'année scolaire
     */
    private function estDansAnneeScolaire($inscriptionId, $mois, $annee)
    {
        $anneeScolaire = $this->getAnneeScolaire($inscriptionId);

        if (!$anneeScolaire) {
            return false;
        }

        $date = Carbon::create($annee, $mois, 15);

        return $date->between($anneeScolaire['date_debut'], $anneeScolaire['date_fin']);
    }

    /**
     * Récupérer la liste des mois disponibles pour une inscription
     * GET /api/cantine/mois-disponibles/{inscriptionId}
     */
    public function getMoisDisponibles($inscriptionId)
    {
        $anneeScolaire = $this->getAnneeScolaire($inscriptionId);

        if (!$anneeScolaire) {
            return [];
        }

        $moisDisponibles = [];
        $currentDate = $anneeScolaire['date_debut']->copy();

        while ($currentDate <= $anneeScolaire['date_fin']) {
            $moisDisponibles[] = [
                'mois' => (int)$currentDate->month,
                'annee' => (int)$currentDate->year,
                'libelle' => $currentDate->translatedFormat('F Y'),
                'est_actuel' => $currentDate->month == date('m') && $currentDate->year == date('Y')
            ];
            $currentDate->addMonth();
        }

        return $moisDisponibles;
    }

    /**
     * Récupérer les jours d'un mois (limité à l'année scolaire)
     */
    public function getJoursMois($inscriptionId, $mois, $annee)
    {
        // ✅ Vérifier que le mois/année est dans l'année scolaire
        if (!$this->estDansAnneeScolaire($inscriptionId, $mois, $annee)) {
            return [
                'error' => true,
                'message' => 'Le mois sélectionné est en dehors de l\'année scolaire',
                'mois' => $mois,
                'annee' => $annee
            ];
        }

        $prixJournalier = $this->getPrixJournalier();

        if ($prixJournalier == 0) {
            return null;
        }

        $typeFrais = TypeFrais::where('libelle', 'Cantine')->first();

        $echeance = Echeance::where('inscription_id', $inscriptionId)
            ->where('mois', $mois)
            ->where('annee', $annee)
            ->where('type_frais_id', $typeFrais->id)
            ->first();

        if (!$echeance) {
            return null;
        }

        $presences = PresenceCantine::where('inscription_id', $inscriptionId)
            ->whereMonth('date_presence', $mois)
            ->whereYear('date_presence', $annee)
            ->get()
            ->keyBy('date_presence');

        $debutMois = Carbon::create($annee, $mois, 1);
        $finMois = $debutMois->copy()->endOfMonth();
        $currentDate = $debutMois->copy();

        $jours = [];
        $totalPaye = 0;
        $nbJoursPayes = 0;

        while ($currentDate <= $finMois) {
            if (!$currentDate->isWeekend()) {
                $presence = $presences->get($currentDate->toDateString());
                $estPaye = $presence ? $presence->est_paye : false;

                if ($estPaye) {
                    $totalPaye += $prixJournalier;
                    $nbJoursPayes++;
                }

                $jours[] = [
                    'id' => $presence ? $presence->id : null,
                    'date' => $currentDate->format('Y-m-d'),
                    'date_affichage' => $currentDate->format('d/m/Y'),
                    'jour' => $this->getNomJour($currentDate),
                    'montant' => $prixJournalier,
                    'est_paye' => $estPaye,
                    'selected' => false
                ];
            }
            $currentDate->addDay();
        }

        $totalJours = count($jours);
        $totalMois = $prixJournalier * $totalJours;
        $resteAPayer = $totalMois - $totalPaye;

        $echeance->montant = $totalMois;
        $echeance->montant_paye = $totalPaye;
        $echeance->montant_restant = $resteAPayer;
        $echeance->statut = $totalPaye >= $totalMois ? 'paye' : ($totalPaye > 0 ? 'partiel' : 'impaye');
        $echeance->save();

        return [
            'echeance_id' => $echeance->id,
            'libelle' => $echeance->libelle,
            'montant_total' => $totalMois,
            'montant_paye' => $totalPaye,
            'montant_restant' => $resteAPayer,
            'prix_par_jour' => $prixJournalier,
            'annee_scolaire' => $this->getAnneeScolaire($inscriptionId),
            'mois_actuel' => [
                'mois' => $mois,
                'annee' => $annee,
                'libelle' => Carbon::create($annee, $mois, 1)->translatedFormat('F Y')
            ],
            'jours' => $jours,
            'resume' => [
                'total_jours' => $totalJours,
                'jours_payes' => $nbJoursPayes,
                'jours_non_payes' => $totalJours - $nbJoursPayes,
                'total_paye' => $totalPaye,
                'total_non_paye' => $resteAPayer
            ]
        ];
    }

    /**
     * Payer des jours (avec validation année scolaire)
     */
    public function payerJours($inscriptionId, array $dates)
    {
        DB::beginTransaction();

        try {
            // ✅ Vérifier que toutes les dates sont dans l'année scolaire
            foreach ($dates as $dateStr) {
                $date = Carbon::parse($dateStr);
                if (!$this->estDansAnneeScolaire($inscriptionId, $date->month, $date->year)) {
                    return [
                        'success' => false,
                        'message' => 'La date ' . $date->format('d/m/Y') . ' est en dehors de l\'année scolaire'
                    ];
                }
            }

            $prixJournalier = $this->getPrixJournalier();

            if ($prixJournalier == 0) {
                return [
                    'success' => false,
                    'message' => 'Prix de la cantine non configuré'
                ];
            }

            if (empty($dates)) {
                return [
                    'success' => false,
                    'message' => 'Aucune date sélectionnée'
                ];
            }

            // Nettoyer les doublons
            $datesUniques = array_unique($dates);

            // Vérifier les jours déjà payés
            $dejaPayes = PresenceCantine::where('inscription_id', $inscriptionId)
                ->whereIn('date_presence', $datesUniques)
                ->where('est_paye', true)
                ->pluck('date_presence')
                ->toArray();

            if (!empty($dejaPayes)) {
                return [
                    'success' => false,
                    'message' => 'Certains jours sont déjà payés',
                    'jours_deja_payes' => $dejaPayes
                ];
            }

            $nbJours = count($datesUniques);
            $totalMontant = $nbJours * $prixJournalier;

            $premiereDate = Carbon::parse($datesUniques[0]);
            $mois = $premiereDate->month;
            $annee = $premiereDate->year;

            $datesPayees = [];

            // Créer ou récupérer les présences
            foreach ($datesUniques as $dateStr) {
                $date = Carbon::parse($dateStr);

                $presence = PresenceCantine::updateOrCreate(
                    [
                        'inscription_id' => $inscriptionId,
                        'date_presence' => $date->toDateString()
                    ],
                    [
                        'est_paye' => false,
                        'paiement_id' => null
                    ]
                );

                $datesPayees[] = $date->format('d/m/Y');
            }

            $typeFrais = TypeFrais::where('libelle', 'Cantine')->first();
            $echeance = Echeance::firstOrCreate(
                [
                    'inscription_id' => $inscriptionId,
                    'mois' => $mois,
                    'annee' => $annee,
                    'type_frais_id' => $typeFrais->id
                ],
                [
                    'libelle' => 'Cantine - ' . $this->getNomMois($mois) . ' ' . $annee,
                    'montant' => 0,
                    'date_echeance' => Carbon::create($annee, $mois, 1)->endOfMonth(),
                    'statut' => 'impaye',
                    'montant_paye' => 0,
                    'montant_restant' => 0,
                    'a_details' => false,
                ]
            );

            $reference = 'PAY-' . date('Ymd') . '-' . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);

            $paiement = Paiement::create([
                'reference' => $reference,
                'inscription_id' => $inscriptionId,
                'echeance_id' => $echeance->id,
                'montant' => $totalMontant,
                'date_paiement' => now(),
                'utilisateur_id' => 1,
            ]);

            PresenceCantine::where('inscription_id', $inscriptionId)
                ->whereIn('date_presence', $datesUniques)
                ->update([
                    'est_paye' => true,
                    'paiement_id' => $paiement->id
                ]);

            $this->recalculerEcheance($inscriptionId, $mois, $annee);

            $numeroRecu = 'REC-' . date('Ymd') . '-' . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);

            $recu = Recu::create([
                'numero' => $numeroRecu,
                'paiement_id' => $paiement->id,
                'inscription_id' => $inscriptionId,
                'montant' => $totalMontant,
                'date_emission' => now(),
                'libelle' => "Cantine - " . $nbJours . " jour(s)",
                'details' => implode(', ', $datesPayees)
            ]);

            DB::commit();

            return [
                'success' => true,
                'message' => 'Paiement effectué avec succès',
                'total_paye' => $totalMontant,
                'nb_jours' => $nbJours,
                'dates' => $datesPayees,
                'recu' => [
                    'numero' => $recu->numero,
                    'libelle' => $recu->libelle,
                    'details' => $recu->details
                ]
            ];

        } catch (\Exception $e) {
            DB::rollBack();

            Log::error('Erreur payerJours: ' . $e->getMessage(), [
                'inscription_id' => $inscriptionId,
                'dates' => $dates,
                'trace' => $e->getTraceAsString()
            ]);

            return [
                'success' => false,
                'message' => 'Erreur lors du paiement: ' . $e->getMessage()
            ];
        }
    }

    private function recalculerEcheance($inscriptionId, $mois, $annee)
    {
        $prixJournalier = $this->getPrixJournalier();
        $typeFrais = TypeFrais::where('libelle', 'Cantine')->first();
        $echeance = Echeance::where('inscription_id', $inscriptionId)
            ->where('mois', $mois)
            ->where('annee', $annee)
            ->where('type_frais_id', $typeFrais->id)
            ->first();

        if (!$echeance) return;

        $presences = PresenceCantine::where('inscription_id', $inscriptionId)
            ->whereMonth('date_presence', $mois)
            ->whereYear('date_presence', $annee)
            ->get();

        $nbJours = $presences->count();
        $nbJoursPayes = $presences->where('est_paye', true)->count();

        $montantTotal = $nbJours * $prixJournalier;
        $montantPaye = $nbJoursPayes * $prixJournalier;

        $echeance->montant = $montantTotal;
        $echeance->montant_paye = $montantPaye;
        $echeance->montant_restant = $montantTotal - $montantPaye;
        $echeance->statut = $montantTotal == 0 ? 'impaye' : ($montantPaye >= $montantTotal ? 'paye' : 'partiel');
        $echeance->save();
    }

    private function getNomJour($date)
    {
        $jours = ['Dimanche', 'Lundi', 'Mardi', 'Mercredi', 'Jeudi', 'Vendredi', 'Samedi'];
        return $jours[$date->dayOfWeek];
    }

    private function getNomMois($mois)
    {
        $noms = [1 => 'Janvier', 2 => 'Février', 3 => 'Mars', 4 => 'Avril',
                 5 => 'Mai', 6 => 'Juin', 7 => 'Juillet', 8 => 'Août',
                 9 => 'Septembre', 10 => 'Octobre', 11 => 'Novembre', 12 => 'Décembre'];
        return $noms[$mois] ?? '';
    }

        /**
     * 4. MARQUER UNE PRÉSENCE (Cantinier)
     * POST /api/cantine/presence
     */
    public function marquerPresence($inscriptionId, $date, $estPresent)
    {
        try {
            if ($estPresent) {
                // Créer ou mettre à jour la présence (sans la marquer comme payée)
                $presence = PresenceCantine::updateOrCreate(
                    [
                        'inscription_id' => $inscriptionId,
                        'date_presence' => $date
                    ],
                    [
                        'est_paye' => false,
                        'paiement_id' => null
                    ]
                );

                // Recalculer l'échéance du mois concerné
                $dateObj = Carbon::parse($date);
                $this->recalculerEcheance($inscriptionId, $dateObj->month, $dateObj->year);

                return [
                    'success' => true,
                    'message' => 'Présence marquée avec succès',
                    'data' => $presence
                ];
            } else {
                // Supprimer la présence si absent
                $deleted = PresenceCantine::where('inscription_id', $inscriptionId)
                    ->where('date_presence', $date)
                    ->delete();

                // Recalculer l'échéance du mois concerné
                $dateObj = Carbon::parse($date);
                $this->recalculerEcheance($inscriptionId, $dateObj->month, $dateObj->year);

                return [
                    'success' => true,
                    'message' => 'Absence marquée avec succès',
                    'deleted' => $deleted
                ];
            }
        } catch (\Exception $e) {
            Log::error('Erreur marquerPresence: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Erreur lors du marquage: ' . $e->getMessage()
            ];
        }
    }
}

