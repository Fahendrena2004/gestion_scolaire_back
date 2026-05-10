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
        Schema::table('niveaux', function (Blueprint $output) {
            $output->string('serie')->nullable()->after('nom_niveau');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('niveaux', function (Blueprint $output) {
            $output->dropColumn('serie');
        });
    }
};
