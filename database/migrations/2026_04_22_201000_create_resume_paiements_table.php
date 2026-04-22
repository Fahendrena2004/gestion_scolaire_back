<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('resume_paiements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('inscription_id')->unique()->constrained('inscriptions')->cascadeOnDelete();
            $table->decimal('total_du', 12, 2)->default(0);
            $table->decimal('total_paye', 12, 2)->default(0);
            $table->decimal('total_restant', 12, 2)->default(0);
            $table->timestamps();

            $table->index(['total_restant', 'total_paye']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('resume_paiements');
    }
};
