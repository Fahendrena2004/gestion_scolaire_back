<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('notes')) {
            // Supprime les doublons en conservant l'entrée la plus récente pour chaque trio
            $duplicates = DB::table('notes')
                ->select('inscription_id', 'matiere_id', 'periode', DB::raw('MAX(id) as keep_id'))
                ->groupBy('inscription_id', 'matiere_id', 'periode')
                ->havingRaw('COUNT(*) > 1')
                ->get();

            foreach ($duplicates as $duplicate) {
                DB::table('notes')
                    ->where('inscription_id', $duplicate->inscription_id)
                    ->where('matiere_id', $duplicate->matiere_id)
                    ->where('periode', $duplicate->periode)
                    ->where('id', '!=', $duplicate->keep_id)
                    ->delete();
            }

            Schema::table('notes', function (Blueprint $table) {
                $table->unique(
                    ['inscription_id', 'matiere_id', 'periode'],
                    'unique_note_eleve_matiere_periode'
                );
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('notes')) {
            Schema::table('notes', function (Blueprint $table) {
                $table->dropUnique('unique_note_eleve_matiere_periode');
            });
        }
    }
};
