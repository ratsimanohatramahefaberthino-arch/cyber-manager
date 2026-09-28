<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('postes', function (Blueprint $table) {
            $table->string('agent_url')->nullable()->after('derniere_communication');
        });
    }

    public function down(): void
    {
        Schema::table('postes', function (Blueprint $table) {
            $table->dropColumn('agent_url');
        });
    }
};