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
        Schema::create('notes', function (Blueprint $table) {
            $table->id();
            $table->decimal('valeur', 10, 2);
            $table->string('periode', 50);
            $table->date('date');
            $table->string('type', 50);


            $table->foreignId('matiere_id')
                    ->constrained('matieres')
                    ->onDelete('cascade')
                    ->onUpdate('cascade');

            $table->foreignId('inscription_id')
                    ->constrained('inscriptions')
                    ->onDelete('cascade')
                    ->onUpdate('cascade');
            $table->string('appreciation', 255)->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('notes');
    }
};
