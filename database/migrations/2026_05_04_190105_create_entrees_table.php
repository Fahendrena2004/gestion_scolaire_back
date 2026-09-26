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
        Schema::create('entrees', function (Blueprint $table) {
            $table->id();
            $table->string('reference')->unique();
            $table->decimal('montant', 15, 2);
            $table->date('date_entree');
            $table->foreignId('type_entree_id')->constrained('categories_entree');
            $table->foreignId('inscription_id')->nullable()->constrained('inscriptions')->onDelete('set null');
            $table->foreignId('donneur_id')->nullable()->constrained('donneurs')->onDelete('set null');
            $table->foreignId('annee_scolaire_id')->constrained('annee_scolaires');
            $table->string('description')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('utilisateurs');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('entrees');
    }
};
