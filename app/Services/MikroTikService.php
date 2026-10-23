<?php

namespace App\Services;

use App\Services\MikroTik\MikrotikConnection;
use RuntimeException;

/**
 * Façade haut niveau vers l'API RouterOS.
 *
 * Responsabilités :
 *  - gérer la connexion + login ;
 *  - exposer des méthodes métier simples (listHotspotUsers, ...) ;
 *  - isoler toute la cuisine du protocole dans MikrotikConnection.
 *
 * Ne contient AUCUN accès aux Controllers, AUCUN SQL.
 */
class MikroTikService
{
    private ?MikrotikConnection $connection = null;
    private bool $loggedIn = false;

    public function __construct(
        private array $config
    ) {
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
        if ($this->loggedIn) {
            return;
        }

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

    /**
     * Vérifie la connexion et retourne les infos de base du routeur.
     *
     * @return array{identity: ?string, version: ?string, board_name: ?string, uptime: ?string}
     */
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

    /**
     * Envoie une commande /... et retourne uniquement les enregistrements !re.
     *
     * @param  array<string, scalar>  $arguments
     * @return array<int, array<string,string>>
     */
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

    /**
     * @param  string[]  $words
     * @return array<int, array<string,string>>
     */
    private function send(array $words): array
    {
        $this->login();

        $reply = $this->connection()->sendSentence($words);

        if ($this->replyHasTrap($reply)) {
            $msg = $this->extractTrapMessage($reply);
            throw new RuntimeException(
                'MikroTik ' . ($words[0] ?? '?') . ' : ' . $msg
            );
        }

        $records = [];
        foreach ($reply as $item) {
            if ($item['type'] === '!re') {
                $records[] = $item['attributes'];
            }
        }
        return $records;
    }

    /**
     * Liste les utilisateurs HotSpot configurés.
     */
    public function listHotspotUsers(): array
    {
        return $this->query('/ip/hotspot/user/print');
    }

    /**
     * Liste les sessions HotSpot actuellement actives.
     */
    public function listHotspotActive(): array
    {
        return $this->query('/ip/hotspot/active/print');
    }

    /**
     * Liste les profils HotSpot.
     */
    public function listHotspotProfiles(): array
    {
        return $this->query('/ip/hotspot/user/profile/print');
    }

    /**
     * Liste les instances de serveur HotSpot configurées sur le routeur
     * (le "Server" du formulaire d'ajout — différent du profil).
     */
    public function listHotspotServers(): array
    {
        return $this->query('/ip/hotspot/print');
    }

    /**
     * Table d'enregistrement Wi-Fi : associe une MAC à l'interface wireless
     * (wlan1, wlan2…) qui la sert. Permet de retrouver le vrai SSID.
     */
    public function listWirelessRegistrationTable(): array
    {
        return $this->query('/interface/wireless/registration-table/print');
    }

    /**
     * Interfaces wireless configurées (nom → SSID).
     */
    public function listWirelessInterfaces(): array
    {
        return $this->query('/interface/wireless/print');
    }

    /**
     * Crée un utilisateur HotSpot.
     *
     * @throws \RuntimeException si la commande échoue côté RouterOS
     */
    public function createHotspotUser(
        string $username,
        string $password,
        ?string $profile = null,
        ?string $comment = null,
        ?string $limitUptime = null,
        ?string $server = null,
        ?string $limitBytesTotal = null,
    ): void {
        $profile ??= config('mikrotik.hotspot_profile', 'default');

        $args = [
            'name'     => $username,
            'password' => $password,
            'profile'  => $profile,
        ];

        if ($comment !== null && $comment !== '') {
            $args['comment'] = $comment;
        }
        if ($limitUptime !== null && $limitUptime !== '') {
            $args['limit-uptime'] = $limitUptime;
        }
        if ($server !== null && $server !== '' && $server !== 'all') {
            $args['server'] = $server;
        }
        if ($limitBytesTotal !== null && $limitBytesTotal !== '') {
            $args['limit-bytes-total'] = $limitBytesTotal;
        }

        $this->query('/ip/hotspot/user/add', $args);
    }

    /**
     * Recherche un utilisateur HotSpot par nom exact.
     * Retourne le premier enregistrement trouvé ou null.
     */
    public function findHotspotUser(string $username): ?array
    {
        $rows = $this->queryFiltered('/ip/hotspot/user/print', [
            'name' => $username,
        ]);

        return $rows[0] ?? null;
    }

    /**
     * Supprime un utilisateur HotSpot par nom.
     * Retourne true si supprimé, false s'il n'existait pas.
     */
    public function deleteHotspotUser(string $username): bool
    {
        $user = $this->findHotspotUser($username);

        if ($user === null || !isset($user['.id'])) {
            return false;
        }

        $this->queryWithId('/ip/hotspot/user/remove', $user['.id']);

        return true;
    }

    /**
     * Liste uniquement les utilisateurs créés par Cyber Manager
     * (ceux dont le nom commence par le préfixe configuré).
     *
     * @return array<int, array<string,string>>
     */
    public function listCyberManagerHotspotUsers(): array
    {
        $prefix = config('mikrotik.hotspot_user_prefix', 'cm-');

        return array_values(array_filter(
            $this->listHotspotUsers(),
            fn (array $u) => str_starts_with($u['name'] ?? '', $prefix)
        ));
    }

    /**
     * Baux DHCP — tous les appareils connus du réseau (même non authentifiés).
     */
    public function listDhcpLeases(): array
    {
        return $this->query('/ip/dhcp-server/lease/print');
    }

    /**
     * Hosts connus du HotSpot — appareils ayant déjà été vus par le portail.
     */
    public function listHotspotHosts(): array
    {
        return $this->query('/ip/hotspot/host/print');
    }

    /**
     * Détail d'une session HotSpot active par son .id.
     */
    public function findHotspotActive(string $id): ?array
    {
        $rows = $this->queryFiltered('/ip/hotspot/active/print', [
            '.id' => $id,
        ]);

        return $rows[0] ?? null;
    }

    /**
     * Déconnecte un client actif par son adresse MAC.
     * Retourne true si une session active a été trouvée et supprimée.
     */
    public function disconnectHotspotActiveByMac(string $mac): bool
    {
        $rows = $this->queryFiltered('/ip/hotspot/active/print', [
            'mac-address' => $mac,
        ]);

        if (empty($rows) || !isset($rows[0]['.id'])) {
            return false;
        }

        $this->queryWithId('/ip/hotspot/active/remove', $rows[0]['.id']);

        return true;
    }

    /**
     * Active ou désactive un compte HotSpot.
     */
    public function setHotspotUserDisabled(string $username, bool $disabled): bool
    {
        $user = $this->findHotspotUser($username);

        if ($user === null || !isset($user['.id'])) {
            return false;
        }

        $this->queryWithId('/ip/hotspot/user/set', $user['.id'], [
            'disabled' => $disabled ? 'yes' : 'no',
        ]);

        return true;
    }

    /**
     * Convertit une durée en minutes au format uptime RouterOS.
     * Ex: 30 → "30m", 90 → "1h30m", 1500 → "1d1h".
     */
    public static function formatUptime(int $minutes): string
    {
        if ($minutes <= 0) {
            return '1m';
        }

        $days  = intdiv($minutes, 1440);
        $hours = intdiv($minutes % 1440, 60);
        $mins  = $minutes % 60;

        $out = '';
        if ($days  > 0) { $out .= "{$days}d"; }
        if ($hours > 0) { $out .= "{$hours}h"; }
        if ($mins  > 0) { $out .= "{$mins}m"; }

        return $out ?: '1m';
    }

    // -----------------------------------------------------------------
    // Helpers
    // -----------------------------------------------------------------

    private function replyHasDone(array $reply): bool
    {
        foreach ($reply as $item) {
            if ($item['type'] === '!done') {
                return true;
            }
        }
        return false;
    }

    private function replyHasTrap(array $reply): bool
    {
        foreach ($reply as $item) {
            if ($item['type'] === '!trap' || $item['type'] === '!fatal') {
                return true;
            }
        }
        return false;
    }

    private function extractTrapMessage(array $reply): ?string
    {
        foreach ($reply as $item) {
            if ($item['type'] === '!trap' || $item['type'] === '!fatal') {
                return $item['attributes']['message']
                    ?? $item['attributes']['category']
                    ?? 'Erreur inconnue';
            }
        }
        return null;
    }

        /**
     * Autorise l'adresse IP de ce serveur à être vue par les clients non connectés.
     */
    public function configurerWalledGarden(string $ip, string $port = '80'): void
    {
        // On vérifie si la règle exacte (IP + Port) existe déjà
        $existantes = $this->queryFiltered('/ip/hotspot/walled-garden/ip/print', [
            'dst-address' => $ip,
            'dst-port'    => $port
        ]);
        
        if (empty($existantes)) {
            $this->query('/ip/hotspot/walled-garden/ip/add', [
                'dst-address' => $ip,
                'dst-port'    => $port,
                'protocol'    => 'tcp',
                'action'      => 'accept',
                'comment'     => 'Portail Captif Laravel (Auto)'
            ]);
        }
    }

    /**
     * Ajoute ou met à jour un nom de domaine local sur le routeur.
     * Ex: portail.cyber pointe vers l'IP de Laravel.
     */
    public function configurerDnsLocal(string $domaine, string $ip): void
    {
        $ip = trim($ip);
        $existantes = $this->queryFiltered('/ip/dns/static/print', ['name' => $domaine]);

        if (!empty($existantes)) {
            $id = $existantes[0]['.id'];
            $this->queryWithId('/ip/dns/static/set', $id, ['address' => $ip]);
        } else {
            $this->query('/ip/dns/static/add', [
                'name'    => $domaine,
                'address' => $ip,
                'comment' => 'Portail Captif Laravel (Auto)'
            ]);
        }
    }

        /**
     * Configure le nom de domaine (DNS Name) dans le profil HotSpot.
     */
    public function configurerDnsNameHotspot(string $nomDeDomaine): void
    {
        $profils = $this->query('/ip/hotspot/profile/print');
        
        if (empty($profils)) {
            throw new \RuntimeException("Aucun profil HotSpot trouvé sur le routeur.");
        }

        // On boucle sur TOUS les profils pour être sûr que le bon soit configuré
        foreach ($profils as $profil) {
            $this->queryWithId('/ip/hotspot/profile/set', $profil['.id'], [
                'dns-name' => $nomDeDomaine,
            ]);
        }
    }
}