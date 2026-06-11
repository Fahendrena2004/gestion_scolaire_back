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
        Schema::table('notes', function (Blueprint $table) {
            $table->dropColumn(['valeur', 'type']);
            $table->decimal('interro1', 10, 2)->nullable();
            $table->decimal('interro2', 10, 2)->nullable();
            $table->decimal('examen', 10, 2)->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('notes', function (Blueprint $table) {
            $table->decimal('valeur', 10, 2)->nullable();
            $table->string('type', 50)->nullable();
            $table->dropColumn(['interro1', 'interro2', 'examen']);
        });
    }
};
