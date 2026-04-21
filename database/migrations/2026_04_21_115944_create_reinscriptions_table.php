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
        Schema::create('reinscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('inscription_id')->constrained('inscriptions')->onDelete('restrict');
            $table->foreignId('eleve_id')->constrained('eleves')->onDelete('restrict');
            $table->foreignId('annee_scolaire_id')->constrained('annee_scolaires');
            $table->foreignId('classe_id')->constrained('classes');
            $table->decimal('montant_reinscription', 10, 2);
            $table->decimal('parascolaire', 10, 2)->default(0);
            $table->decimal('cantine', 10, 2)->default(0);
            $table->boolean('est_paye')->default(false);
            $table->date('date_reinscription');
            $table->foreignId('utilisateur_id')->constrained('utilisateurs');
            $table->timestamps();

            $table->index('eleve_id');
            $table->index('annee_scolaire_id');
            $table->unique(['eleve_id', 'annee_scolaire_id'], 'unique_reinscription');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('reinscriptions');
    }
};
