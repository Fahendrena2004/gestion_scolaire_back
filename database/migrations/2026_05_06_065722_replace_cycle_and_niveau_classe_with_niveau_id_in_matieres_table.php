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
        Schema::table('matieres', function (Blueprint $table) {
            // Drop old columns if they exist
            if (Schema::hasColumn('matieres', 'cycle')) {
                $table->dropColumn('cycle');
            }
            if (Schema::hasColumn('matieres', 'niveau_classe')) {
                $table->dropColumn('niveau_classe');
            }
            
            // Add niveau_id foreign key
            $table->foreignId('niveau_id')->nullable()->constrained('niveaux')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('matieres', function (Blueprint $table) {
            $table->dropForeign(['niveau_id']);
            $table->dropColumn('niveau_id');
            $table->string('cycle')->nullable();
            $table->string('niveau_classe')->nullable();
        });
    }
};
