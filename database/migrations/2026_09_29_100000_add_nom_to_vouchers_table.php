<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vouchers', function (Blueprint $table) {
            $table->string('nom', 100)->nullable()->after('username');
        });

        // Rétro-remplissage : les permanents existants (seeder étape 1)
        // prennent leur username comme nom d'affichage.
        DB::table('vouchers')
            ->where('type_voucher', 'permanent')
            ->whereNull('nom')
            ->update(['nom' => DB::raw('username')]);
    }

    public function down(): void
    {
        Schema::table('vouchers', function (Blueprint $table) {
            $table->dropColumn('nom');
        });
    }
};