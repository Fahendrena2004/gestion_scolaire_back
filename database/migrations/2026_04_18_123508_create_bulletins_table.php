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
        Schema::create('bulletins', function (Blueprint $table) {
            $table->id();
            $table->string('periode');
            $table->decimal('moyenne_eleve', 10, 2);
            $table->decimal('moyenne_classe', 10, 2);
            $table->integer('rang');

            $table->foreignId('id_annee_scolaire')
                    ->constrained('annee_scolaires')
                    ->onDelete('cascade')
                    ->onUpdate('cascade');

            $table->foreignId('id_eleve')
                    ->constrained('eleves')
                    ->onDelete('cascade')
                    ->onUpdate('cascade');

            $table->foreignId('id_classe')
                    ->constrained('classes')
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
        Schema::dropIfExists('bulletins');
    }
};
