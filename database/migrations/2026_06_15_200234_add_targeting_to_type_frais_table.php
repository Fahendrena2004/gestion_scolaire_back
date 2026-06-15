<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('type_frais', function (Blueprint $table) {
            $table->enum('target_type', ['general', 'cycle', 'niveau'])->default('general')->after('annee_scolaire_id');
            $table->string('target_value', 100)->nullable()->after('target_type');
            $table->enum('frequence', ['unique', 'mensuel'])->default('unique')->after('est_obligatoire');
            $table->string('categorie', 50)->default('autre')->after('frequence');
            $table->integer('ordre_affichage')->default(0)->after('categorie');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('type_frais', function (Blueprint $table) {
            $table->dropColumn([
                'target_type',
                'target_value',
                'frequence',
                'categorie',
                'ordre_affichage'
            ]);
        });
    }
};
