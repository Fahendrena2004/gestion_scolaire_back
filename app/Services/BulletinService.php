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
        $matieres = Matieres::all();
        $moyennes = [];

        foreach ($matieres as $matiere) {
            $moyenne = $this->calculerMoyenneMatiere($inscriptionId, $matiere->id, $periode);
            $moyennes[$matiere->id] = [
                'matiere' => $matiere,
                'moyenne' => $moyenne,
                'coefficient' => $matiere->coefficient
            ];
        }

        return $moyennes;
    }

    public function calculerMoyenneGenerale($inscriptionId, $periode)
    {
        $moyennesParMatiere = $this->calculerMoyennesParMatiere($inscriptionId, $periode);
        
        $totalPondere = 0;
        $totalCoefficients = 0;

        foreach ($moyennesParMatiere as $data) {
            if ($data['moyenne'] > 0) {
                $totalPondere += $data['moyenne'] * $data['coefficient'];
                $totalCoefficients += $data['coefficient'];
            }
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
        
        $moyenneEleve = $this->calculerMoyenneGenerale($inscriptionId, $periode);
        
        $autresInscriptions = Inscription::where('id_classe', $inscription->id_classe)
            ->where('id_annee_scolaire', $inscription->id_annee_scolaire)
            ->get();
        
        $moyennes = [];
        foreach ($autresInscriptions as $other) {
            $moyennes[$other->id] = $this->calculerMoyenneGenerale($other->id, $periode);
        }
        
        arsort($moyennes);
        
        $rang = 1;
        foreach ($moyennes as $id => $moy) {
            if ($id == $inscriptionId) {
                return $rang;
            }
            $rang++;
        }
        
        return $rang;
    }

    public function genererBulletin($inscriptionId, $periode)
    {
        try {
            DB::beginTransaction();
            
            $bulletinExistant = Bulletin::where('inscription_id', $inscriptionId)
                ->where('periode', $periode)
                ->first();
            
            if ($bulletinExistant) {
                throw new \Exception('Un bulletin existe déjà pour cette période.');
            }
            
            $inscription = Inscription::find($inscriptionId);
            if (!$inscription) {
                throw new \Exception('Inscription non trouvée');
            }
            
            $moyenneGenerale = $this->calculerMoyenneGenerale($inscriptionId, $periode);
            $moyenneClasse = $this->calculerMoyenneClasse(
                $inscription->id_classe,
                $periode,
                $inscription->id_annee_scolaire
            );
            $rang = $this->determinerRang($inscriptionId, $periode);
            $decision = $moyenneGenerale >= 10 ? 'ADMIS' : ($moyenneGenerale >= 8 ? 'REPRISE' : 'REDOUBLANT');
            
            $bulletin = Bulletin::create([
                'inscription_id' => $inscriptionId,
                'moyenne_eleve' => $moyenneGenerale,
                'moyenne_classe' => $moyenneClasse,
                'rang' => $rang,
                'periode' => $periode,
                'decision' => $decision,
                'appreciation' => $this->genererAppreciation($moyenneGenerale, $rang)
            ]);
            
            $moyennesParMatiere = $this->calculerMoyennesParMatiere($inscriptionId, $periode);
            
            foreach ($moyennesParMatiere as $matiereId => $data) {
                if ($data['moyenne'] > 0) {
                    DetailBulletins::create([
                        'bulletin_id' => $bulletin->id,
                        'matiere_id' => $matiereId,
                        'moyenne_matiere' => $data['moyenne'],
                        'rang_matiere' => 0,
                        'appreciation' => $this->genererAppreciationMatiere($data['moyenne'])
                    ]);
                }
            }
            
            DB::commit();
            
            return Bulletin::with(['inscription.eleve', 'detailBulletins.matiere'])->find($bulletin->id);
            
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Erreur: ' . $e->getMessage());
            throw $e;
        }
    }

    private function genererAppreciation($moyenne, $rang)
    {
        if ($moyenne >= 16) return "Excellent travail ! Félicitations !";
        if ($moyenne >= 14) return "Très bon travail, continuez !";
        if ($moyenne >= 12) return "Bon travail, pouvez mieux faire.";
        if ($moyenne >= 10) return "Travail acceptable, des efforts sont nécessaires.";
        return "Résultats insuffisants, travaillez davantage.";
    }

    private function genererAppreciationMatiere($moyenne)
    {
        if ($moyenne >= 16) return "Excellent !";
        if ($moyenne >= 14) return "Très bien !";
        if ($moyenne >= 12) return "Bien.";
        if ($moyenne >= 10) return "Passable.";
        return "Insuffisant.";
    }
    public function genererBulletinsClasse($classeId, $periode, $anneeScolaireId)
{
    $inscriptions = Inscription::where('id_classe', $classeId)
        ->where('id_annee_scolaire', $anneeScolaireId)
        ->get();
    
    $resultats = [];
    foreach ($inscriptions as $inscription) {
        try {
            $bulletin = $this->genererBulletin($inscription->id, $periode);
            $resultats[$inscription->id] = ['success' => true, 'bulletin' => $bulletin];
        } catch (\Exception $e) {
            $resultats[$inscription->id] = ['success' => false, 'error' => $e->getMessage()];
        }
    }
    
    return $resultats;
}
}