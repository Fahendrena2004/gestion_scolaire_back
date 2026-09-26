<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('calendrier_scolaires', function (Blueprint $table) {
            $table->enum('trimestre', ['T1', 'T2', 'T3'])->nullable()->after('type');
            $table->date('date_examen')->nullable()->after('date_fin');
        });
    }

    public function down(): void
    {
        Schema::table('calendrier_scolaires', function (Blueprint $table) {
            $table->dropColumn(['trimestre', 'date_examen']);
        });
    }
};
