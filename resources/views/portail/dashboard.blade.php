<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover">
<meta name="theme-color" content="#0b1f24">
<title>Mon espace Wi-Fi</title>
<style>
:root{--nuit:#0b1f24;--onde:#3dd6d0;--ambre:#ffb020;--violet:#a99bff;--txt:#e8f3f3;--sub:#8fb0b3}
*{box-sizing:border-box;margin:0}
body{font-family:system-ui,-apple-system,"Segoe UI",Roboto,sans-serif;color:var(--txt);min-height:100vh;background:radial-gradient(90% 50% at 50% -5%,#1b5c63 0,transparent 70%),var(--nuit);padding:1.5rem 1rem}
.wrap{max-width:440px;margin:0 auto;display:grid;gap:.9rem}
.top{display:flex;align-items:center;gap:.7rem}
.top img{width:42px;height:42px;border-radius:12px;background:#fff;object-fit:contain;padding:4px}
.top b{font-size:1.05rem;font-weight:700;flex:1}
.pill{display:flex;align-items:center;gap:.4rem;font-size:.8rem;font-weight:600;padding:.35rem .75rem;border-radius:999px;background:rgba(61,214,208,.12);color:var(--onde);border:1px solid rgba(61,214,208,.3)}
.pill.off{background:rgba(255,255,255,.06);color:var(--sub);border-color:rgba(255,255,255,.12)}
.card{background:rgba(255,255,255,.06);border:1px solid rgba(255,255,255,.11);border-radius:24px;padding:1.3rem}
.lbl{font-size:.85rem;color:var(--sub);font-weight:500}
.temps{font-size:clamp(2.8rem,15vw,3.6rem);font-weight:700;font-variant-numeric:tabular-nums;line-height:1.1;margin-top:.25rem;font-family:monospace}
.montant{font-size:clamp(2rem,10vw,2.5rem);font-weight:750;color:var(--ambre);font-variant-numeric:tabular-nums;line-height:1.1}
.note{font-size:.78rem;color:var(--sub);margin-top:.35rem}
.row{display:flex;justify-content:space-between;align-items:baseline;margin-bottom:.45rem;font-size:.88rem}
.bar{height:8px;border-radius:99px;background:rgba(255,255,255,.1);overflow:hidden}
.bar i{display:block;height:100%;width:0;border-radius:99px;transition:width .8s ease}
.down{color:var(--onde)}.down+.bar i{background:var(--onde)}
.up{color:var(--violet)}.up+.bar i{background:var(--violet)}
.total{display:flex;justify-content:space-between;margin-top:1.2rem;padding-top:.9rem;border-top:1px solid rgba(255,255,255,.1);font-size:.85rem;color:var(--sub)}
.warn{background:rgba(255,176,32,.14);border:1px solid rgba(255,176,32,.35);color:#ffd88a;border-radius:14px;padding:.7rem;font-size:.85rem;display:none}
.fin{position:fixed;inset:0;display:none;place-items:center;background:rgba(11,31,36,.95);text-align:center;z-index:5}
[hidden]{display:none!important}
</style>
</head>
<body>
<div class="wrap">
    <div class="top">
        @if($logo)<img src="{{ $logo }}" alt="">@endif
        <b>{{ $titre ?? 'Mon espace Wi-Fi' }}</b>
        <span class="pill off" id="pill"><i></i><span id="pilltxt">Connexion…</span></span>
    </div>

    <div class="warn" id="warn">Connexion au serveur perdue. Nouvelle tentative…</div>

    <section class="card">
        <div class="lbl">Temps de connexion</div>
        <div class="temps" id="temps">--:--:--</div>
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
        <div class="total"><span>Données utilisées</span><b id="total">0 o</b></div>
    </section>
</div>

<div class="fin" id="fin">
    <div>
        <h2>Session terminée</h2>
        <p style="margin: 1rem 0;">Vous n'êtes plus connecté au Wi-Fi.</p>
        <a href="/portail" style="background:var(--ambre);color:#000;padding:.8rem 1.5rem;text-decoration:none;border-radius:12px;font-weight:bold;">Se reconnecter</a>
    </div>
</div>

<script>
(function(){
    var MAC = @json($mac);

    var POLL_MS      = 2000;  // cadence normale
    var FAST_MS      = 1000;  // 2e requête rapide pour afficher le débit tout de suite
    var ENDED_MS     = 5000;  // cadence quand la session est terminée (détecte une reconnexion)
    var TIMEOUT_MS   = 5000;  // délai max d'une requête
    var MAX_FAILS    = 2;     // l'alerte apparaît au-delà de 2 échecs consécutifs
    var SMOOTH       = 0.3;   // poids de l'ancienne valeur (0 = instantané, 1 = figé)

    var $ = function(i){ return document.getElementById(i); };
    var p2 = function(n){ return n < 10 ? '0' + n : '' + n; };

    var S = {
        up: 0,        // uptime (s) renvoyé par le routeur
        at: 0,        // moment local de réception
        on: false,    // le chrono doit tourner
        ended: false, // overlay "Session terminée" affiché
        prev: null,   // dernier échantillon {t, d, u}
        vd: 0, vu: 0, // débits lissés (octets/s)
        fails: 0,
        timer: null,
        busy: false
    };

    function fmtT(s){
        s = Math.max(0, Math.floor(s));
        return p2(Math.floor(s / 3600)) + ':' + p2(Math.floor(s % 3600 / 60)) + ':' + p2(s % 60);
    }
    function fmtB(b){
        if (b < 1024) return Math.round(b) + ' o';
        var u = ['Ko', 'Mo', 'Go'], i = -1;
        do { b /= 1024; i++; } while (b >= 1024 && i < 2);
        return b.toFixed(1) + ' ' + u[i];
    }
    function gauge(v){ return Math.min(100, Math.log10(1 + v) / 7 * 100); }

    function pill(on, txt){
        $('pill').className = 'pill' + (on ? '' : ' off');
        $('pilltxt').textContent = txt;
    }

    function resetDebit(){
        S.prev = null; S.vd = 0; S.vu = 0;
        $('vdown').textContent = '0 o/s';
        $('vup').textContent = '0 o/s';
        $('bdown').style.width = '0%';
        $('bup').style.width = '0%';
    }

    // Chrono local (fluide entre deux requêtes) : s'arrête dès que S.on est faux
    function tick(){
        if (!S.on) return;
        $('temps').textContent = fmtT(S.up + (Date.now() - S.at) / 1000);
    }

    function showEnded(){
        S.on = false;
        S.ended = true;
        resetDebit();
        pill(false, 'Hors ligne');
        $('warn').style.display = 'none';
        $('fin').style.display = 'grid';
    }

    function hideEnded(){
        S.ended = false;
        $('fin').style.display = 'none';
    }

    // Traite une réponse valide de l'API. Retourne le délai avant la prochaine requête.
    function onData(d){
        S.fails = 0;
        $('warn').style.display = 'none';

        if (!d || !d.connected) {
            showEnded();
            return ENDED_MS;
        }

        // La session est revenue (reconnexion) : on repart proprement
        if (S.ended) hideEnded();

        var now = Date.now();
        var premier = !S.prev;

        if (S.prev) {
            var dt = (now - S.prev.t) / 1000;
            var dd = d.bytes_down - S.prev.d;
            var du = d.bytes_up - S.prev.u;

            // Compteur remis à zéro côté routeur (nouvelle session) : on repart de 0
            if (dd < 0 || du < 0) {
                S.vd = 0; S.vu = 0;
            } else if (dt > 0.2) {
                S.vd = SMOOTH * S.vd + (1 - SMOOTH) * (dd / dt);
                S.vu = SMOOTH * S.vu + (1 - SMOOTH) * (du / dt);
            }
        }
        S.prev = { t: now, d: d.bytes_down, u: d.bytes_up };

        S.up = d.uptime;
        S.at = now;
        S.on = true;

        pill(true, 'En ligne');
        $('montant').textContent = d.montant.toLocaleString('fr-FR') + ' Ar';
        $('note').textContent = d.minimum_applique
            ? 'Minimum de ' + d.tarif_minimum + ' Ar, puis ' + d.tarif_minute + ' Ar/min.'
            : 'Tarif : ' + d.tarif_minute + ' Ar/min.';
        $('vdown').textContent = fmtB(S.vd) + '/s';
        $('vup').textContent = fmtB(S.vu) + '/s';
        $('bdown').style.width = gauge(S.vd) + '%';
        $('bup').style.width = gauge(S.vu) + '%';
        $('total').textContent = fmtB(d.bytes_down + d.bytes_up);
        tick();

        // Premier échantillon : on redemande vite pour afficher le débit sans attendre
        return premier ? FAST_MS : POLL_MS;
    }

    // Erreur réseau / 503 / timeout : on ne conclut rien sur la session
    function onFail(){
        S.fails++;
        if (S.fails > MAX_FAILS) {
            $('warn').style.display = 'block';
            if (!S.ended) pill(false, 'Reconnexion…');
        }
        // Le chrono local continue : on ne sait pas que la session est finie.
        // Les échantillons de débit sont invalidés pour éviter un pic faux au retour.
        S.prev = null;
        return POLL_MS;
    }

    function poll(){
        if (S.busy) return;
        S.busy = true;

        var ac = new AbortController();
        var to = setTimeout(function(){ ac.abort(); }, TIMEOUT_MS);
        var url = '/api/portail/stats' + (MAC ? '?mac=' + encodeURIComponent(MAC) : '');

        fetch(url, { signal: ac.signal, cache: 'no-store' })
            .then(function(r){
                if (!r.ok) throw new Error(r.status);
                return r.json();
            })
            .then(onData, onFail)
            .then(function(delay){
                clearTimeout(to);
                S.busy = false;
                clearTimeout(S.timer);
                S.timer = setTimeout(poll, delay);
            });
    }

    // Retour sur l'onglet (mobile en veille) : on rafraîchit immédiatement
    document.addEventListener('visibilitychange', function(){
        if (!document.hidden) { clearTimeout(S.timer); poll(); }
    });

    setInterval(tick, 1000);
    poll();
})();
</script>
</body>
</html>