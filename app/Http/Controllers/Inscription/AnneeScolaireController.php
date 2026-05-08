<?php

namespace App\Http\Controllers\Inscription;

use App\Http\Controllers\Controller;
use App\Models\Inscription\AnneeScolaire;
use App\Models\Inscription\CalendrierScolaire;
use App\Models\Inscription\Classe;
use App\Models\Inscription\TypeFrais;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class AnneeScolaireController extends Controller
{
    public function index()
    {
        $anneesScolaires = AnneeScolaire::withCount('classes')
            ->withSum('classes as effectif_total', 'effectif')
            ->withCount(['typeFrais as frais_count', 'calendrierScolaire as evenements_count'])
            ->latest('date_debut')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $anneesScolaires,
        ]);
    }

    public function store(Request $request)
    {
        // Normaliser le statut "actif" vers "en_cours" pour la compatibilité
        if ($request->statut === 'actif') {
            $request->merge(['statut' => 'en_cours']);
        }

        $validator = $this->validator($request);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        DB::beginTransaction();

        try {
            if ($request->statut === 'en_cours') {
                AnneeScolaire::where('statut', 'en_cours')->update(['statut' => 'termine']);
            }

            $annee = AnneeScolaire::create($request->only(['date_debut', 'date_fin', 'statut', 'date_debut_inscription', 'date_fin_inscription']));

            $this->synchroniserClasses($annee, $request->input('classes', []));
            $this->remplacerCalendrier($annee, $request->input('calendrier', []));
            $this->upsertFrais($annee, $request->input('frais', []));

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Annee scolaire creee',
                'data' => $this->chargerConfiguration($annee->id),
            ], 201);
        } catch (\Throwable $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la creation de l annee scolaire',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function show($id)
    {
        try {
            $annee = $this->chargerConfiguration($id);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Année scolaire non trouvée',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $annee,
        ]);
    }

    public function update(Request $request, $id)
    {
        // Normaliser le statut "actif" vers "en_cours" pour la compatibilité
        if ($request->statut === 'actif') {
            $request->merge(['statut' => 'en_cours']);
        }

        $annee = AnneeScolaire::findOrFail($id);
        $validator = $this->validator($request, true);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        DB::beginTransaction();

        try {
            if ($request->statut === 'en_cours') {
                AnneeScolaire::where('statut', 'en_cours')
                    ->where('id', '!=', $id)
                    ->update(['statut' => 'termine']);
            }

            $annee->update($request->only(['date_debut', 'date_fin', 'statut', 'date_debut_inscription', 'date_fin_inscription']));

            if ($request->has('classes')) {
                $this->synchroniserClasses($annee, $request->input('classes', []));
            }

            if ($request->has('calendrier')) {
                $this->remplacerCalendrier($annee, $request->input('calendrier', []));
            }

            if ($request->has('frais')) {
                $this->upsertFrais($annee, $request->input('frais', []));
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Annee scolaire mise a jour',
                'data' => $this->chargerConfiguration($annee->id),
            ]);
        } catch (\Throwable $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la mise a jour de l annee scolaire',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function destroy($id)
    {
        $annee = AnneeScolaire::findOrFail($id);
        $annee->delete();

        return response()->json([
            'success' => true,
            'message' => 'Annee scolaire supprimee',
        ]);
    }

    public function getActive()
    {
        $annee = AnneeScolaire::where('statut', 'en_cours')->first();

        if (!$annee) {
            return response()->json([
                'success' => false,
                'message' => 'Inscription non trouvée (aucune année scolaire active)',
            ], 404);
        }

        $config = $this->chargerConfiguration($annee->id);

        return response()->json([
            'success' => true,
            'message' => $config->est_inscription_ouverte
                ? 'Année scolaire active trouvée'
                : 'Année scolaire active trouvée, mais la période d inscription est fermée',
            'data' => $config,
        ]);
    }

    private function validator(Request $request, bool $isUpdate = false)
    {
        $required = $isUpdate ? 'sometimes' : 'required';

        return Validator::make($request->all(), [
            'date_debut'              => [$required, 'date'],
            'date_fin'                => [$required, 'date', 'after:date_debut'],
            'statut'                  => [$required, 'in:en_cours,termine,planifie'],
            'date_debut_inscription'  => 'nullable|date',
            'date_fin_inscription'    => 'nullable|date|after_or_equal:date_debut_inscription',
            'classes'                 => 'nullable|array',
            'classes.*.id'            => 'required|exists:classes,id',
            'classes.*.effectif'      => 'nullable|integer|min:0',
            'classes.*.max_effectif'  => 'nullable|integer|min:1|max:200',
            'calendrier'              => 'nullable|array',
            'calendrier.*.type'       => 'required|in:examen,vacance,autre',
            'calendrier.*.trimestre'  => 'nullable|in:T1,T2,T3',
            'calendrier.*.titre'      => 'required|string|max:150',
            'calendrier.*.date_debut' => 'required|date',
            'calendrier.*.date_fin'   => 'required|date',
            'calendrier.*.date_examen' => 'nullable|date',
            'calendrier.*.description' => 'nullable|string',
            'frais'                   => 'nullable|array',
            'frais.*.id'              => 'nullable|exists:type_frais,id',
            'frais.*.libelle'         => 'required|string|max:100',
            'frais.*.montant'         => 'required|numeric|min:0',
            'frais.*.est_obligatoire' => 'required|boolean',
        ]);
    }

    private function synchroniserClasses(AnneeScolaire $annee, array $classes): void
    {
        foreach ($classes as $data) {
            $classeExistante = Classe::find($data['id']);

            if (!$classeExistante) {
                continue;
            }

            // Au lieu de déplacer la classe (ce qui détruit l'historique), on la duplique pour la nouvelle année
            Classe::create([
                'nom_classe'       => $classeExistante->nom_classe,
                'niveau_id'        => $classeExistante->niveau_id,
                'code_division'    => $classeExistante->code_division,
                'max_effectif'     => $data['max_effectif'] ?? $classeExistante->max_effectif,
                'anneeScolaire_id' => $annee->id,
                'effectif'         => 0, // On remet l'effectif à 0 pour la nouvelle année
            ]);
        }
    }

    private function remplacerCalendrier(AnneeScolaire $annee, array $calendrier): void
    {
        CalendrierScolaire::where('annee_scolaire_id', $annee->id)->delete();

        foreach ($calendrier as $evenement) {
            if ($evenement['date_fin'] < $evenement['date_debut']) {
                throw new \InvalidArgumentException('La date de fin d un evenement du calendrier doit etre superieure ou egale a la date de debut.');
            }

            CalendrierScolaire::create([
                'annee_scolaire_id' => $annee->id,
                'type' => $evenement['type'],
                'titre' => $evenement['titre'],
                'date_debut' => $evenement['date_debut'],
                'date_fin' => $evenement['date_fin'],
                'description' => $evenement['description'] ?? null,
            ]);
        }
    }

    private function upsertFrais(AnneeScolaire $annee, array $frais): void
    {
        foreach ($frais as $ligne) {
            $attributes = [
                'annee_scolaire_id' => $annee->id,
                'libelle' => $ligne['libelle'],
            ];

            if (!empty($ligne['id'])) {
                $fraisExistant = TypeFrais::where('annee_scolaire_id', $annee->id)
                    ->where('id', $ligne['id'])
                    ->first();

                if ($fraisExistant) {
                    $fraisExistant->update([
                        'libelle' => $ligne['libelle'],
                        'montant' => $ligne['montant'],
                        'est_obligatoire' => $ligne['est_obligatoire'],
                    ]);

                    continue;
                }
            }

            TypeFrais::updateOrCreate($attributes, [
                'montant' => $ligne['montant'],
                'est_obligatoire' => $ligne['est_obligatoire'],
            ]);
        }
    }

    private function chargerConfiguration(int $id): AnneeScolaire
    {
        return AnneeScolaire::with([
            'classes.niveau',
            'typeFrais',
            'calendrierScolaire',
        ])
            ->withCount('classes')
            ->withSum('classes as effectif_total', 'effectif')
            ->findOrFail($id);
    }
}
