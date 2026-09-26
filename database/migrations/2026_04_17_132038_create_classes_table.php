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
        Schema::create('classes', function (Blueprint $table) {
            $table->id();
            $table->string('nom_classe',50);

            $table->foreignId('niveau_id')
                  ->constrained('niveaux')
                  ->onDelete('cascade')
                  ->onUpdate('cascade');

            $table->string('code_division',5)->nullable();

            $table->integer('effectif')->default(0);

            $table->foreignId('anneeScolaire_id')
                  ->constrained('annee_scolaires')
                  ->onDelete('cascade')
                  ->onUpdate('cascade');


            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('classes');
    }
};
