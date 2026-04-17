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
        Schema::create('autre_information', function (Blueprint $table) {
            $table->id();
            $table->foreignId('id_eleve')
                  ->constrained('eleves')
                  ->onDelete('cascade')
                  ->onUpdate('cascade');

            $table->string('nom_champ',100);
            $table->text('valeur_champ')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('autre_information');
    }
};
