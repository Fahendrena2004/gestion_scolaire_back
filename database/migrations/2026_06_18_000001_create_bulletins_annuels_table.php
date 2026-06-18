<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bulletins_annuels', function (Blueprint $table) {
            $table->id();

            $table->foreignId('inscription_id')
                ->constrained('inscriptions')
                ->onUpdate('cascade')
                ->onDelete('cascade');

            // Moyennes des 3 trimestres (nullable si non disponibles)
            $table->decimal('moyenne_t1', 5, 2)->nullable();
            $table->decimal('moyenne_t2', 5, 2)->nullable();
            $table->decimal('moyenne_t3', 5, 2)->nullable();

            // Résultat annuel
            $table->decimal('moyenne_annuelle', 5, 2)->default(0);
            $table->integer('rang_annuel')->default(0);
            $table->decimal('moyenne_classe_annuelle', 5, 2)->default(0);

            // Décision : ADMIS | REDOUBLANT | NON_EVALUE
            $table->string('decision', 20)->default('NON_EVALUE');
            $table->string('appreciation', 255)->nullable();

            // Métadonnées
            $table->tinyInteger('nb_trimestres')->default(0);
            $table->boolean('est_complet')->default(false);

            $table->timestamps();

            // Un seul bulletin annuel par inscription
            $table->unique('inscription_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bulletins_annuels');
    }
};
