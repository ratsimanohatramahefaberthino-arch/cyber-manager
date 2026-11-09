<?php
// Usage : php diag-mikrotik.php HOTE PORT USER MOTDEPASSE NB_ESSAIS MODE
// MODE = mot (un envoi par mot, comme l'application) ou bloc (un seul envoi)
[$script, $host, $port, $user, $pass, $n, $mode] = array_pad($argv, 7, null);
$host = $host ?? '128.0.1.1';
$port = (int) ($port ?? 8728);
$user = $user ?? 'admin';
$pass = $pass ?? '';
$n    = (int) ($n ?? 10);
$mode = $mode ?? 'mot';

function mot(string $w): string
{
    $l = strlen($w);
    if ($l < 0x80) {
        return chr($l) . $w;
    }
    $l |= 0x8000;
    return chr(($l >> 8) & 0xFF) . chr($l & 0xFF) . $w;
}

function envoyer($s, array $mots, string $mode): void
{
    if ($mode === 'bloc') {
        fwrite($s, implode('', array_map('mot', $mots)) . "\x00");
        return;
    }
    foreach ($mots as $m) {
        fwrite($s, mot($m));
    }
    fwrite($s, "\x00");
}

function lire($s, float $max): array
{
    $buf = '';
    $t0  = microtime(true);
    while ((microtime(true) - $t0) < $max) {
        $r = [$s]; $w = null; $e = null;
        $reste = $max - (microtime(true) - $t0);
        if (stream_select($r, $w, $e, (int) $reste, (int) (($reste - (int) $reste) * 1000000)) > 0) {
            $c = fread($s, 8192);
            if ($c === false || $c === '') {
                return [$buf, 'FERME', (microtime(true) - $t0) * 1000];
            }
            $buf .= $c;
            if (str_contains($buf, '!done') || str_contains($buf, '!fatal') || str_contains($buf, '!trap')) {
                return [$buf, 'OK', (microtime(true) - $t0) * 1000];
            }
        }
    }
    return [$buf, 'TIMEOUT', (microtime(true) - $t0) * 1000];
}

function propre(string $b): string
{
    return substr(preg_replace('/[^\x20-\x7E]/', '.', $b), 0, 80);
}

echo "Cible $host:$port, utilisateur $user, $n essais, mode $mode\n";

for ($i = 1; $i <= $n; $i++) {
    $t = microtime(true);
    $s = @stream_socket_client("tcp://$host:$port", $errno, $errstr, 5);
    $tc = (microtime(true) - $t) * 1000;

    if (!$s) {
        printf("#%d CONNEXION ECHEC apres %.0f ms (errno %d)\n", $i, $tc, $errno);
        sleep(1);
        continue;
    }
    printf("#%d connexion OK %.0f ms | ", $i, $tc);

    envoyer($s, ['/login', "=name=$user", "=password=$pass"], $mode);
    [$buf, $st, $d] = lire($s, 5.0);
    printf("login %s %.0f ms [%s] | ", $st, $d, propre($buf));

    if ($st === 'OK') {
        envoyer($s, ['/system/identity/print'], $mode);
        [$buf, $st, $d] = lire($s, 5.0);
        printf("commande %s %.0f ms [%s]", $st, $d, propre($buf));
    }

    fclose($s);
    echo "\n";
    sleep(1);
}