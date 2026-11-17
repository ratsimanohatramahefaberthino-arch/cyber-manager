<?php

namespace App\Services\MikroTik;

use RuntimeException;

/**
 * Connexion TCP bas niveau à l'API RouterOS (port 8728 / 8729 SSL).
 *
 * Ne contient AUCUNE logique métier : uniquement l'encodage/décodage
 * du protocole "sentences" de RouterOS.
 *
 * Compatible RouterOS 6.43+ (login simple) et 7.x.
 */
class MikrotikConnection
{
    /** @var resource|null */
    private $socket = null;

    public function __construct(
        private string $host,
        private int $port = 8728,
        private float $timeout = 5.0,
        private bool $useSsl = false,
    ) {
    }

    public function connect(): void
    {
        if ($this->socket !== null) {
            return;
        }

        $transport = $this->useSsl ? 'ssl' : 'tcp';
        $address   = "{$transport}://{$this->host}:{$this->port}";

        // Pour SSL auto-signé (RouterOS par défaut). À durcir plus tard.
        $context = stream_context_create([
            'ssl' => [
                'verify_peer'      => false,
                'verify_peer_name' => false,
            ],
        ]);

        $errno  = 0;
        $errstr = '';

        $socket = @stream_socket_client(
            $address,
            $errno,
            $errstr,
            $this->timeout,
            STREAM_CLIENT_CONNECT,
            $context
        );

        if ($socket === false) {
            throw new RuntimeException(
                "Connexion MikroTik impossible ({$this->host}:{$this->port}) : "
                . "{$errstr} (errno {$errno})"
            );
        }

        stream_set_timeout($socket, (int) $this->timeout);
        $this->socket = $socket;
    }

    public function disconnect(): void
    {
        if ($this->socket !== null) {
            @fclose($this->socket);
            $this->socket = null;
        }
    }

    public function isConnected(): bool
    {
        return $this->socket !== null;
    }

    /**
     * Envoie une phrase (liste de mots) et retourne la réponse complète
     * jusqu'à !done ou !fatal.
     *
     * @param  string[]  $words
     * @return array<int, array{type: string, attributes: array<string,string>}>
     */
    public function sendSentence(array $words): array
    {
        if ($this->socket === null) {
            throw new RuntimeException('Non connecté au MikroTik.');
        }

        foreach ($words as $word) {
            $this->writeWord($word);
        }
        // Fin de phrase = mot vide
        $this->writeWord('');

        return $this->readReply();
    }

    // -----------------------------------------------------------------
    // Encodage / décodage
    // -----------------------------------------------------------------

    private function writeWord(string $word): void
    {
        fwrite($this->socket, $this->encodeLength(strlen($word)) . $word);
    }

    private function encodeLength(int $length): string
    {
        if ($length < 0x80) {
            return chr($length);
        }
        if ($length < 0x4000) {
            $length |= 0x8000;
            return chr(($length >> 8) & 0xFF) . chr($length & 0xFF);
        }
        if ($length < 0x200000) {
            $length |= 0xC00000;
            return chr(($length >> 16) & 0xFF)
                 . chr(($length >> 8) & 0xFF)
                 . chr($length & 0xFF);
        }
        if ($length < 0x10000000) {
            $length |= 0xE0000000;
            return chr(($length >> 24) & 0xFF)
                 . chr(($length >> 16) & 0xFF)
                 . chr(($length >> 8) & 0xFF)
                 . chr($length & 0xFF);
        }
        return chr(0xF0)
             . chr(($length >> 24) & 0xFF)
             . chr(($length >> 16) & 0xFF)
             . chr(($length >> 8) & 0xFF)
             . chr($length & 0xFF);
    }

    private function readLength(): int
    {
        $byte = ord($this->readBytes(1));

        if (($byte & 0x80) === 0x00) {
            return $byte;
        }
        if (($byte & 0xC0) === 0x80) {
            $b2 = ord($this->readBytes(1));
            return (($byte & 0x3F) << 8) | $b2;
        }
        if (($byte & 0xE0) === 0xC0) {
            $rest = $this->readBytes(2);
            return (($byte & 0x1F) << 16) | unpack('n', $rest)[1];
        }
        if (($byte & 0xF0) === 0xE0) {
            $rest = $this->readBytes(3);
            return (($byte & 0x0F) << 24) | unpack('N', "\x00" . $rest)[1];
        }
        if (($byte & 0xF8) === 0xF0) {
            return unpack('N', $this->readBytes(4))[1];
        }

        throw new RuntimeException(
            'Longueur MikroTik invalide : 0x' . dechex($byte)
        );
    }

    private function readBytes(int $n): string
    {
        $buffer = '';
        while (strlen($buffer) < $n) {
            $chunk = fread($this->socket, $n - strlen($buffer));

            if ($chunk === false || $chunk === '') {
                $info = stream_get_meta_data($this->socket);
                if (!empty($info['timed_out'])) {
                    throw new RuntimeException('Timeout de lecture MikroTik.');
                }
                if (feof($this->socket)) {
                    throw new RuntimeException('Connexion MikroTik fermée.');
                }
                throw new RuntimeException('Lecture MikroTik échouée.');
            }
            $buffer .= $chunk;
        }
        return $buffer;
    }

    /**
     * Lit une réponse complète jusqu'à !done ou !fatal.
     */
    private function readReply(): array
    {
        $reply = [];

        while (true) {
            $sentence = $this->readSentence();

            if (empty($sentence)) {
                continue;
            }

            $type       = array_shift($sentence);
            $attributes = [];

            foreach ($sentence as $word) {
                if ($word === '' || $word[0] !== '=') {
                    continue;
                }
                $rest  = substr($word, 1);
                $eqPos = strpos($rest, '=');
                if ($eqPos === false) {
                    $attributes[$rest] = '';
                } else {
                    $attributes[substr($rest, 0, $eqPos)] = substr($rest, $eqPos + 1);
                }
            }

            $reply[] = [
                'type'       => $type,
                'attributes' => $attributes,
            ];

            if ($type === '!done' || $type === '!fatal') {
                return $reply;
            }
        }
    }

    /**
     * @return string[]
     */
    private function readSentence(): array
    {
        $words = [];
        while (true) {
            $length = $this->readLength();
            if ($length === 0) {
                return $words;
            }
            $words[] = $this->readBytes($length);
        }
    }
}