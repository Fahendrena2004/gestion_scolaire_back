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
        Schema::create('sorties', function (Blueprint $table) {
            $table->id();
            $table->string('reference')->unique();
            $table->decimal('montant', 15, 2);
            $table->date('date_sortie');
            $table->foreignId('type_sortie_id')->constrained('categories_sortie');
            $table->foreignId('staff_id')->nullable()->constrained('staffs')->onDelete('set null');
            $table->enum('statut', ['brouillon', 'valide', 'paye'])->default('brouillon');
            $table->foreignId('annee_scolaire_id')->constrained('annee_scolaires');
            $table->string('description')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('utilisateurs');
            $table->foreignId('validated_by')->nullable()->constrained('utilisateurs');
            $table->foreignId('paid_by')->nullable()->constrained('utilisateurs');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sorties');
    }
};
