<script>
function assistantInstall(init) {
    return {
        etape: 1, vu: false, t: 0, timer: null,
        maxId: init.maxId, dernierMax: init.maxId,
        nouveau: null, nom: '', enregistre: false, enCours: false, erreur: '', reseauKo: false,

        init() { this.$watch('etape', (e) => { e === 3 ? this.demarrer() : this.arreter(); }); },
        aller(n) { this.erreur = ''; this.etape = n; },

        demarrer() {
            this.arreter(); this.t = 0; this.verifier();
            this.timer = setInterval(() => { this.t++; if (this.t % 4 === 0) this.verifier(); }, 1000);
        },
        arreter() { if (this.timer) { clearInterval(this.timer); this.timer = null; } },

        async verifier() {
            try {
                const r = await cmFetch(init.urlPostes);
                if (!r.ok) throw new Error('http');
                const d = await r.json();
                this.reseauKo = false;
                this.dernierMax = d.max_id;
                const n = (d.postes || []).filter(p => p.id > this.maxId).sort((a, b) => b.id - a.id)[0];
                if (n && this.etape === 3) { this.nouveau = n; this.nom = n.nom; this.enregistre = false; this.etape = 4; }
            } catch (e) { this.reseauKo = true; }
        },

        async renommer() {
            if (!this.nouveau || this.enCours) return;
            this.enCours = true; this.erreur = '';
            try {
                const r = await cmFetch(init.urlRenommer.replace('__ID__', this.nouveau.id), {
                    method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ nom: this.nom }),
                });
                const d = await r.json().catch(() => ({}));
                if (r.ok && d.ok) { this.nom = d.nom; this.nouveau.nom = d.nom; this.enregistre = true; }
                else this.erreur = d.message || 'Impossible de renommer ce poste.';
            } catch (e) { this.erreur = 'Le serveur ne répond pas. Réessayez.'; }
            this.enCours = false;
        },

        recommencer() {
            this.maxId = this.dernierMax; this.nouveau = null; this.nom = ''; this.enregistre = false;
            this.t = 0; this.erreur = ''; this.etape = 1;
        },
    };
}
</script>

<section x-data="assistantInstall(@js($assistant))" class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">

    {{-- Bandeau d'étapes --}}
    <ol class="flex items-center gap-2 border-b border-slate-100 bg-gradient-to-r from-violet-50 to-slate-50 px-4 py-4 sm:px-6">
        @foreach ([['Télécharger', 'download'], ['Lancer', 'desktop'], ['Attendre', 'clock'], ['Renommer', 'pencil']] as $i => [$titre, $ic])
            @php $n = $i + 1; @endphp
            <li class="flex items-center gap-2 {{ $n < 4 ? 'flex-1' : '' }}">
                <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full transition"
                      :class="etape === {{ $n }} ? 'bg-violet-600 text-white shadow-md shadow-violet-200' : (etape > {{ $n }} ? 'bg-emerald-500 text-white' : 'bg-slate-200 text-slate-500')">
                    <x-icone nom="{{ $ic }}" classe="h-4 w-4" x-show="etape <= {{ $n }}" />
                    <x-icone nom="check" classe="h-5 w-5" x-show="etape > {{ $n }}" x-cloak />
                </span>
                <span class="hidden text-sm font-semibold sm:inline"
                      :class="etape === {{ $n }} ? 'text-violet-700' : 'text-slate-500'">{{ $n }}. {{ $titre }}</span>
                @if ($n < 4)
                    <span class="h-0.5 flex-1 rounded-full transition" :class="etape > {{ $n }} ? 'bg-emerald-400' : 'bg-slate-200'"></span>
                @endif
            </li>
        @endforeach
    </ol>

    <div class="p-5 sm:p-8">

        {{-- ═══ Étape 1 ═══ --}}
        <div x-show="etape === 1" x-transition.opacity class="space-y-4">
            <div>
                <h2 class="text-xl font-bold text-slate-900">1. Téléchargez l'installateur</h2>
                <p class="mt-1 text-sm text-slate-600">
                    Vous obtenez un dossier .zip qui contient l'installateur et l'adresse de ce serveur. Faites un clic droit dessus,
                    « Extraire tout », puis copiez le dossier obtenu sur une clé USB.
                </p>
            </div>
            @if ($peutTelecharger)
                <a href="{{ route('parametres.installation.telecharger') }}"
                   class="inline-flex items-center gap-2 rounded-xl bg-violet-600 px-5 py-3 text-sm font-semibold text-white shadow-md shadow-violet-200 transition hover:bg-violet-700 focus:outline-none focus-visible:ring-2 focus-visible:ring-violet-500 focus-visible:ring-offset-2">
                    <x-icone nom="download" classe="h-5 w-5" /> Télécharger l'installateur
                </a>
            @else
                <p class="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">
                    L'installateur n'est pas encore disponible sur le serveur. Voyez la carte « Manquant » plus bas : elle indique où le placer.
                    <a href="#fichiers" class="font-semibold underline">Voir les fichiers</a>
                </p>
            @endif
            <div class="flex justify-end">
                <button type="button" @click="aller(2)"
                        class="inline-flex h-10 items-center gap-2 rounded-xl border border-slate-300 bg-white px-4 text-sm font-semibold text-slate-700 hover:bg-slate-50">
                    C'est fait, étape suivante →
                </button>
            </div>
        </div>

        {{-- ═══ Étape 2 ═══ --}}
        <div x-show="etape === 2" x-cloak x-transition.opacity class="space-y-5">
            <div>
                <h2 class="text-xl font-bold text-slate-900">2. Lancez l'installateur sur le nouveau poste</h2>
                <p class="mt-1 text-sm text-slate-600">
                    Branchez la clé sur le poste Windows. Faites un clic droit sur
                    <code class="rounded bg-slate-100 px-1.5 py-0.5 text-xs font-semibold text-slate-800">{{ $nomExe }}</code>
                    puis <strong>Exécuter en tant qu'administrateur</strong>.
                </p>
            </div>

            <div class="grid gap-4 md:grid-cols-2">
                <div class="rounded-xl border border-violet-100 bg-violet-50/60 p-4">
                    <p class="text-xs font-bold uppercase tracking-wider text-violet-700">Adresse du serveur</p>
                    <div class="mt-2 flex items-center justify-between gap-2">
                        <span class="min-w-0 break-all font-mono text-base font-bold text-slate-900">{{ $url }}</span>
                        <button type="button" @click="cmCopy(@js($url))" aria-label="Copier l'adresse"
                                class="shrink-0 rounded-lg p-2 text-slate-500 hover:bg-white hover:text-slate-800"><x-icone nom="copy" classe="h-4 w-4" /></button>
                    </div>
                    <p class="mt-2 text-xs text-slate-500">Déjà écrite dans le dossier téléchargé : à ne saisir que si l'installateur la demande.</p>
                </div>

                <div class="rounded-xl border border-violet-100 bg-violet-50/60 p-4">
                    <p class="text-xs font-bold uppercase tracking-wider text-violet-700">Code d'installation</p>
                    @if ($code)
                        <div class="mt-2 flex items-center justify-between gap-2">
                            <span class="min-w-0 truncate font-mono text-lg font-bold tracking-widest text-violet-700"
                                  x-text="vu ? @js($code) : '••••-••••'"></span>
                            <span class="flex shrink-0 items-center gap-1">
                                <button type="button" @click="vu = !vu" :aria-label="vu ? 'Masquer le code' : 'Afficher le code'"
                                        class="rounded-lg p-2 text-slate-500 hover:bg-white hover:text-slate-800">
                                    <x-icone nom="eye" classe="h-4 w-4" x-show="!vu" />
                                    <x-icone nom="eye-off" classe="h-4 w-4" x-show="vu" x-cloak />
                                </button>
                                <button type="button" @click="cmCopy(@js($code))" aria-label="Copier le code"
                                        class="rounded-lg p-2 text-slate-500 hover:bg-white hover:text-slate-800"><x-icone nom="copy" classe="h-4 w-4" /></button>
                            </span>
                        </div>
                        <p class="mt-2 text-xs text-slate-500">Le même code sert pour tous les postes. Ne le montrez pas aux clients.</p>
                    @else
                        <a href="{{ route('enregistrement.index') }}" class="mt-2 inline-flex items-center gap-2 text-sm font-semibold text-violet-700 underline">
                            <x-icone nom="key" classe="h-4 w-4" /> Voir le code dans Enregistrement
                        </a>
                    @endif
                </div>
            </div>

            <p class="text-xs text-slate-500">
                Windows affiche un avertissement ? C'est normal, voir « Windows ou l'antivirus affiche un avertissement » plus bas.
            </p>

            <div class="flex flex-wrap justify-between gap-2">
                <button type="button" @click="aller(1)" class="h-10 rounded-xl px-4 text-sm font-semibold text-slate-500 hover:bg-slate-100">← Retour</button>
                <button type="button" @click="aller(3)"
                        class="inline-flex h-10 items-center rounded-xl bg-violet-600 px-5 text-sm font-semibold text-white shadow-sm hover:bg-violet-700">
                    Le programme est lancé →
                </button>
            </div>
        </div>

        {{-- ═══ Étape 3 ═══ --}}
        <div x-show="etape === 3" x-cloak x-transition.opacity class="space-y-5">
            <div class="flex items-center gap-4">
                <span class="relative flex h-14 w-14 shrink-0 items-center justify-center">
                    <span class="absolute inset-0 animate-ping rounded-full bg-violet-200 opacity-60"></span>
                    <span class="relative flex h-14 w-14 items-center justify-center rounded-full bg-violet-600 text-white"><x-icone nom="desktop" classe="h-7 w-7" /></span>
                </span>
                <div>
                    <h2 class="text-xl font-bold text-slate-900">3. En attente du poste…</h2>
                    <p class="text-sm text-slate-600">
                        Saisissez le code sur le poste puis cliquez sur « Installer ». Cette page détecte le poste toute seule
                        (<span x-text="t"></span> s).
                    </p>
                </div>
            </div>

            <p x-show="reseauKo" x-cloak class="rounded-xl border border-rose-200 bg-rose-50 px-4 py-2.5 text-sm text-rose-800">
                Cette page n'arrive plus à joindre le serveur. Nouvelle tentative automatique…
            </p>

            <div x-show="t >= 90" x-cloak class="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">
                <strong>Rien ne apparaît ?</strong> Vérifiez que le poste est sur le même réseau que ce serveur et que le code est correct.
                Si ce poste était déjà installé, il est déjà dans la liste : passez à l'étape suivante ou
                <a href="{{ route('postes.index') }}" class="font-semibold underline">ouvrez les postes</a>.
            </div>

            <div class="flex flex-wrap justify-between gap-2">
                <button type="button" @click="aller(2)" class="h-10 rounded-xl px-4 text-sm font-semibold text-slate-500 hover:bg-slate-100">← Retour</button>
                <a href="{{ route('postes.index') }}" class="inline-flex h-10 items-center rounded-xl border border-slate-300 bg-white px-4 text-sm font-semibold text-slate-700 hover:bg-slate-50">
                    Le poste est déjà dans la liste
                </a>
            </div>
        </div>

        {{-- ═══ Étape 4 ═══ --}}
        <div x-show="etape === 4" x-cloak x-transition.opacity class="space-y-5">
            <div class="flex items-center gap-4">
                <span class="flex h-14 w-14 shrink-0 items-center justify-center rounded-full bg-emerald-500 text-white"><x-icone nom="check" classe="h-8 w-8" /></span>
                <div>
                    <h2 class="text-xl font-bold text-slate-900">4. Poste enregistré !</h2>
                    <p class="text-sm text-slate-600">
                        Il apparaît dans la liste sous le nom
                        <strong class="text-slate-900" x-text="nouveau ? nouveau.nom : ''"></strong>.
                        Vous pouvez le renommer (par exemple « Poste 5 » ou « Caisse »).
                    </p>
                </div>
            </div>

            <form @submit.prevent="renommer()" class="flex flex-col gap-2 sm:flex-row sm:items-center">
                <input type="text" x-model="nom" maxlength="100" aria-label="Nom du poste" required
                       class="h-11 w-full rounded-xl border border-slate-300 bg-white px-3.5 text-sm text-slate-900 shadow-sm focus:border-violet-500 focus:ring-2 focus:ring-violet-500/20 sm:max-w-xs">
                <button type="submit" :disabled="enCours"
                        class="inline-flex h-11 items-center justify-center gap-2 rounded-xl bg-violet-600 px-5 text-sm font-semibold text-white shadow-sm hover:bg-violet-700 disabled:opacity-50">
                    <span x-show="enCours" x-cloak class="h-4 w-4 animate-spin rounded-full border-2 border-white border-t-transparent"></span>
                    Renommer
                </button>
                <span x-show="enregistre" x-cloak class="inline-flex items-center gap-1.5 text-sm font-semibold text-emerald-700">
                    <x-icone nom="check" classe="h-4 w-4" /> Nom enregistré
                </span>
            </form>
            <p x-show="erreur" x-cloak x-text="erreur" class="text-sm font-medium text-rose-600"></p>

            <div class="flex flex-wrap justify-between gap-2 border-t border-slate-100 pt-4">
                <button type="button" @click="recommencer()"
                        class="inline-flex h-10 items-center rounded-xl border border-slate-300 bg-white px-4 text-sm font-semibold text-slate-700 hover:bg-slate-50">
                    Installer un autre poste
                </button>
                <a href="{{ route('postes.index') }}" class="inline-flex h-10 items-center gap-2 rounded-xl bg-violet-600 px-5 text-sm font-semibold text-white shadow-sm hover:bg-violet-700">
                    <x-icone nom="desktop" classe="h-4 w-4" /> Voir les postes
                </a>
            </div>
        </div>
    </div>
</section>