<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover">
<meta name="theme-color" content="{{ $config['fond_couleur_1'] ?? '#0b1f24' }}">
<title>Mon espace Wi-Fi</title>
<style>
:root{
    --bg-1: {{ $config['fond_couleur_1'] ?? '#0b1f24' }};
    --bg-2: {{ $config['fond_couleur_2'] ?? '#1b5c63' }};
    --onde: {{ $config['accent_couleur'] ?? '#3dd6d0' }};
    --ambre: {{ $config['bouton_fond'] ?? '#ffb020' }};
    --txt: #ffffff;
    --sub: #94a3b8;
    --card-bg: rgba(255, 255, 255, 0.07);
    --card-border: rgba(255, 255, 255, 0.12);
    --font-family: {{ $config['police'] ?? 'system-ui, sans-serif' }};
}
*{box-sizing:border-box;margin:0}
body{
    font-family: var(--font-family);
    color: var(--txt);
    min-height: 100vh;
    min-height: 100dvh;
    background: radial-gradient(100% 60% at 50% 0, var(--bg-2) 0, transparent 75%), var(--bg-1);
    padding: 1.5rem 1rem;
}
.wrap{max-width:440px;margin:0 auto;display:grid;gap:.9rem}
.top{display:flex;align-items:center;gap:.8rem}
.top img{width:44px;height:44px;border-radius:12px;background:#fff;object-fit:contain;padding:4px;box-shadow:0 4px 12px rgba(0,0,0,.25)}
.top b{font-size:1.1rem;font-weight:700;flex:1}
.pill{display:inline-flex;align-items:center;gap:.45rem;font-size:.8rem;font-weight:600;padding:.4rem .85rem;border-radius:999px;transition:all .2s ease}
.pill.on{background:rgba(61,214,208,.15);color:var(--onde);border:1px solid rgba(61,214,208,.4)}
.pill.pause{background:rgba(255,176,32,.15);color:var(--ambre);border:1px solid rgba(255,176,32,.4)}
.pill.off{background:rgba(255,255,255,.08);color:var(--sub);border:1px solid rgba(255,255,255,.15)}
.pill i{width:7px;height:7px;border-radius:50%;background:currentColor;display:inline-block}

.card{background:var(--card-bg);border:1px solid var(--card-border);border-radius:24px;padding:1.3rem;backdrop-filter:blur(8px)}
.lbl{font-size:.85rem;color:var(--sub);font-weight:500}
.temps{font-size:clamp(2.8rem,14vw,3.6rem);font-weight:750;font-variant-numeric:tabular-nums;line-height:1.1;margin-top:.25rem;font-family:monospace}
.montant{font-size:clamp(2rem,10vw,2.5rem);font-weight:800;color:var(--ambre);font-variant-numeric:tabular-nums;line-height:1.1}
.note{font-size:.78rem;color:var(--sub);margin-top:.35rem}
.row{display:flex;justify-content:space-between;align-items:baseline;margin-bottom:.45rem;font-size:.88rem}
.bar{height:8px;border-radius:99px;background:rgba(255,255,255,.1);overflow:hidden}
.bar i{display:block;height:100%;width:0;border-radius:99px;transition:width .6s ease}
.down{color:var(--onde)}.down+.bar i{background:var(--onde)}
.up{color:#a78bfa}.up+.bar i{background:#a78bfa}
.total{display:flex;justify-content:space-between;margin-top:1.1rem;padding-top:.85rem;border-top:1px solid rgba(255,255,255,.1);font-size:.85rem;color:var(--sub)}

.warn{background:rgba(255,176,32,.15);border:1px solid rgba(255,176,32,.35);color:#ffd88a;border-radius:14px;padding:.75rem 1rem;font-size:.85rem;display:none;text-align:center}
.fin{position:fixed;inset:0;display:none;place-items:center;background:rgba(11,31,36,.96);text-align:center;z-index:50;padding:1.5rem}
.fin-box{background:rgba(255,255,255,.05);border:1px solid rgba(255,255,255,.12);border-radius:24px;padding:2rem;max-width:360px;width:100%}
</style>
</head>
<body>
<div class="wrap">
    <div class="top">
        @if($logo)<img src="{{ $logo }}" alt="Logo">@endif
        <b>{{ $config['titre'] ?? 'Mon espace Wi-Fi' }}</b>
        <span class="pill off" id="pill"><i></i><span id="pilltxt">Connexion…</span></span>
    </div>

    <div class="warn" id="warn">Synchronisation temporairement ralentie…</div>

    <section class="card">
        <div class="lbl" id="lbl-temps">Temps de connexion</div>
        <div class="temps" id="temps">00:00:00</div>
    </section>

    <section class="card">
        <div class="lbl">Montant à payer</div>
        <div class="montant" id="montant">-- Ar</div>
        <div class="note" id="note">Calcul en temps réel.</div>
    </section>

    <section class="card">
        <div class="row"><span class="lbl">Reçu (↓)</span><b class="down" id="vdown">0 o/s</b></div>
        <div class="bar"><i id="bdown"></i></div>
        <div class="row" style="margin-top:1rem"><span class="lbl">Envoyé (↑)</span><b class="up" id="vup">0 o/s</b></div>
        <div class="bar"><i id="bup"></i></div>
        <div class="total"><span>Volume total utilisé</span><b id="total">0 o</b></div>
    </section>
</div>

<div class="fin" id="fin">
    <div class="fin-box">
        <h2 style="font-size:1.4rem;font-weight:700;margin-bottom:.5rem">Session terminée</h2>
        <p style="color:var(--sub);margin-bottom:1.5rem;font-size:.92rem">Votre session est arrêtée. Veuillez régler votre solde au comptoir.</p>
        <a href="/portail" style="display:inline-block;background:var(--ambre);color:#2b1c00;padding:.85rem 1.6rem;text-decoration:none;border-radius:14px;font-weight:750;">Se reconnecter</a>
    </div>
</div>

<script>
(function(){
    var MAC = @json($mac);

    var POLL_MS    = 2500;
    var TIMEOUT_MS = 6000;
    var SMOOTH     = 0.35;

    var $ = function(id){ return document.getElementById(id); };
    var p2 = function(n){ return n < 10 ? '0' + n : '' + n; };

    var S = {
        uptime: 0,
        recuAt: 0,
        running: false, // Vrai UNIQUEMENT si le chrono doit avancer
        prevDown: 0,
        prevUp: 0,
        prevT: 0,
        vDown: 0,
        vUp: 0,
        fails: 0,
        busy: false
    };

    function fmtTemps(s){
        s = Math.max(0, Math.floor(s));
        var h = Math.floor(s / 3600);
        var m = Math.floor((s % 3600) / 60);
        var sec = s % 60;
        return p2(h) + ':' + p2(m) + ':' + p2(sec);
    }

    function fmtOctets(b){
        if (b < 1024) return Math.round(b) + ' o';
        var u = ['Ko', 'Mo', 'Go'], i = -1;
        do { b /= 1024; i++; } while (b >= 1024 && i < 2);
        return b.toFixed(1) + ' ' + u[i];
    }

    function setStatut(type, texte){
        var el = $('pill');
        el.className = 'pill ' + type;
        $('pilltxt').textContent = texte;
    }

    // Le chrono avance localement SEULEMENT si running === true
    function tick(){
        if (!S.running) return;
        var diff = Math.floor((Date.now() - S.recuAt) / 1000);
        $('temps').textContent = fmtTemps(S.uptime + diff);
    }

    function updateData(d){
        S.fails = 0;
        $('warn').style.display = 'none';

        // 1. Session définitivement clôturée
        if (d.terminee) {
            S.running = false;
            $('fin').style.display = 'grid';
            setStatut('off', 'Terminée');
            return;
        }

        // 2. Client déconnecté ou en pause -> LE CHRONO SE FIGE
        if (!d.connected || d.en_pause) {
            S.running = false;
            S.uptime = d.uptime || S.uptime;
            $('temps').textContent = fmtTemps(S.uptime);
            $('lbl-temps').textContent = 'Temps (En pause / Déconnecté)';
            setStatut('pause', 'En pause');
            $('montant').textContent = (d.montant || 0).toLocaleString('fr-FR') + ' Ar';
            $('vdown').textContent = '0 o/s';
            $('vup').textContent = '0 o/s';
            $('bdown').style.width = '0%';
            $('bup').style.width = '0%';
            return;
        }

        // 3. Client actif en ligne
        $('lbl-temps').textContent = 'Temps de connexion';
        setStatut('on', 'En ligne');

        var now = Date.now();
        if (S.prevT > 0 && d.bytes_down >= S.prevDown && d.bytes_up >= S.prevUp) {
            var dt = (now - S.prevT) / 1000;
            if (dt > 0.3) {
                var instDown = (d.bytes_down - S.prevDown) / dt;
                var instUp = (d.bytes_up - S.prevUp) / dt;
                S.vDown = SMOOTH * S.vDown + (1 - SMOOTH) * instDown;
                S.vUp = SMOOTH * S.vUp + (1 - SMOOTH) * instUp;
            }
        }

        S.prevDown = d.bytes_down;
        S.prevUp = d.bytes_up;
        S.prevT = now;

        S.uptime = d.uptime;
        S.recuAt = now;
        S.running = true;

        $('temps').textContent = fmtTemps(S.uptime);
        $('montant').textContent = d.montant.toLocaleString('fr-FR') + ' Ar';
        $('note').textContent = d.minimum_applique
            ? 'Minimum de ' + d.tarif_minimum + ' Ar, puis ' + d.tarif_minute + ' Ar/min.'
            : 'Tarif calculé : ' + d.tarif_minute + ' Ar/min.';

        $('vdown').textContent = fmtOctets(S.vDown) + '/s';
        $('vup').textContent = fmtOctets(S.vUp) + '/s';
        $('bdown').style.width = Math.min(100, Math.log10(1 + S.vDown) / 6.5 * 100) + '%';
        $('bup').style.width = Math.min(100, Math.log10(1 + S.vUp) / 6.5 * 100) + '%';
        $('total').textContent = fmtOctets(d.bytes_down + d.bytes_up);
    }

    function sync(){
        if (S.busy) return;
        S.busy = true;

        var c = new AbortController();
        var to = setTimeout(function(){ c.abort(); }, TIMEOUT_MS);
        var url = '/api/portail/stats' + (MAC ? '?mac=' + encodeURIComponent(MAC) : '');

        fetch(url, { signal: c.signal, cache: 'no-store' })
            .then(function(res){
                if (!res.ok) throw new Error('HTTP ' + res.status);
                return res.json();
            })
            .then(function(data){
                clearTimeout(to);
                updateData(data);
            })
            .catch(function(){
                clearTimeout(to);
                S.fails++;
                // Ne fige pas abusivement sur une simple micro-coupure réseau
                if (S.fails >= 3) {
                    $('warn').style.display = 'block';
                    setStatut('off', 'Reconnexion…');
                }
            })
            .finally(function(){
                S.busy = false;
                setTimeout(sync, POLL_MS);
            });
    }

    document.addEventListener('visibilitychange', function(){
        if (!document.hidden) { S.fails = 0; sync(); }
    });

    setInterval(tick, 1000);
    sync();
})();
</script>
</body>
</html>