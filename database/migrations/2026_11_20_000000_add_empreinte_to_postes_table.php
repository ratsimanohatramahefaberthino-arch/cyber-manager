<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('postes', function (Blueprint $table) {
            $table->string('empreinte_uuid', 40)->nullable();   // UUID SMBIOS (jamais un secret)
            $table->text('empreinte_macs')->nullable();         // JSON : MAC des cartes physiques
        });
    }

    public function down(): void
    {
        Schema::table('postes', function (Blueprint $table) {
            $table->dropColumn(['empreinte_uuid', 'empreinte_macs']);
        });
    }
};