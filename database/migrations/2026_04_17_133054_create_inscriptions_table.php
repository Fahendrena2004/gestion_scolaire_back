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
        Schema::create('inscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('id_eleve')
                  ->constrained('eleves')
                  ->onDelete('cascade')
                  ->onUpdate('cascade');

            $table->foreignId('id_classe')
                  ->constrained('classes')
                  ->onDelete('cascade')
                  ->onUpdate('cascade');

            $table->foreignId('id_annee_scolaire')
                  ->constrained('annee_scolaires')
                  ->onDelete('cascade')
                  ->onUpdate('cascade');

            $table->foreignId('utilisateur_id')
                  ->nullable()
                  ->constrained('utilisateurs')
                  ->onDelete('set null');

            $table->date('date_inscription');
            $table->boolean('parascolaire')->default(false);
            $table->boolean('cantine')->default(false);

            $table->decimal('montant_total', 10, 2)->default(0);
            $table->decimal('montant_net', 10, 2)->default(0);

            $table->timestamps();

            $table->unique(['id_eleve', 'id_annee_scolaire'], 'unique_inscription_par_an');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('inscriptions');
    }
};
