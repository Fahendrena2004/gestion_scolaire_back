<?php

namespace App\Http\Controllers\Setup;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use App\Models\Inscription\AnneeScolaire;
use App\Models\Inscription\Niveau;
use App\Models\Inscription\Classe;
use App\Models\Inscription\TypeFrais;
use App\Models\Gestion_note\Matieres;

class InstallController extends Controller
{
    public function createAnneeScolaire(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'date_debut' => 'required|date',
            'date_fin' => 'required|date|after:date_debut',
            'statut' => 'required|in:en_cours,termine,planifie'
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        if ($request->statut === 'en_cours') {
            AnneeScolaire::where('statut', 'en_cours')->update(['statut' => 'termine']);
        }

        $annee = AnneeScolaire::create($request->all());

        return response()->json([
            'success' => true,
            'message' => 'Année scolaire créée avec succès',
            'step' => 1,
            'next_step' => '/api/setup/niveaux',
            'data' => $annee
        ]);
    }

    public function createNiveaux(Request $request)
    {
        if (Niveau::count() > 0) {
            return response()->json([
                'success' => true,
                'message' => 'Les niveaux existent déjà',
                'step' => 2,
                'next_step' => '/api/setup/classes',
                'data' => Niveau::all()
            ]);
        }

        $niveaux = [
            ['cycle' => 'primaire', 'nom_niveau' => 'CP'],
            ['cycle' => 'primaire', 'nom_niveau' => 'CE1'],
            ['cycle' => 'primaire', 'nom_niveau' => 'CE2'],
            ['cycle' => 'primaire', 'nom_niveau' => 'CM1'],
            ['cycle' => 'primaire', 'nom_niveau' => 'CM2'],
            ['cycle' => 'college', 'nom_niveau' => '6ème'],
            ['cycle' => 'college', 'nom_niveau' => '5ème'],
            ['cycle' => 'college', 'nom_niveau' => '4ème'],
            ['cycle' => 'college', 'nom_niveau' => '3ème'],
            ['cycle' => 'lycee', 'nom_niveau' => 'Seconde'],
            ['cycle' => 'lycee', 'nom_niveau' => 'Première'],
            ['cycle' => 'lycee', 'nom_niveau' => 'Terminale'],
        ];

        $created = [];
        foreach ($niveaux as $niveau) {
            $created[] = Niveau::create($niveau);
        }

        return response()->json([
            'success' => true,
            'message' => count($created) . ' niveaux créés avec succès',
            'step' => 2,
            'next_step' => '/api/setup/classes',
            'data' => $created
        ]);
    }

    public function generateClasses(Request $request)
    {
        $anneeActive = AnneeScolaire::where('statut', 'en_cours')->first();
        
        if (!$anneeActive) {
            return response()->json([
                'success' => false,
                'message' => 'Aucune année scolaire active.',
                'step' => 1,
                'required_step' => '/api/setup/annee-scolaire'
            ], 400);
        }

        if (Classe::count() > 0) {
            return response()->json([
                'success' => true,
                'message' => 'Les classes existent déjà',
                'step' => 3,
                'next_step' => '/api/setup/frais',
                'data' => Classe::with('niveau')->get()
            ]);
        }

        $niveaux = Niveau::all();
        $lettres = ['A', 'B', 'C', 'D'];
        $created = [];
        $totalClasses = 0;

        foreach ($niveaux as $niveau) {
            $nbDivisions = $this->getNombreDivisions($niveau->cycle, $niveau->nom_niveau);

            for ($i = 0; $i < $nbDivisions; $i++) {
                $classe = Classe::create([
                    'nom_classe' => $niveau->nom_niveau . ' ' . $lettres[$i],
                    'niveau_id' => $niveau->id,
                    'code_division' => $lettres[$i],
                    'effectif' => 0,
                    'anneeScolaire_id' => $anneeActive->id,
                ]);
                $created[] = $classe;
                $totalClasses++;
            }
        }

        return response()->json([
            'success' => true,
            'message' => $totalClasses . ' classes générées automatiquement',
            'step' => 3,
            'next_step' => '/api/setup/frais',
            'annee_scolaire' => $anneeActive,
            'data' => $created
        ]);
    }

    private function getNombreDivisions(string $cycle, string $nomNiveau): int
    {
        return match(true) {
            $cycle == 'primaire' => 2,
            $nomNiveau == '6ème' => 4,
            $nomNiveau == 'Seconde' => 4,
            $cycle == 'college' => 3,
            $cycle == 'lycee' => 3,
            default => 2,
        };
    }

    public function createTypeFrais(Request $request)
    {
        if (TypeFrais::count() > 0) {
            return response()->json([
                'success' => true,
                'message' => 'Les types de frais existent déjà',
                'step' => 4,
                'next_step' => '/api/setup/matieres',
                'data' => TypeFrais::all()
            ]);
        }

        $frais = [
            ['libelle' => 'Inscription', 'montant' => 75000, 'est_obligatoire' => true],
            ['libelle' => 'Scolarité - Primaire', 'montant' => 350000, 'est_obligatoire' => true],
            ['libelle' => 'Scolarité - Collège', 'montant' => 450000, 'est_obligatoire' => true],
            ['libelle' => 'Scolarité - Lycée', 'montant' => 550000, 'est_obligatoire' => true],
            ['libelle' => 'Frais technologiques', 'montant' => 15000, 'est_obligatoire' => true],
            ['libelle' => 'Parascolaire', 'montant' => 50000, 'est_obligatoire' => false],
            ['libelle' => 'Sports', 'montant' => 45000, 'est_obligatoire' => false],
            ['libelle' => 'Cantine', 'montant' => 75000, 'est_obligatoire' => false],
        ];

        $created = [];
        foreach ($frais as $f) {
            $created[] = TypeFrais::create($f);
        }

        return response()->json([
            'success' => true,
            'message' => count($created) . ' types de frais créés',
            'step' => 4,
            'next_step' => '/api/setup/matieres',
            'data' => $created
        ]);
    }

    public function getStatus()
    {
        $status = [
            'annee_scolaire' => [
                'exists' => AnneeScolaire::count() > 0,
                'active' => AnneeScolaire::where('statut', 'en_cours')->first(),
                'count' => AnneeScolaire::count()
            ],
            'niveaux' => [
                'exists' => Niveau::count() > 0,
                'count' => Niveau::count()
            ],
            'classes' => [
                'exists' => Classe::count() > 0,
                'count' => Classe::count()
            ],
            'frais' => [
                'exists' => TypeFrais::count() > 0,
                'count' => TypeFrais::count()
            ],
            'matieres' => [
                'exists' => Matieres::count() > 0,
                'count' => Matieres::count()
            ]
        ];

        if (!$status['annee_scolaire']['exists']) {
            $status['next_step'] = '/api/setup/annee-scolaire';
            $status['step'] = 1;
        } elseif (!$status['niveaux']['exists']) {
            $status['next_step'] = '/api/setup/niveaux';
            $status['step'] = 2;
        } elseif (!$status['classes']['exists']) {
            $status['next_step'] = '/api/setup/classes';
            $status['step'] = 3;
        } elseif (!$status['frais']['exists']) {
            $status['next_step'] = '/api/setup/frais';
            $status['step'] = 4;
        } elseif (!$status['matieres']['exists']) {
            $status['next_step'] = '/api/setup/matieres';
            $status['step'] = 5;
        } else {
            $status['next_step'] = null;
            $status['step'] = 6;
            $status['complete'] = true;
            $status['message'] = '✅ Installation complète !';
        }

        return response()->json([
            'success' => true,
            'status' => $status
        ]);
    }

    public function reset(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'confirmation' => 'required|string|in:RESET'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Confirmation requise. Envoyez "confirmation": "RESET"'
            ], 422);
        }

        Matieres::truncate();
        TypeFrais::truncate();
        Classe::truncate();
        Niveau::truncate();
        AnneeScolaire::truncate();

        return response()->json([
            'success' => true,
            'message' => 'Toutes les données ont été réinitialisées',
            'next_step' => '/api/setup/annee-scolaire',
            'step' => 1
        ]);
    }
}