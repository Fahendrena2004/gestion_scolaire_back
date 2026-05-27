<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Carbon\Carbon;
use App\Models\Paiement\PaiementMensuel;
use App\Models\Notification;

class GenerateMonthlyOverdueNotifications extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'notifications:generate-monthly-overdue';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Génère des notifications pour les paiements mensuels impayés du mois précédent';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $now = Carbon::now();
        $previous = $now->subMonth();
        $month = (int) $previous->format('n');
        $year = (int) $previous->format('Y');

        $this->info("Génération des notifications pour les paiements impayés du mois {$month}/{$year}");

        $paiements = PaiementMensuel::with('resume.inscription.eleve')
            ->where('annee', $year)
            ->where('mois', $month)
            ->whereNull('paiement_id')
            ->get();

        $count = 0;

        foreach ($paiements as $pm) {
            $resume = $pm->resume;
            $inscription = $resume ? $resume->inscription : null;
            $eleve = $inscription ? $inscription->eleve : null;

            $eleveNom = $eleve ? trim(($eleve->nom ?? '') . ' ' . ($eleve->prenom ?? '')) : 'Élève inconnu';
            $montant = number_format((float) ($pm->montant ?? 0), 0, ',', ' ');

            $message = "Retard de paiement pour {$eleveNom} — Mois {$month}/{$year} (Montant: {$montant} Ar)";

            // Avoid creating duplicate unread notifications with same message
            $exists = Notification::where('message', $message)
                ->whereNull('read_at')
                ->exists();

            if ($exists) {
                continue;
            }

            Notification::create([
                'titre' => 'Retard de Paiement',
                'message' => $message,
                'type' => 'warning',
                'user_id' => null,
                'link' => $inscription ? ("/caissier/paiement?student_id={$inscription->id}") : null,
            ]);

            $count++;
        }

        $this->info("Notifications créées: {$count}");

        return 0;
    }
}
