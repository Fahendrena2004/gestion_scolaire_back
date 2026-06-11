<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Inscription\Paiement;
use App\Models\Inscription\TypeFrais;
use App\Models\Inscription\Inscription;
use Carbon\Carbon;

$paiements = Paiement::where('type', 'avance')->whereNull('type_frais_id')->get();
foreach($paiements as $p) {
  $inscription = Inscription::with('classe.niveau')->find($p->inscription_id);
  if (!$inscription) continue;

  $cycle = $inscription->classe?->niveau?->cycle ?? '';
  $libelle = match ($cycle) {
      'primaire' => 'Scolarité - Primaire',
      'college' => 'Scolarité - Collège',
      'lycee' => 'Scolarité - Lycée',
      default => 'Scolarité',
  };

  $typeScolariteId = $inscription->fraisAppliques()
      ->whereHas('typeFrais', function ($query) use ($libelle) {
          $query->where('libelle', 'like', '%' . $libelle . '%');
      })
      ->value('id_frais');

  if (!$typeScolariteId) {
      $base = trim(str_replace(['Scolarité', '-'], '', $libelle));
      $typeScolariteId = $inscription->fraisAppliques()
          ->whereHas('typeFrais', function ($query) use ($base) {
              $query->where('libelle', 'like', '%colage%')
                    ->orWhere('libelle', 'like', '%scolarit%');
          })
          ->value('id_frais');
  }

  if ($typeScolariteId) {
    $annee = $inscription->anneeScolaire;
    $dateDebut = $annee ? Carbon::parse($annee->date_debut)->startOfMonth() : Carbon::parse(date('Y') . '-09-01')->startOfMonth();

    $p->type = 'scolarite_mensuelle';
    $p->type_frais_id = $typeScolariteId;
    $p->details = ['mois' => $dateDebut->month, 'annee' => $dateDebut->year];
    $p->save();
    echo "Fixed Paiement ID {$p->id} linked to Scolarite Month {$dateDebut->month}\n";
  }
}
echo "Done\n";
