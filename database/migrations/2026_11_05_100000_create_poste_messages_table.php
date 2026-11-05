<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('poste_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('poste_id')->constrained('postes')->cascadeOnDelete();
            $table->string('sens', 10);            // admin | client
            $table->string('contenu', 500);
            $table->timestamp('lu_at')->nullable();
            $table->timestamps();

            $table->index(['poste_id', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('poste_messages');
    }
};