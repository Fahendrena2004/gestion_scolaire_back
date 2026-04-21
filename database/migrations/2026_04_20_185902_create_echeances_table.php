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
        Schema::create('echeances', function (Blueprint $table) {
            $table->id();

            $table->foreignId('inscription_id')->constrained('inscriptions')->onDelete('cascade');
            $table->foreignId('type_frais_id')->constrained('type_frais');
            $table->string('libelle', 150);
            $table->decimal('montant', 10, 2);
            $table->integer('mois')->nullable()->comment('1-12 pour mensuel, null pour unique');
            $table->integer('annee');
            $table->date('date_echeance');
            $table->enum('statut', ['impaye', 'partiel', 'paye'])->default('impaye');
            $table->decimal('montant_paye', 10, 2)->default(0);
            $table->decimal('montant_restant', 10, 2);
            $table->boolean('a_details')->default(false)->comment('true pour cantine, false pour ecolage');
            $table->timestamps();

            $table->index(['inscription_id', 'statut']);
            $table->index('date_echeance');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('echeances');
    }
};
