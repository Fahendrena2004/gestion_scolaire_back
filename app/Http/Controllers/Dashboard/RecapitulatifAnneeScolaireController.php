<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\Inscription\AnneeScolaire;
use App\Models\Inscription\Classe;
use App\Models\Inscription\Inscription;
use App\Models\Inscription\Paiement;
use App\Models\Gestion_note\Bulletin;
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
            ->when($niveauId, function ($query) use ($niveauId) {
                $query->whereHas('niveau', function ($q) use ($niveauId) {
                    if (is_numeric($niveauId)) {
                        $q->where('id', $niveauId);
                    } else {
                        $q->where('nom_niveau', $niveauId);
                    }
                });
            })
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

    public function statistiques(Request $request, $anneeId)
    {
        $annee = AnneeScolaire::findOrFail($anneeId);
        $niveauId = $request->query('niveau_id');
        $classeId = $request->query('classe_id');

        $classesQuery = Classe::where('anneeScolaire_id', $annee->id)
            ->when($niveauId, function ($query) use ($niveauId) {
                $query->whereHas('niveau', function ($q) use ($niveauId) {
                    if (is_numeric($niveauId)) {
                        $q->where('id', $niveauId);
                    } else {
                        $q->where('nom_niveau', $niveauId);
                    }
                });
            })
            ->when($classeId, fn($q) => $q->where('id', $classeId));

        $resultatsClasses = $classesQuery->get()->map(function ($classe) use ($annee) {
            $inscriptions = Inscription::where('id_classe', $classe->id)
                ->where('id_annee_scolaire', $annee->id)
                ->pluck('id');

            $effectif = $inscriptions->count();

            // Statistiques par trimestre
            $trimestres = ['Trimestre 1', 'Trimestre 2', 'Trimestre 3'];
            $statsTrimestres = collect($trimestres)->map(function ($trimestre) use ($inscriptions) {
                $bulletins = Bulletin::whereIn('inscription_id', $inscriptions)
                    ->where('periode', $trimestre)
                    ->get();

                $moyenneClasse = $bulletins->avg('moyenne_eleve');
                $nombreAdmis = $bulletins->where('moyenne_eleve', '>=', 10)->count();
                $tauxReussite = $bulletins->count() > 0 ? round(($nombreAdmis / $bulletins->count()) * 100, 2) : 0;

                return [
                    'trimestre' => $trimestre,
                    'moyenne_classe' => round($moyenneClasse, 2),
                    'taux_reussite' => $tauxReussite,
                    'nombre_bulletins' => $bulletins->count()
                ];
            });

            // Calculer la moyenne annuelle (moyenne des moyennes des trimestres)
            $moyenneAnnuelle = $statsTrimestres->avg('moyenne_classe');
            
            // Taux de réussite global (basé sur la moyenne annuelle >= 10 ou moyenne des taux)
            $tauxReussiteGlobal = $statsTrimestres->avg('taux_reussite');

            return [
                'id' => $classe->id,
                'nom' => $classe->nom_classe,
                'effectif' => $effectif,
                'moyenne_annuelle' => round($moyenneAnnuelle, 2),
                't1' => $statsTrimestres->where('trimestre', 'Trimestre 1')->first()['moyenne_classe'] ?? 0,
                't2' => $statsTrimestres->where('trimestre', 'Trimestre 2')->first()['moyenne_classe'] ?? 0,
                't3' => $statsTrimestres->where('trimestre', 'Trimestre 3')->first()['moyenne_classe'] ?? 0,
                'taux_reussite' => round($tauxReussiteGlobal, 2),
                'statistiques_trimestres' => $statsTrimestres
            ];
        });

        return response()->json([
            'success' => true,
            'data' => [
                'annee_scolaire' => [
                    'id' => $annee->id,
                    'libelle' => $annee->libelle,
                ],
                'classes' => $resultatsClasses,
                'total_eleves' => $resultatsClasses->sum('effectif')
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

        $restePaye = $totalPrix - $totalCollecte;

        // Répartition par statut de paiement
        $resumes = ResumePaiement::whereHas('inscription', function ($query) use ($annee) {
            $query->where('id_annee_scolaire', $annee->id);
        })->get();

        $totalPayer = $resumes->filter(fn($r) => $r->total_restant <= 0)->count();
        $totalPartiel = $resumes->filter(fn($r) => $r->total_paye > 0 && $r->total_restant > 0)->count();
        $totalImpayer = $resumes->filter(fn($r) => $r->total_paye <= 0)->count();

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
                    'name' => Carbon::create($periode['annee'], $periode['mois'], 1)->translatedFormat('M'),
                    'amount' => round($collecte, 2),
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
                'summary' => [
                    'attendu' => round($totalPrix, 2),
                    'encaisse' => round($totalCollecte, 2),
                    'restant' => round($restePaye, 2),
                    'taux_recouvrement' => $this->calculerTauxRecouvrement($totalCollecte, $totalPrix),
                    'nb_payes' => $totalPayer,
                    'nb_partiels' => $totalPartiel,
                    'nb_impayes' => $totalImpayer,
                ],
                'evolution' => $evolutionMensuelle,
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
    public function rechercherEtudiant(Request $request, $anneeId)
    {
        $annee = AnneeScolaire::findOrFail($anneeId);
        $search = $request->query('search');
        $cycle = $request->query('cycle');
        $niveauId = $request->query('niveau_id');
        $classeId = $request->query('classe_id');

        $inscriptions = Inscription::with(['eleve', 'classe.niveau', 'resumePaiement'])
            ->where('id_annee_scolaire', $annee->id)
            ->when($cycle, function ($query) use ($cycle) {
                $query->whereHas('classe.niveau', function ($q) use ($cycle) {
                    $q->where('cycle', $cycle);
                });
            })
            ->when($niveauId, function ($query) use ($niveauId) {
                $query->whereHas('classe.niveau', function ($q) use ($niveauId) {
                    if (is_numeric($niveauId)) {
                        $q->where('id', $niveauId);
                    } else {
                        $q->where('nom_niveau', $niveauId);
                    }
                });
            })
            ->when($classeId, function ($query) use ($classeId) {
                $query->where('id_classe', $classeId);
            })
            ->whereHas('eleve', function ($query) use ($search) {
                if ($search) {
                    $query->where(function($q) use ($search) {
                        $q->where('nom', 'like', "%{$search}%")
                          ->orWhere('prenom', 'like', "%{$search}%")
                          ->orWhere('matricule', 'like', "%{$search}%");
                    });
                }
            })
            ->get()
            ->map(function ($ins) {
                $moyenneGeneral = Bulletin::where('inscription_id', $ins->id)->avg('moyenne_eleve');
                
                $statusPaye = 'impaye';
                if ($ins->resumePaiement) {
                    if ($ins->resumePaiement->total_restant <= 0) {
                        $statusPaye = 'paye';
                    } elseif ($ins->resumePaiement->total_paye > 0) {
                        $statusPaye = 'partiel';
                    }
                }

                return [
                    'id' => $ins->eleve->id,
                    'matricule' => $ins->eleve->matricule,
                    'nom' => $ins->eleve->nom,
                    'prenom' => $ins->eleve->prenom,
                    'classe_nom' => $ins->classe->nom_classe,
                    'statut_paiement' => $statusPaye,
                    'moyenne' => round($moyenneGeneral, 2)
                ];
            });

        return response()->json([
            'success' => true,
            'data' => $inscriptions
        ]);
    }
}