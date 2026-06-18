<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('detail_bulletins', function (Blueprint $table) {
            // rang_matiere already exists but make it nullable (an unranked student has no rank)
            $table->integer('rang_matiere')->nullable()->change();
            $table->string('appreciation', 255)->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('detail_bulletins', function (Blueprint $table) {
            $table->integer('rang_matiere')->nullable(false)->change();
        });
    }
};
