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
        Schema::create('eleves', function (Blueprint $table) {
            $table->id();
            $table->string('nom',100);
            $table->string('prenom',100)->nullable();
            $table->date('date_naissance');
            $table->string('lieu_naissance',150);
            $table->enum('sexe', ['M', 'F']);
            $table->text('adresse')->nullable();
            $table->string('matricule',50)->unique();


            $table->timestamps();

        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('eleves');
    }
};
