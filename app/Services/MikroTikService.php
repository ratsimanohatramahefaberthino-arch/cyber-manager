<?php

namespace App\Services;

use App\Services\MikroTik\MikrotikConnection;
use App\Models\CyberSetting;
use RuntimeException;

class MikroTikService
{
    private ?MikrotikConnection $connection = null;
    private bool $loggedIn = false;
    private array $config;

    public function __construct(array $config = [])
    {
        $this->config = [
            'host'     => $config['host']     ?? CyberSetting::reseau('mikrotik_host', env('MIKROTIK_HOST', '128.0.1.1')),
            'port'     => $config['port']     ?? (int) CyberSetting::reseau('mikrotik_port', env('MIKROTIK_PORT', '8728')),
            'user'     => $config['user']     ?? CyberSetting::reseau('mikrotik_user', env('MIKROTIK_USER', 'admin')),
            'password' => $config['password'] ?? CyberSetting::reseau('mikrotik_password', env('MIKROTIK_PASSWORD', '')),
            'ssl'      => $config['ssl']      ?? false,
            'timeout'  => $config['timeout']  ?? 5.0,
        ];
    }

    public function connection(): MikrotikConnection
    {
        if ($this->connection === null) {
            $this->connection = new MikrotikConnection(
                host:   $this->config['host'],
                port:   (int) $this->config['port'],
                timeout: (float) $this->config['timeout'],
                useSsl: (bool) $this->config['ssl'],
            );
        }
        return $this->connection;
    }

    public function login(): void
    {
        if ($this->loggedIn) return;

        $this->connection()->connect();
        $reply = $this->connection()->sendSentence([
            '/login',
            '=name='     . $this->config['user'],
            '=password=' . $this->config['password'],
        ]);

        if (!$this->replyHasDone($reply)) {
            $msg = $this->extractTrapMessage($reply) ?? 'Login MikroTik échoué';
            $this->disconnect();
            throw new RuntimeException($msg);
        }
        $this->loggedIn = true;
    }

    public function disconnect(): void
    {
        if ($this->connection !== null) {
            $this->connection->disconnect();
        }
        $this->connection = null;
        $this->loggedIn   = false;
    }

    public function testConnection(): array
    {
        $identity = $this->query('/system/identity/print');
        $resource = $this->query('/system/resource/print');

        return [
            'identity'   => $identity[0]['name']       ?? null,
            'version'    => $resource[0]['version']    ?? null,
            'board_name' => $resource[0]['board-name'] ?? null,
            'uptime'     => $resource[0]['uptime']     ?? null,
        ];
    }

    public function query(string $command, array $arguments = []): array
    {
        $words = [$command];
        foreach ($arguments as $key => $value) {
            $words[] = "={$key}={$value}";
        }
        return $this->send($words);
    }

    public function queryFiltered(string $command, array $filters = []): array
    {
        $words = [$command];
        foreach ($filters as $key => $value) {
            $words[] = "?{$key}={$value}";
        }
        return $this->send($words);
    }

    public function queryWithId(string $command, string $id, array $arguments = []): array
    {
        $words = [$command, "=.id={$id}"];
        foreach ($arguments as $key => $value) {
            $words[] = "={$key}={$value}";
        }
        return $this->send($words);
    }

    private function send(array $words): array
    {
        $this->login();
        $reply = $this->connection()->sendSentence($words);

        if ($this->replyHasTrap($reply)) {
            $msg = $this->extractTrapMessage($reply);
            $this->disconnect();
            throw new RuntimeException('MikroTik ' . ($words[0] ?? '?') . ' : ' . $msg);
        }

        $records = [];
        foreach ($reply as $item) {
            if ($item['type'] === '!re') {
                $records[] = $item['attributes'];
            }
        }
        $this->disconnect();
        return $records;
    }

    // -----------------------------------------------------------------
    // Méthodes existantes (Hotspot, DHCP, etc.)
    // -----------------------------------------------------------------
    
    public function listHotspotUsers(): array { return $this->query('/ip/hotspot/user/print'); }
    public function listHotspotActive(): array { return $this->query('/ip/hotspot/active/print'); }
    public function listHotspotProfiles(): array { return $this->query('/ip/hotspot/user/profile/print'); }
    public function listHotspotServers(): array { return $this->query('/ip/hotspot/print'); }

    public function createHotspotUser(string $username, string $password, ?string $profile = null, ?string $comment = null, ?string $limitUptime = null, ?string $server = null, ?string $limitBytesTotal = null): void
    {
        $profile ??= config('mikrotik.hotspot_profile', 'default');
        $args = ['name' => $username, 'password' => $password, 'profile' => $profile];
        if ($comment) $args['comment'] = $comment;
        if ($limitUptime) $args['limit-uptime'] = $limitUptime;
        if ($server && $server !== 'all') $args['server'] = $server;
        if ($limitBytesTotal) $args['limit-bytes-total'] = $limitBytesTotal;
        $this->query('/ip/hotspot/user/add', $args);
    }

    public function findHotspotUser(string $username): ?array
    {
        return $this->queryFiltered('/ip/hotspot/user/print', ['name' => $username])[0] ?? null;
    }

    public function deleteHotspotUser(string $username): bool
    {
        $user = $this->findHotspotUser($username);
        if ($user === null || !isset($user['.id'])) return false;
        $this->queryWithId('/ip/hotspot/user/remove', $user['.id']);
        return true;
    }

    public function listDhcpLeases(): array { return $this->query('/ip/dhcp-server/lease/print'); }
    public function listHotspotHosts(): array { return $this->query('/ip/hotspot/host/print'); }
    public function findHotspotActive(string $id): ?array { return $this->queryFiltered('/ip/hotspot/active/print', ['.id' => $id])[0] ?? null; }

    public function disconnectHotspotActiveByMac(string $mac): bool
    {
        $mac = strtoupper(str_replace('-', ':', trim($mac)));
        $rows = $this->queryFiltered('/ip/hotspot/active/print', ['mac-address' => $mac]);
        if (empty($rows) || !isset($rows[0]['.id'])) return false;
        $this->queryWithId('/ip/hotspot/active/remove', $rows[0]['.id']);
        return true;
    }

    public function trouverIpParMac(string $mac): ?string
    {
        $mac = strtoupper(str_replace('-', ':', trim($mac)));
        foreach ($this->queryFiltered('/ip/hotspot/host/print', ['mac-address' => $mac]) as $hote) {
            if (!empty($hote['address'])) return $hote['address'];
        }
        foreach ($this->queryFiltered('/ip/dhcp-server/lease/print', ['mac-address' => $mac]) as $bail) {
            $ip = $bail['active-address'] ?? $bail['address'] ?? null;
            if (!empty($ip)) return $ip;
        }
        return null;
    }

    public function connecterPosteHotspot(string $username, string $password, string $mac, ?string $ipIndice = null): bool
    {
        $mac = strtoupper(str_replace('-', ':', trim($mac)));
        $ip  = $this->trouverIpParMac($mac) ?? ($ipIndice !== '' ? $ipIndice : null);

        $actives = $this->queryFiltered('/ip/hotspot/active/print', ['mac-address' => $mac]);
        foreach ($actives as $ligne) {
            $memeCompte = ($ligne['user'] ?? null) === $username;
            $memeIp     = $ip === null || ($ligne['address'] ?? null) === $ip;
            if ($memeCompte && $memeIp) return true;
        }

        $this->retirerSessionsActives(['mac-address' => $mac]);
        $this->retirerSessionsActives(['user' => $username]);

        if ($ip === null) throw new RuntimeException("Adresse IP introuvable sur MikroTik pour la MAC {$mac}.");

        $this->query('/ip/hotspot/active/login', [
            'user'        => $username,
            'password'    => $password,
            'ip'          => $ip,
            'mac-address' => $mac,
        ]);
        return true;
    }

    public function deconnecterPosteHotspot(string $mac, ?string $username = null): bool
    {
        $mac = strtoupper(str_replace('-', ':', trim($mac)));
        $retirees = $this->retirerSessionsActives(['mac-address' => $mac]);
        if ($username) $retirees += $this->retirerSessionsActives(['user' => $username]);

        try {
            foreach ($this->queryFiltered('/ip/hotspot/cookie/print', ['mac-address' => $mac]) as $cookie) {
                if (isset($cookie['.id'])) $this->queryWithId('/ip/hotspot/cookie/remove', $cookie['.id']);
            }
        } catch (\Throwable) {}
        return $retirees > 0;
    }

    public function setHotspotUserDisabled(string $username, bool $disabled): bool
    {
        $user = $this->findHotspotUser($username);
        if ($user === null || !isset($user['.id'])) return false;
        $this->queryWithId('/ip/hotspot/user/set', $user['.id'], ['disabled' => $disabled ? 'yes' : 'no']);
        return true;
    }

    public static function formatUptime(int $minutes): string
    {
        if ($minutes <= 0) return '1m';
        $days  = intdiv($minutes, 1440);
        $hours = intdiv($minutes % 1440, 60);
        $mins  = $minutes % 60;
        $out = '';
        if ($days > 0) $out .= "{$days}d";
        if ($hours > 0) $out .= "{$hours}h";
        if ($mins > 0) $out .= "{$mins}m";
        return $out ?: '1m';
    }

    private function retirerSessionsActives(array $filtres): int
    {
        $retirees = 0;
        foreach ($this->queryFiltered('/ip/hotspot/active/print', $filtres) as $ligne) {
            if (isset($ligne['.id'])) {
                $this->queryWithId('/ip/hotspot/active/remove', $ligne['.id']);
                $retirees++;
            }
        }
        return $retirees;
    }

    // -----------------------------------------------------------------
    // MÉTHODES RÉSEAU (Combinaison Google AI Studio + Base de données)
    // -----------------------------------------------------------------

    public function detecterIpLocaleVersRouteur(): ?string
    {
        $hoteRouteur = $this->config['host'] ?? null;
        if (!$hoteRouteur) return null;

        $sock = @socket_create(AF_INET, SOCK_DGRAM, SOL_UDP);
        if ($sock) {
            if (@socket_connect($sock, $hoteRouteur, (int) ($this->config['port'] ?? 8728))) {
                @socket_getsockname($sock, $ipLocale);
                @socket_close($sock);
                if (!empty($ipLocale) && $ipLocale !== '0.0.0.0' && !str_starts_with($ipLocale, '127.') && !str_starts_with($ipLocale, '169.254.')) {
                    return $ipLocale;
                }
            }
            @socket_close($sock);
        }
        return null;
    }

    public function nettoyerReglesObsolètesPortail(): void
    {
        foreach ($this->query('/ip/dns/static/print') as $dns) {
            $comment = $dns['comment'] ?? '';
            $address = $dns['address'] ?? '';
            if (str_contains($comment, 'Portail Captif') || str_contains($comment, 'Cyber Manager') || str_starts_with($address, '169.254.')) {
                if (isset($dns['.id'])) $this->queryWithId('/ip/dns/static/remove', $dns['.id']);
            }
        }

        foreach ($this->query('/ip/hotspot/walled-garden/ip/print') as $wg) {
            $comment = $wg['comment'] ?? '';
            $dstAddress = $wg['dst-address'] ?? '';
            if (str_starts_with($dstAddress, '169.254.') || str_contains($comment, 'Portail Captif Laravel (Auto)')) {
                if (isset($wg['.id'])) $this->queryWithId('/ip/hotspot/walled-garden/ip/remove', $wg['.id']);
            }
        }
    }

    public function configurerWalledGarden(string $ip, string $port = '80'): void
    {
        $existantes = $this->queryFiltered('/ip/hotspot/walled-garden/ip/print', [
            'dst-address' => $ip,
            'dst-port'    => $port,
        ]);

        if (empty($existantes)) {
            $this->query('/ip/hotspot/walled-garden/ip/add', [
                'dst-address' => $ip,
                'dst-port'    => $port,
                'protocol'    => 'tcp',
                'action'      => 'accept',
                'comment'     => 'Cyber Manager - Portail Captif',
            ]);
        }
    }

    public function installerRedirectionHotspot(string $urlPortail): bool
    {
        $profils = $this->query('/ip/hotspot/profile/print');
        $htmlDir = 'flash/hotspot';
        foreach ($profils as $p) {
            if (!empty($p['html-directory'])) {
                $htmlDir = $p['html-directory'];
                break;
            }
        }

        $cheminFichier = trim($htmlDir, '/') . '/login.html';

        $contenu = '<!DOCTYPE html>' . "\n"
            . '<html><head><meta charset="utf-8">' . "\n"
            . '<meta http-equiv="refresh" content="0; url=' . $urlPortail . '?mac=$(mac)&ip=$(ip)&link-login-only=$(link-login-only-esc)&link-orig=$(link-orig-esc)&error=$(error-esc)">' . "\n"
            . '<script type="text/javascript">' . "\n"
            . 'window.location.href="' . $urlPortail . '?mac=$(mac)&ip=$(ip)&link-login-only="+encodeURIComponent("$(link-login-only)")+"&link-orig="+encodeURIComponent("$(link-orig)")+"&error="+encodeURIComponent("$(error)");' . "\n"
            . '</script></head>' . "\n"
            . '<body style="background:#0f172a;color:#fff;text-align:center;padding-top:40px;font-family:sans-serif;">' . "\n"
            . '<p>Connexion au portail Wi-Fi en cours...</p>' . "\n"
            . '</body></html>';

        try {
            $fichiers = $this->queryFiltered('/file/print', ['name' => $cheminFichier]);
            if (!empty($fichiers) && isset($fichiers[0]['.id'])) {
                $this->queryWithId('/file/set', $fichiers[0]['.id'], ['contents' => $contenu]);
            } else {
                $this->query('/file/add', ['name' => $cheminFichier, 'contents' => $contenu]);
            }
            return true;
        } catch (\Throwable) {
            return false;
        }
    }

    public function verifierEtAjusterDhcpDns(): void
    {
        $networks = $this->query('/ip/dhcp-server/network/print');
        $adresses = $this->query('/ip/address/print');

        $routeurIp = null;
        foreach ($adresses as $adr) {
            $ipNet = explode('/', $adr['address'] ?? '')[0] ?? '';
            if ($ipNet && !str_starts_with($ipNet, '127.') && ($adr['interface'] ?? '') !== 'ether1') {
                $routeurIp = $ipNet;
                break;
            }
        }

        if (!$routeurIp) return;

        foreach ($networks as $net) {
            $dnsCourant = $net['dns-server'] ?? '';
            if (!str_contains($dnsCourant, $routeurIp) && isset($net['.id'])) {
                $this->queryWithId('/ip/dhcp-server/network/set', $net['.id'], [
                    'dns-server' => $routeurIp,
                ]);
            }
        }
    }

    public function redemarrerHotspotEtPurger(): void
    {
        try {
            $this->query('/ip/dns/cache/flush');

            $hotspots = $this->query('/ip/hotspot/print');
            foreach ($hotspots as $hs) {
                if (isset($hs['.id'])) $this->queryWithId('/ip/hotspot/disable', $hs['.id']);
            }
            usleep(500000);
            foreach ($hotspots as $hs) {
                if (isset($hs['.id'])) $this->queryWithId('/ip/hotspot/enable', $hs['.id']);
            }
        } catch (\Throwable) {}
    }

    // Helpers
    private function replyHasDone(array $reply): bool
    {
        foreach ($reply as $item) if ($item['type'] === '!done') return true;
        return false;
    }

    private function replyHasTrap(array $reply): bool
    {
        foreach ($reply as $item) if ($item['type'] === '!trap' || $item['type'] === '!fatal') return true;
        return false;
    }

    private function extractTrapMessage(array $reply): ?string
    {
        foreach ($reply as $item) {
            if ($item['type'] === '!trap' || $item['type'] === '!fatal') {
                return $item['attributes']['message'] ?? $item['attributes']['category'] ?? 'Erreur inconnue';
            }
        }
        return null;
    }

        /**
     * Ajoute une règle DNS statique sur le routeur pour associer le nom du portail
     * à l'adresse IP du serveur.
     */
    public function configurerDnsLocal(string $domaine, string $ip): void
    {
        $existantes = $this->queryFiltered('/ip/dns/static/print', ['name' => $domaine]);

        if (!empty($existantes)) {
            $id = $existantes[0]['.id'];
            $this->queryWithId('/ip/dns/static/set', $id, ['address' => $ip]);
        } else {
            $this->query('/ip/dns/static/add', [
                'name'    => $domaine,
                'address' => $ip,
                'comment' => 'Portail Captif (Auto)',
            ]);
        }
    }

        /** Vide le cache DNS du routeur, sans toucher au HotSpot (les clients restent connectés). */
    public function viderCacheDns(): void
    {
        try {
            $this->query('/ip/dns/cache/flush');
        } catch (\Throwable) {
        }
    }
}