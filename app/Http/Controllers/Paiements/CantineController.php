<?php

namespace App\Http\Controllers\Paiements;

use App\Http\Controllers\Controller;
use App\Models\Inscription\Inscription;
use App\Models\Inscription\Paiement;
use App\Models\Inscription\TypeFrais;
use App\Models\Paiement\PresenceCantine;
use App\Models\Paiement\Recu;
use App\Models\Paiement\ResumePaiement;
use App\Models\Finance\Caisse;
use App\Models\Finance\CategorieEntree;
use App\Models\Finance\Entree;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CantineController extends Controller
{
    public function marquerPresence(Request $request)
    {
        $request->validate([
            'inscription_id' => 'required|exists:inscriptions,id',
            'date' => 'required|date',
            'est_present' => 'required|boolean',
        ]);

        $inscription = Inscription::findOrFail($request->inscription_id);
        $typeCantine = $this->getTypeCantine($inscription->id_annee_scolaire);

        if (!$inscription->cantine) {
            return response()->json([
                'success' => false,
                'message' => 'Cet eleve n est pas inscrit a la cantine',
            ], 422);
        }

        if (!$typeCantine) {
            return response()->json([
                'success' => false,
                'message' => 'Type de frais Cantine introuvable',
            ], 422);
        }

        $date = Carbon::parse($request->date)->toDateString();

        if ($request->boolean('est_present')) {
            $presence = PresenceCantine::updateOrCreate(
                [
                    'inscription_id' => $inscription->id,
                    'date_presence' => $date,
                ],
                [
                    'montant' => $typeCantine->montant,
                ]
            );

            $this->mettreAJourResumeDepuisCantine($inscription->id);

            return response()->json([
                'success' => true,
                'data' => $presence,
            ]);
        }

        $presence = PresenceCantine::where('inscription_id', $inscription->id)
            ->whereDate('date_presence', $date)
            ->first();

        if ($presence && $presence->est_paye) {
            return response()->json([
                'success' => false,
                'message' => 'Impossible de supprimer une presence deja payee',
            ], 422);
        }

        if ($presence) {
            $presence->delete();
        }

        $this->mettreAJourResumeDepuisCantine($inscription->id);

        return response()->json([
            'success' => true,
            'message' => 'Presence retiree',
        ]);
    }

    public function getMoisDisponibles($inscriptionId)
    {
        $mois = PresenceCantine::where('inscription_id', $inscriptionId)
            ->selectRaw('YEAR(date_presence) as annee, MONTH(date_presence) as mois')
            ->distinct()
            ->orderBy('annee')
            ->orderBy('mois')
            ->get()
            ->map(function ($item) {
                return [
                    'annee' => (int) $item->annee,
                    'mois' => (int) $item->mois,
                    'libelle' => Carbon::create($item->annee, $item->mois, 1)->translatedFormat('F Y'),
                ];
            })
            ->values();

        return response()->json([
            'success' => true,
            'data' => $mois,
        ]);
    }

    public function getJours($inscriptionId, Request $request)
    {
        $mois = (int) $request->query('mois');
        $annee = (int) $request->query('annee');

        $query = PresenceCantine::where('inscription_id', $inscriptionId);

        if ($mois > 0) {
            $query->whereMonth('date_presence', $mois);
        }

        if ($annee > 0) {
            $query->whereYear('date_presence', $annee);
        }

        $jours = $query->orderBy('date_presence')
            ->get()
            ->map(function ($presence) {
                return [
                    'id' => $presence->id,
                    'date_presence' => $presence->date_presence->format('Y-m-d'),
                    'montant' => $presence->montant,
                    'est_paye' => $presence->est_paye,
                    'paiement_id' => $presence->paiement_id,
                ];
            })
            ->values();

        return response()->json([
            'success' => true,
            'data' => [
                'jours' => $jours,
                'total_restant' => $jours->where('est_paye', false)->sum('montant'),
            ],
        ]);
    }

    public function payerJours(Request $request)
    {
        $request->validate([
            'inscription_id' => 'required|exists:inscriptions,id',
            'dates' => 'required|array|min:1',
            'dates.*' => 'required|date_format:Y-m-d',
            'reference' => 'nullable|string',
        ]);

        DB::beginTransaction();

        try {
            $inscription = Inscription::with('resumePaiement')->findOrFail($request->inscription_id);
            $typeCantine = $this->getTypeCantine($inscription->id_annee_scolaire);
            $userId = $this->getUtilisateurId($request);

            $presences = PresenceCantine::where('inscription_id', $inscription->id)
                ->whereIn('date_presence', $request->dates)
                ->where('est_paye', false)
                ->orderBy('date_presence')
                ->get();

            if ($presences->isEmpty()) {
                DB::rollBack();

                return response()->json([
                    'success' => false,
                    'message' => 'Aucun jour valide selectionne',
                ], 422);
            }

            $totalMontant = (float) $presences->sum('montant');

            $paiement = Paiement::create([
                'reference' => $this->genererReference(),
                'inscription_id' => $inscription->id,
                'type_frais_id' => $typeCantine?->id,
                'type' => 'cantine_journalier',
                'libelle' => 'Cantine - ' . $presences->count() . ' jour(s)',
                'details' => [
                    'dates' => $presences->pluck('date_presence')->map(fn ($date) => $date->format('Y-m-d'))->all(),
                ],
                'montant' => $totalMontant,
                'date_paiement' => now(),
                'utilisateur_id' => $userId,
            ]);

            PresenceCantine::whereIn('id', $presences->pluck('id'))
                ->update([
                    'est_paye' => true,
                    'paiement_id' => $paiement->id,
                ]);

            $recu = Recu::create([
                'numero' => Recu::genererNumero(),
                'paiement_id' => $paiement->id,
                'inscription_id' => $inscription->id,
                'montant' => $totalMontant,
                'date_emission' => now(),
                'libelle' => 'Cantine',
                'details' => json_encode($paiement->details),
            ]);

            $this->mettreAJourResume($inscription->resumePaiement);

            // --- INTEGRATION FINANCE ---
            $typeCantineCat = CategorieEntree::where('nom', 'like', '%Cantine%')->first()
                            ?? CategorieEntree::where('nom', 'like', '%Autres%')->first()
                            ?? CategorieEntree::where('nom', 'like', '%Inscription%')->first();
            if ($typeCantineCat && $totalMontant > 0) {
                Entree::create([
                    'reference' => 'ENT-CAN-' . time(),
                    'montant' => $totalMontant,
                    'date_entree' => now(),
                    'type_entree_id' => $typeCantineCat->id,
                    'inscription_id' => $inscription->id,
                    'annee_scolaire_id' => $inscription->id_annee_scolaire,
                    'description' => 'Paiement cantine pour ' . $presences->count() . ' jour(s)',
                    'created_by' => $userId
                ]);

                $caisse = Caisse::firstOrCreate(
                    ['annee_scolaire_id' => $inscription->id_annee_scolaire],
                    ['nom' => 'Caisse Principale', 'solde' => 0]
                );
                $caisse->increment('solde', $totalMontant);
            }
            // ---------------------------

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Paiement des jours effectue avec succes',
                'data' => [
                    'paiement_id' => $paiement->id,
                    'recu_id' => $recu->id,
                    'total_paye' => $totalMontant,
                    'nombre_jours' => $presences->count(),
                ],
            ]);
        } catch (\Throwable $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Erreur lors du paiement: ' . $e->getMessage(),
            ], 500);
        }
    }

    private function mettreAJourResume($resume): void
    {
        if (!$resume) {
            return;
        }

        $totalPaye = (float) Paiement::where('inscription_id', $resume->inscription_id)->sum('montant');

        $resume->update([
            'total_paye' => $totalPaye,
            'total_restant' => max((float) $resume->total_du - $totalPaye, 0),
        ]);
    }

    private function mettreAJourResumeDepuisCantine(int $inscriptionId): void
    {
        $resume = ResumePaiement::where('inscription_id', $inscriptionId)->first();

        if (!$resume) {
            return;
        }

        $baseSansCantine = (float) DB::table('frais_appliques')
            ->where('id_inscription', $inscriptionId)
            ->sum('montant');
        $totalCantine = (float) PresenceCantine::where('inscription_id', $inscriptionId)->sum('montant');
        $totalPaye = (float) Paiement::where('inscription_id', $inscriptionId)->sum('montant');

        $nouveauTotalDu = $baseSansCantine + $totalCantine;

        $resume->update([
            'total_du' => $nouveauTotalDu,
            'total_paye' => $totalPaye,
            'total_restant' => max($nouveauTotalDu - $totalPaye, 0),
        ]);
    }

    private function genererReference(): string
    {
        $lastId = Paiement::max('id') ?? 0;
        $numero = str_pad($lastId + 1, 6, '0', STR_PAD_LEFT);

        return 'PAY-' . date('Y') . '-' . $numero;
    }

    private function getUtilisateurId(Request $request): ?int
    {
        $user = $request->user();

        return $user ? (int) $user->getKey() : null;
    }

    private function getTypeCantine(?int $anneeScolaireId): ?TypeFrais
    {
        return TypeFrais::where('libelle', 'Cantine')
            ->where(function ($query) use ($anneeScolaireId) {
                if ($anneeScolaireId) {
                    $query->where('annee_scolaire_id', $anneeScolaireId)
                        ->orWhereNull('annee_scolaire_id');
                } else {
                    $query->whereNull('annee_scolaire_id');
                }
            })
            ->orderByRaw('CASE WHEN annee_scolaire_id IS NULL THEN 1 ELSE 0 END')
            ->first();
    }
}