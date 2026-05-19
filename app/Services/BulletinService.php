<?php

namespace App\Services;

use App\Models\Gestion_note\Notes;
use App\Models\Gestion_note\Matieres;
use App\Models\Gestion_note\Bulletin;
use App\Models\Gestion_note\DetailBulletins;
use App\Models\Inscription\Inscription;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class BulletinService
{
    public function calculerMoyenneMatiere($inscriptionId, $matiereId, $periode)
    {
        $notes = Notes::where('inscription_id', $inscriptionId)
            ->where('matiere_id', $matiereId)
            ->where('periode', $periode)
            ->get();

        if ($notes->isEmpty()) {
            return 0;
        }

        $total = $notes->sum('valeur');
        $nombreNotes = $notes->count();

        return round($total / $nombreNotes, 2);
    }

    public function calculerMoyennesParMatiere($inscriptionId, $periode)
    {
        $inscription = Inscription::with('classe.niveau')->find($inscriptionId);
        if (!$inscription) return [];

        $classe = $inscription->classe;
        $niveau = $classe->niveau;

        // Filtrer les matières correspondant à l'élève :
        // 1. Matières spécifiques à sa classe
        // 2. OU Matières globales correspondant à son cycle et niveau (champs strings)
        $matieres = Matieres::where('classe_id', $classe->id)
            ->orWhere(function($query) use ($niveau) {
                $query->whereNull('classe_id')
                      ->where('cycle', $niveau->cycle)
                      ->where('niveau_classe', $niveau->nom_niveau);
            })
            ->get();

        $moyennes = [];

        foreach ($matieres as $matiere) {
            $moyenne = $this->calculerMoyenneMatiere($inscriptionId, $matiere->id, $periode);
            if ($moyenne > 0) {
                $moyennes[$matiere->id] = [
                    'matiere' => $matiere,
                    'moyenne' => $moyenne,
                    'coefficient' => $matiere->coefficient
                ];
            }
        }

        return $moyennes;
    }

    public function calculerMoyenneGenerale($inscriptionId, $periode)
    {
        $moyennesParMatiere = $this->calculerMoyennesParMatiere($inscriptionId, $periode);
        
        $totalPondere = 0;
        $totalCoefficients = 0;

        foreach ($moyennesParMatiere as $data) {
            $totalPondere += $data['moyenne'] * $data['coefficient'];
            $totalCoefficients += $data['coefficient'];
        }

        return $totalCoefficients > 0 ? round($totalPondere / $totalCoefficients, 2) : 0;
    }

    public function calculerMoyenneClasse($classeId, $periode, $anneeScolaireId = null)
    {
        $query = Inscription::where('id_classe', $classeId);
        
        if ($anneeScolaireId) {
            $query->where('id_annee_scolaire', $anneeScolaireId);
        }
        
        $inscriptions = $query->get();
        
        if ($inscriptions->isEmpty()) {
            return 0;
        }

        $totalMoyennes = 0;
        $compteur = 0;

        foreach ($inscriptions as $inscription) {
            $moyenne = $this->calculerMoyenneGenerale($inscription->id, $periode);
            if ($moyenne > 0) {
                $totalMoyennes += $moyenne;
                $compteur++;
            }
        }

        return $compteur > 0 ? round($totalMoyennes / $compteur, 2) : 0;
    }

    public function determinerRang($inscriptionId, $periode)
    {
        $inscription = Inscription::find($inscriptionId);
        if (!$inscription) {
            return 0;
        }
        
        $autresInscriptions = Inscription::where('id_classe', $inscription->id_classe)
            ->where('id_annee_scolaire', $inscription->id_annee_scolaire)
            ->get();
        
        $moyennes = [];
        foreach ($autresInscriptions as $other) {
            $moy = $this->calculerMoyenneGenerale($other->id, $periode);
            if ($moy > 0) {
                $moyennes[$other->id] = $moy;
            }
        }
        
        arsort($moyennes);
        
        $rang = 1;
        $count = 0;
        $prevMoy = -1;
        foreach ($moyennes as $id => $moy) {
            $count++;
            if ($moy != $prevMoy) {
                $currentRang = $count;
            }
            if ($id == $inscriptionId) {
                return $currentRang;
            }
            $prevMoy = $moy;
        }
        
        return 0;
    }

    public function genererBulletin($inscriptionId, $periode, $forceUpdate = true)
    {
        try {
            DB::beginTransaction();
            
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
            $moyenneClasse = $this->calculerMoyenneClasse(
                $inscription->id_classe,
                $periode,
                $inscription->id_annee_scolaire
            );
            $rang = $this->determinerRang($inscriptionId, $periode);
            $decision = $moyenneGenerale >= 10 ? 'ADMIS' : ($moyenneGenerale >= 8 ? 'REPRISE' : 'REDOUBLANT');
            
            $data = [
                'inscription_id' => $inscriptionId,
                'moyenne_eleve' => $moyenneGenerale,
                'moyenne_classe' => $moyenneClasse,
                'rang' => $rang,
                'periode' => $periode,
                'decision' => $decision,
                'appreciation' => $this->genererAppreciation($moyenneGenerale, $rang)
            ];

            if ($bulletinExistant) {
                $bulletinExistant->update($data);
                $bulletin = $bulletinExistant;
                // Supprimer les anciens détails pour les recréer
                DetailBulletins::where('bulletin_id', $bulletin->id)->delete();
            } else {
                $bulletin = Bulletin::create($data);
            }
            
            $moyennesParMatiere = $this->calculerMoyennesParMatiere($inscriptionId, $periode);
            
            foreach ($moyennesParMatiere as $matiereId => $details) {
                DetailBulletins::create([
                    'bulletin_id' => $bulletin->id,
                    'matiere_id' => $matiereId,
                    'moyenne_matiere' => $details['moyenne'],
                    'rang_matiere' => $this->calculerRangMatiere($inscription->id_classe, $inscription->id_annee_scolaire, $matiereId, $periode, $details['moyenne']),
                    'appreciation' => $this->genererAppreciationMatiere($details['moyenne'])
                ]);
            }
            
            DB::commit();
            
            return Bulletin::with(['inscription.eleve', 'detailBulletins.matiere'])->find($bulletin->id);
            
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Erreur generation bulletin: ' . $e->getMessage());
            throw $e;
        }
    }

    public function calculerRangMatiere($classeId, $anneeScolaireId, $matiereId, $periode, $moyenneEleve)
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

    private function genererAppreciation($moyenne, $rang)
    {
        $v = (float)$moyenne;
        if ($v >= 0 && $v <= 5) {
            return 'blame';
        } elseif ($v >= 6 && $v <= 9) {
            return 'Insuffisant';
        } elseif ($v >= 10 && $v <= 12) {
            return 'passable';
        } elseif ($v >= 12 && $v <= 14) {
            return 'Assez-bien';
        } elseif ($v >= 15 && $v <= 16) {
            return 'bien';
        } else {
            return 'tres-bien';
        }
    }

    private function genererAppreciationMatiere($moyenne)
    {
        $v = (float)$moyenne;
        if ($v >= 0 && $v <= 5) {
            return 'balme';
        } elseif ($v >= 6 && $v <= 9) {
            return 'Insuffisant';
        } elseif ($v >= 10 && $v <= 12) {
            return 'passable';
        } elseif ($v >= 12 && $v <= 14) {
            return 'Assez-bien';
        } elseif ($v >= 15 && $v <= 16) {
            return 'bien';
        } else {
            return 'tres-bien';
        }
    }

    public function genererBulletinsClasse($classeId, $periode, $anneeScolaireId)
    {
        $inscriptions = Inscription::where('id_classe', $classeId)
            ->where('id_annee_scolaire', $anneeScolaireId)
            ->get();
        
        if ($inscriptions->isEmpty()) {
            return [];
        }

        // Pré-calculer les moyennes générales pour tout le monde
        $moyennesG = [];
        foreach ($inscriptions as $ins) {
            $moy = $this->calculerMoyenneGenerale($ins->id, $periode);
            if ($moy > 0) {
                $moyennesG[$ins->id] = $moy;
            }
        }

        // Calculer la moyenne de classe
        $moyenneClasse = count($moyennesG) > 0 ? round(array_sum($moyennesG) / count($moyennesG), 2) : 0;

        // Trier pour les rangs
        arsort($moyennesG);
        $rangs = [];
        $count = 0;
        $currentRang = 0;
        $prevMoy = -1;
        foreach ($moyennesG as $id => $moy) {
            $count++;
            if ($moy != $prevMoy) {
                $currentRang = $count;
            }
            $rangs[$id] = $currentRang;
            $prevMoy = $moy;
        }
        
        $resultats = [];
        foreach ($inscriptions as $inscription) {
            try {
                // Pour la classe, on peut optimiser en passant les rangs déjà calculés
                // Mais pour garder la logique propre, on appelle genererBulletin ou on duplique un peu
                // Ici je vais appeler une version légèrement modifiée ou juste faire le save direct
                
                $bulletinExistant = Bulletin::where('inscription_id', $inscription->id)
                    ->where('periode', $periode)
                    ->first();
                
                $moyEleve = $moyennesG[$inscription->id] ?? 0;
                $rangEleve = $rangs[$inscription->id] ?? 0;
                $decision = $moyEleve >= 10 ? 'ADMIS' : ($moyEleve >= 8 ? 'REPRISE' : 'REDOUBLANT');

                $data = [
                    'inscription_id' => $inscription->id,
                    'moyenne_eleve' => $moyEleve,
                    'moyenne_classe' => $moyenneClasse,
                    'rang' => $rangEleve,
                    'periode' => $periode,
                    'decision' => $decision,
                    'appreciation' => $this->genererAppreciation($moyEleve, $rangEleve)
                ];

                DB::beginTransaction();
                if ($bulletinExistant) {
                    $bulletinExistant->update($data);
                    $bulletin = $bulletinExistant;
                    DetailBulletins::where('bulletin_id', $bulletin->id)->delete();
                } else {
                    $bulletin = Bulletin::create($data);
                }

                $moyennesParMatiere = $this->calculerMoyennesParMatiere($inscription->id, $periode);
                foreach ($moyennesParMatiere as $matiereId => $details) {
                    DetailBulletins::create([
                        'bulletin_id' => $bulletin->id,
                        'matiere_id' => $matiereId,
                        'moyenne_matiere' => $details['moyenne'],
                        'rang_matiere' => $this->calculerRangMatiere($classeId, $anneeScolaireId, $matiereId, $periode, $details['moyenne']),
                        'appreciation' => $this->genererAppreciationMatiere($details['moyenne'])
                    ]);
                }
                DB::commit();

                $resultats[$inscription->id] = [
                    'success' => true, 
                    'bulletin' => Bulletin::with(['inscription.eleve', 'detailBulletins.matiere'])->find($bulletin->id)
                ];
            } catch (\Exception $e) {
                DB::rollBack();
                $resultats[$inscription->id] = ['success' => false, 'error' => $e->getMessage()];
            }
        }
        
        return $resultats;
    }

}