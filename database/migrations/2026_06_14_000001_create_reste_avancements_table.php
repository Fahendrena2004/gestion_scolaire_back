<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('reste_avancements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('inscription_id')
                  ->constrained('inscriptions')
                  ->onDelete('cascade');
            $table->decimal('montant_rest', 12, 2)->default(0);
            $table->enum('statut', ['en_attente', 'partiel', 'regle'])->default('en_attente');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reste_avancements');
    }
};
