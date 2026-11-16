<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

/**
 * Écoute UDP : les postes demandent « qui est le serveur Cyber Manager ? » (broadcast),
 * on répond avec le port HTTP. L'adresse IP est celle de l'expéditeur de la réponse (côté poste).
 * Aucun accès à la base : ne tombe jamais en panne avec MySQL.
 */
class DecouverteServeur extends Command
{
    private const REQUETE = 'CYBER-MANAGER?1';

    protected $signature = 'cyber:decouverte {--http-port=8000 : Port HTTP du serveur} {--udp-port=47474 : Port UDP d\'écoute}';
    protected $description = 'Répond aux postes qui cherchent le serveur Cyber Manager sur le réseau local';

    public function handle(): int
    {
        $udp  = (int) $this->option('udp-port');
        $http = (int) $this->option('http-port');
        if ($udp < 1 || $udp > 65535 || $http < 1 || $http > 65535) {
            $this->error('Port invalide.');
            return self::FAILURE;
        }

        $socket = @stream_socket_server("udp://0.0.0.0:{$udp}", $errno, $errstr, STREAM_SERVER_BIND);
        if (!$socket) {
            $this->error("Port UDP {$udp} indisponible : {$errstr}");
            return self::FAILURE;
        }

        $reponse = json_encode(['cyber_manager' => true, 'port' => $http]);
        $this->info("Écoute UDP {$udp} : réponse « port HTTP {$http} ». Ctrl+C pour arrêter.");

        $compteurs = [];   // anti-saturation : 20 demandes / 10 s / adresse

        while (true) {
            $lire = [$socket];
            $ecrire = null;
            $except = null;
            $n = @stream_select($lire, $ecrire, $except, 30);

            if ($n === false) {
                usleep(500000);
                continue;
            }
            if ($n === 0) {
                $compteurs = [];
                continue;
            }

            $donnees = stream_socket_recvfrom($socket, 256, 0, $pair);
            if ($donnees === false || trim($donnees) !== self::REQUETE) {
                continue;
            }

            $ip  = explode(':', (string) $pair)[0];
            $now = time();
            [$debut, $nb] = $compteurs[$ip] ?? [$now, 0];
            if ($now - $debut > 10) {
                $debut = $now;
                $nb = 0;
            }
            $compteurs[$ip] = [$debut, ++$nb];
            if (count($compteurs) > 1000) {
                $compteurs = [];
            }
            if ($nb > 20) {
                continue;
            }

            @stream_socket_sendto($socket, $reponse, 0, $pair);
        }
    }
}