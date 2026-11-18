<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('verification_code', 6)->nullable()->after('email_verified_at');
            $table->timestamp('verification_code_expires_at')->nullable()->after('verification_code');
            $table->string('reset_password_code', 6)->nullable()->after('verification_code_expires_at');
            $table->timestamp('reset_password_code_expires_at')->nullable()->after('reset_password_code');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'verification_code', 'verification_code_expires_at',
                'reset_password_code', 'reset_password_code_expires_at'
            ]);
        });
    }
};