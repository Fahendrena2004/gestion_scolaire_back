<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('type_frais', function (Blueprint $table) {
            $table->foreignId('annee_scolaire_id')
                ->nullable()
                ->after('id')
                ->constrained('annee_scolaires')
                ->nullOnDelete();

            $table->index(['annee_scolaire_id', 'libelle'], 'type_frais_annee_libelle_index');
        });
    }

    public function down(): void
    {
        Schema::table('type_frais', function (Blueprint $table) {
            $table->dropIndex('type_frais_annee_libelle_index');
            $table->dropConstrainedForeignId('annee_scolaire_id');
        });
    }
};
