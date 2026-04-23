<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\Inscription\AnneeScolaire;
use App\Models\Inscription\Classe;
use App\Models\Inscription\Inscription;
use App\Models\Inscription\Paiement;
use App\Models\Paiement\Recu;
use App\Models\Paiement\ResumePaiement;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class RecapitulatifAnneeScolaireController extends Controller
{
    public function anneesScolaires()
    {
        $annees = AnneeScolaire::orderByDesc('date_debut')
            ->get()
            ->map(function (AnneeScolaire $annee) {
                return [
                    'id' => $annee->id,
                    'date_debut' => $annee->date_debut,
                    'date_fin' => $annee->date_fin,
                    'statut' => $annee->statut,
                    'libelle' => $annee->libelle,
                ];
            });

        return response()->json([
            'success' => true,
            'data' => $annees,
        ]);
    }

    public function classesParAnnee(Request $request, $anneeId)
    {
        $annee = AnneeScolaire::findOrFail($anneeId);
        $niveauId = $request->query('niveau_id');

        $classes = Classe::query()
            ->with('niveau')
            ->where('anneeScolaire_id', $annee->id)
            ->when($niveauId, fn ($query) => $query->where('niveau_id', $niveauId))
            ->orderBy('nom_classe')
            ->get()
            ->map(function (Classe $classe) use ($annee) {
                $nombreInscrits = Inscription::where('id_annee_scolaire', $annee->id)
                    ->where('id_classe', $classe->id)
                    ->count();

                $totalDu = (float) ResumePaiement::whereHas('inscription', function ($query) use ($annee, $classe) {
                    $query->where('id_annee_scolaire', $annee->id)
                        ->where('id_classe', $classe->id);
                })->sum('total_du');

                $totalCollecte = (float) Paiement::whereHas('inscription', function ($query) use ($annee, $classe) {
                    $query->where('id_annee_scolaire', $annee->id)
                        ->where('id_classe', $classe->id);
                })->sum('montant');

                return [
                    'id' => $classe->id,
                    'nom_classe' => $classe->nom_classe,
                    'code_division' => $classe->code_division,
                    'effectif' => $classe->effectif,
                    'niveau' => $classe->niveau ? [
                        'id' => $classe->niveau->id,
                        'nom_niveau' => $classe->niveau->nom_niveau,
                        'cycle' => $classe->niveau->cycle,
                    ] : null,
                    'nombre_inscrits' => $nombreInscrits,
                    'total_du' => round($totalDu, 2),
                    'total_collecte' => round($totalCollecte, 2),
                    'taux_recouvrement' => $this->calculerTauxRecouvrement($totalCollecte, $totalDu),
                ];
            })
            ->values();

        return response()->json([
            'success' => true,
            'data' => [
                'annee_scolaire' => [
                    'id' => $annee->id,
                    'libelle' => $annee->libelle,
                ],
                'filtre_niveau_id' => $niveauId ? (int) $niveauId : null,
                'classes' => $classes,
            ],
        ]);
    }

    public function detailClasse($anneeId, $classeId)
    {
        $annee = AnneeScolaire::findOrFail($anneeId);
        $classe = Classe::with('niveau')
            ->where('anneeScolaire_id', $annee->id)
            ->findOrFail($classeId);

        $inscriptions = Inscription::with(['eleve', 'resumePaiement'])
            ->where('id_annee_scolaire', $annee->id)
            ->where('id_classe', $classe->id)
            ->orderByDesc('date_inscription')
            ->get()
            ->map(function (Inscription $inscription) {
                $totalDu = (float) ($inscription->resumePaiement->total_du ?? $inscription->montant_net ?? 0);
                $totalPaye = (float) ($inscription->resumePaiement->total_paye ?? 0);
                $restant = (float) ($inscription->resumePaiement->total_restant ?? max($totalDu - $totalPaye, 0));

                return [
                    'inscription_id' => $inscription->id,
                    'date_inscription' => $inscription->date_inscription,
                    'eleve' => [
                        'id' => $inscription->eleve?->id,
                        'matricule' => $inscription->eleve?->matricule,
                        'nom' => $inscription->eleve?->nom,
                        'prenom' => $inscription->eleve?->prenom,
                    ],
                    'cantine' => (bool) $inscription->cantine,
                    'parascolaire' => (bool) $inscription->parascolaire,
                    'montant_total' => round((float) $inscription->montant_total, 2),
                    'montant_net' => round((float) $inscription->montant_net, 2),
                    'total_du' => round($totalDu, 2),
                    'total_paye' => round($totalPaye, 2),
                    'restant' => round($restant, 2),
                    'est_solde' => $restant <= 0,
                ];
            })
            ->values();

        return response()->json([
            'success' => true,
            'data' => [
                'annee_scolaire' => [
                    'id' => $annee->id,
                    'libelle' => $annee->libelle,
                ],
                'classe' => [
                    'id' => $classe->id,
                    'nom_classe' => $classe->nom_classe,
                    'code_division' => $classe->code_division,
                    'effectif' => $classe->effectif,
                    'niveau' => $classe->niveau ? [
                        'id' => $classe->niveau->id,
                        'nom_niveau' => $classe->niveau->nom_niveau,
                        'cycle' => $classe->niveau->cycle,
                    ] : null,
                ],
                'resume' => [
                    'nombre_inscrits' => $inscriptions->count(),
                    'total_du' => round((float) $inscriptions->sum('total_du'), 2),
                    'total_paye' => round((float) $inscriptions->sum('total_paye'), 2),
                    'total_restant' => round((float) $inscriptions->sum('restant'), 2),
                ],
                'inscriptions' => $inscriptions,
            ],
        ]);
    }

    public function statistiques($anneeId)
    {
        $annee = AnneeScolaire::findOrFail($anneeId);
        $precedente = AnneeScolaire::where('date_debut', '<', $annee->date_debut)
            ->orderByDesc('date_debut')
            ->first();

        $nombreElevesInscrits = Inscription::where('id_annee_scolaire', $annee->id)->count();
        $nombreRecus = Recu::whereHas('inscription', function ($query) use ($annee) {
            $query->where('id_annee_scolaire', $annee->id);
        })->count();
        $totalCollecte = (float) Paiement::whereHas('inscription', function ($query) use ($annee) {
            $query->where('id_annee_scolaire', $annee->id);
        })->sum('montant');

        $inscritsPrecedents = $precedente
            ? Inscription::where('id_annee_scolaire', $precedente->id)->count()
            : 0;
        $collectePrecedente = $precedente
            ? (float) Paiement::whereHas('inscription', function ($query) use ($precedente) {
                $query->where('id_annee_scolaire', $precedente->id);
            })->sum('montant')
            : 0;

        $repartitionParNiveau = DB::table('inscriptions')
            ->join('classes', 'classes.id', '=', 'inscriptions.id_classe')
            ->join('niveaux', 'niveaux.id', '=', 'classes.niveau_id')
            ->where('inscriptions.id_annee_scolaire', $annee->id)
            ->groupBy('niveaux.id', 'niveaux.nom_niveau', 'niveaux.cycle')
            ->orderBy('niveaux.nom_niveau')
            ->selectRaw('niveaux.id, niveaux.nom_niveau, niveaux.cycle, COUNT(inscriptions.id) as nombre_eleves')
            ->get()
            ->map(fn ($row) => [
                'niveau_id' => (int) $row->id,
                'nom_niveau' => $row->nom_niveau,
                'cycle' => $row->cycle,
                'nombre_eleves' => (int) $row->nombre_eleves,
            ])
            ->values();

        return response()->json([
            'success' => true,
            'data' => [
                'annee_scolaire' => [
                    'id' => $annee->id,
                    'libelle' => $annee->libelle,
                ],
                'statistique' => [
                    'nombre_eleves_inscrits' => $nombreElevesInscrits,
                    'nombre_recus' => $nombreRecus,
                    'total_collecte' => round($totalCollecte, 2),
                    'croissance_annuelle' => [
                        'annee_precedente' => $precedente ? [
                            'id' => $precedente->id,
                            'libelle' => $precedente->libelle,
                        ] : null,
                        'inscriptions' => [
                            'valeur_actuelle' => $nombreElevesInscrits,
                            'valeur_precedente' => $inscritsPrecedents,
                            'taux' => $this->calculerCroissance($nombreElevesInscrits, $inscritsPrecedents),
                        ],
                        'collecte' => [
                            'valeur_actuelle' => round($totalCollecte, 2),
                            'valeur_precedente' => round($collectePrecedente, 2),
                            'taux' => $this->calculerCroissance($totalCollecte, $collectePrecedente),
                        ],
                    ],
                    'repartition_par_niveau' => $repartitionParNiveau,
                ],
            ],
        ]);
    }

    public function finance($anneeId)
    {
        $annee = AnneeScolaire::findOrFail($anneeId);

        $totalPrix = (float) ResumePaiement::whereHas('inscription', function ($query) use ($annee) {
            $query->where('id_annee_scolaire', $annee->id);
        })->sum('total_du');

        $totalCollecte = (float) Paiement::whereHas('inscription', function ($query) use ($annee) {
            $query->where('id_annee_scolaire', $annee->id);
        })->sum('montant');

        $elevesConcernes = Paiement::whereHas('inscription', function ($query) use ($annee) {
            $query->where('id_annee_scolaire', $annee->id);
        })->distinct('inscription_id')->count('inscription_id');

        $evolutionMensuelle = collect($this->genererMoisAnneeScolaire($annee))
            ->map(function (array $periode) use ($annee) {
                $collecte = (float) Paiement::whereHas('inscription', function ($query) use ($annee) {
                    $query->where('id_annee_scolaire', $annee->id);
                })
                    ->whereMonth('date_paiement', $periode['mois'])
                    ->whereYear('date_paiement', $periode['annee'])
                    ->sum('montant');

                return [
                    'mois' => $periode['mois'],
                    'annee' => $periode['annee'],
                    'libelle' => Carbon::create($periode['annee'], $periode['mois'], 1)->translatedFormat('F Y'),
                    'total_collecte' => round($collecte, 2),
                ];
            })
            ->values();

        $repartitionParTypeFrais = DB::table('paiements')
            ->join('inscriptions', 'inscriptions.id', '=', 'paiements.inscription_id')
            ->leftJoin('type_frais', 'type_frais.id', '=', 'paiements.type_frais_id')
            ->where('inscriptions.id_annee_scolaire', $annee->id)
            ->groupBy('paiements.type', 'paiements.libelle', 'type_frais.libelle')
            ->selectRaw('COALESCE(type_frais.libelle, paiements.libelle, paiements.type, "Non defini") as type_frais')
            ->selectRaw('SUM(paiements.montant) as total_collecte')
            ->selectRaw('COUNT(paiements.id) as nombre_paiements')
            ->orderByDesc('total_collecte')
            ->get()
            ->map(fn ($row) => [
                'type_frais' => $row->type_frais,
                'total_collecte' => round((float) $row->total_collecte, 2),
                'nombre_paiements' => (int) $row->nombre_paiements,
            ])
            ->values();

        $paiementParNiveau = DB::table('paiements')
            ->join('inscriptions', 'inscriptions.id', '=', 'paiements.inscription_id')
            ->join('classes', 'classes.id', '=', 'inscriptions.id_classe')
            ->join('niveaux', 'niveaux.id', '=', 'classes.niveau_id')
            ->where('inscriptions.id_annee_scolaire', $annee->id)
            ->groupBy('niveaux.id', 'niveaux.nom_niveau', 'niveaux.cycle')
            ->orderBy('niveaux.nom_niveau')
            ->selectRaw('niveaux.id, niveaux.nom_niveau, niveaux.cycle, SUM(paiements.montant) as total_collecte')
            ->get()
            ->map(fn ($row) => [
                'niveau_id' => (int) $row->id,
                'nom_niveau' => $row->nom_niveau,
                'cycle' => $row->cycle,
                'total_collecte' => round((float) $row->total_collecte, 2),
            ])
            ->values();

        return response()->json([
            'success' => true,
            'data' => [
                'annee_scolaire' => [
                    'id' => $annee->id,
                    'libelle' => $annee->libelle,
                ],
                'finance' => [
                    'total_prix' => round($totalPrix, 2),
                    'total_collecte' => round($totalCollecte, 2),
                    'taux_recouvrement' => $this->calculerTauxRecouvrement($totalCollecte, $totalPrix),
                    'eleves_concernes' => $elevesConcernes,
                    'evolution_mensuelle' => $evolutionMensuelle,
                    'repartition_paiement_par_type_frais' => $repartitionParTypeFrais,
                    'paiement_par_niveau' => $paiementParNiveau,
                ],
            ],
        ]);
    }

    public function journalCaisse(Request $request, $anneeId)
    {
        $annee = AnneeScolaire::findOrFail($anneeId);
        $date = $request->query('date')
            ? Carbon::parse($request->query('date'))->toDateString()
            : Carbon::today()->toDateString();
        $nom = trim((string) $request->query('nom', ''));
        $email = trim((string) $request->query('email', ''));

        $query = Paiement::with(['utilisateur', 'inscription.eleve', 'inscription.classe.niveau', 'typeFrais', 'recu'])
            ->whereDate('date_paiement', $date)
            ->whereHas('inscription', function ($query) use ($annee) {
                $query->where('id_annee_scolaire', $annee->id);
            })
            ->when($nom !== '', function ($query) use ($nom) {
                $query->whereHas('utilisateur', function ($subQuery) use ($nom) {
                    $subQuery->where('nom', 'like', "%{$nom}%")
                        ->orWhere('prenom', 'like', "%{$nom}%");
                });
            })
            ->when($email !== '', function ($query) use ($email) {
                $query->whereHas('utilisateur', function ($subQuery) use ($email) {
                    $subQuery->where('email', 'like', "%{$email}%");
                });
            })
            ->orderByDesc('created_at');

        $transactions = $query->get()
            ->map(function (Paiement $paiement) {
                return [
                    'id' => $paiement->id,
                    'reference' => $paiement->reference,
                    'date_paiement' => optional($paiement->date_paiement)->format('Y-m-d'),
                    'montant' => round((float) $paiement->montant, 2),
                    'type' => $paiement->type,
                    'libelle' => $paiement->libelle ?? $paiement->typeFrais?->libelle,
                    'type_frais' => $paiement->typeFrais?->libelle,
                    'recu' => $paiement->recu ? [
                        'id' => $paiement->recu->id,
                        'numero' => $paiement->recu->numero,
                    ] : null,
                    'eleve' => [
                        'id' => $paiement->inscription?->eleve?->id,
                        'matricule' => $paiement->inscription?->eleve?->matricule,
                        'nom' => $paiement->inscription?->eleve?->nom,
                        'prenom' => $paiement->inscription?->eleve?->prenom,
                    ],
                    'classe' => $paiement->inscription?->classe ? [
                        'id' => $paiement->inscription->classe->id,
                        'nom_classe' => $paiement->inscription->classe->nom_classe,
                        'niveau' => $paiement->inscription->classe->niveau?->nom_niveau,
                    ] : null,
                    'caissier' => $paiement->utilisateur ? [
                        'id' => $paiement->utilisateur->id,
                        'nom' => $paiement->utilisateur->nom,
                        'prenom' => $paiement->utilisateur->prenom,
                        'email' => $paiement->utilisateur->email,
                    ] : null,
                ];
            })
            ->values();

        return response()->json([
            'success' => true,
            'data' => [
                'annee_scolaire' => [
                    'id' => $annee->id,
                    'libelle' => $annee->libelle,
                ],
                'date_journee' => $date,
                'filtres' => [
                    'nom' => $nom !== '' ? $nom : null,
                    'email' => $email !== '' ? $email : null,
                ],
                'journal_caisse' => [
                    'nombre_transactions' => $transactions->count(),
                    'total_encaissement' => round((float) $transactions->sum('montant'), 2),
                    'transactions' => $transactions,
                ],
            ],
        ]);
    }

    private function calculerTauxRecouvrement(float $totalCollecte, float $totalDu): float
    {
        if ($totalDu <= 0) {
            return 0;
        }

        return round(($totalCollecte / $totalDu) * 100, 2);
    }

    private function calculerCroissance(float $valeurActuelle, float $valeurPrecedente): float
    {
        if ($valeurPrecedente <= 0) {
            return $valeurActuelle > 0 ? 100 : 0;
        }

        return round((($valeurActuelle - $valeurPrecedente) / $valeurPrecedente) * 100, 2);
    }

    private function genererMoisAnneeScolaire(AnneeScolaire $annee): array
    {
        $dateDebut = Carbon::parse($annee->date_debut)->startOfMonth();
        $dateFin = Carbon::parse($annee->date_fin)->startOfMonth();
        $mois = [];

        while ($dateDebut <= $dateFin) {
            $mois[] = [
                'mois' => $dateDebut->month,
                'annee' => $dateDebut->year,
            ];

            $dateDebut->addMonth();
        }

        return $mois;
    }
}
