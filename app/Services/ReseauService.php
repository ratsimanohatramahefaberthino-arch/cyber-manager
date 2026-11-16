<?php

namespace App\Services;

/** Adresse du serveur sur le réseau local (jamais « localhost »). */
class ReseauService
{
    /** Adresse de la carte réseau qui sert à joindre le réseau (route par défaut). */
    public static function ipPrincipale(): ?string
    {
        try {
            // Aucun paquet n'est envoyé : on demande seulement à Windows quelle adresse il utiliserait.
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

    public static function urlServeur(int $port): string
    {
        $ip = self::ipPrincipale() ?? '127.0.0.1';

        return 'http://' . $ip . ($port === 80 ? '' : ':' . $port);
    }
}