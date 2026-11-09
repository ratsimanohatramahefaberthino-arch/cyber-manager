@extends('layouts.cyber-manager', ['title' => 'Postes — Cyber Manager'])

@section('content')
<script>
const POSTES_URL = @json(url('/postes'));

const COULEURS = {
    disponible: '#22c55e', occupe: '#f59e0b', suspendu: '#8b5cf6',
    verrouille: '#ef4444', desactive: '#94a3b8', hors_ligne: '#94a3b8',
};
const LIBELLES = {
    disponible: 'Libre', occupe: 'Utilisé', suspendu: 'En pause',
    verrouille: 'Verrouillé', desactive: 'Désactivé', hors_ligne: 'Non communiqué',
};
const MODES = { forfait: 'Forfait', postpaye: 'Postpayé', libre: 'Débloqué (sans facturation)' };

function svgIcone(etat, taille = 'h-9 w-9') {
    const c = COULEURS[etat] || '#94a3b8';
    let badge = '';
    if (etat === 'hors_ligne') {
        badge = '<circle cx="31" cy="31" r="8" fill="#dc2626" stroke="#fff" stroke-width="1.5"/>'
              + '<rect x="30" y="26" width="2" height="6" fill="#fff"/><rect x="30" y="33" width="2" height="2" fill="#fff"/>';
    } else if (etat === 'desactive') {
        badge = '<line x1="6" y1="30" x2="30" y2="6" stroke="#475569" stroke-width="3" stroke-linecap="round"/>';
    } else if (etat === 'verrouille') {
        badge = '<rect x="26" y="25" width="10" height="8" rx="1.5" fill="#fff" stroke="#b91c1c" stroke-width="1.5"/>'
              + '<path d="M28 25v-3a3 3 0 0 1 6 0v3" fill="none" stroke="#b91c1c" stroke-width="1.5"/>';
    } else if (etat === 'suspendu') {
        badge = '<rect x="12" y="10" width="3.5" height="11" fill="#fff"/><rect x="20.5" y="10" width="3.5" height="11" fill="#fff"/>';
    }
    return `<svg viewBox="0 0 38 38" class="${taille}">
        <rect x="3" y="4" width="26" height="19" rx="2.5" fill="${c}" stroke="#1e293b" stroke-width="1.5"/>
        <rect x="6" y="7" width="20" height="13" rx="1" fill="#0f172a" opacity=".35"/>
        <rect x="13" y="23" width="6" height="4" fill="#64748b"/><rect x="9" y="27" width="14" height="2.5" rx="1" fill="#475569"/>
        ${badge}</svg>`;
}

function fmtDuree(s) {
    if (s === null || s === undefined) return '—';
    s = Math.max(0, Math.floor(s));
    const p = n => String(n).padStart(2, '0');
    return `${p(Math.floor(s / 3600))}:${p(Math.floor((s % 3600) / 60))}:${p(s % 60)}`;
}
const fmtAr = n => (Number(n) || 0).toLocaleString('fr-FR') + ' Ar';

const BOUTONS = {
    vert:   'bg-emerald-600 hover:bg-emerald-700 text-white',
    rouge:  'bg-rose-600 hover:bg-rose-700 text-white',
    ambre:  'bg-amber-500 hover:bg-amber-600 text-white',
    violet: 'bg-violet-600 hover:bg-violet-700 text-white',
    bleu:   'bg-sky-600 hover:bg-sky-700 text-white',
    gris:   'bg-slate-600 hover:bg-slate-700 text-white',
};

function postesApp() {
    return {
        postes: @json($postes),
        selId: null,
        menu: { ouvert: false, x: 0, y: 0, id: null },
        modal: null,
        form: { duree: 60, type: 'forfait', minutes: 30 },
        hs: { username: '', password: '', voir: false, erreur: '' },
        chat: [], chatDernier: 0, texte: '',
        nonLusVus: {},
        maintenant: Date.now(), recuLe: Date.now(),
        horsServeur: false, notif: null, occupe: false,

        init() {
            this.selId = this.postes[0]?.id ?? null;
            this.postes.forEach(p => this.nonLusVus[p.id] = p.non_lus ?? 0);
            if (this.selId) this.chargerChat(true);
            setInterval(() => this.maintenant = Date.now(), 1000);
            setInterval(() => this.rafraichir(), 3000);
            document.addEventListener('click', () => this.menu.ouvert = false);
            document.addEventListener('keydown', e => { if (e.key === 'Escape') { this.menu.ouvert = false; this.modal = null; } });
        },

        get sel() { return this.postes.find(p => p.id === this.selId) || null; },
        get bilan() {
            const n = e => this.postes.filter(p => p.etat === e).length;
            return `${this.postes.length} poste(s) — ${n('occupe') + n('suspendu') + n('verrouille')} utilisé(s), ${n('hors_ligne')} non communiqué(s)`;
        },
        get totalNonLus() {
            return this.postes.filter(p => p.id !== this.selId).reduce((t, p) => t + (p.non_lus || 0), 0);
        },

        icone: svgIcone,
        libelle(e) { return LIBELLES[e] || e; },
        mode(m) { return MODES[m] || m; },
        classeBouton(c) { return BOUTONS[c] || BOUTONS.gris; },
        fmtAr,
        titreModal() {
            const t = { demarrer: 'Démarrer — ', recharger: 'Recharger — ', hotspot: 'Identifiants HotSpot — ' };
            return (t[this.modal] || '') + (this.sel?.nom ?? '');
        },

        decalage(p) {
            if (!p.session || !['occupe', 'verrouille'].includes(p.etat)) return 0;
            return Math.floor((this.maintenant - this.recuLe) / 1000);
        },
        ecoule(p) { return p.session ? fmtDuree(p.session.ecoule_s + this.decalage(p)) : '—'; },
        restant(p) {
            if (!p.session || p.session.restant_s === null) return '—';
            return fmtDuree(p.session.restant_s - this.decalage(p));
        },
        tempsListe(p) {
            if (!p.session) return '';
            return p.session.restant_s !== null ? this.restant(p) : this.ecoule(p);
        },

        async rafraichir() {
            try {
                const r = await cmFetch(POSTES_URL + '/etat');
                const d = await r.json();
                for (const p of d.postes) {
                    const avant = this.nonLusVus[p.id] ?? 0;
                    if ((p.non_lus || 0) > avant && p.id !== this.selId) {
                        this.afficher(`Nouveau message de ${p.nom}`, true);
                    }
                    this.nonLusVus[p.id] = p.non_lus || 0;
                }
                this.postes = d.postes;
                this.recuLe = Date.now();
                this.horsServeur = false;
                if (this.selId && !this.sel) this.selId = null;
                if (this.selId) this.chargerChat(false);
            } catch (e) { this.horsServeur = true; }
        },

        choisir(id) { this.selId = id; this.chat = []; this.chatDernier = 0; this.chargerChat(true); },

        ouvrirMenu(e, id) {
            this.selId = id;
            this.menu = { ouvert: true, x: Math.min(e.clientX, window.innerWidth - 220), y: Math.min(e.clientY, window.innerHeight - 320), id };
            this.chat = []; this.chatDernier = 0; this.chargerChat(true);
        },

        async lancer(id, action) {
            this.menu.ouvert = false;
            this.selId = id;
            const def = (this.postes.find(p => p.id === id)?.actions || []).find(a => a.cle === action);
            if (!def) return;
            if (def.modal) { this.modal = action; return; }
            if (def.confirm && !confirm(def.confirm)) return;
            await this.envoyer(id, action, {});
        },

        async envoyer(id, action, corps) {
            if (this.occupe) return;
            this.occupe = true;
            try {
                const r = await cmFetch(`${POSTES_URL}/${id}/${action}`, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(corps),
                });
                const d = await r.json().catch(() => ({}));
                this.afficher(d.message || (r.ok ? 'Action effectuée.' : 'Erreur.'), r.ok);
                if (r.ok) this.modal = null;
            } catch (e) { this.afficher('Serveur injoignable.', false); }
            this.occupe = false;
            await this.rafraichir();
        },

        validerModal() {
            const id = this.selId;
            if (this.modal === 'demarrer') {
                this.envoyer(id, 'demarrer', { type: this.form.type, duree_minutes: this.form.type === 'forfait' ? Number(this.form.duree) : null });
            } else if (this.modal === 'recharger') {
                this.modal = null;
                const p = this.postes.find(x => x.id === id);
                if (p && p.session && p.session.restant_s !== null) {
                    p.session.restant_s += (Number(this.form.minutes) * 60);
                    p.session.duree_min += Number(this.form.minutes);
                }
                this.afficher("Rechargement en cours...", true);
                this.envoyer(id, 'recharger', { minutes: Number(this.form.minutes) });
            } else if (this.modal === 'hotspot') {
                this.enregistrerHotspot();
            }
        },

        ouvrirHotspot() {
            if (!this.sel) return;
            this.hs = { username: this.sel.hotspot_username || '', password: '', voir: false, erreur: '' };
            this.modal = 'hotspot';
        },

        async enregistrerHotspot() {
            if (this.occupe) return;
            const u = this.hs.username.trim(), p = this.hs.password.trim();
            if (/\s/.test(u) || /\s/.test(p)) { this.hs.erreur = "Aucun espace n'est autorisé."; return; }
            if (u && u.length < 3) { this.hs.erreur = "L'identifiant doit contenir au moins 3 caractères."; return; }
            if (p && p.length < 4) { this.hs.erreur = 'Le mot de passe doit contenir au moins 4 caractères.'; return; }

            this.hs.erreur = '';
            this.occupe = true;
            try {
                const r = await cmFetch(`${POSTES_URL}/${this.selId}/hotspot`, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ hotspot_username: u || null, hotspot_password: p || null }),
                });
                const d = await r.json().catch(() => ({}));
                if (r.ok) {
                    this.modal = null;
                    this.afficher(d.message || 'Identifiants enregistrés.', true, 7000);
                } else {
                    this.hs.erreur = (d.errors && Object.values(d.errors)[0]?.[0]) || d.message || 'Erreur.';
                }
            } catch (e) { this.hs.erreur = 'Serveur injoignable.'; }
            this.occupe = false;
            await this.rafraichir();
        },

        async chargerChat(reset) {
            const id = this.selId;
            if (!id) return;
            try {
                const r = await cmFetch(`${POSTES_URL}/${id}/chat?apres=${reset ? 0 : this.chatDernier}`);
                const d = await r.json();
                if (id !== this.selId) return;
                if (reset) this.chat = [];
                if (d.messages?.length) {
                    this.chat.push(...d.messages);
                    this.chatDernier = d.messages[d.messages.length - 1].id;
                    this.$nextTick(() => { const b = this.$refs.chatBox; if (b) b.scrollTop = b.scrollHeight; });
                }
            } catch (e) {}
        },

        async envoyerMessage() {
            const t = this.texte.trim();
            if (!t || !this.selId) return;
            this.texte = '';
            await this.envoyer(this.selId, 'message', { message: t });
            this.chargerChat(false);
        },

        afficher(msg, ok, ms = 3000) {
            this.notif = { msg, ok };
            setTimeout(() => this.notif = null, ms);
        },
    };
}
</script>

{{-- LE CONTENEUR PRINCIPAL EST EN FLEX-ROW : 2 COLONNES INDEPENDAMMENT DE LA LARGEUR --}}
<div x-data="postesApp()" x-init="init()" class="mx-auto flex h-[calc(100vh-7.5rem)] max-w-[1500px] flex-col">

    <div class="mb-3 flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-semibold tracking-tight text-slate-900">Postes clients</h1>
            <p class="text-sm text-slate-500" x-text="bilan"></p>
        </div>
        <div class="flex items-center gap-2">
            <span x-show="totalNonLus > 0" x-cloak class="rounded-full bg-amber-100 px-3 py-1 text-xs font-semibold text-amber-800">
                💬 <span x-text="totalNonLus"></span> message(s) non lu(s)
            </span>
            <span x-show="horsServeur" x-cloak class="rounded-full bg-rose-50 px-3 py-1 text-xs font-semibold text-rose-700">
                Connexion au serveur perdue — nouvelle tentative…
            </span>
        </div>
    </div>

    {{-- DEBUT DU TABLEAU A 2 COLONNES --}}
    <div class="flex min-h-0 flex-1 flex-row gap-4">

        {{-- COLONNE GAUCHE : liste verticale (largeur fixe) --}}
        <aside class="flex w-80 shrink-0 flex-col overflow-hidden rounded-2xl border border-indigo-950 bg-indigo-950 shadow-sm">
            <div class="border-b border-white/10 px-4 py-3 text-xs font-semibold uppercase tracking-wider text-indigo-300">
                Postes de travail
            </div>
            <div class="flex-1 space-y-1.5 overflow-y-auto p-2">
                <template x-for="p in postes" :key="p.id">
                    <button type="button"
                            @click="choisir(p.id)"
                            @contextmenu.prevent.stop="ouvrirMenu($event, p.id)"
                            :class="selId === p.id ? 'bg-white text-slate-900 shadow' : 'bg-white/5 text-indigo-100 hover:bg-white/10'"
                            class="flex w-full items-center gap-3 rounded-xl px-3 py-2.5 text-left transition">
                        <span x-html="icone(p.etat)" class="shrink-0"></span>
                        <span class="min-w-0 flex-1">
                            <span class="block truncate text-sm font-semibold" x-text="p.nom"></span>
                            <span class="block truncate text-xs"
                                  :class="selId === p.id ? 'text-slate-500' : 'text-indigo-300'"
                                  x-text="libelle(p.etat)"></span>
                        </span>
                        <span x-show="p.non_lus > 0 && selId !== p.id" x-cloak
                              class="shrink-0 rounded-full bg-amber-400 px-1.5 text-[10px] font-bold text-slate-900"
                              x-text="'💬 ' + p.non_lus"></span>
                        <span x-show="p.session" x-cloak class="shrink-0 font-mono text-xs font-bold"
                              :class="selId === p.id ? 'text-slate-700' : 'text-amber-300'"
                              x-text="tempsListe(p)"></span>
                    </button>
                </template>

                <div x-show="postes.length === 0" x-cloak class="p-6 text-center text-sm text-indigo-300">
                    Aucun poste enregistré. Lancez l'agent sur un PC : il s'annoncera automatiquement.
                </div>
            </div>
            <div class="border-t border-white/10 px-4 py-2 text-[11px] text-indigo-400">
                Clic droit sur un poste pour ses actions.
            </div>
        </aside>

        {{-- COLONNE DROITE : détails (prend tout l'espace restant) --}}
        <section class="flex min-h-0 flex-1 flex-col overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">

            <template x-if="!sel">
                <div class="flex flex-1 flex-col items-center justify-center p-10 text-center text-slate-400">
                    <div class="mb-3 opacity-40" x-html="icone('disponible', 'h-16 w-16')"></div>
                    Sélectionnez un poste dans la liste pour voir ses détails.
                </div>
            </template>

            <template x-if="sel">
                <div class="flex min-h-0 flex-1 flex-col">
                    <div class="flex items-center gap-3 border-b border-slate-100 bg-slate-50 px-6 py-4">
                        <span x-html="icone(sel.etat, 'h-12 w-12')"></span>
                        <div class="min-w-0 flex-1">
                            <h2 class="truncate text-xl font-bold text-slate-900" x-text="sel.nom"></h2>
                            <p class="text-xs text-slate-500">
                                <span x-text="sel.ip || 'IP inconnue'"></span> ·
                                <span x-text="sel.mac || 'MAC inconnue'"></span>
                                <span x-show="sel.version"> · agent v<span x-text="sel.version"></span></span>
                            </p>
                        </div>
                        <button type="button" @click="ouvrirHotspot()"
                                class="rounded-lg border border-slate-300 bg-white px-3 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-100"
                                title="Compte HotSpot MikroTik de ce poste">
                            ⚙ Identifiants HotSpot
                        </button>
                        <span class="rounded-full px-3 py-1 text-xs font-bold text-white"
                              :style="`background:${({disponible:'#16a34a',occupe:'#d97706',suspendu:'#7c3aed',verrouille:'#dc2626',desactive:'#64748b',hors_ligne:'#64748b'})[sel.etat]}`"
                              x-text="libelle(sel.etat)"></span>
                    </div>

                    <div class="grid min-h-0 flex-1 grid-cols-1 gap-0 overflow-y-auto lg:grid-cols-2">

                        {{-- Informations --}}
                        <div class="space-y-2 p-6">
                            <template x-for="[nom, val, fort] in [
                                ['État', libelle(sel.etat), true],
                                ['Type de session', sel.session ? mode(sel.session.mode) : '—', false],
                                ['Heure de début', sel.session?.debut ?? '—', false],
                                ['Temps écoulé', ecoule(sel), false],
                                ['Temps restant', restant(sel), true],
                                ['Montant actuel', sel.session ? fmtAr(sel.session.montant) : '—', true],
                                ['Compte HotSpot', sel.hotspot_configure ? sel.hotspot_username : 'Non configuré', !sel.hotspot_configure],
                            ]" :key="nom">
                                <div class="flex items-center gap-3">
                                    <span class="w-40 shrink-0 text-right text-sm text-slate-500" x-text="nom"></span>
                                    <span class="flex-1 rounded border border-amber-100 bg-amber-50/70 px-3 py-1.5 font-mono text-sm text-slate-900"
                                          :class="(fort ? 'font-bold ' : '') + (nom === 'Compte HotSpot' && !sel.hotspot_configure ? 'text-rose-600' : '')"
                                          x-text="val"></span>
                                </div>
                            </template>
                            <p x-show="!sel.hotspot_configure" x-cloak class="pl-[10.75rem] text-xs text-rose-600">
                                Sans compte HotSpot, Internet ne sera pas activé pour ce poste.
                            </p>
                        </div>

                        {{-- Chat --}}
                        <div class="flex min-h-[280px] flex-col border-t border-slate-100 p-6 lg:border-l lg:border-t-0">
                            <h3 class="mb-2 text-sm font-semibold text-slate-700">Discussion avec le client</h3>
                            <div x-ref="chatBox" class="flex-1 space-y-2 overflow-y-auto rounded-xl border border-slate-200 bg-slate-50 p-3">
                                <template x-for="m in chat" :key="m.id">
                                    <div :class="m.sens === 'admin' ? 'text-right' : 'text-left'">
                                        <span class="inline-block max-w-[85%] rounded-2xl px-3 py-1.5 text-sm"
                                              :class="m.sens === 'admin' ? 'bg-indigo-600 text-white' : 'bg-white text-slate-800 ring-1 ring-slate-200'"
                                              x-text="m.contenu"></span>
                                        <div class="text-[10px] text-slate-400" x-text="m.heure"></div>
                                    </div>
                                </template>
                                <p x-show="chat.length === 0" x-cloak class="py-6 text-center text-xs text-slate-400">Aucun message.</p>
                            </div>
                            <form @submit.prevent="envoyerMessage()" class="mt-2 flex gap-2">
                                <input x-model="texte" maxlength="500" placeholder="Ex : il vous reste 5 minutes"
                                       :disabled="sel.etat === 'hors_ligne' || sel.etat === 'desactive'"
                                       class="flex-1 rounded-lg border-slate-300 text-sm disabled:bg-slate-100">
                                <button type="submit" :disabled="!texte.trim() || sel.etat === 'hors_ligne' || sel.etat === 'desactive'"
                                        class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700 disabled:opacity-40">
                                    Envoyer
                                </button>
                            </form>
                        </div>
                    </div>

                    {{-- Barre d'actions (dynamique selon l'état) --}}
                    <div class="flex flex-wrap items-center gap-2 border-t border-slate-100 bg-slate-50 px-6 py-3">
                        <template x-for="a in sel.actions" :key="a.cle">
                            <button type="button" @click="lancer(sel.id, a.cle)" :disabled="occupe"
                                    :class="classeBouton(a.couleur)"
                                    class="rounded-lg px-4 py-2 text-sm font-semibold shadow-sm transition disabled:opacity-50"
                                    x-text="a.label"></button>
                        </template>
                        <span x-show="sel.actions.length === 0" x-cloak class="text-sm text-slate-400">Aucune action disponible.</span>
                    </div>
                </div>
            </template>
        </section>
    </div>

    {{-- Menu contextuel (clic droit) --}}
    <div x-show="menu.ouvert" x-cloak @click.stop
         :style="`left:${menu.x}px; top:${menu.y}px`"
         class="fixed z-[9400] w-52 overflow-hidden rounded-xl border border-slate-200 bg-white py-1 shadow-2xl">
        <template x-for="a in (postes.find(p => p.id === menu.id)?.actions || [])" :key="a.cle">
            <button type="button" @click="lancer(menu.id, a.cle)"
                    class="flex w-full items-center gap-2 px-4 py-2 text-left text-sm text-slate-700 hover:bg-indigo-50">
                <span class="h-2 w-2 rounded-full" :class="{
                    'bg-emerald-500': a.couleur==='vert', 'bg-rose-500': a.couleur==='rouge', 'bg-amber-500': a.couleur==='ambre',
                    'bg-violet-500': a.couleur==='violet', 'bg-sky-500': a.couleur==='bleu', 'bg-slate-500': a.couleur==='gris' }"></span>
                <span x-text="a.label"></span>
            </button>
        </template>
        <p x-show="(postes.find(p => p.id === menu.id)?.actions || []).length === 0" class="px-4 py-2 text-xs text-slate-400">Aucune action.</p>
    </div>

    {{-- Modales Démarrer / Recharger / Identifiants HotSpot --}}
    <div x-show="modal" x-cloak class="fixed inset-0 z-[9300] flex items-center justify-center p-4">
        <div class="absolute inset-0 bg-slate-900/60" @click="modal = null"></div>
        <div class="relative w-full max-w-md rounded-2xl bg-white shadow-2xl">
            <div class="border-b border-slate-100 p-5">
                <h2 class="text-lg font-bold" x-text="titreModal()"></h2>
            </div>
            <form @submit.prevent="validerModal()">
                <div class="space-y-4 p-5">
                    <template x-if="modal === 'demarrer'">
                        <div class="space-y-4">
                            <div>
                                <label class="mb-1 block text-sm font-medium">Type de session</label>
                                <select x-model="form.type" class="w-full rounded-lg border-slate-300">
                                    <option value="forfait">Forfait (durée limitée)</option>
                                    <option value="postpaye">Postpayé (compteur libre)</option>
                                </select>
                            </div>
                            <div x-show="form.type === 'forfait'">
                                <label class="mb-1 block text-sm font-medium">Durée (minutes)</label>
                                <input type="number" x-model="form.duree" min="1" max="1440" class="w-full rounded-lg border-slate-300">
                                <div class="mt-2 flex gap-2">
                                    <template x-for="m in [15, 30, 60, 120]" :key="m">
                                        <button type="button" @click="form.duree = m"
                                                class="rounded-full border border-slate-300 px-3 py-1 text-xs hover:bg-slate-50" x-text="m + ' min'"></button>
                                    </template>
                                </div>
                            </div>
                        </div>
                    </template>
                    <template x-if="modal === 'recharger'">
                        <div>
                            <label class="mb-1 block text-sm font-medium">Minutes à ajouter</label>
                            <input type="number" x-model="form.minutes" min="1" max="1440" class="w-full rounded-lg border-slate-300">
                        </div>
                    </template>
                    <template x-if="modal === 'hotspot'">
                        <div class="space-y-4">
                            <p class="text-xs text-slate-500">
                                Compte HotSpot créé dans MikroTik pour ce poste (ex : POSTE2). Laissez l'identifiant vide pour dissocier le poste.
                            </p>
                            <div>
                                <label class="mb-1 block text-sm font-medium">Identifiant</label>
                                <input x-model="hs.username" maxlength="64" autocomplete="off" spellcheck="false"
                                       class="w-full rounded-lg border-slate-300 font-mono">
                            </div>
                            <div>
                                <label class="mb-1 block text-sm font-medium">Mot de passe</label>
                                <div class="flex gap-2">
                                    <input :type="hs.voir ? 'text' : 'password'" x-model="hs.password" maxlength="64"
                                           autocomplete="new-password" spellcheck="false"
                                           :placeholder="sel?.hotspot_configure ? 'Laisser vide pour conserver l\'actuel' : ''"
                                           class="w-full rounded-lg border-slate-300 font-mono">
                                    <button type="button" @click="hs.voir = !hs.voir"
                                            class="rounded-lg border border-slate-300 px-3 text-xs hover:bg-slate-50" x-text="hs.voir ? 'Masquer' : 'Afficher'"></button>
                                </div>
                            </div>
                            <p x-show="hs.erreur" x-cloak class="rounded-lg bg-rose-50 px-3 py-2 text-sm text-rose-700" x-text="hs.erreur"></p>
                        </div>
                    </template>
                </div>
                <div class="flex justify-end gap-2 rounded-b-2xl bg-slate-50 p-4">
                    <button type="button" @click="modal = null" class="rounded-lg border bg-white px-4 py-2 text-sm">Annuler</button>
                    <button type="submit" :disabled="occupe" class="rounded-lg bg-emerald-600 px-5 py-2 text-sm font-semibold text-white hover:bg-emerald-700 disabled:opacity-50"
                            x-text="modal === 'hotspot' ? 'Enregistrer' : 'Valider'"></button>
                </div>
            </form>
        </div>
    </div>

    {{-- Notification --}}
    <div x-show="notif" x-cloak x-transition
         class="fixed bottom-6 right-6 z-[9500] max-w-md rounded-xl px-4 py-3 text-sm font-medium text-white shadow-lg"
         :class="notif?.ok ? 'bg-emerald-600' : 'bg-rose-600'" x-text="notif?.msg"></div>
</div>
@endsection