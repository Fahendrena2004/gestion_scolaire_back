<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('paiements', function (Blueprint $table) {
            if (!Schema::hasColumn('paiements', 'type_frais_id')) {
                $table->foreignId('type_frais_id')->nullable()->after('inscription_id')->constrained('type_frais')->nullOnDelete();
            }

            if (!Schema::hasColumn('paiements', 'type')) {
                $table->string('type', 50)->nullable()->after('reference');
            }

            if (!Schema::hasColumn('paiements', 'libelle')) {
                $table->string('libelle', 200)->nullable()->after('type');
            }

            if (!Schema::hasColumn('paiements', 'details')) {
                $table->json('details')->nullable()->after('libelle');
            }

            $table->index(['inscription_id', 'type']);
            $table->index(['type_frais_id', 'date_paiement']);
        });

        Schema::table('presences_cantine', function (Blueprint $table) {
            if (!Schema::hasColumn('presences_cantine', 'montant')) {
                $table->decimal('montant', 10, 2)->default(0)->after('date_presence');
            }

            $table->unique(['inscription_id', 'date_presence']);
            $table->index(['date_presence', 'est_paye']);
        });
    }

    public function down(): void
    {
        Schema::table('presences_cantine', function (Blueprint $table) {
            $table->dropUnique(['inscription_id', 'date_presence']);
            $table->dropIndex(['date_presence', 'est_paye']);

            if (Schema::hasColumn('presences_cantine', 'montant')) {
                $table->dropColumn('montant');
            }
        });

        Schema::table('paiements', function (Blueprint $table) {
            $table->dropIndex(['inscription_id', 'type']);
            $table->dropIndex(['type_frais_id', 'date_paiement']);

            if (Schema::hasColumn('paiements', 'type_frais_id')) {
                $table->dropConstrainedForeignId('type_frais_id');
            }

            foreach (['details', 'libelle', 'type'] as $column) {
                if (Schema::hasColumn('paiements', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
