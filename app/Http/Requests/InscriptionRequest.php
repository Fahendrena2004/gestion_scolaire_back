<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use App\Models\Inscription\Classe;

class InscriptionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    // Définir les règles de validation pour les champs d'inscription, en incluant des règles spécifiques pour les champs liés à l'inscription et en vérifiant la cohérence entre le niveau et la classe sélectionnés
    public function rules(): array
    {
        return [
            'nom' => 'required|string|max:100',
            'prenom' => 'nullable|string|max:100',
            'date_naissance' => 'required|date|before:today',
            'lieu_naissance' => 'required|string|max:150',
            'sexe' => 'required|in:M,F',
            'adresse' => 'nullable|string',
            'cycle' => 'required|string|in:primaire,college,lycee',
            'niveau_id' => 'required|exists:niveaux,id',
            'classe_id' => [
                'required',
                'exists:classes,id',
                function ($attribute, $value, $fail) {
                    $classe = Classe::find($value);
                    if ($classe && $classe->niveau_id != $this->niveau_id) {
                        $fail('La classe ne correspond pas au niveau sélectionné.');
                    }
                },
            ],
            'parascolaire' => 'boolean',
            'cantine' => 'boolean',
            'montant_verse' => 'nullable|numeric|min:0',
        ];
    }

    // Récupérer les champs fixes liés à l'élève, les champs d'inscription liés à la classe et aux options, et les champs dynamiques supplémentaires qui peuvent être ajoutés lors de l'inscription
    public function getFixedFields(): array
    {
        return [
            'nom' => $this->nom,
            'prenom' => $this->prenom,
            'date_naissance' => $this->date_naissance,
            'lieu_naissance' => $this->lieu_naissance,
            'sexe' => $this->sexe,
            'adresse' => $this->adresse,
        ];
    }

    // Récupérer les champs liés à l'inscription, notamment la classe sélectionnée et les options parascolaire et cantine, qui peuvent influencer le calcul des frais d'inscription
    public function getInscriptionFields(): array
    {
        return [
            'classe_id' => $this->classe_id,
            'parascolaire' => $this->parascolaire ?? false,
            'cantine' => $this->cantine ?? false,
            'montant_verse' => $this->montant_verse ?? 0, 
        ];
    }


    // Récupérer les champs dynamiques supplémentaires qui ne font pas partie des champs fixes ou d'inscription, permettant ainsi de stocker des informations personnalisées pour chaque élève lors de l'inscription
    public function getDynamicFields(): array
    {

        // Les champs fixes et d'inscription qui sont déjà définis et ne doivent pas être inclus dans les champs dynamiques
        $fixedFields = [
            'nom', 'prenom', 'date_naissance', 'lieu_naissance',
            'sexe', 'adresse', 'cycle', 'niveau_id', 'classe_id',
            'parascolaire', 'cantine', 'montant_verse'
        ];

        $allFields = $this->all();
        $dynamicFields = [];

        // Parcourir tous les champs de la requête et ajouter ceux qui ne font pas partie des champs fixes ou d'inscription aux champs dynamiques, en vérifiant que les valeurs ne sont pas nulles ou vides
        foreach ($allFields as $key => $value) {
            if (!in_array($key, $fixedFields) && !is_null($value) && $value !== '') {
                $dynamicFields[$key] = $value;
            }
        }

        return $dynamicFields;
    }


    // Définir les messages d'erreur personnalisés pour les règles de validation, afin de fournir des retours clairs et spécifiques à l'utilisateur en cas de données manquantes ou incorrectes lors de l'inscription
    public function messages(): array
    {
        return [
            'nom.required' => 'Le nom est obligatoire',
            'prenom.required' => 'Le prénom est obligatoire',
            'date_naissance.required' => 'La date de naissance est obligatoire',
            'lieu_naissance.required' => 'Le lieu de naissance est obligatoire',
            'sexe.required' => 'Le sexe est obligatoire',
            'cycle.required' => 'Le cycle est obligatoire',
            'niveau_id.required' => 'Le niveau est obligatoire',
            'classe_id.required' => 'La classe est obligatoire',
        ];
    }
}