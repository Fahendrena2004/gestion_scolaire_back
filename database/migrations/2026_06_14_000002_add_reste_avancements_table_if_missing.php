<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Create the table only if it does not already exist to avoid data loss.
        if (!Schema::hasTable('reste_avancements')) {
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
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Only drop the table if it exists to avoid accidental errors.
        if (Schema::hasTable('reste_avancements')) {
            Schema::dropIfExists('reste_avancements');
        }
    }
};
