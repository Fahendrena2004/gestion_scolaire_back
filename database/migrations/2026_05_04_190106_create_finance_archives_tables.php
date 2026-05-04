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
        Schema::create('entrees_archive', function (Blueprint $table) {
            $table->id();
            $table->string('reference');
            $table->decimal('montant', 15, 2);
            $table->date('date_entree');
            $table->string('type_entree_nom');
            $table->string('source_nom')->nullable(); // Nom de l'élève ou du donneur
            $table->string('annee_scolaire_libelle');
            $table->string('description')->nullable();
            $table->timestamps();
        });

        Schema::create('sorties_archive', function (Blueprint $table) {
            $table->id();
            $table->string('reference');
            $table->decimal('montant', 15, 2);
            $table->date('date_sortie');
            $table->string('type_sortie_nom');
            $table->string('beneficiaire_nom')->nullable(); // Nom du staff ou autre
            $table->string('annee_scolaire_libelle');
            $table->string('description')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('entrees_archive');
        Schema::dropIfExists('sorties_archive');
    }
};
