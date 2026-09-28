<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('restitutions', function (Blueprint $table) {
            $table->id();

            $table->foreignId('session_id')
                ->constrained('cyber_sessions')
                ->cascadeOnDelete();

            $table->dateTime('date_heure');

            $table->unsignedInteger('montant');

            $table->string('motif');

            $table->text('description')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('restitutions');
    }
};