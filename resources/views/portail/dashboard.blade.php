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
body{font-family:system-ui,-apple-system,"Segoe UI",Roboto,sans-serif;color:var(--txt);min-height:100vh;min-height:100dvh;background:radial-gradient(90% 50% at 50% -5%,#1b5c63 0,transparent 70%),radial-gradient(60% 40% at 100% 100%,#2a2466 0,transparent 70%),var(--nuit);padding:max(1.2rem,env(safe-area-inset-top)) 1rem calc(1.5rem + env(safe-area-inset-bottom));-webkit-text-size-adjust:100%}
.wrap{max-width:440px;margin:0 auto;display:grid;gap:.9rem}
.top{display:flex;align-items:center;gap:.7rem}
.top img{width:42px;height:42px;border-radius:12px;background:#fff;object-fit:contain;padding:4px}
.top b{font-size:1.05rem;font-weight:700;flex:1;min-width:0;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.pill{display:flex;align-items:center;gap:.4rem;font-size:.8rem;font-weight:600;padding:.35rem .75rem;border-radius:999px;background:rgba(61,214,208,.12);color:var(--onde);border:1px solid rgba(61,214,208,.3)}
.pill i{width:8px;height:8px;border-radius:50%;background:currentColor;animation:p 1.8s ease-in-out infinite}
.pill.off{background:rgba(255,255,255,.06);color:var(--sub);border-color:rgba(255,255,255,.12)}.pill.off i{animation:none}
@keyframes p{50%{opacity:.25}}
.card{background:rgba(255,255,255,.06);border:1px solid rgba(255,255,255,.11);border-radius:24px;padding:1.3rem;-webkit-backdrop-filter:blur(14px);backdrop-filter:blur(14px)}
.lbl{font-size:.85rem;color:var(--sub);font-weight:500}
.temps{font-size:clamp(2.8rem,15vw,3.6rem);font-weight:700;font-variant-numeric:tabular-nums;letter-spacing:.01em;line-height:1.1;margin-top:.25rem;font-family:ui-monospace,SFMono-Regular,Menlo,Consolas,monospace}
.meter{display:flex;align-items:center;gap:1.1rem}
.ring{position:relative;width:96px;height:96px;flex:none}
.ring svg{width:100%;height:100%;transform:rotate(-90deg)}
.ring circle{fill:none;stroke-width:8;stroke-linecap:round}
.ring .bg{stroke:rgba(255,255,255,.1)}.ring .fg{stroke:var(--ambre);stroke-dasharray:264;stroke-dashoffset:264;transition:stroke-dashoffset 1s linear}
.ring span{position:absolute;inset:0;display:grid;place-items:center;font-size:1.25rem;font-weight:700;font-variant-numeric:tabular-nums}
.ring small{position:absolute;left:0;right:0;bottom:22px;text-align:center;font-size:.62rem;color:var(--sub)}
.montant{font-size:clamp(2rem,10vw,2.5rem);font-weight:750;color:var(--ambre);font-variant-numeric:tabular-nums;line-height:1.1}
.note{font-size:.78rem;color:var(--sub);margin-top:.35rem;line-height:1.35}
.dbit+.dbit{margin-top:1.1rem}
.row{display:flex;justify-content:space-between;align-items:baseline;margin-bottom:.45rem;font-size:.88rem}
.row b{font-variant-numeric:tabular-nums;font-size:1.05rem}
.bar{height:8px;border-radius:99px;background:rgba(255,255,255,.1);overflow:hidden}
.bar i{display:block;height:100%;width:0;border-radius:99px;transition:width .8s ease}
.down{color:var(--onde)}.down+.bar i{background:var(--onde)}
.up{color:var(--violet)}.up+.bar i{background:var(--violet)}
.total{display:flex;justify-content:space-between;margin-top:1.2rem;padding-top:.9rem;border-top:1px solid rgba(255,255,255,.1);font-size:.85rem;color:var(--sub)}
.total b{color:var(--txt);font-variant-numeric:tabular-nums}
.quit{display:block;text-align:center;text-decoration:none;color:var(--sub);font-size:.9rem;padding:.9rem;border-radius:16px;border:1px solid rgba(255,255,255,.12)}
.quit:active{background:rgba(255,255,255,.06)}
.warn{background:rgba(255,176,32,.14);border:1px solid rgba(255,176,32,.35);color:#ffd88a;border-radius:14px;padding:.7rem .9rem;font-size:.85rem}
.fin{position:fixed;inset:0;display:grid;place-items:center;padding:1.5rem;background:rgba(11,31,36,.92);-webkit-backdrop-filter:blur(8px);backdrop-filter:blur(8px);text-align:center;z-index:5}
[hidden]{display:none!important}
.fin h2{font-size:1.4rem;margin-bottom:.4rem}.fin p{color:var(--sub);margin-bottom:1.2rem;font-size:.95rem}
.fin a{display:inline-block;background:var(--ambre);color:#2b1c00;font-weight:750;text-decoration:none;padding:.9rem 1.8rem;border-radius:16px}
:focus-visible{outline:3px solid var(--onde);outline-offset:2px}
@media (prefers-reduced-motion:reduce){*{animation:none!important;transition:none!important}}
</style>
</head>
<body>
<div class="wrap">
    <div class="top">
        @if($logo)<img src="{{ $logo }}" alt="">@endif
        <b>{{ $titre ?? 'Mon espace Wi-Fi' }}</b>
        <span class="pill" id="pill"><i></i><span id="pilltxt">Connexion…</span></span>
    </div>

    <div class="warn" id="warn" hidden role="status">Connexion au routeur perdue. Nouvelle tentative…</div>

    <section class="card">
        <div class="lbl">Temps de connexion</div>
        <div class="temps" id="temps">--:--:--</div>
    </section>

    <section class="card meter">
        <div class="ring" aria-hidden="true">
            <svg viewBox="0 0 100 100"><circle class="bg" cx="50" cy="50" r="42"/><circle class="fg" id="ring" cx="50" cy="50" r="42"/></svg>
            <span id="reste">--</span>
        </div>
        <div>
            <div class="lbl">Montant à payer</div>
            <div class="montant" id="montant">-- Ar</div>
            <div class="note" id="note">Tarif : {{ $tarif_minute }} Ar/min. L'anneau indique les secondes avant la prochaine minute facturée.</div>
        </div>
    </section>

    <section class="card">
        <div class="dbit">
            <div class="row"><span class="lbl">Reçu (↓)</span><b class="down" id="vdown">0 o/s</b></div>
            <div class="bar"><i id="bdown"></i></div>
        </div>
        <div class="dbit">
            <div class="row"><span class="lbl">Envoyé (↑)</span><b class="up" id="vup">0 o/s</b></div>
            <div class="bar"><i id="bup"></i></div>
        </div>
        <div class="total"><span>Données utilisées</span><b id="total">0 o</b></div>
    </section>

    <a class="quit" href="http://{{ $mikrotik_ip }}/logout">Me déconnecter</a>
</div>

<div class="fin" id="fin" hidden>
    <div>
        <h2>Session terminée</h2>
        <p>Vous n'êtes plus connecté au Wi-Fi.</p>
        <a href="/portail">Se reconnecter</a>
    </div>
</div>

<script>
(function(){
    var MAC=@json($mac), POLL=3000, C=264;
    var $=function(i){return document.getElementById(i)};
    var S={up:0,at:0,on:false,prev:null,vd:0,vu:0,fails:0,timer:null};
    var p2=function(n){return n<10?'0'+n:''+n};

    function fmtT(s){s=Math.max(0,Math.floor(s));return p2(Math.floor(s/3600))+':'+p2(Math.floor(s%3600/60))+':'+p2(s%60)}
    function fmtB(b){if(b<1024)return Math.round(b)+' o';var u=['Ko','Mo','Go'],i=-1;do{b/=1024;i++}while(b>=1024&&i<2);return b.toFixed(b<10?2:1)+' '+u[i]}
    // Jauge logarithmique : 0 → 10 Mo/s
    function gauge(v){return Math.min(100,Math.log10(1+v)/7*100)}

    function pill(on,txt){var e=$('pill');e.className='pill'+(on?'':' off');$('pilltxt').textContent=txt}

    function show(d){
        var now=Date.now();
        if(S.prev){
            var dt=(now-S.prev.t)/1000;
            if(dt>0){
                S.vd=.5*S.vd+.5*Math.max(0,(d.bytes_down-S.prev.d)/dt);
                S.vu=.5*S.vu+.5*Math.max(0,(d.bytes_up-S.prev.u)/dt);
            }
        }
        S.prev={t:now,d:d.bytes_down,u:d.bytes_up};
        S.up=d.uptime;S.at=now;S.on=true;
        pill(true,'En ligne');
        $('montant').textContent=d.montant.toLocaleString('fr-FR')+' Ar';
        $('note').textContent=d.minimum_applique
            ? 'Minimum de '+d.tarif_minimum.toLocaleString('fr-FR')+' Ar facturé, puis '+d.tarif_minute+' Ar/min.'
            : 'Tarif : '+d.tarif_minute+' Ar/min. L\'anneau indique les secondes avant la prochaine minute facturée.';
        $('vdown').textContent=fmtB(S.vd)+'/s';$('vup').textContent=fmtB(S.vu)+'/s';
        $('bdown').style.width=gauge(S.vd)+'%';$('bup').style.width=gauge(S.vu)+'%';
        $('total').textContent=fmtB(d.bytes_down+d.bytes_up);
        tick();
    }

    function ended(){S.on=false;pill(false,'Hors ligne');$('fin').hidden=false}
    function warn(v){$('warn').hidden=!v}

    // Fait avancer le chrono chaque seconde entre deux sondages.
    function tick(){
        if(!S.on)return;
        var s=S.up+(Date.now()-S.at)/1000;
        $('temps').textContent=fmtT(s);
        var r=60-Math.floor(s%60);
        $('reste').textContent=r;
        $('ring').style.strokeDashoffset=C*(r/60);
    }

    function poll(){
        var ac=new AbortController(),to=setTimeout(function(){ac.abort()},5000);
        fetch('/api/portail/stats'+(MAC?'?mac='+encodeURIComponent(MAC):''),{signal:ac.signal,cache:'no-store',headers:{Accept:'application/json'}})
            .then(function(r){if(!r.ok)throw new Error(r.status);return r.json()})
            .then(function(d){S.fails=0;warn(false);d.connected?show(d):ended()})
            .catch(function(){if(++S.fails>=2)warn(true)})
            .then(function(){clearTimeout(to);next()});
    }
    function next(){clearTimeout(S.timer);if(!document.hidden&&$('fin').hidden)S.timer=setTimeout(poll,POLL)}

    // Économise batterie et routeur quand l'onglet est caché.
    document.addEventListener('visibilitychange',function(){if(!document.hidden){clearTimeout(S.timer);poll()}});

    setInterval(tick,1000);
    poll();
})();
</script>
</body>
</html>