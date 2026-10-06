<?php

namespace App\Console\Commands;

use App\Services\MikroTikService;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class MikrotikUserTestCommand extends Command
{
    protected $signature = 'mikrotik:user-test
                            {--profile= : Profil HotSpot à utiliser (défaut: config)}
                            {--uptime=5m : limit-uptime du user de test}
                            {--keep : Ne pas supprimer l\'utilisateur à la fin}';

    protected $description = 'Teste création / recherche / suppression d\'un utilisateur HotSpot MikroTik';

    public function handle(MikroTikService $service): int
    {
        $prefix  = config('mikrotik.hotspot_user_prefix', 'cm-');
        $profile = $this->option('profile')
            ?: config('mikrotik.hotspot_profile', 'default');
        $uptime  = $this->option('uptime');

        $username = $prefix . 'TEST-' . strtoupper(Str::random(6));
        $password = strtoupper(Str::random(8));

        $this->info("Profil utilisé : {$profile}");
        $this->info("Création utilisateur HotSpot de test : {$username}");

        // Étape 1 : création
        try {
            $service->createHotspotUser(
                username:    $username,
                password:    $password,
                profile:     $profile,
                comment:     'Cyber Manager - test ' . now()->format('Y-m-d H:i:s'),
                limitUptime: $uptime,
            );
            $this->info('✓ Utilisateur créé');
        } catch (\Throwable $e) {
            $this->error('Échec création : ' . $e->getMessage());
            return self::FAILURE;
        }

        // Étape 2 : recherche
        $found = $service->findHotspotUser($username);
        if ($found === null) {
            $this->error('Échec : utilisateur introuvable après création');
            return self::FAILURE;
        }
        $this->info('✓ Utilisateur retrouvé (id: ' . ($found['.id'] ?? '?') . ')');
        $this->newLine();

        // Aperçu de quelques champs utiles
        $this->table(
            ['Champ', 'Valeur'],
            [
                ['name',         $found['name']         ?? '-'],
                ['profile',      $found['profile']      ?? '-'],
                ['limit-uptime', $found['limit-uptime'] ?? '-'],
                ['comment',      $found['comment']      ?? '-'],
                ['uptime',       $found['uptime']       ?? '-'],
            ]
        );

        if ($this->option('keep')) {
            $this->warn("Utilisateur conservé (--keep). Supprime-le manuellement quand tu veux.");
            return self::SUCCESS;
        }

        // Étape 3 : suppression
        try {
            $service->deleteHotspotUser($username);
            $this->info('✓ Utilisateur supprimé');
        } catch (\Throwable $e) {
            $this->error('Échec suppression : ' . $e->getMessage());
            return self::FAILURE;
        }

        $this->newLine();
        $this->info('Test complet réussi (création + recherche + suppression).');
        return self::SUCCESS;
    }
}