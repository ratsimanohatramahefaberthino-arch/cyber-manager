<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover">
<meta name="theme-color" content="#0b1f24">
<title>{{ $titre ?? 'Connexion Wi-Fi' }}</title>
{{-- CSS inline : aucune requête externe, car avant connexion le client n'a pas Internet (seul le walled garden répond). --}}
<style>
:root{--nuit:#0b1f24;--onde:#3dd6d0;--ambre:#ffb020;--papier:#f4f7f6;--encre:#10262b;--gris:#5d7377;--err:#b3261e}
*{box-sizing:border-box;margin:0}
body{font-family:system-ui,-apple-system,"Segoe UI",Roboto,sans-serif;background:var(--nuit);color:var(--encre);min-height:100vh;min-height:100dvh;display:flex;flex-direction:column;-webkit-text-size-adjust:100%}
.hero{padding:max(2rem,env(safe-area-inset-top)) 1.5rem 5rem;text-align:center;color:#fff;background:radial-gradient(120% 90% at 50% 0,#1b5c63 0,transparent 70%),var(--nuit)}
.logo{width:76px;height:76px;margin:0 auto 1rem;border-radius:22px;background:#fff;display:grid;place-items:center;padding:10px;box-shadow:0 8px 30px rgba(61,214,208,.28)}
.logo img{max-width:100%;max-height:100%;object-fit:contain}
.logo svg{width:38px;height:38px;color:#12353c}
h1{font-size:1.6rem;line-height:1.2;font-weight:750;letter-spacing:-.01em}
.hero p{margin:.55rem auto 0;max-width:32ch;color:#b9d3d4;font-size:.95rem;line-height:1.45}
main{flex:1;background:var(--papier);border-radius:28px 28px 0 0;margin-top:-3rem;padding:1.6rem 1.25rem calc(1.5rem + env(safe-area-inset-bottom))}
.wrap{max-width:380px;margin:0 auto}
.erreur{display:flex;gap:.6rem;align-items:flex-start;background:#fdecea;color:var(--err);border:1px solid #f5c6c2;border-radius:14px;padding:.8rem .9rem;font-size:.9rem;line-height:1.35;margin-bottom:1.1rem}
.erreur svg{flex:none;width:20px;height:20px;margin-top:1px}
label{display:block;font-size:.85rem;font-weight:600;color:var(--gris);margin:0 0 .35rem .15rem}
.champ{position:relative;margin-bottom:1rem}
input{width:100%;height:54px;border:1.5px solid #cfdcdc;border-radius:14px;background:#fff;padding:0 1rem;font:inherit;font-size:16px;color:var(--encre);outline:0;transition:border-color .15s,box-shadow .15s}
input:focus{border-color:var(--onde);box-shadow:0 0 0 4px rgba(61,214,208,.2)}
#mdp{padding-right:3.2rem}
.oeil{position:absolute;right:.35rem;bottom:.35rem;width:44px;height:44px;border:0;background:none;color:var(--gris);border-radius:12px;display:grid;place-items:center;cursor:pointer}
.oeil:focus-visible,.btn:focus-visible{outline:3px solid var(--onde);outline-offset:2px}
.oeil svg{width:22px;height:22px}
.btn{width:100%;height:56px;margin-top:.4rem;border:0;border-radius:16px;background:var(--ambre);color:#2b1c00;font:inherit;font-size:1.05rem;font-weight:750;display:flex;align-items:center;justify-content:center;gap:.6rem;cursor:pointer;box-shadow:0 6px 18px rgba(255,176,32,.35);transition:transform .1s,filter .15s}
.btn:active{transform:scale(.98)}
.btn[disabled]{filter:saturate(.7) brightness(.95);cursor:wait}
.spin{display:none;width:20px;height:20px;border:3px solid rgba(43,28,0,.25);border-top-color:#2b1c00;border-radius:50%;animation:t .7s linear infinite}
.btn[disabled] .spin{display:block}
@keyframes t{to{transform:rotate(360deg)}}
.aide{margin-top:1.4rem;text-align:center;font-size:.88rem;color:var(--gris);line-height:1.5}
.tarif{display:inline-block;margin-top:.5rem;background:#e3eeee;color:var(--encre);border-radius:999px;padding:.35rem .85rem;font-size:.82rem;font-weight:600}
@media (prefers-reduced-motion:reduce){*{animation-duration:2s!important;transition:none!important}}
@media (min-width:640px){body{justify-content:center;align-items:center;padding:2rem}.hero{width:100%;max-width:440px;border-radius:28px 28px 0 0;padding-bottom:5rem}main{flex:none;width:100%;max-width:440px;border-radius:0 0 28px 28px;margin-top:0}.hero+main{margin-top:-3rem;border-radius:28px}body>.hero{padding-top:2rem}}
</style>
</head>
<body>
<header class="hero">
    <div class="logo">
        @if($logo)
            <img src="{{ $logo }}" alt="">
        @else
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="M2 8.8a15 15 0 0 1 20 0M5 12.5a10 10 0 0 1 14 0M8.5 16a5 5 0 0 1 7 0"/><circle cx="12" cy="19.5" r="1" fill="currentColor"/></svg>
        @endif
    </div>
    <h1>{{ $titre ?? 'Bienvenue' }}</h1>
    <p>{{ $message ?? 'Entrez votre identifiant pour accéder à Internet.' }}</p>
</header>

<main>
<div class="wrap">
    @if(!empty($erreur))
        <div class="erreur" role="alert">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M12 7.5v5M12 16h.01"/></svg>
            <span>{{ $erreur }}</span>
        </div>
    @endif

    <form id="f" action="{{ $login_url }}" method="POST" autocomplete="on">
        <input type="hidden" name="dst" value="{{ $dst }}">
        <input type="hidden" name="popup" value="true">

        <div class="champ">
            <label for="u">Identifiant</label>
            <input id="u" type="text" name="username" required autofocus autocomplete="username" autocapitalize="off" autocorrect="off" spellcheck="false" inputmode="text" placeholder="Ex : cm-4821">
        </div>

        <div class="champ">
            <label for="mdp">Mot de passe</label>
            <input id="mdp" type="password" name="password" required autocomplete="current-password" autocapitalize="off" placeholder="Votre mot de passe">
            <button type="button" class="oeil" id="oeil" aria-label="Afficher le mot de passe">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M2 12s3.6-7 10-7 10 7 10 7-3.6 7-10 7S2 12 2 12z"/><circle cx="12" cy="12" r="3"/></svg>
            </button>
        </div>

        <button class="btn" id="go" type="submit">
            <span class="spin" aria-hidden="true"></span>
            <span id="lbl">Se connecter</span>
        </button>
    </form>

    <p class="aide">
        Pas encore d'identifiant ? Demandez un ticket à la caisse.
        @if(!empty($tarif_minute))
            <br><span class="tarif">{{ $tarif_minute }} Ar/min · minimum {{ $tarif_minimum }} Ar</span>
        @endif
    </p>
</div>
</main>

<script>
(function(){
    var f=document.getElementById('f'),b=document.getElementById('go'),l=document.getElementById('lbl'),
        m=document.getElementById('mdp'),o=document.getElementById('oeil');
    o.addEventListener('click',function(){
        var v=m.type==='password';
        m.type=v?'text':'password';
        o.setAttribute('aria-label',v?'Masquer le mot de passe':'Afficher le mot de passe');
    });
    f.addEventListener('submit',function(){
        b.disabled=true;l.textContent='Connexion…';
        // Filet de sécurité : si le routeur ne répond pas, on réactive le bouton.
        setTimeout(function(){b.disabled=false;l.textContent='Se connecter';},15000);
    });
    // Retour arrière / cache de page (bfcache) : on remet le bouton à neuf.
    window.addEventListener('pageshow',function(){b.disabled=false;l.textContent='Se connecter';});
})();
</script>
</body>
</html>