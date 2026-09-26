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
        Schema::create('frais_appliques', function (Blueprint $table) {
            $table->id();
            $table->foreignId('id_frais')
                    ->constrained('type_frais')
                    ->onDelete('cascade')
                    ->onUpdate('cascade');

            
            $table->decimal('montant', 10, 2)->default(0);
            $table->foreignId('id_inscription')
                    ->constrained('inscriptions')
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
        Schema::dropIfExists('frais_appliques');
    }
};
