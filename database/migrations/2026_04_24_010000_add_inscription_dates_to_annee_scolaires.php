<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('annee_scolaires', function (Blueprint $table) {
            $table->date('date_debut_inscription')->nullable()->after('date_fin');
            $table->date('date_fin_inscription')->nullable()->after('date_debut_inscription');
        });
    }

    public function down(): void
    {
        Schema::table('annee_scolaires', function (Blueprint $table) {
            $table->dropColumn(['date_debut_inscription', 'date_fin_inscription']);
        });
    }
};
