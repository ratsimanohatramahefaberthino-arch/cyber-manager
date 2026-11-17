<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('postes', function (Blueprint $table) {
            $table->string('internet_etat', 16)->nullable();   // autorise | coupe | erreur | non_gere
            $table->timestamp('internet_maj_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('postes', function (Blueprint $table) {
            $table->dropColumn(['internet_etat', 'internet_maj_at']);
        });
    }
};