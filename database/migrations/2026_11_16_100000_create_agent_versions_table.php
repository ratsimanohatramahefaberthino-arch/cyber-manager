<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('agent_versions', function (Blueprint $table) {
            $table->id();
            $table->string('version', 32)->unique();
            $table->string('fichier', 128);
            $table->string('sha256', 64);
            $table->unsignedBigInteger('taille')->default(0);
            $table->text('notes')->nullable();
            // brouillon | pilote | general | archivee
            $table->string('statut', 16)->default('brouillon');
            $table->json('pilotes')->nullable();
            $table->timestamp('publiee_pilote_at')->nullable();
            $table->timestamp('publiee_generale_at')->nullable();
            $table->timestamps();
        });

        Schema::create('agent_mises_a_jour', function (Blueprint $table) {
            $table->id();
            $table->foreignId('poste_id')->constrained('postes')->cascadeOnDelete();
            $table->string('version', 32);
            $table->boolean('succes');
            $table->string('message', 500)->nullable();
            $table->timestamps();

            $table->index(['poste_id', 'id']);
        });

        // Reprise de l'ancien manifest.json (version 2.0.0 déjà déposée)
        try {
            $chemin = storage_path('app/private/agent-packages/manifest.json');
            if (is_file($chemin)) {
                $m = json_decode((string) file_get_contents($chemin), true);
                $zip = is_array($m) ? storage_path('app/private/agent-packages/' . ($m['fichier'] ?? '')) : '';
                if (is_array($m) && !empty($m['version']) && !empty($m['fichier']) && is_file($zip)) {
                    $pilotes = array_map('intval', $m['pilotes'] ?? []);
                    \DB::table('agent_versions')->insert([
                        'version'             => (string) $m['version'],
                        'fichier'             => (string) $m['fichier'],
                        'sha256'              => strtolower($m['sha256'] ?? hash_file('sha256', $zip)),
                        'taille'              => filesize($zip),
                        'notes'               => 'Importée depuis manifest.json',
                        'statut'              => !empty($m['general']) ? 'general' : ($pilotes ? 'pilote' : 'brouillon'),
                        'pilotes'             => $pilotes ? json_encode($pilotes) : null,
                        'publiee_generale_at' => !empty($m['general']) ? now() : null,
                        'publiee_pilote_at'   => $pilotes ? now() : null,
                        'created_at'          => now(),
                        'updated_at'          => now(),
                    ]);
                }
            }
        } catch (\Throwable) {
            // L'import est facultatif : on ne bloque jamais la migration.
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('agent_mises_a_jour');
        Schema::dropIfExists('agent_versions');
    }
};