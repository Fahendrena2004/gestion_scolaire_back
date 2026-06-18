<?php

namespace App\Services;

use App\Models\Gestion_note\Notes;
use App\Models\Gestion_note\Matieres;
use App\Models\Gestion_note\Bulletin;
use App\Models\Gestion_note\BulletinAnnuel;
use App\Models\Gestion_note\DetailBulletins;
use App\Models\Inscription\Inscription;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class BulletinService
{
    // =========================================================================
    // A. BARÈME D'APPRÉCIATION
    // =========================================================================

    public function genererAppreciation(float $moyenne): string
    {
        if ($moyenne >= 17) return 'Très Bien';
        if ($moyenne >= 15) return 'Bien';
        if ($moyenne >= 12) return 'Assez Bien';
        if ($moyenne >= 10) return 'Passable';
        if ($moyenne >= 6)  return 'Insuffisant';
        return 'Faible';
    }

    // =========================================================================
    // B. CALCUL DE LA MOYENNE PAR MATIÈRE
    // MOYENNE = (interro1 + interro2 + examen) / nb_valeurs_non_nulles
    // =========================================================================

    public function calculerMoyenneMatiere(int $inscriptionId, int $matiereId, string $periode): float
    {
        $note = Notes::where('inscription_id', $inscriptionId)
            ->where('matiere_id', $matiereId)
            ->where('periode', $periode)
            ->first();

        if (!$note) return 0.0;

        $total  = 0.0;
        $nombre = 0;

        if ($note->interro1 !== null) { $total += (float)$note->interro1; $nombre++; }
        if ($note->interro2 !== null) { $total += (float)$note->interro2; $nombre++; }
        if ($note->examen   !== null) { $total += (float)$note->examen;   $nombre++; }

        if ($nombre === 0) return 0.0;

        return round($total / $nombre, 2);
    }

    // =========================================================================
    // C. CALCUL DES MOYENNES PAR MATIÈRE (toutes matières d'un élève)
    // Retourne uniquement les matières avec moyenne > 0
    // =========================================================================

    public function calculerMoyennesParMatiere(int $inscriptionId, string $periode): array
    {
        $inscription = Inscription::with('classe.niveau')->find($inscriptionId);
        if (!$inscription) return [];

        $classe = $inscription->classe;
        $niveau = $classe ? $classe->niveau : null;

        $matieres = Matieres::where(function ($q) use ($classe, $niveau) {
            if ($classe) {
                $q->where('classe_id', $classe->id);
            }
            if ($niveau) {
                $q->orWhere(function ($q2) use ($niveau) {
                    $q2->whereNull('classe_id')->where('niveau_id', $niveau->id);
                });
            }
        })->get();

        $moyennes = [];
        foreach ($matieres as $matiere) {
            $moyenne = $this->calculerMoyenneMatiere($inscriptionId, $matiere->id, $periode);
            if ($moyenne > 0) {
                $moyennes[$matiere->id] = [
                    'matiere'     => $matiere,
                    'moyenne'     => $moyenne,
                    'coefficient' => (float)($matiere->coefficient ?? 1),
                ];
            }
        }

        return $moyennes;
    }

    // =========================================================================
    // D. CALCUL DE LA MOYENNE GÉNÉRALE PONDÉRÉE
    // MOYENNE_GENERALE = Σ(moy_matière × coeff) / Σ(coeff)
    // Seules les matières avec notes incluses
    // =========================================================================

    public function calculerMoyenneGenerale(int $inscriptionId, string $periode): float
    {
        $moyennesParMatiere = $this->calculerMoyennesParMatiere($inscriptionId, $periode);

        $totalPondere     = 0.0;
        $totalCoefficient = 0.0;

        foreach ($moyennesParMatiere as $data) {
            $totalPondere     += $data['moyenne'] * $data['coefficient'];
            $totalCoefficient += $data['coefficient'];
        }

        return $totalCoefficient > 0 ? round($totalPondere / $totalCoefficient, 2) : 0.0;
    }

    // =========================================================================
    // E. CALCUL DU RANG GÉNÉRAL (gestion des ex-æquo)
    // =========================================================================

    public function calculerRang(int $inscriptionId, string $periode): int
    {
        $inscription = Inscription::find($inscriptionId);
        if (!$inscription) return 0;

        $inscriptions = Inscription::where('id_classe', $inscription->id_classe)
            ->where('id_annee_scolaire', $inscription->id_annee_scolaire)
            ->get();

        $moyennes = [];
        foreach ($inscriptions as $ins) {
            $moy = $this->calculerMoyenneGenerale($ins->id, $periode);
            if ($moy > 0) {
                $moyennes[$ins->id] = $moy;
            }
        }

        arsort($moyennes);

        $rang     = 0;
        $count    = 0;
        $prevMoy  = -1;

        foreach ($moyennes as $id => $moy) {
            $count++;
            if ($moy != $prevMoy) {
                $rang = $count;
            }
            if ($id == $inscriptionId) {
                return $rang;
            }
            $prevMoy = $moy;
        }

        return 0;
    }

    // =========================================================================
    // F. CALCUL DU RANG PAR MATIÈRE (gestion des ex-æquo)
    // =========================================================================

    public function calculerRangMatiere(int $classeId, int $anneeScolaireId, int $matiereId, string $periode, float $moyenneEleve): int
    {
        $inscriptions = Inscription::where('id_classe', $classeId)
            ->where('id_annee_scolaire', $anneeScolaireId)
            ->get();

        $moyennes = [];
        foreach ($inscriptions as $ins) {
            $moy = $this->calculerMoyenneMatiere($ins->id, $matiereId, $periode);
            if ($moy > 0) {
                $moyennes[] = $moy;
            }
        }

        rsort($moyennes);
        $uniqueMoyennes = array_values(array_unique($moyennes));

        foreach ($uniqueMoyennes as $index => $m) {
            if ($m == $moyenneEleve) {
                return $index + 1;
            }
        }

        return 0;
    }

    // =========================================================================
    // G. CALCUL DE LA MOYENNE DE CLASSE
    // Seuls les élèves ayant au moins une note sont inclus
    // =========================================================================

    public function calculerMoyenneClasse(int $classeId, string $periode, ?int $anneeScolaireId = null): float
    {
        $query = Inscription::where('id_classe', $classeId);
        if ($anneeScolaireId) {
            $query->where('id_annee_scolaire', $anneeScolaireId);
        }
        $inscriptions = $query->get();

        if ($inscriptions->isEmpty()) return 0.0;

        $total   = 0.0;
        $compteur = 0;

        foreach ($inscriptions as $ins) {
            $moy = $this->calculerMoyenneGenerale($ins->id, $periode);
            if ($moy > 0) {
                $total += $moy;
                $compteur++;
            }
        }

        return $compteur > 0 ? round($total / $compteur, 2) : 0.0;
    }

    // =========================================================================
    // H. GÉNÉRATION DU BULLETIN INDIVIDUEL
    // =========================================================================

    public function genererBulletin(int $inscriptionId, string $periode, bool $forceUpdate = true): Bulletin
    {
        DB::beginTransaction();
        try {
            $inscription = Inscription::find($inscriptionId);
            if (!$inscription) {
                throw new \Exception('Inscription non trouvée');
            }

            $bulletinExistant = Bulletin::where('inscription_id', $inscriptionId)
                ->where('periode', $periode)
                ->first();

            if ($bulletinExistant && !$forceUpdate) {
                throw new \Exception('Un bulletin existe déjà pour cette période.');
            }

            $moyenneGenerale = $this->calculerMoyenneGenerale($inscriptionId, $periode);
            $moyenneClasse   = $this->calculerMoyenneClasse(
                $inscription->id_classe,
                $periode,
                $inscription->id_annee_scolaire
            );
            $rang     = $this->calculerRang($inscriptionId, $periode);
            $decision = $this->determinerDecisionTrimestrielle($moyenneGenerale);

            $data = [
                'inscription_id' => $inscriptionId,
                'moyenne_eleve'  => $moyenneGenerale,
                'moyenne_classe' => $moyenneClasse,
                'rang'           => $rang,
                'periode'        => $periode,
                'decision'       => $decision,
                'appreciation'   => $this->genererAppreciation($moyenneGenerale),
            ];

            if ($bulletinExistant) {
                $bulletinExistant->update($data);
                $bulletin = $bulletinExistant;
                DetailBulletins::where('bulletin_id', $bulletin->id)->delete();
            } else {
                $bulletin = Bulletin::create($data);
            }

            $moyennesParMatiere = $this->calculerMoyennesParMatiere($inscriptionId, $periode);

            foreach ($moyennesParMatiere as $matiereId => $details) {
                $rangMat = $this->calculerRangMatiere(
                    $inscription->id_classe,
                    $inscription->id_annee_scolaire,
                    $matiereId,
                    $periode,
                    $details['moyenne']
                );
                DetailBulletins::create([
                    'bulletin_id'    => $bulletin->id,
                    'matiere_id'     => $matiereId,
                    'moyenne_matiere'=> $details['moyenne'],
                    'rang_matiere'   => $rangMat,
                    'appreciation'   => $this->genererAppreciation($details['moyenne']),
                ]);
            }

            DB::commit();

            return Bulletin::with(['inscription.eleve', 'detailBulletins.matiere'])->find($bulletin->id);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Erreur genererBulletin: ' . $e->getMessage());
            throw $e;
        }
    }

    // =========================================================================
    // I. GÉNÉRATION DES BULLETINS D'UNE CLASSE ENTIÈRE (optimisé)
    // =========================================================================

    public function genererBulletinsClasse(int $classeId, string $periode, int $anneeScolaireId): array
    {
        $inscriptions = Inscription::where('id_classe', $classeId)
            ->where('id_annee_scolaire', $anneeScolaireId)
            ->get();

        if ($inscriptions->isEmpty()) return [];

        // Pré-calcul des moyennes générales
        $moyennesG = [];
        foreach ($inscriptions as $ins) {
            $moy = $this->calculerMoyenneGenerale($ins->id, $periode);
            if ($moy > 0) {
                $moyennesG[$ins->id] = $moy;
            }
        }

        // Moyenne de classe
        $moyenneClasse = count($moyennesG) > 0
            ? round(array_sum($moyennesG) / count($moyennesG), 2)
            : 0.0;

        // Calcul des rangs avec gestion des ex-æquo
        arsort($moyennesG);
        $rangs       = [];
        $count       = 0;
        $currentRang = 0;
        $prevMoy     = -1;

        foreach ($moyennesG as $id => $moy) {
            $count++;
            if ($moy != $prevMoy) {
                $currentRang = $count;
            }
            $rangs[$id] = $currentRang;
            $prevMoy    = $moy;
        }

        $resultats = [];

        foreach ($inscriptions as $inscription) {
            DB::beginTransaction();
            try {
                $bulletinExistant = Bulletin::where('inscription_id', $inscription->id)
                    ->where('periode', $periode)
                    ->first();

                $moyEleve  = $moyennesG[$inscription->id] ?? 0.0;
                $rangEleve = $rangs[$inscription->id]     ?? 0;
                $decision  = $this->determinerDecisionTrimestrielle($moyEleve);

                $data = [
                    'inscription_id' => $inscription->id,
                    'moyenne_eleve'  => $moyEleve,
                    'moyenne_classe' => $moyenneClasse,
                    'rang'           => $rangEleve,
                    'periode'        => $periode,
                    'decision'       => $decision,
                    'appreciation'   => $this->genererAppreciation($moyEleve),
                ];

                if ($bulletinExistant) {
                    $bulletinExistant->update($data);
                    $bulletin = $bulletinExistant;
                    DetailBulletins::where('bulletin_id', $bulletin->id)->delete();
                } else {
                    $bulletin = Bulletin::create($data);
                }

                $moyennesParMatiere = $this->calculerMoyennesParMatiere($inscription->id, $periode);
                foreach ($moyennesParMatiere as $matiereId => $details) {
                    $rangMat = $this->calculerRangMatiere(
                        $classeId,
                        $anneeScolaireId,
                        $matiereId,
                        $periode,
                        $details['moyenne']
                    );
                    DetailBulletins::create([
                        'bulletin_id'    => $bulletin->id,
                        'matiere_id'     => $matiereId,
                        'moyenne_matiere'=> $details['moyenne'],
                        'rang_matiere'   => $rangMat,
                        'appreciation'   => $this->genererAppreciation($details['moyenne']),
                    ]);
                }

                DB::commit();

                $resultats[$inscription->id] = [
                    'success' => true,
                    'bulletin'=> Bulletin::with(['inscription.eleve', 'detailBulletins.matiere'])->find($bulletin->id),
                ];
            } catch (\Exception $e) {
                DB::rollBack();
                $resultats[$inscription->id] = ['success' => false, 'error' => $e->getMessage()];
            }
        }

        return $resultats;
    }

    // =========================================================================
    // J. CALCUL DE LA MOYENNE ANNUELLE
    // Si 0 trimestre → 0 ; 1 → T1 ; 2 → (T1+T2)/2 ; 3 → (T1+T2+T3)/3
    // =========================================================================

    public function calculerMoyenneAnnuelle(int $inscriptionId): array
    {
        $periodes = ['TRIMESTRE_1' => null, 'TRIMESTRE_2' => null, 'TRIMESTRE_3' => null];

        foreach (array_keys($periodes) as $periode) {
            $moy = $this->calculerMoyenneGenerale($inscriptionId, $periode);
            if ($moy > 0) {
                $periodes[$periode] = $moy;
            }
        }

        $disponibles = array_filter($periodes, fn($v) => $v !== null);
        $nb          = count($disponibles);

        $moyenneAnnuelle = $nb > 0 ? round(array_sum($disponibles) / $nb, 2) : 0.0;

        return [
            'moyenne_t1'      => $periodes['TRIMESTRE_1'],
            'moyenne_t2'      => $periodes['TRIMESTRE_2'],
            'moyenne_t3'      => $periodes['TRIMESTRE_3'],
            'moyenne_annuelle' => $moyenneAnnuelle,
            'nb_trimestres'   => $nb,
        ];
    }

    // =========================================================================
    // K. DÉTERMINATION DE LA DÉCISION ANNUELLE (SANS REPRISE)
    // =========================================================================

    public function determinerDecisionAnnuelle(float $moyenne, int $nbTrimestres): string
    {
        if ($nbTrimestres === 0) return 'NON_EVALUE';
        if ($moyenne >= 10)     return 'ADMIS';
        return 'REDOUBLANT';
    }

    // Décision trimestrielle (intermédiaire) — pas de Reprise non plus
    private function determinerDecisionTrimestrielle(float $moyenne): string
    {
        if ($moyenne === 0.0) return 'NON_EVALUE';
        if ($moyenne >= 10)   return 'ADMIS';
        return 'REDOUBLANT';
    }

    // =========================================================================
    // L. GÉNÉRATION DU BULLETIN ANNUEL INDIVIDUEL
    // =========================================================================

    public function genererBulletinAnnuel(int $inscriptionId): BulletinAnnuel
    {
        DB::beginTransaction();
        try {
            $inscription = Inscription::find($inscriptionId);
            if (!$inscription) {
                throw new \Exception('Inscription non trouvée');
            }

            $annuel  = $this->calculerMoyenneAnnuelle($inscriptionId);
            $decision = $this->determinerDecisionAnnuelle($annuel['moyenne_annuelle'], $annuel['nb_trimestres']);

            // Rang annuel et moyenne de classe annuelle calculés dynamiquement
            // (on les recalculera en masse lors de genererBulletinsAnnuels)
            $data = [
                'inscription_id'        => $inscriptionId,
                'moyenne_t1'            => $annuel['moyenne_t1'],
                'moyenne_t2'            => $annuel['moyenne_t2'],
                'moyenne_t3'            => $annuel['moyenne_t3'],
                'moyenne_annuelle'      => $annuel['moyenne_annuelle'],
                'rang_annuel'           => 0,
                'moyenne_classe_annuelle'=> 0.0,
                'decision'              => $decision,
                'appreciation'          => $this->genererAppreciation($annuel['moyenne_annuelle']),
                'nb_trimestres'         => $annuel['nb_trimestres'],
                'est_complet'           => $annuel['nb_trimestres'] === 3,
            ];

            $bulletinAnnuel = BulletinAnnuel::updateOrCreate(
                ['inscription_id' => $inscriptionId],
                $data
            );

            DB::commit();

            return $bulletinAnnuel->load('inscription.eleve');

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Erreur genererBulletinAnnuel: ' . $e->getMessage());
            throw $e;
        }
    }

    // =========================================================================
    // M. GÉNÉRATION DES BULLETINS ANNUELS D'UNE CLASSE ENTIÈRE
    // =========================================================================

    public function genererBulletinsAnnuels(int $classeId, int $anneeScolaireId): array
    {
        $inscriptions = Inscription::where('id_classe', $classeId)
            ->where('id_annee_scolaire', $anneeScolaireId)
            ->get();

        if ($inscriptions->isEmpty()) return [];

        // 1. Calculer toutes les moyennes annuelles
        $moyennesA = [];
        foreach ($inscriptions as $ins) {
            $annuel = $this->calculerMoyenneAnnuelle($ins->id);
            if ($annuel['nb_trimestres'] > 0) {
                $moyennesA[$ins->id] = $annuel;
            }
        }

        // 2. Moyenne de classe annuelle
        $somme = array_sum(array_column($moyennesA, 'moyenne_annuelle'));
        $moyenneClasseAnnuelle = count($moyennesA) > 0
            ? round($somme / count($moyennesA), 2)
            : 0.0;

        // 3. Rangs annuels avec ex-æquo
        $seulesMoyennes = array_map(fn($a) => $a['moyenne_annuelle'], $moyennesA);
        arsort($seulesMoyennes);
        $rangsAnnuels = [];
        $count        = 0;
        $currentRang  = 0;
        $prevMoy      = -1;

        foreach ($seulesMoyennes as $id => $moy) {
            $count++;
            if ($moy != $prevMoy) {
                $currentRang = $count;
            }
            $rangsAnnuels[$id] = $currentRang;
            $prevMoy = $moy;
        }

        // 4. Enregistrement
        $resultats = [];
        foreach ($inscriptions as $inscription) {
            DB::beginTransaction();
            try {
                $annuel   = $moyennesA[$inscription->id] ?? ['moyenne_t1'=>null,'moyenne_t2'=>null,'moyenne_t3'=>null,'moyenne_annuelle'=>0,'nb_trimestres'=>0];
                $decision = $this->determinerDecisionAnnuelle($annuel['moyenne_annuelle'], $annuel['nb_trimestres']);

                $bulletinAnnuel = BulletinAnnuel::updateOrCreate(
                    ['inscription_id' => $inscription->id],
                    [
                        'moyenne_t1'             => $annuel['moyenne_t1'],
                        'moyenne_t2'             => $annuel['moyenne_t2'],
                        'moyenne_t3'             => $annuel['moyenne_t3'],
                        'moyenne_annuelle'       => $annuel['moyenne_annuelle'],
                        'rang_annuel'            => $rangsAnnuels[$inscription->id] ?? 0,
                        'moyenne_classe_annuelle'=> $moyenneClasseAnnuelle,
                        'decision'               => $decision,
                        'appreciation'           => $this->genererAppreciation($annuel['moyenne_annuelle']),
                        'nb_trimestres'          => $annuel['nb_trimestres'],
                        'est_complet'            => $annuel['nb_trimestres'] === 3,
                    ]
                );

                DB::commit();
                $resultats[$inscription->id] = ['success' => true, 'bulletin_annuel' => $bulletinAnnuel->load('inscription.eleve')];

            } catch (\Exception $e) {
                DB::rollBack();
                $resultats[$inscription->id] = ['success' => false, 'error' => $e->getMessage()];
            }
        }

        return $resultats;
    }

    // =========================================================================
    // N. STATISTIQUES D'EFFECTIF (helper partagé)
    // =========================================================================

    public function getEffectifClasse(int $classeId, ?int $anneeScolaireId = null): int
    {
        $q = Inscription::where('id_classe', $classeId);
        if ($anneeScolaireId) $q->where('id_annee_scolaire', $anneeScolaireId);
        return $q->count();
    }
}