@extends('layouts.cyber-manager', ['title' => 'Paramètres — Cyber Manager'])

@section('content')
<style>
    .cm-p-wrap { max-width: 1100px; margin: 0 auto; }
    .cm-p-grid { display: grid; grid-template-columns: minmax(0, 1.2fr) minmax(0, 1fr); gap: 24px; align-items: start; }
    @media (max-width: 900px) { .cm-p-grid { grid-template-columns: minmax(0, 1fr); } }
    .cm-p-card { background: #fff; border: 1px solid #e2e8f0; border-radius: 16px; padding: 24px; box-shadow: 0 1px 2px rgba(0,0,0,.04); }
    .cm-p-card h2 { font-size: 16px; font-weight: 700; color: #0f172a; margin-bottom: 4px; }
    .cm-p-card .cm-p-aide { font-size: 12px; color: #64748b; margin-bottom: 18px; }
    .cm-p-champ { margin-bottom: 16px; }
    .cm-p-champ label { display: block; font-size: 13px; font-weight: 600; color: #334155; margin-bottom: 6px; }
    .cm-p-champ input[type=text] { width: 100%; box-sizing: border-box; border: 1px solid #cbd5e1; border-radius: 10px; padding: 9px 12px; font-size: 14px; }
    .cm-p-champ small { display: block; font-size: 11px; color: #94a3b8; margin-top: 4px; }
    .cm-p-couleur { display: flex; gap: 10px; align-items: center; }
    .cm-p-couleur input[type=color] { width: 48px; height: 40px; padding: 2px; border: 1px solid #cbd5e1; border-radius: 10px; background: #fff; cursor: pointer; }
    .cm-p-drop { display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 8px; border: 2px dashed #cbd5e1; border-radius: 14px; padding: 22px; text-align: center; cursor: pointer; transition: all .15s; background: #f8fafc; }
    .cm-p-drop.survol { border-color: #6366f1; background: #eef2ff; }
    .cm-p-drop img { max-width: 220px; max-height: 120px; object-fit: contain; }
    .cm-p-drop p { font-size: 13px; color: #64748b; }
    .cm-p-btn { border: 0; border-radius: 10px; padding: 10px 20px; font-size: 14px; font-weight: 700; color: #fff; background: #4f46e5; cursor: pointer; }
    .cm-p-btn:hover { background: #4338ca; }
    .cm-p-btn-rouge { background: #fff; color: #dc2626; border: 1px solid #fecaca; padding: 6px 14px; font-size: 12px; margin-top: 10px; }
    .cm-p-btn-rouge:hover { background: #fef2f2; }
    .cm-p-ok { background: #ecfdf5; border: 1px solid #a7f3d0; color: #047857; padding: 10px 14px; border-radius: 10px; font-size: 14px; margin-bottom: 16px; }
    .cm-p-err { background: #fef2f2; border: 1px solid #fecaca; color: #b91c1c; padding: 10px 14px; border-radius: 10px; font-size: 13px; margin-bottom: 16px; }
    .cm-p-apercu { border-radius: 16px; padding: 28px 16px; text-align: center; background: #0b1226; color: #e2e8f0; position: relative; overflow: hidden; }
    .cm-p-apercu .halo { position: absolute; inset: 0; opacity: .25; }
    .cm-p-apercu .contenu { position: relative; }
    .cm-p-logo-def { width: 84px; height: 84px; border-radius: 22px; margin: 0 auto 14px; display: flex; align-items: center; justify-content: center; }
    .cm-p-apercu img.cm-p-logo { max-width: 200px; max-height: 110px; object-fit: contain; margin: 0 auto 14px; display: block; }
    .cm-p-nom { font-size: 30px; font-weight: 800; letter-spacing: 3px; text-transform: uppercase; line-height: 1.1; word-break: break-word; }
    .cm-p-slogan { font-size: 11px; letter-spacing: 3px; text-transform: uppercase; color: #94a3b8; margin-top: 6px; }
    .cm-p-pastille { display: inline-block; margin-top: 18px; padding: 6px 18px; border-radius: 10px; font-size: 14px; font-weight: 700; background: rgba(255,255,255,.07); border: 1px solid rgba(255,255,255,.1); }
</style>

<script>
function parametresApp(couleur, logoUrl, nom, slogan) {
    return {
        couleur, logoUrl, nom, slogan, survol: false, nomFichier: '',
        choisir(f) {
            if (!f) return;
            this.nomFichier = f.name;
            this.logoUrl = URL.createObjectURL(f);
        },
        deposer(e) {
            this.survol = false;
            const f = e.dataTransfer.files && e.dataTransfer.files[0];
            if (!f) return;
            try { this.$refs.fichier.files = e.dataTransfer.files; } catch (_) {}
            this.choisir(f);
        },
        confirmerSuppression(e) { if (!confirm('Supprimer le logo ?')) e.preventDefault(); },
    };
}
</script>

<div class="cm-p-wrap" x-data="parametresApp(@js($p['couleur_principale']), @js($logo ? route('agent.logo', ['v' => $logoVersion]) : null), @js($p['nom_cyber']), @js((string) $p['slogan']))">
    <div style="margin-bottom: 18px;">
        <h1 class="text-2xl font-semibold tracking-tight text-slate-900">Paramètres</h1>
        <p class="text-sm text-slate-500">Identité du cyber : affichée sur l'écran de verrouillage des postes et dans l'administration.</p>
    </div>

    @if (session('success')) <div class="cm-p-ok">{{ session('success') }}</div> @endif
    @if ($errors->any())
        <div class="cm-p-err">@foreach ($errors->all() as $e) <div>• {{ $e }}</div> @endforeach</div>
    @endif

    <div class="cm-p-grid">
        <form method="POST" action="{{ route('parametres.update') }}" enctype="multipart/form-data" class="cm-p-card">
            @csrf

            <h2>Identité</h2>
            <p class="cm-p-aide">Ces informations sont envoyées automatiquement à tous les postes clients.</p>

            <div class="cm-p-champ">
                <label for="nom_cyber">Nom du cyber</label>
                <input type="text" id="nom_cyber" name="nom_cyber" maxlength="60" required x-model="nom" value="{{ old('nom_cyber', $p['nom_cyber']) }}">
            </div>

            <div class="cm-p-champ">
                <label for="slogan">Slogan (facultatif)</label>
                <input type="text" id="slogan" name="slogan" maxlength="80" x-model="slogan" value="{{ old('slogan', $p['slogan']) }}">
                <small>Affiché sous le nom, sur l'écran de verrouillage.</small>
            </div>

            <div class="cm-p-champ">
                <label for="nom_admin">Nom de l'administrateur (facultatif)</label>
                <input type="text" id="nom_admin" name="nom_admin" maxlength="60" value="{{ old('nom_admin', $p['nom_admin']) }}">
                <small>Affiché en haut à droite à la place du nom du compte.</small>
            </div>

            <div class="cm-p-champ">
                <label>Couleur principale</label>
                <div class="cm-p-couleur">
                    <input type="color" x-model="couleur">
                    <input type="text" name="couleur_principale" maxlength="7" x-model="couleur" style="max-width: 140px; font-family: monospace;">
                </div>
                <small>Utilisée pour l'ambiance de l'écran de verrouillage et l'icône de l'agent.</small>
            </div>

            <div class="cm-p-champ">
                <label>Logo</label>
                <div class="cm-p-drop" :class="survol ? 'survol' : ''"
                     @click="$refs.fichier.click()"
                     @dragover.prevent="survol = true" @dragleave.prevent="survol = false" @drop.prevent="deposer($event)">
                    <template x-if="logoUrl"><img :src="logoUrl" alt="Logo"></template>
                    <p><strong>Glissez une image ici</strong> ou cliquez pour parcourir</p>
                    <p style="font-size: 11px;">PNG ou JPG · 2 Mo max · 64 × 64 px minimum (idéal : carré 512 × 512, fond transparent en PNG)</p>
                    <p x-show="nomFichier" x-cloak style="color:#4f46e5; font-weight:600;" x-text="nomFichier"></p>
                </div>
                <input type="file" name="logo" accept="image/png,image/jpeg" x-ref="fichier" style="display:none" @change="choisir($event.target.files[0])">
            </div>

            <button type="submit" class="cm-p-btn">Enregistrer</button>
        </form>

        <div>
            <div class="cm-p-card">
                <h2>Aperçu de l'écran de verrouillage</h2>
                <p class="cm-p-aide">Mise à jour en direct pendant la saisie.</p>
                <div class="cm-p-apercu">
                    <div class="halo" :style="`background: radial-gradient(circle at 70% 10%, ${couleur}, transparent 60%)`"></div>
                    <div class="contenu">
                        <template x-if="logoUrl"><img class="cm-p-logo" :src="logoUrl" alt=""></template>
                        <template x-if="!logoUrl">
                            <div class="cm-p-logo-def" :style="`background: linear-gradient(135deg, ${couleur}, #312e81)`">
                                <svg width="44" height="44" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="12" rx="2"/><path d="M8 20h8M12 16v4"/></svg>
                            </div>
                        </template>
                        <div class="cm-p-nom" x-text="nom || 'Cyber Manager'"></div>
                        <div class="cm-p-slogan" x-show="slogan" x-text="slogan"></div>
                        <div class="cm-p-pastille">POSTE 1</div>
                    </div>
                </div>
            </div>

            @if ($logo)
                <form method="POST" action="{{ route('parametres.logo.supprimer') }}" @submit="confirmerSuppression($event)" style="margin-top: 12px; text-align: right;">
                    @csrf @method('DELETE')
                    <button type="submit" class="cm-p-btn cm-p-btn-rouge">Supprimer le logo</button>
                </form>
            @endif
        </div>
    </div>
</div>
@endsection