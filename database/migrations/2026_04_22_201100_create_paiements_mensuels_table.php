<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('paiements_mensuels', function (Blueprint $table) {
            $table->id();
            $table->foreignId('resume_id')->constrained('resume_paiements')->cascadeOnDelete();
            $table->unsignedTinyInteger('mois');
            $table->unsignedSmallInteger('annee');
            $table->decimal('montant', 12, 2);
            $table->foreignId('paiement_id')->nullable()->constrained('paiements')->nullOnDelete();
            $table->timestamps();

            $table->unique(['resume_id', 'mois', 'annee']);
            $table->index(['annee', 'mois']);
            $table->index(['resume_id', 'paiement_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('paiements_mensuels');
    }
};
