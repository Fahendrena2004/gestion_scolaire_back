<?php
$file = 'app/Http/Controllers/Paiements/ScolariteController.php';
$content = file_get_contents($file);

// Replace addMonths
$content = preg_replace('/if \(\$dateDebut->diffInMonths\(\$dateFin\) < 5\) \{\s*\$dateFin = \$dateDebut->copy\(\)->addMonths\(9\);\s*\}/s', '', $content);

// Find the start
$start = preg_match('/private function getTypeFraisScolarit.*?Id/s', $content, $matches, PREG_OFFSET_CAPTURE);
if ($start) {
    $start_pos = $matches[0][1];
    $end = strpos($content, 'return $id;', $start_pos);
    $end = strpos($content, '}', $end) + 1;

    $newMethod = "private function getTypeFraisScolariteId(Inscription \$inscription): ?int
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

    $content = substr_replace($content, $newMethod, $start_pos, $end - $start_pos);
}

// Fix other typos
$content = preg_replace('/Scolarit.{1,3} non/u', 'Scolarité non', $content);
$content = preg_replace('/Paiement Scolarit.{1,3}/u', 'Paiement Scolarité', $content);
$content = preg_replace('/scolarit.{1,3} \(/u', 'scolarité (', $content);

file_put_contents($file, $content);
echo "Fixed!";
