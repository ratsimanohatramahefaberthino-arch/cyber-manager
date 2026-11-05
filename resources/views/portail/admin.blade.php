@extends('layouts.cyber-manager', ['title' => 'Studio Portail Captif'])

@section('content')
@php
    $portPortail = request()->getPort();
    $urlPortail  = 'http://inside.net' . (in_array($portPortail, [80, 443]) ? '' : ':' . $portPortail) . '/portail';
    $ipServeur   = env('SERVER_IP') ?: request()->server('SERVER_ADDR');
    $ipInvalide  = !$ipServeur || in_array($ipServeur, ['127.0.0.1', '::1']) || str_starts_with((string) $ipServeur, '169.254');
    $logoInitial = !empty($config['logo']) ? Storage::url($config['logo']) : '';

    $formInitial = collect($config)->except('logo')->all();
    $formInitial['annonce_active']  = (string) ($config['annonce_active'] ?? '0') === '1';
    $formInitial['supprimer_logo']  = false;

    $payload = [
        'form'      => $formInitial,
        'logoUrl'   => $logoInitial,
        'urlPortail'=> $urlPortail,
    ];

    // Classes pour les champs (prennent 100% de leur colonne, ni plus ni moins)
    $champ = 'w-full rounded-lg border-slate-300 bg-white text-sm text-slate-900 shadow-sm placeholder:text-slate-400 focus:border-indigo-500 focus:ring-indigo-500';
    $label = 'mb-1.5 block text-[13px] font-medium text-slate-700';
    $swatch = 'h-9 w-9 shrink-0 cursor-pointer appearance-none rounded-md border-0 bg-transparent p-0 [&::-webkit-color-swatch-wrapper]:p-0 [&::-webkit-color-swatch]:rounded-md [&::-webkit-color-swatch]:border [&::-webkit-color-swatch]:border-slate-200 [&::-moz-color-swatch]:rounded-md [&::-moz-color-swatch]:border-slate-200';

    $couleursTextes  = [['titre_couleur', 'Couleur du titre'], ['message_couleur', 'Couleur du message']];
    $couleursFond    = [['fond_couleur_1', 'Fond principal'], ['fond_couleur_2', 'Halo lumineux']];
    $couleursCarte   = [['carte_couleur', 'Fond de la carte'], ['carte_texte', 'Texte de la carte']];
    $couleursBouton  = [['bouton_fond', 'Fond du bouton'], ['bouton_texte', 'Texte du bouton']];
@endphp

<script>
document.addEventListener('alpine:init', () => {
    Alpine.data('studioPortail', (init) => ({
        tab: 'general',
        mode: 'mobile',
        copie: false,
        envoi: false,
        scale: 1,

        form: init.form,
        initial: null,
        logoUrl: init.logoUrl,
        logoInitial: init.logoUrl,
        logoNom: '',
        urlPortail: init.urlPortail,

        devices: {
            mobile:  { w: 375,  h: 760 },
            desktop: { w: 1000, h: 650 },
        },

        get dev() { return this.devices[this.mode]; },
        get frameW() { return this.dev.w + (this.mode === 'mobile' ? 24 : 0); },
        get frameH() { return this.dev.h + (this.mode === 'mobile' ? 24 : 40); },

        get dirty() {
            return JSON.stringify(this.form) !== JSON.stringify(this.initial) || this.logoNom !== '';
        },

        get screenStyle() {
            const f = this.form;
            return {
                fontFamily: f.police,
                background: f.fond_type === 'gradient'
                    ? 'radial-gradient(120% 90% at 50% 0, ' + f.fond_couleur_2 + ' 0, transparent 70%), ' + f.fond_couleur_1
                    : f.fond_couleur_1,
            };
        },

        init() {
            this.initial = JSON.parse(JSON.stringify(this.form));
            setTimeout(() => this.ajuster(), 100);
            window.addEventListener('resize', () => this.ajuster());
            this.$watch('mode', () => setTimeout(() => this.ajuster(), 50));
        },

        ajuster() {
            const s = this.$refs.stage;
            if (!s) return;
            const marge = 40;
            const sc = Math.min(
                1,
                (s.clientWidth  - marge) / this.frameW,
                (s.clientHeight - marge) / this.frameH
            );
            this.scale = Math.max(0.2, sc);
        },

        changerLogo(e) {
            const f = e.target.files[0];
            if (!f) return;
            if (f.size > 2 * 1024 * 1024) { alert('2 Mo maximum.'); return; }
            this.logoNom = f.name;
            this.logoUrl = URL.createObjectURL(f);
            this.form.supprimer_logo = false;
        },

        retirerLogo() {
            this.logoUrl = '';
            this.logoNom = '';
            this.form.supprimer_logo = true;
            if (this.$refs.logoInput) this.$refs.logoInput.value = '';
        },

        annuler() {
            this.form = JSON.parse(JSON.stringify(this.initial));
            this.logoUrl = this.logoInitial;
            this.logoNom = '';
        },

        copier() {
            window.cmCopy(this.urlPortail);
            this.copie = true;
            setTimeout(() => this.copie = false, 2000);
        },
    }));
});
</script>

<div class="w-full max-w-[1600px] mx-auto pb-10 overflow-x-hidden" x-data="studioPortail(@js($payload))">

    {{-- Messages --}}
    @if(session('success'))
        <div class="mb-4 flex items-center gap-3 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-800">
            <x-icone nom="check" class="h-5 w-5 shrink-0 text-emerald-600" />
            <span>{{ session('success') }}</span>
        </div>
    @endif
    @if(session('error'))
        <div class="mb-4 flex items-start gap-3 rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm font-medium text-rose-800">
            <x-icone nom="warning" class="mt-0.5 h-5 w-5 shrink-0 text-rose-600" />
            <span>{{ session('error') }}</span>
        </div>
    @endif

    <div class="mb-6 flex flex-wrap items-center justify-between gap-3">
        <div>
            <h1 class="text-2xl font-semibold tracking-tight text-slate-900">Portail Captif</h1>
            <p class="text-sm text-slate-500">Personnalisez la page de connexion Wi-Fi de vos clients.</p>
        </div>
    </div>

    {{-- Grille 12 colonnes : 5 pour les réglages, 7 pour l'aperçu --}}
    <div class="grid grid-cols-1 lg:grid-cols-12 gap-6 items-start w-full">

        {{-- =============================================================== --}}
        {{-- GAUCHE : RÉGLAGES (5 colonnes / 12)                             --}}
        {{-- =============================================================== --}}
        <div class="col-span-1 lg:col-span-5 w-full min-w-0 rounded-2xl border border-slate-200 bg-white shadow-sm overflow-hidden flex flex-col">
            
            <div class="flex items-center justify-between gap-3 border-b border-slate-200 px-5 py-4 bg-slate-50/50">
                <div class="flex min-w-0 items-center gap-3">
                    <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-indigo-600 text-white shadow-sm shadow-indigo-200">
                        <x-icone nom="link" class="h-5 w-5" />
                    </span>
                    <div class="min-w-0">
                        <h1 class="truncate text-base font-semibold leading-tight text-slate-900">Éditeur de portail</h1>
                        <p class="truncate text-xs text-slate-500">Page de connexion Wi-Fi</p>
                    </div>
                </div>

                <span x-show="dirty" x-cloak class="inline-flex shrink-0 items-center gap-1.5 rounded-full border border-amber-300 bg-amber-50 px-2.5 py-1 text-xs font-semibold text-amber-800">
                    <span class="h-1.5 w-1.5 animate-pulse rounded-full bg-amber-500"></span>
                    Non sauvegardé
                </span>
            </div>

            <div class="flex gap-1 border-b border-slate-200 px-3" role="tablist">
                @foreach([['general', 'Général'], ['design', 'Design'], ['reseau', 'Réseau']] as [$id, $nom])
                    <button type="button" role="tab" @click="tab = '{{ $id }}'" :aria-selected="tab === '{{ $id }}'"
                            :class="tab === '{{ $id }}' ? 'border-indigo-600 text-indigo-700' : 'border-transparent text-slate-500 hover:text-slate-800'"
                            class="-mb-px border-b-2 px-4 py-3 text-sm font-semibold transition focus:outline-none">
                        {{ $nom }}
                    </button>
                @endforeach
            </div>

            <form id="form-portail" action="{{ route('portail.admin.save') }}" method="POST" enctype="multipart/form-data" @submit="envoi = true" class="flex flex-col">
                @csrf
                <input type="hidden" name="supprimer_logo" :value="form.supprimer_logo ? '1' : '0'">
                <input type="hidden" name="annonce_active" :value="form.annonce_active ? '1' : '0'">

                <div class="space-y-6 px-5 py-6">
                    {{-- GÉNÉRAL --}}
                    <div x-show="tab === 'general'" class="space-y-6">
                        <div>
                            <span class="{{ $label }}">Logo</span>
                            <div class="flex items-center gap-4 rounded-xl border border-dashed border-slate-300 bg-slate-50/60 p-3 w-full">
                                <div class="flex h-16 w-16 shrink-0 items-center justify-center overflow-hidden rounded-xl border border-slate-200 bg-white p-1.5">
                                    <template x-if="logoUrl"><img :src="logoUrl" alt="" class="max-h-full max-w-full object-contain"></template>
                                    <template x-if="!logoUrl"><x-icone nom="image" class="h-6 w-6 text-slate-300" /></template>
                                </div>
                                <div class="min-w-0 flex-1">
                                    <div class="flex flex-wrap items-center gap-2">
                                        <label class="inline-flex cursor-pointer items-center rounded-lg border border-slate-300 bg-white px-3 py-1.5 text-xs font-semibold text-slate-700 shadow-sm transition hover:bg-slate-50">
                                            <span x-text="logoUrl ? 'Remplacer' : 'Choisir une image'"></span>
                                            <input type="file" name="logo" accept="image/*" x-ref="logoInput" @change="changerLogo($event)" class="sr-only">
                                        </label>
                                        <button type="button" x-show="logoUrl" x-cloak @click="retirerLogo()" class="rounded-lg px-2 py-1.5 text-xs font-semibold text-rose-600 hover:bg-rose-50">Retirer</button>
                                    </div>
                                    <p class="mt-1.5 truncate text-xs text-slate-500" x-text="logoNom || 'PNG, JPG ou SVG, 2 Mo max.'"></p>
                                </div>
                            </div>
                        </div>

                        <div class="space-y-3">
                            <div>
                                <label for="f-titre" class="{{ $label }}">Titre d'accueil</label>
                                <input id="f-titre" type="text" name="titre" x-model="form.titre" required maxlength="255" class="{{ $champ }}">
                            </div>
                            <div>
                                <div class="mb-1.5 flex items-center justify-between w-full">
                                    <label for="f-taille" class="text-[13px] font-medium text-slate-700">Taille du titre</label>
                                    <span class="font-mono text-xs text-indigo-600" x-text="form.titre_taille + ' px'"></span>
                                </div>
                                <input id="f-taille" type="range" name="titre_taille" min="16" max="48" x-model="form.titre_taille" class="w-full cursor-pointer accent-indigo-600">
                            </div>
                        </div>

                        <div>
                            <label for="f-message" class="{{ $label }}">Message de bienvenue</label>
                            <textarea id="f-message" name="message" x-model="form.message" rows="3" required maxlength="1000" class="{{ $champ }} resize-none"></textarea>
                        </div>

                        <div class="grid grid-cols-2 gap-3 w-full">
                            @foreach($couleursTextes as [$n, $l])
                                <label class="flex cursor-pointer items-center gap-3 rounded-xl border border-slate-200 bg-white p-2 pr-3 transition hover:border-slate-300">
                                    <input type="color" name="{{ $n }}" x-model="form.{{ $n }}" class="{{ $swatch }}">
                                    <span class="min-w-0">
                                        <span class="block truncate text-[13px] font-medium text-slate-700">{{ $l }}</span>
                                    </span>
                                </label>
                            @endforeach
                        </div>

                        <div class="rounded-xl border border-slate-200 p-4 w-full">
                            <div class="flex items-start justify-between gap-4">
                                <div>
                                    <p class="text-sm font-semibold text-slate-900">Bannière d'annonce</p>
                                </div>
                                <button type="button" @click="form.annonce_active = !form.annonce_active" :class="form.annonce_active ? 'bg-indigo-600' : 'bg-slate-300'" class="relative mt-0.5 inline-flex h-6 w-11 shrink-0 cursor-pointer rounded-full transition-colors">
                                    <span :class="form.annonce_active ? 'translate-x-5' : 'translate-x-0.5'" class="mt-0.5 inline-block h-5 w-5 rounded-full bg-white shadow transition-transform"></span>
                                </button>
                            </div>
                            <div x-show="form.annonce_active" x-cloak class="mt-4 space-y-3 border-t border-slate-100 pt-4 w-full">
                                <input type="text" name="annonce_texte" x-model="form.annonce_texte" maxlength="255" placeholder="Ex : Promo" class="{{ $champ }}">
                                <div class="grid grid-cols-2 gap-3 w-full">
                                    <label class="flex cursor-pointer items-center gap-3 rounded-xl border border-slate-200 bg-white p-2">
                                        <input type="color" name="annonce_fond" x-model="form.annonce_fond" class="{{ $swatch }}">
                                        <span class="text-[13px] font-medium">Fond</span>
                                    </label>
                                    <label class="flex cursor-pointer items-center gap-3 rounded-xl border border-slate-200 bg-white p-2">
                                        <input type="color" name="annonce_texte_color" x-model="form.annonce_texte_color" class="{{ $swatch }}">
                                        <span class="text-[13px] font-medium">Texte</span>
                                    </label>
                                </div>
                            </div>
                        </div>

                        <div>
                            <label for="f-footer" class="{{ $label }}">Texte d'aide</label>
                            <input id="f-footer" type="text" name="footer_texte" x-model="form.footer_texte" maxlength="255" class="{{ $champ }}">
                        </div>
                    </div>

                    {{-- DESIGN --}}
                    <div x-show="tab === 'design'" x-cloak class="space-y-6">
                        <div>
                            <label for="f-police" class="{{ $label }}">Police</label>
                            <select id="f-police" name="police" x-model="form.police" class="{{ $champ }}">
                                <option value="Inter, system-ui, sans-serif">Inter</option>
                                <option value="'Segoe UI', Roboto, sans-serif">Segoe UI / Roboto</option>
                                <option value="'Outfit', sans-serif">Outfit</option>
                                <option value="'Poppins', sans-serif">Poppins</option>
                            </select>
                        </div>

                        <div class="space-y-3 w-full">
                            <span class="{{ $label }} !mb-0">Arrière-plan</span>
                            <div class="grid grid-cols-2 gap-1 rounded-xl bg-slate-100 p-1 w-full">
                                <label :class="form.fond_type === 'gradient' ? 'bg-white text-indigo-700 shadow-sm' : 'text-slate-500'" class="flex cursor-pointer justify-center rounded-lg py-2 text-xs font-semibold">
                                    <input type="radio" name="fond_type" value="gradient" x-model="form.fond_type" class="sr-only"> Dégradé
                                </label>
                                <label :class="form.fond_type === 'solid' ? 'bg-white text-indigo-700 shadow-sm' : 'text-slate-500'" class="flex cursor-pointer justify-center rounded-lg py-2 text-xs font-semibold">
                                    <input type="radio" name="fond_type" value="solid" x-model="form.fond_type" class="sr-only"> Uni
                                </label>
                            </div>
                            <div class="grid grid-cols-2 gap-3 w-full">
                                @foreach($couleursFond as [$n, $l])
                                    <label @if($n === 'fond_couleur_2') x-show="form.fond_type === 'gradient'" @endif class="flex cursor-pointer items-center gap-3 rounded-xl border border-slate-200 bg-white p-2">
                                        <input type="color" name="{{ $n }}" x-model="form.{{ $n }}" class="{{ $swatch }}">
                                        <span class="text-[13px] font-medium">{{ $l }}</span>
                                    </label>
                                @endforeach
                            </div>
                        </div>

                        <div class="space-y-3 w-full">
                            <span class="{{ $label }} !mb-0">Carte de connexion</span>
                            <div class="grid grid-cols-2 gap-3 w-full">
                                @foreach($couleursCarte as [$n, $l])
                                    <label class="flex cursor-pointer items-center gap-3 rounded-xl border border-slate-200 bg-white p-2">
                                        <input type="color" name="{{ $n }}" x-model="form.{{ $n }}" class="{{ $swatch }}">
                                        <span class="text-[13px] font-medium">{{ $l }}</span>
                                    </label>
                                @endforeach
                            </div>
                        </div>

                        <div class="space-y-3 w-full">
                            <span class="{{ $label }} !mb-0">Bouton</span>
                            <div class="grid grid-cols-2 gap-3 w-full">
                                @foreach($couleursBouton as [$n, $l])
                                    <label class="flex cursor-pointer items-center gap-3 rounded-xl border border-slate-200 bg-white p-2">
                                        <input type="color" name="{{ $n }}" x-model="form.{{ $n }}" class="{{ $swatch }}">
                                        <span class="text-[13px] font-medium">{{ $l }}</span>
                                    </label>
                                @endforeach
                            </div>
                            <div class="mt-2 w-full">
                                <input type="range" name="bouton_rayon" min="0" max="32" x-model="form.bouton_rayon" class="w-full accent-indigo-600">
                            </div>
                        </div>
                    </div>

                    {{-- RÉSEAU --}}
                    <div x-show="tab === 'reseau'" x-cloak class="space-y-6 w-full">
                        <div class="w-full">
                            <span class="{{ $label }}">Adresse du portail</span>
                            <div class="flex gap-2 w-full">
                                <input type="text" readonly value="{{ $urlPortail }}" class="w-full rounded-lg border-slate-200 bg-slate-50 font-mono text-xs text-slate-600">
                                <button type="button" @click="copier()" class="inline-flex w-24 shrink-0 items-center justify-center rounded-lg border border-slate-300 bg-white text-xs font-semibold">
                                    <span x-show="!copie">Copier</span>
                                    <span x-show="copie" class="text-emerald-600">Copié</span>
                                </button>
                            </div>
                        </div>

                        <div class="rounded-xl border p-4 w-full {{ $ipInvalide ? 'border-rose-200 bg-rose-50' : 'border-slate-200 bg-slate-50' }}">
                            <span class="text-[13px] font-medium">IP Serveur : </span>
                            <code class="rounded bg-white px-2 py-0.5 text-xs">{{ $ipServeur ?: 'Inconnue' }}</code>
                        </div>

                        <div class="rounded-xl border border-slate-200 p-4 w-full">
                            <button type="submit" formaction="{{ route('portail.admin.configurer') }}" class="w-full rounded-lg bg-slate-900 px-4 py-2.5 text-sm font-semibold text-white hover:bg-slate-800">
                                Configurer le routeur
                            </button>
                        </div>
                    </div>
                </div>

                <div class="flex items-center justify-end gap-2 border-t border-slate-200 bg-slate-50 px-5 py-3 mt-auto">
                    <button type="button" @click="annuler()" :disabled="!dirty" class="rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-semibold disabled:opacity-40">Annuler</button>
                    <button type="submit" class="inline-flex items-center gap-2 rounded-lg bg-indigo-600 px-5 py-2 text-sm font-semibold text-white shadow-sm hover:bg-indigo-700">
                        <x-icone nom="check" class="h-4 w-4" /> Enregistrer
                    </button>
                </div>
            </form>
        </div>

        {{-- =============================================================== --}}
        {{-- DROITE : APERÇU (7 colonnes / 12)                               --}}
        {{-- =============================================================== --}}
        <div class="col-span-1 lg:col-span-7 w-full min-w-0 sticky top-20">
            <div class="flex flex-col overflow-hidden rounded-2xl border border-slate-800 bg-slate-900 shadow-xl h-[750px] w-full">
                {{-- Barre d'outils --}}
                <div class="flex items-center justify-between gap-3 border-b border-white/10 bg-slate-950/70 px-4 py-3 shrink-0">
                    <div class="flex items-center gap-2 text-sm font-medium text-slate-300">
                        <span class="relative flex h-2 w-2">
                            <span class="absolute inline-flex h-full w-full animate-ping rounded-full bg-emerald-400 opacity-60"></span>
                            <span class="relative inline-flex h-2 w-2 rounded-full bg-emerald-500"></span>
                        </span>
                        Aperçu en direct
                    </div>

                    <div class="inline-flex rounded-lg border border-white/10 bg-slate-900 p-0.5">
                        <button type="button" @click="mode = 'mobile'" :class="mode === 'mobile' ? 'bg-indigo-600 text-white' : 'text-slate-400'" class="inline-flex items-center gap-2 rounded-md px-3 py-1.5 text-xs font-semibold transition">
                            Mobile
                        </button>
                        <button type="button" @click="mode = 'desktop'" :class="mode === 'desktop' ? 'bg-indigo-600 text-white' : 'text-slate-400'" class="inline-flex items-center gap-2 rounded-md px-3 py-1.5 text-xs font-semibold transition">
                            Desktop
                        </button>
                    </div>

                    <span class="w-14 text-right font-mono text-xs text-slate-500" x-text="Math.round(scale * 100) + ' %'"></span>
                </div>

                {{-- Zone d'affichage de l'aperçu --}}
                <div x-ref="stage" class="relative flex flex-1 items-center justify-center overflow-hidden w-full" style="background-color:#0f172a;background-image:radial-gradient(#27364d 1px,transparent 1px);background-size:20px 20px;">
                    
                    <div class="relative shrink-0" :style="{ width: (frameW * scale) + 'px', height: (frameH * scale) + 'px' }">
                        <div class="absolute left-0 top-0 origin-top-left" :style="{ width: frameW + 'px', height: frameH + 'px', transform: 'scale(' + scale + ')' }">
                            
                            {{-- Coque du téléphone --}}
                            <div class="flex h-full w-full flex-col bg-slate-950 shadow-2xl ring-1 ring-white/10" :class="mode === 'mobile' ? 'rounded-[46px] p-3' : 'rounded-xl'">
                                
                                <div x-show="mode === 'desktop'" class="flex h-10 shrink-0 items-center gap-3 px-4">
                                    <div class="flex gap-1.5">
                                        <span class="h-3 w-3 rounded-full bg-slate-700"></span>
                                        <span class="h-3 w-3 rounded-full bg-slate-700"></span>
                                        <span class="h-3 w-3 rounded-full bg-slate-700"></span>
                                    </div>
                                    <div class="mx-auto flex h-6 w-[420px] items-center justify-center truncate rounded-md bg-slate-800 px-3 font-mono text-[11px] text-slate-400">{{ $urlPortail }}</div>
                                </div>

                                <div class="relative min-h-0 flex-1 overflow-hidden" :class="mode === 'mobile' ? 'rounded-[36px]' : 'rounded-b-xl'">
                                    <div x-show="mode === 'mobile'" class="pointer-events-none absolute left-1/2 top-2 z-10 h-5 w-24 -translate-x-1/2 rounded-full bg-black/80"></div>
                                    
                                    <div class="h-full overflow-y-auto" :style="screenStyle">
                                        <div class="flex min-h-full" :class="mode === 'desktop' ? 'items-center justify-center p-10' : 'flex-col'">
                                            <div class="flex flex-col" :class="mode === 'desktop' ? 'w-[430px]' : 'min-h-full flex-1'">
                                                
                                                <header class="px-6 pb-16 text-center text-white" :class="mode === 'mobile' ? 'pt-12' : 'pt-4'">
                                                    <div class="mx-auto mb-4 grid h-[76px] w-[76px] place-items-center overflow-hidden rounded-[22px] bg-white p-2.5 shadow-xl">
                                                        <template x-if="logoUrl"><img :src="logoUrl" class="max-h-full max-w-full object-contain"></template>
                                                        <template x-if="!logoUrl">
                                                            <x-icone nom="wifi" class="h-9 w-9 text-slate-800" />
                                                        </template>
                                                    </div>
                                                    <h2 class="font-bold leading-tight" :style="{ color: form.titre_couleur, fontSize: form.titre_taille + 'px' }" x-text="form.titre || 'Titre'"></h2>
                                                    <p class="mx-auto mt-2 max-w-[32ch] text-[15px]" :style="{ color: form.message_couleur }" x-text="form.message || 'Message'"></p>
                                                </header>

                                                <div class="relative -mt-10 space-y-4 p-6 shadow-[0_-8px_30px_rgba(0,0,0,0.18)]" :class="mode === 'desktop' ? 'rounded-[28px]' : 'flex-1 rounded-t-[28px]'" :style="{ background: form.carte_couleur, color: form.carte_texte }">
                                                    <div x-show="form.annonce_active && form.annonce_texte" class="rounded-xl px-3 py-2.5 text-center text-xs font-bold" :style="{ background: form.annonce_fond, color: form.annonce_texte_color }" x-text="form.annonce_texte"></div>
                                                    
                                                    <div>
                                                        <div class="mb-1.5 ml-0.5 text-[13px] font-semibold opacity-60">Identifiant</div>
                                                        <div class="flex h-[54px] items-center rounded-[14px] bg-white px-4 text-base text-slate-400" :style="{ border: '1.5px solid ' + form.accent_couleur }">Ex : cm-4821</div>
                                                    </div>
                                                    
                                                    <div class="flex h-14 w-full items-center justify-center text-[17px] font-bold shadow-lg" :style="{ background: form.bouton_fond, color: form.bouton_texte, borderRadius: form.bouton_rayon + 'px' }">
                                                        Se connecter
                                                    </div>
                                                    
                                                    <div class="pt-1 text-center">
                                                        <p class="text-sm opacity-75" x-text="form.footer_texte"></p>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection