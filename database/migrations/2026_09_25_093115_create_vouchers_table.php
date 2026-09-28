<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vouchers', function (Blueprint $table) {
            $table->id();

            $table->foreignId('lot_voucher_id')
                ->nullable()
                ->constrained('lot_vouchers')
                ->nullOnDelete();

            /*
             * Identifiants réellement utilisés par le HotSpot MikroTik.
             */
            $table->string('username')->unique();

            $table->string('password');

            /*
             * Code affichable/imprimable pour le client.
             * Dans notre fonctionnement, il peut correspondre
             * au password/code HotSpot.
             */
            $table->string('code')->unique();

            $table->unsignedInteger('montant');

            $table->unsignedInteger('duree');

            /*
             * disponible
             * utilise
             * expire
             * annule
             */
            $table->string('etat')->default('disponible');

            /*
             * cyber_manager = créé par Cyber Manager
             * mikrotik = récupéré depuis MikroTik/Mikhmon
             * manuel = créé manuellement
             */
            $table->string('source')->default('cyber_manager');

            /*
             * Identifiant du compte HotSpot sur MikroTik
             * lorsqu'il est synchronisé.
             */
            $table->string('mikrotik_id')->nullable();

            $table->dateTime('date_heure_creation');

            $table->dateTime('date_heure_utilisation')->nullable();

            $table->dateTime('date_heure_expiration')->nullable();

            $table->dateTime('derniere_synchronisation')->nullable();

            $table->text('description')->nullable();

            $table->timestamps();

            $table->index('etat');
            $table->index('username');
            $table->index('source');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vouchers');
    }
};