<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Inscription\Paiement;
use App\Models\Paiement\PaiementMensuel;
use App\Models\Paiement\Recu;

$paiements = Paiement::where('type', 'scolarite_mensuelle')->where('montant', '<', 5000)->get();
$grouped = $paiements->groupBy('inscription_id');

foreach ($grouped as $inscriptionId => $payments) {
    // Group by exact date/time
    $byDate = $payments->groupBy('date_paiement');

    foreach ($byDate as $date => $txs) {
        if ($txs->count() > 1) {
            $totalMontant = $txs->sum('montant');

            // Keep the first one, update it to the total amount
            $first = $txs->first();
            $first->montant = $totalMontant;

            // It should be linked to the first month in the details array, let's keep it as is
            $first->save();

            if ($first->recu) {
                $first->recu->montant = $totalMontant;
                $first->recu->save();
            }

            echo "Merged " . $txs->count() . " payments into Paiement ID {$first->id} for amount {$totalMontant}\n";

            // Delete the rest
            $toDelete = $txs->slice(1);
            foreach ($toDelete as $p) {
                if ($p->recu) $p->recu->delete();
                PaiementMensuel::where('paiement_id', $p->id)->delete();
                $p->delete();
            }
        }
    }
}
echo "Done\n";
