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
            $table->string('periode',10);
            $table->decimal('moyenne_eleve', 10, 2);
            $table->decimal('moyenne_classe', 10, 2);
            $table->integer('rang');
            $table->string('decision', 255);
            $table->string('appreciation', 255);

            $table->foreignId('inscription_id')
            ->constrained('inscriptions')
            ->onUpdate('cascade')
            ->onDelete('cascade');

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
