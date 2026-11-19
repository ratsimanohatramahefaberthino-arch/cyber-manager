<?php

namespace App\Console\Commands;

use App\Services\ReseauService;
use Illuminate\Console\Command;

/**
 * Écoute UDP : les postes demandent « qui est le serveur Cyber Manager ? » (broadcast),
 * on répond avec le port HTTP. L'adresse IP est celle de l'expéditeur de la réponse (côté poste).
 *
 * Sans --http-port (cas normal), le port est DÉTECTÉ : on cherche, sur ce PC, le serveur Cyber Manager qui répond
 * (signature /api/agent/decouverte), et on recommence toutes les 30 s. Si le port change, les postes suivent.
 * Pas d'accès à la base ici : seul le serveur web en a besoin. Tant qu'aucun port n'est détecté, on ne répond pas.
 */
class DecouverteServeur extends Command
{
    private const REQUETE = 'CYBER-MANAGER?1';
    private const PORTS_USUELS = [80, 8000, 8080, 8081, 8888];

    protected $signature = 'cyber:decouverte {--http-port=0 : Port HTTP du serveur (0 = détection automatique)} {--udp-port=47474 : Port UDP d\'écoute}';
    protected $description = 'Répond aux postes qui cherchent le serveur Cyber Manager sur le réseau local';

    public function handle(): int
    {
        $udp  = (int) $this->option('udp-port');
        $fixe = (int) $this->option('http-port');
        if ($udp < 1 || $udp > 65535 || $fixe < 0 || $fixe > 65535) {
            $this->error('Port invalide.');
            return self::FAILURE;
        }

        $socket = @stream_socket_server("udp://0.0.0.0:{$udp}", $errno, $errstr, STREAM_SERVER_BIND);
        if (!$socket) {
            $this->error("Port UDP {$udp} indisponible : {$errstr}");
            return self::FAILURE;
        }

        $port = $fixe;
        $prochaineDetection = 0;
        $attente = $fixe > 0 ? 30 : 5;

        $this->info($fixe > 0
            ? "Écoute UDP {$udp} : réponse « port HTTP {$fixe} » (port imposé). Ctrl+C pour arrêter."
            : "Écoute UDP {$udp} : port HTTP détecté automatiquement. Ctrl+C pour arrêter.");

        $compteurs = [];   // anti-saturation : 20 demandes / 10 s / adresse

        while (true) {
            if ($fixe === 0 && time() >= $prochaineDetection) {
                $trouve = $this->detecterPort();
                if ($trouve !== null && $trouve !== $port) {
                    $port = $trouve;
                    $this->info("Port HTTP détecté : {$port}");
                }
                $prochaineDetection = time() + ($port > 0 ? 30 : 5);
            }

            $lire = [$socket];
            $ecrire = null;
            $except = null;
            $n = @stream_select($lire, $ecrire, $except, $attente);

            if ($n === false) {
                usleep(500000);
                continue;
            }
            if ($n === 0) {
                $compteurs = [];
                continue;
            }

            $donnees = stream_socket_recvfrom($socket, 256, 0, $pair);
            if ($donnees === false || trim($donnees) !== self::REQUETE || $port === 0) {
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

            @stream_socket_sendto($socket, json_encode(['cyber_manager' => true, 'port' => $port]), 0, $pair);
        }
    }

    /** Premier port, sur ce PC, où répond le serveur Cyber Manager. */
    private function detecterPort(): ?int
    {
        $ports = [];
        $portApp = parse_url((string) config('app.url'), PHP_URL_PORT);
        if ($portApp) {
            $ports[] = (int) $portApp;
        }
        foreach (self::PORTS_USUELS as $p) {
            $ports[] = $p;
        }
        $ports = array_values(array_unique($ports));

        // « artisan serve --host=<IP> » n'écoute pas sur 127.0.0.1 : on essaie aussi les adresses de ce PC
        $hotes = array_merge(['127.0.0.1'], ReseauService::toutes());

        foreach ($ports as $p) {
            foreach ($hotes as $h) {
                if ($this->estCyberManager($h, $p)) {
                    return $p;
                }
            }
        }

        return null;
    }

    private function estCyberManager(string $hote, int $port): bool
    {
        $contexte = stream_context_create(['http' => ['timeout' => 2, 'ignore_errors' => true, 'header' => "Accept: application/json\r\n"]]);
        $corps = @file_get_contents("http://{$hote}:{$port}/api/agent/decouverte", false, $contexte);
        if ($corps === false || strlen($corps) > 4096) {
            return false;
        }
        $json = json_decode($corps, true);

        return is_array($json) && ($json['cyber_manager'] ?? false) === true;
    }
}