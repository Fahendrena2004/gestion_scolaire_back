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
        Schema::create('staffs', function (Blueprint $table) {
            $table->id();
            $table->string('nom', 50);
            $table->string('prenom', 50);
            $table->string('telephone', 20)->nullable();
            $table->string('matricule', 20)->unique();
            $table->string('email', 100)->unique();
            $table->string('fonction', 50);
            $table->decimal('salaire', 10, 2);
            $table->string('adresse', 75);
            $table->enum('sexe', ['masculin', 'feminin']);
            $table->date('date_naissance');
            $table->string('lieu_naissance', 50);
            $table->unsignedBigInteger('utilisateur_id')->nullable();
            $table->foreign('utilisateur_id')->references('id')->on('utilisateurs')->onDelete('set null');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('staff');
    }
};
