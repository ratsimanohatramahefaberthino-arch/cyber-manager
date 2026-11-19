<?php

namespace App\Services;

/** Adresse du serveur sur le réseau local (jamais « localhost »). */
class ReseauService
{
    /** Adresse de la carte réseau qui sert à joindre le réseau (route par défaut). */
    public static function ipPrincipale(): ?string
    {
        try {
            $s = @stream_socket_client('udp://192.0.2.1:53', $errno, $errstr, 1);
            if ($s) {
                $nom = (string) stream_socket_get_name($s, false);
                fclose($s);
                $ip = explode(':', $nom)[0] ?? '';
                if (self::valide($ip)) {
                    return $ip;
                }
            }
        } catch (\Throwable) {
        }

        return self::toutes()[0] ?? null;
    }

    /** Toutes les adresses IPv4 de l'ordinateur (utile s'il a plusieurs cartes réseau). */
    public static function toutes(): array
    {
        $liste = [];

        try {
            if (function_exists('net_get_interfaces')) {
                foreach ((array) @net_get_interfaces() as $nom => $carte) {
                    if (isset($carte['up']) && $carte['up'] === false) {
                        continue;
                    }
                    if (preg_match('/vEthernet|VirtualBox|VMware|Hyper-V|WSL|Loopback|Bluetooth|Tailscale|ZeroTier/i', (string) $nom)) {
                        continue;   // cartes virtuelles : jamais l'adresse que les postes doivent utiliser
                    }
                    foreach ((array) ($carte['unicast'] ?? []) as $adresse) {
                        $ip = (string) ($adresse['address'] ?? '');
                        if (self::valide($ip)) {
                            $liste[] = $ip;
                        }
                    }
                }
            }
        } catch (\Throwable) {
        }

        try {
            foreach ((array) @gethostbynamel((string) gethostname()) as $ip) {
                if (self::valide($ip)) {
                    $liste[] = $ip;
                }
            }
        } catch (\Throwable) {
        }

        return array_values(array_unique($liste));
    }

    private static function valide(string $ip): bool
    {
        return filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)
            && !str_starts_with($ip, '127.')
            && !str_starts_with($ip, '169.254.');
    }

    /**
     * URL que les postes doivent utiliser pour joindre ce serveur.
     *
     *  - Port : celui par lequel la page a été ouverte. C'est, par définition, un port où le serveur répond réellement
     *    (80 avec Apache, 8000 avec « artisan serve », ou n'importe quel autre).
     *  - Adresse : si la page a été ouverte avec une adresse IP de CET ordinateur (http://192.168.1.10:8000),
     *    on garde celle-là (cas de plusieurs cartes réseau) ; sinon (localhost, nom de l'ordinateur) on prend
     *    l'adresse de la carte réseau par défaut.
     */
    public static function urlServeur(?int $port = null): string
    {
        $requete = function_exists('request') ? request() : null;

        $port = ($port !== null && $port > 0) ? $port : (int) ($requete?->getPort() ?: 80);
        $hote = (string) ($requete?->getHost() ?? '');

        $ip = null;
        if ($hote !== '' && filter_var($hote, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) && self::valide($hote)
            && in_array($hote, self::toutes(), true)) {
            $ip = $hote;
        }
        $ip ??= self::ipPrincipale() ?? '127.0.0.1';

        return 'http://' . $ip . ($port === 80 ? '' : ':' . $port);
    }
}