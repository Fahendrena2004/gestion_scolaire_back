<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Inscription\FraisApplique;
use Carbon\Carbon;

$frais = FraisApplique::with(['typeFrais', 'inscription.anneeScolaire', 'inscription.resumePaiement'])->get();
foreach($frais as $f) {
  $libelle = str_replace(['é', 'è', 'ê'], 'e', strtolower($f->typeFrais?->libelle ?? ''));
  $isScolarite = str_contains($libelle, 'scolarit') || str_contains($libelle, 'ecolage') || str_contains($libelle, 'mensualit') || str_contains($libelle, 'mensuel') || str_contains($libelle, 'pension');

  if ($isScolarite) {
    $anneeScolaire = $f->inscription->anneeScolaire;
    if (!$anneeScolaire) continue;

    $dateDebut = Carbon::parse($anneeScolaire->date_debut)->startOfMonth();
    $dateFin = Carbon::parse($anneeScolaire->date_fin)->startOfMonth();
    $nbMois = max($dateDebut->diffInMonths($dateFin) + 1, 1);

    $montantBase = $f->typeFrais->montant;

    if ($f->montant == $montantBase && $nbMois > 1) {
      $diff = ($montantBase * $nbMois) - $f->montant;
      $f->montant = $montantBase * $nbMois;
      $f->save();

      if ($f->inscription) {
          $f->inscription->increment('montant_total', $diff);
          $f->inscription->increment('montant_net', $diff);
          if ($f->inscription->resumePaiement) {
              $f->inscription->resumePaiement->increment('total_du', $diff);
              $f->inscription->resumePaiement->increment('total_restant', $diff);
          }
      }
      echo "Fixed ID {$f->id}: added {$diff}\n";
    }
  }
}
echo "Done\n";
