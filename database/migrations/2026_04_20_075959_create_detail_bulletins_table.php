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
        Schema::create('detail_bulletins', function (Blueprint $table) {
            $table->id();
            $table->decimal('moyenne_matiere', 10, 2);
            $table->integer('rang_matiere');
            $table->string('appreciation', 255);
            $table->foreignId('bulletin_id')
                    ->constrained('bulletins')
                    ->onDelete('cascade')
                    ->onUpdate('cascade');
                    
            $table->foreignId('matiere_id')
                    ->constrained('matieres')
                    ->onDelete('cascade')
                    ->onUpdate('cascade');
                    
                    
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('detail_bulletins');
    }
};
