@extends('layouts.cyber-manager', ['title' => 'Centre de contrôle du Portail Captif'])

@section('content')
@php
    $portPortail  = request()->getPort();
    $urlPortail   = 'http://inside.net' . (in_array($portPortail, [80, 443]) ? '' : ':' . $portPortail) . '/portail';
    $ipServeur    = env('SERVER_IP') ?: request()->server('SERVER_ADDR');
    $ipInvalide   = !$ipServeur || in_array($ipServeur, ['127.0.0.1', '::1']) || str_starts_with((string) $ipServeur, '169.254');
    $logoInitial  = $logo ? Storage::url($logo) : '';
    $ouvrirReseau = session()->has('error') || str_contains((string) session('success'), 'Routeur');
@endphp

<style>[x-cloak]{display:none!important}</style>

<div class="max-w-[1500px] mx-auto pb-10"
     x-data="{
        tab: @js($ouvrirReseau ? 'reseau' : 'general'),
        mode: 'mobile',
        titre: @js($titre),
        message: @js($message),
        logoUrl: @js($logoInitial),
        logoNom: @js($logo ? basename($logo) : ''),
        logoErreur: '',
        initial: { titre: @js($titre), message: @js($message), logoUrl: @js($logoInitial) },
        urlPortail: @js($urlPortail),
        copie: false,
        pret: false,
        scale: 1,
        devices: {
            mobile:  { w: 360,  h: 740, bezel: 12, top: 0,  bottom: 0  },
            desktop: { w: 1280, h: 760, bezel: 10, top: 34, bottom: 46 }
        },

        get dev() { return this.devices[this.mode]; },
        get monitorW() { return this.dev.w + this.dev.bezel * 2; },
        get outerW() { return this.monitorW; },
        get outerH() { return this.dev.h + this.dev.top + this.dev.bezel * 2 + this.dev.bottom; },
        get modifie() {
            return this.titre !== this.initial.titre
                || this.message !== this.initial.message
                || this.logoUrl !== this.initial.logoUrl;
        },

        init() {
            this.$nextTick(() => this.ajuster());
            if (window.ResizeObserver) {
                new ResizeObserver(() => this.ajuster()).observe(this.$refs.stage);
            } else {
                window.addEventListener('resize', () => this.ajuster());
            }
            this.$watch('mode', () => this.$nextTick(() => this.ajuster()));
            ['titre', 'message', 'logoUrl'].forEach(k => this.$watch(k, () => this.synchroniser()));
        },

        ajuster() {
            const s = this.$refs.stage;
            if (!s) return;
            const marge = 32;
            const sc = Math.min(1, (s.clientWidth - marge) / this.outerW, (s.clientHeight - marge) / this.outerH);
            this.scale = Math.max(0.15, sc);
        },

        apresChargement() {
            this.pret = true;
            try {
                const d = this.$refs.frame.contentDocument;
                const style = d.createElement('style');
                style.textContent = 'html,body{scrollbar-width:none;-ms-overflow-style:none}::-webkit-scrollbar{display:none}';
                d.head.appendChild(style);
                // Aperçu : on empêche l'envoi réel du formulaire vers MikroTik
                d.addEventListener('submit', e => e.preventDefault(), true);
            } catch (e) {}
            this.synchroniser();
        },

        synchroniser() {
            try {
                const d = this.$refs.frame.contentDocument;
                if (!d || !d.body) return;
                const h1 = d.querySelector('.hero h1');
                if (h1) h1.textContent = this.titre || 'Bienvenue';
                const p = d.querySelector('.hero p');
                if (p) p.textContent = this.message || '';
                d.title = this.titre || 'Connexion Wi-Fi';
                const l = d.querySelector('.logo');
                if (l && this.logoUrl && this.logoUrl !== this.initial.logoUrl) {
                    const img = d.createElement('img');
                    img.src = this.logoUrl;
                    img.alt = '';
                    l.replaceChildren(img);
                }
            } catch (e) {}
        },

        recharger() {
            this.pret = false;
            try { this.$refs.frame.contentWindow.location.reload(); }
            catch (e) { this.$refs.frame.src = this.$refs.frame.src; }
        },

        changerLogo(event) {
            const file = event.target.files[0];
            this.logoErreur = '';
            if (!file) return;
            if (file.size > 2 * 1024 * 1024) {
                this.logoErreur = 'Fichier trop lourd (max 2 Mo).';
                event.target.value = '';
                return;
            }
            if (this.logoUrl && this.logoUrl.startsWith('blob:')) URL.revokeObjectURL(this.logoUrl);
            this.logoNom = file.name;
            this.logoUrl = URL.createObjectURL(file);
        },

        copier() {
            if (!navigator.clipboard) return;
            navigator.clipboard.writeText(this.urlPortail).then(() => {
                this.copie = true;
                setTimeout(() => this.copie = false, 2000);
            });
        }
     }">

    {{-- En-tête --}}
    <div class="mb-6 flex flex-wrap items-end justify-between gap-3">
        <div>
            <h1 class="text-2xl font-bold text-slate-900">Centre de contrôle du Portail Captif</h1>
            <p class="text-slate-500">Personnalisez la page de connexion Wi-Fi et configurez le routeur, sans toucher au code.</p>
        </div>
        <span x-show="modifie" x-cloak
              class="inline-flex items-center gap-2 rounded-full bg-amber-100 px-3 py-1 text-xs font-semibold text-amber-800 border border-amber-200">
            <span class="h-2 w-2 rounded-full bg-amber-500"></span>
            Modifications non enregistrées
        </span>
    </div>

    {{-- Messages flash --}}
    @if(session('success'))
        <div class="mb-4 flex items-center gap-2 rounded-xl border border-emerald-200 bg-emerald-50 p-4 text-sm text-emerald-800 shadow-sm">
            <x-icone nom="check" class="h-5 w-5 shrink-0" /> {{ session('success') }}
        </div>
    @endif
    @if(session('error'))
        <div class="mb-4 flex items-start gap-2 rounded-xl border border-rose-200 bg-rose-50 p-4 text-sm text-rose-800 shadow-sm">
            <x-icone nom="warning" class="mt-0.5 h-5 w-5 shrink-0" /> <span>{{ session('error') }}</span>
        </div>
    @endif

    <div class="grid grid-cols-1 gap-6 lg:grid-cols-5 items-start">

        {{-- ============ GAUCHE : RÉGLAGES (≈ 40 %) ============ --}}
        <div class="lg:col-span-2 overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">

            {{-- Onglets --}}
            <div class="flex border-b border-slate-200 bg-slate-50/70 p-1.5 gap-1">
                <button type="button" @click="tab = 'general'"
                        :class="tab === 'general' ? 'bg-white text-indigo-600 shadow-sm' : 'text-slate-500 hover:text-slate-700'"
                        class="flex-1 rounded-lg px-4 py-2 text-sm font-semibold transition">
                    Général
                </button>
                <button type="button" @click="tab = 'reseau'"
                        :class="tab === 'reseau' ? 'bg-white text-indigo-600 shadow-sm' : 'text-slate-500 hover:text-slate-700'"
                        class="flex-1 rounded-lg px-4 py-2 text-sm font-semibold transition">
                    Réseau
                </button>
            </div>

            {{-- ---------- Onglet GÉNÉRAL ---------- --}}
            <div x-show="tab === 'general'" class="p-6">
                <form action="{{ route('portail.admin.save') }}" method="POST" enctype="multipart/form-data" class="space-y-5">
                    @csrf

                    @if($errors->any())
                        <div class="rounded-lg border border-rose-200 bg-rose-50 p-3 text-sm text-rose-800">
                            <ul class="list-disc pl-5 space-y-0.5">
                                @foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach
                            </ul>
                        </div>
                    @endif

                    {{-- Logo --}}
                    <div>
                        <label class="mb-1 block text-sm font-semibold text-slate-700">Logo de l'établissement</label>
                        <div class="mt-1 flex items-center gap-4">
                            <div class="flex h-16 w-16 shrink-0 items-center justify-center overflow-hidden rounded-xl border border-slate-200 bg-slate-100">
                                <template x-if="logoUrl">
                                    <img :src="logoUrl" alt="" class="h-full w-full object-contain">
                                </template>
                                <template x-if="!logoUrl">
                                    <x-icone nom="link" class="h-6 w-6 text-slate-400" />
                                </template>
                            </div>
                            <div class="min-w-0 flex-1">
                                <input type="file" name="logo" accept="image/*" @change="changerLogo($event)"
                                       class="block w-full text-sm text-slate-500 file:mr-4 file:rounded-full file:border-0 file:bg-slate-100 file:px-4 file:py-2 file:text-sm file:font-semibold file:text-slate-700 hover:file:bg-slate-200">
                                <p class="mt-1 truncate text-xs text-slate-400" x-show="logoNom && !logoErreur" x-text="logoNom"></p>
                                <p class="mt-1 text-xs text-rose-600" x-show="logoErreur" x-text="logoErreur" x-cloak></p>
                                <p class="mt-1 text-xs text-slate-400" x-show="!logoNom && !logoErreur">PNG, JPG ou SVG. Max 2 Mo.</p>
                            </div>
                        </div>
                    </div>

                    {{-- Titre --}}
                    <div>
                        <label for="titre" class="mb-1 block text-sm font-semibold text-slate-700">Titre d'accueil</label>
                        <input id="titre" type="text" name="titre" x-model="titre" required maxlength="255"
                               class="w-full rounded-lg border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                    </div>

                    {{-- Message --}}
                    <div>
                        <label for="message" class="mb-1 block text-sm font-semibold text-slate-700">Message de bienvenue</label>
                        <textarea id="message" name="message" x-model="message" rows="3" required
                                  class="w-full rounded-lg border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"></textarea>
                    </div>

                    {{-- Tarif (lecture seule) --}}
                    <div class="rounded-xl border border-sky-100 bg-sky-50 p-4">
                        <div class="flex items-start gap-3">
                            <x-icone nom="warning" class="mt-0.5 h-5 w-5 shrink-0 text-sky-600" />
                            <div>
                                <h4 class="text-sm font-semibold text-sky-900">Tarification affichée</h4>
                                <p class="mt-1 text-sm text-sky-800">
                                    Tarif Wi-Fi actuel :
                                    <strong>{{ $tarifWifi ? $tarifWifi->montant_par_minute . ' Ar/min' : 'Non configuré' }}</strong>
                                    @if($tarifWifi && $tarifWifi->montant_minimum)
                                        , minimum <strong>{{ $tarifWifi->montant_minimum }} Ar</strong>
                                    @endif.
                                </p>
                                <p class="mt-2 text-xs text-sky-600">Pour modifier ces prix, rendez-vous dans le menu « Tarifs ».</p>
                            </div>
                        </div>
                    </div>

                    <button type="submit"
                            class="flex w-full items-center justify-center gap-2 rounded-xl bg-indigo-600 px-6 py-3 font-semibold text-white shadow-sm transition hover:bg-indigo-700">
                        <x-icone nom="check" class="h-5 w-5" />
                        Enregistrer les réglages
                    </button>
                </form>
            </div>

            {{-- ---------- Onglet RÉSEAU ---------- --}}
            <div x-show="tab === 'reseau'" x-cloak class="space-y-6 p-6">

                {{-- URL du portail --}}
                <div>
                    <label class="mb-1 block text-sm font-semibold text-slate-700">Adresse du portail</label>
                    <div class="flex items-center gap-2">
                        <input type="text" readonly :value="urlPortail"
                               class="min-w-0 flex-1 rounded-lg border-slate-300 bg-slate-50 font-mono text-sm text-slate-700 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                        <button type="button" @click="copier()"
                                class="shrink-0 rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm font-semibold text-slate-700 transition hover:bg-slate-50">
                            <span x-show="!copie">Copier</span>
                            <span x-show="copie" x-cloak class="text-emerald-600">Copié ✓</span>
                        </button>
                    </div>
                    <p class="mt-1.5 text-xs text-slate-400">C'est l'adresse vers laquelle MikroTik redirige les clients non connectés.</p>
                </div>

                {{-- IP serveur détectée --}}
                <div class="rounded-xl border p-4 {{ $ipInvalide ? 'border-rose-200 bg-rose-50' : 'border-slate-200 bg-slate-50' }}">
                    <div class="flex items-center justify-between gap-3">
                        <span class="text-sm font-semibold {{ $ipInvalide ? 'text-rose-900' : 'text-slate-700' }}">IP du serveur détectée</span>
                        <code class="rounded bg-white px-2 py-0.5 text-sm {{ $ipInvalide ? 'text-rose-700' : 'text-slate-700' }} border border-slate-200">{{ $ipServeur ?: 'inconnue' }}</code>
                    </div>
                    @if($ipInvalide)
                        <p class="mt-2 text-xs text-rose-700">
                            Cette adresse n'est pas utilisable par les clients. Ajoutez
                            <code class="rounded bg-white px-1">SERVER_IP=votre.ip.locale</code> dans le fichier <code class="rounded bg-white px-1">.env</code>,
                            puis relancez la configuration.
                        </p>
                    @endif
                </div>

                {{-- Synchronisation MikroTik --}}
                <div class="border-t border-slate-200 pt-6">
                    <h3 class="mb-2 text-sm font-bold text-slate-900">Synchronisation avec MikroTik</h3>
                    <p class="mb-3 text-xs text-slate-500">En un clic, Laravel configure le routeur :</p>
                    <ul class="mb-4 space-y-1.5 text-xs text-slate-600">
                        <li class="flex gap-2"><span class="text-indigo-500">●</span> Walled Garden : autorise l'accès au serveur avant connexion</li>
                        <li class="flex gap-2"><span class="text-indigo-500">●</span> DNS local : <code class="rounded bg-slate-100 px-1">inside.net</code> pointe vers le serveur</li>
                        <li class="flex gap-2"><span class="text-indigo-500">●</span> Profils HotSpot : nom de domaine du portail appliqué</li>
                    </ul>

                    <form action="{{ route('portail.admin.configurer') }}" method="POST">
                        @csrf
                        <button type="submit"
                                class="flex w-full items-center justify-center gap-2 rounded-xl bg-slate-900 px-6 py-3 font-semibold text-white shadow-sm transition hover:bg-slate-700">
                            <x-icone nom="refresh" class="h-5 w-5" />
                            Configurer le routeur automatiquement
                        </button>
                    </form>
                </div>
            </div>
        </div>

        {{-- ============ DROITE : APERÇU (≈ 60 %) ============ --}}
        <div class="lg:col-span-3 lg:sticky lg:top-4">
            <div class="overflow-hidden rounded-2xl border border-slate-200 bg-slate-100 shadow-sm">

                {{-- Barre d'outils de l'aperçu --}}
                <div class="flex items-center justify-between gap-3 border-b border-slate-200 bg-white px-4 py-3">
                    <div class="flex items-center gap-2 text-xs font-semibold uppercase tracking-widest text-slate-400">
                        <x-icone nom="eye" class="h-4 w-4" /> Aperçu en direct
                    </div>

                    {{-- Toggle Mobile / Desktop --}}
                    <div class="inline-flex rounded-lg bg-slate-100 p-1">
                        <button type="button" @click="mode = 'mobile'"
                                :class="mode === 'mobile' ? 'bg-white text-indigo-600 shadow-sm' : 'text-slate-500 hover:text-slate-700'"
                                class="inline-flex items-center gap-1.5 rounded-md px-3 py-1.5 text-sm font-semibold transition">
                            <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="7" y="2" width="10" height="20" rx="2"/><path d="M11 18h2"/></svg>
                            Mobile
                        </button>
                        <button type="button" @click="mode = 'desktop'"
                                :class="mode === 'desktop' ? 'bg-white text-indigo-600 shadow-sm' : 'text-slate-500 hover:text-slate-700'"
                                class="inline-flex items-center gap-1.5 rounded-md px-3 py-1.5 text-sm font-semibold transition">
                            <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="4" width="20" height="13" rx="2"/><path d="M8 21h8M12 17v4"/></svg>
                            Desktop
                        </button>
                    </div>

                    <button type="button" @click="recharger()" title="Recharger l'aperçu"
                            class="rounded-lg border border-slate-200 bg-white p-2 text-slate-500 transition hover:bg-slate-50 hover:text-slate-700">
                        <x-icone nom="refresh" class="h-4 w-4" />
                    </button>
                </div>

                {{-- Scène : centre l'appareil et gère l'échelle --}}
                <div x-ref="stage"
                     class="relative flex items-center justify-center overflow-hidden"
                     style="height: clamp(520px, 78vh, 860px); background-image: radial-gradient(#cbd5e1 1px, transparent 1px); background-size: 20px 20px;">

                    {{-- Chargement --}}
                    <div x-show="!pret" class="absolute inset-0 z-30 flex items-center justify-center bg-slate-100/80">
                        <div class="h-8 w-8 animate-spin rounded-full border-4 border-indigo-200 border-t-indigo-600"></div>
                    </div>

                    {{-- Boîte à la taille réduite --}}
                    <div class="relative shrink-0" :style="{ width: (outerW * scale) + 'px', height: (outerH * scale) + 'px' }">
                        <div class="absolute left-0 top-0 flex origin-top-left flex-col items-center"
                             :style="{ width: outerW + 'px', height: outerH + 'px', transform: 'scale(' + scale + ')' }">

                            {{-- Corps de l'appareil --}}
                            <div class="relative shrink-0 shadow-2xl transition-colors"
                                 :class="mode === 'mobile' ? 'rounded-[3.2rem] bg-slate-900 ring-1 ring-slate-700' : 'rounded-2xl bg-slate-800 ring-1 ring-slate-700'"
                                 :style="{ width: monitorW + 'px', padding: dev.bezel + 'px' }">

                                {{-- Encoche (mobile) --}}
                                <div x-show="mode === 'mobile'"
                                     class="absolute left-1/2 top-[12px] z-20 h-6 w-32 -translate-x-1/2 rounded-b-2xl bg-slate-900"></div>

                                {{-- Écran --}}
                                <div class="overflow-hidden bg-white"
                                     :class="mode === 'mobile' ? 'rounded-[2.4rem]' : 'rounded-md'">

                                    {{-- Barre de navigateur (desktop) --}}
                                    <div x-show="mode === 'desktop'"
                                         class="flex items-center gap-3 border-b border-slate-200 bg-slate-100 px-3"
                                         style="height: 34px;">
                                        <div class="flex gap-1.5">
                                            <span class="h-3 w-3 rounded-full bg-rose-400"></span>
                                            <span class="h-3 w-3 rounded-full bg-amber-400"></span>
                                            <span class="h-3 w-3 rounded-full bg-emerald-400"></span>
                                        </div>
                                        <div class="mx-auto w-1/2 truncate rounded-md bg-white px-3 py-1 text-center text-xs text-slate-500 border border-slate-200"
                                             x-text="urlPortail"></div>
                                        <div class="w-12"></div>
                                    </div>

                                    <iframe x-ref="frame"
                                            src="{{ route('portail.login', [], false) }}"
                                            title="Aperçu du portail captif"
                                            @load="apresChargement()"
                                            class="block border-0 bg-white"
                                            :style="{ width: dev.w + 'px', height: dev.h + 'px' }"></iframe>
                                </div>
                            </div>

                            {{-- Pied de l'écran (desktop) --}}
                            <div x-show="mode === 'desktop'" class="flex flex-col items-center">
                                <div class="h-9 w-24 bg-gradient-to-b from-slate-400 to-slate-300"></div>
                                <div class="h-[10px] w-56 rounded-full bg-slate-300 shadow"></div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="border-t border-slate-200 bg-white px-4 py-2.5 text-center text-xs text-slate-400">
                    Vraie page de connexion. Titre, message et logo se mettent à jour en direct ; le bouton « Se connecter » est désactivé dans l'aperçu.
                </div>
            </div>
        </div>

    </div>
</div>
@endsection