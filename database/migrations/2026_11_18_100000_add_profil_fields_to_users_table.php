<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('telephone', 30)->nullable()->after('avatar');
            $table->text('notes_internes')->nullable()->after('telephone');
            $table->timestamp('last_login_at')->nullable()->after('notes_internes');
            $table->string('last_login_ip', 45)->nullable()->after('last_login_at');
            $table->timestamp('avatar_maj_at')->nullable()->after('last_login_ip');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['telephone', 'notes_internes', 'last_login_at', 'last_login_ip', 'avatar_maj_at']);
        });
    }
};