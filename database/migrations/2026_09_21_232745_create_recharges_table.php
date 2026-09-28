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
        Schema::create('recharges', function (Blueprint $table) {
    $table->id();

    $table->foreignId('session_id')
        ->constrained('cyber_sessions')
        ->cascadeOnDelete();

    $table->dateTime('date_heure');

    $table->unsignedInteger('montant');

    $table->unsignedInteger('duree_ajoutee');

    $table->text('description')->nullable();

    $table->timestamps();
});
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('recharges');
    }
};
