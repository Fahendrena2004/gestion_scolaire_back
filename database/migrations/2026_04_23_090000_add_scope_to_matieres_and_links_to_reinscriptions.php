<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('matieres', 'classe_id')) {
            Schema::table('matieres', function (Blueprint $table) {
                $table->foreignId('classe_id')
                    ->nullable()
                    ->after('coefficient')
                    ->constrained('classes')
                    ->nullOnDelete();
            });
        }

        Schema::table('matieres', function (Blueprint $table) {
            $table->unique(['classe_id', 'nom'], 'matieres_classe_nom_unique');
        });

        Schema::table('reinscriptions', function (Blueprint $table) {
            $table->string('statut', 20)->default('Passant')->after('classe_id');
            $table->foreignId('nouvelle_inscription_id')
                ->nullable()
                ->after('inscription_id')
                ->constrained('inscriptions')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('reinscriptions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('nouvelle_inscription_id');
            $table->dropColumn('statut');
        });

        Schema::table('matieres', function (Blueprint $table) {
            $table->dropUnique('matieres_classe_nom_unique');
        });

        if (Schema::hasColumn('matieres', 'classe_id')) {
            Schema::table('matieres', function (Blueprint $table) {
                $table->dropConstrainedForeignId('classe_id');
            });
        }
    }
};
