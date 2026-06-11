<?php
$file = 'app/Http/Controllers/Paiements/ScolariteController.php';
$content = file_get_contents($file);

// Replace addMonths
$content = preg_replace('/if \(\$dateDebut->diffInMonths\(\$dateFin\) < 5\) \{\s*\$dateFin = \$dateDebut->copy\(\)->addMonths\(9\);\s*\}/s', '', $content);

// Replace getTypeFraisScolariteId completely
$newMethod = "    private function getTypeFraisScolariteId(Inscription \$inscription): ?int
    {
        \$cycle = \$inscription->classe?->niveau?->cycle ?? '';
        \$libelle = match (\$cycle) {
            'primaire' => 'Scolarité - Primaire',
            'college' => 'Scolarité - Collège',
            'lycee' => 'Scolarité - Lycée',
            default => 'Scolarité',
        };

        \$id = \$inscription->fraisAppliques()->whereHas('typeFrais', function (\$query) use (\$libelle) {
            \$query->where('libelle', 'like', '%' . \$libelle . '%');
        })->value('id_frais');

        if (!\$id) {
            \$id = \$inscription->fraisAppliques()->whereHas('typeFrais', function (\$query) {
                \$query->where('libelle', 'like', '%scolarit%')->orWhere('libelle', 'like', '%ecolage%')->orWhere('libelle', 'like', '%mensualit%')->orWhere('libelle', 'like', '%mensuel%')->orWhere('libelle', 'like', '%pension%');
            })->value('id_frais');
        }

        if (!\$id) {
            \$id = \App\Models\Inscription\TypeFrais::where(function(\$query) {
                \$query->where('libelle', 'like', '%scolarit%')->orWhere('libelle', 'like', '%ecolage%')->orWhere('libelle', 'like', '%mensualit%')->orWhere('libelle', 'like', '%mensuel%')->orWhere('libelle', 'like', '%pension%');
            })->value('id');
        }

        return \$id;
    }";

$content = preg_replace('/private function getTypeFraisScolariteId.*?return \$id;\s*\}/s', $newMethod, $content);

file_put_contents($file, $content);
echo "Replaced";
