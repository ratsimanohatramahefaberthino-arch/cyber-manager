@extends('layouts.cyber-manager', ['title' => 'Identité — Paramètres — Cyber Manager'])

@section('content')
@php
    $logoUrl = $logo ? route('agent.logo', ['v' => $logoVersion]) : null;

    $initial = [
        'nom'     => old('nom_cyber', $p['nom_cyber']),
        'slogan'  => old('slogan', (string) $p['slogan']),
        'admin'   => old('nom_admin', (string) $p['nom_admin']),
        'couleur' => old('couleur_principale', $p['couleur_principale']),
        'logoUrl' => $logoUrl,
        'compte'  => Auth::user()->name,
    ];

    $couleursProposees = [
        ['#7c3aed', 'Violet'], ['#4f46e5', 'Indigo'], ['#0891b2', 'Turquoise'],
        ['#059669', 'Vert'],   ['#d97706', 'Orange'], ['#e11d48', 'Rouge'],
    ];

    $champ = 'h-11 w-full rounded-xl border border-slate-300 bg-white px-3.5 text-sm text-slate-900 shadow-sm placeholder:text-slate-400 focus:border-violet-500 focus:ring-2 focus:ring-violet-500/20';
    $label = 'mb-1.5 block text-sm font-semibold text-slate-800';
@endphp

<script>
function identiteForm(init) {
    return {
        nom: init.nom, slogan: init.slogan, admin: init.admin, couleur: init.couleur,
        logoUrl: init.logoUrl, objetUrl: null, nomFichier: '', erreurLogo: '', survol: false, enCours: false,

        init() {
            // Prévient avant de quitter la page avec des changements non enregistrés
            window.addEventListener('beforeunload', (e) => {
                if (this.dirty && !this.enCours) { e.preventDefault(); e.returnValue = ''; }
            });
        },

        get dirty() {
            return this.nom !== init.nom || this.slogan !== init.slogan || this.admin !== init.admin
                || (this.couleur || '').toLowerCase() !== (init.couleur || '').toLowerCase()
                || this.nomFichier !== '';
        },
        get couleurValide() { return /^#[0-9a-fA-F]{6}$/.test(this.couleur); },
        get couleurAffichee() { return this.couleurValide ? this.couleur : init.couleur; },
        get adminAffiche() { return this.admin.trim() !== '' ? this.admin : init.compte; },

        liberer() { if (this.objetUrl) { URL.revokeObjectURL(this.objetUrl); this.objetUrl = null; } },

        choisir(f) {
            this.erreurLogo = '';
            if (!f) return;
            if (!['image/png', 'image/jpeg'].includes(f.type)) {
                this.erreurLogo = 'Choisissez une image au format PNG ou JPG.';
                this.$refs.fichier.value = '';
                return;
            }
            if (f.size > 2 * 1024 * 1024) {
                this.erreurLogo = 'Cette image est trop lourde (2 Mo maximum).';
                this.$refs.fichier.value = '';
                return;
            }
            this.liberer();
            this.objetUrl = URL.createObjectURL(f);
            this.nomFichier = f.name;
            this.logoUrl = this.objetUrl;
        },
        deposer(e) {
            this.survol = false;
            const f = e.dataTransfer.files && e.dataTransfer.files[0];
            if (!f) return;
            try { this.$refs.fichier.files = e.dataTransfer.files; } catch (_) {}
            this.choisir(f);
        },
        annuler() {
            this.liberer();
            this.nom = init.nom; this.slogan = init.slogan; this.admin = init.admin; this.couleur = init.couleur;
            this.logoUrl = init.logoUrl; this.nomFichier = ''; this.erreurLogo = '';
            this.$refs.fichier.value = '';
        },
    };
}
</script>

<div x-data="identiteForm(@js($initial))" class="space-y-6">

    <div>
        <h1 class="text-2xl font-semibold tracking-tight text-slate-900">Identité du cyber</h1>
        <p class="text-sm text-slate-500">Nom, slogan, logo et couleur : affichés sur l'écran de verrouillage des postes et dans l'administration.</p>
    </div>

    <x-flash />

    @if($errors->any())
        <div class="rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800" role="alert">
            <p class="mb-1 font-semibold">Les changements n'ont pas été enregistrés :</p>
            <ul class="list-disc space-y-0.5 pl-5">
                @foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach
            </ul>
        </div>
    @endif

    {{-- Suppression du logo : formulaire séparé (on ne peut pas imbriquer deux formulaires) --}}
    @if($logo)
        <form id="form-logo-supprimer" method="POST" action="{{ route('parametres.logo.supprimer') }}"
              onsubmit="return confirm('Supprimer le logo ?')" class="hidden">
            @csrf @method('DELETE')
        </form>
    @endif

    {{-- Deux colonnes seulement sur grand écran (≥ 1536 px) : le menu de gauche réduit la largeur disponible --}}
    <div class="grid items-start gap-6 2xl:grid-cols-2">

        {{-- ═════════ Formulaire ═════════ --}}
        <form id="form-identite" method="POST" action="{{ route('parametres.update') }}" enctype="multipart/form-data"
              @submit="enCours = true" class="space-y-6">
            @csrf

            <section class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                <h2 class="text-base font-semibold text-slate-900">Informations de votre cyber</h2>
                <p class="mb-5 mt-1 text-sm text-slate-500">Elles sont envoyées automatiquement à tous les postes clients.</p>

                <div class="space-y-5">
                    <div>
                        <label for="nom_cyber" class="{{ $label }}">Nom de votre cyber</label>
                        <input type="text" id="nom_cyber" name="nom_cyber" x-model="nom" maxlength="60" required class="{{ $champ }}">
                    </div>
                    <div>
                        <label for="slogan" class="{{ $label }}">Slogan <span class="font-normal text-slate-400">(facultatif)</span></label>
                        <input type="text" id="slogan" name="slogan" x-model="slogan" maxlength="80" class="{{ $champ }}">
                        <p class="mt-1 text-xs text-slate-500">Affiché sous le nom, sur l'écran de verrouillage.</p>
                    </div>
                    <div>
                        <label for="nom_admin" class="{{ $label }}">Nom de l'administrateur <span class="font-normal text-slate-400">(facultatif)</span></label>
                        <input type="text" id="nom_admin" name="nom_admin" x-model="admin" maxlength="60" class="{{ $champ }}">
                        <p class="mt-1 text-xs text-slate-500">Affiché en haut à droite, à la place du nom du compte.</p>
                    </div>
                </div>
            </section>

            <section class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                <h2 class="text-base font-semibold text-slate-900">Couleur principale</h2>
                <p class="mb-4 mt-1 text-sm text-slate-500">Donne l'ambiance de l'écran de verrouillage et la couleur de l'icône de l'agent.</p>

                <div class="flex flex-wrap items-center gap-3">
                    @foreach($couleursProposees as [$hex, $nomCouleur])
                        <button type="button" title="{{ $nomCouleur }}" aria-label="{{ $nomCouleur }}"
                                @click="couleur = '{{ $hex }}'"
                                :class="couleur.toLowerCase() === '{{ $hex }}' ? 'ring-4 ring-slate-300' : 'hover:ring-2 hover:ring-slate-200'"
                                class="flex h-9 w-9 items-center justify-center rounded-full text-white shadow-sm transition"
                                style="background: {{ $hex }}">
                            <span x-show="couleur.toLowerCase() === '{{ $hex }}'" x-cloak><x-icone nom="check" classe="h-5 w-5" /></span>
                        </button>
                    @endforeach

                    <span class="mx-1 hidden h-6 w-px bg-slate-200 sm:block"></span>

                    <label class="flex items-center gap-2 rounded-xl border border-slate-300 bg-white px-2 py-1.5 shadow-sm">
                        <input type="color" :value="couleurAffichee" @input="couleur = $event.target.value"
                               aria-label="Choisir une autre couleur" class="h-8 w-10 cursor-pointer rounded border-0 bg-transparent p-0">
                        <input type="text" name="couleur_principale" x-model="couleur" maxlength="7" aria-label="Code de la couleur"
                               class="w-24 border-0 bg-transparent p-0 font-mono text-sm font-semibold text-slate-800 focus:ring-0">
                    </label>
                </div>
                <p x-show="!couleurValide" x-cloak class="mt-2 text-xs font-medium text-rose-600">Code de couleur incomplet : il doit ressembler à #7c3aed.</p>
            </section>

            <section class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                <h2 class="text-base font-semibold text-slate-900">Logo</h2>
                <p class="mb-4 mt-1 text-sm text-slate-500">Affiché sur l'écran de verrouillage des postes.</p>

                <div role="button" tabindex="0"
                     @click="$refs.fichier.click()" @keydown.enter.prevent="$refs.fichier.click()"
                     @dragover.prevent="survol = true" @dragleave.prevent="survol = false" @drop.prevent="deposer($event)"
                     :class="survol ? 'border-violet-500 bg-violet-50' : 'border-slate-300 bg-slate-50 hover:border-violet-400'"
                     class="flex cursor-pointer flex-col items-center gap-3 rounded-2xl border-2 border-dashed p-6 text-center transition focus:outline-none focus-visible:ring-2 focus-visible:ring-violet-500">
                    <template x-if="logoUrl"><img :src="logoUrl" alt="Logo actuel" class="max-h-28 max-w-[220px] object-contain"></template>
                    <template x-if="!logoUrl">
                        <span class="flex h-14 w-14 items-center justify-center rounded-2xl bg-white text-slate-400 shadow-sm"><x-icone nom="upload-cloud" classe="h-7 w-7" /></span>
                    </template>
                    <p class="text-sm text-slate-600"><strong class="text-slate-900">Glissez une image ici</strong> ou cliquez pour la choisir</p>
                    <p class="text-xs text-slate-500">PNG ou JPG · 2 Mo maximum · 64 × 64 px minimum<br>Idéal : image carrée 512 × 512, fond transparent (PNG)</p>
                    <p x-show="nomFichier" x-cloak class="text-sm font-semibold text-violet-700" x-text="'Nouvelle image : ' + nomFichier"></p>
                </div>
                <input type="file" name="logo" accept="image/png,image/jpeg" x-ref="fichier" class="hidden" @change="choisir($event.target.files[0])">
                <p x-show="erreurLogo" x-cloak class="mt-2 text-sm font-medium text-rose-600" x-text="erreurLogo"></p>

                @if($logo)
                    <div class="mt-3 text-right">
                        <button type="submit" form="form-logo-supprimer"
                                class="inline-flex h-9 items-center gap-1.5 rounded-lg border border-rose-200 bg-white px-3 text-sm font-medium text-rose-600 hover:bg-rose-50">
                            <x-icone nom="trash" classe="h-4 w-4" /> Supprimer le logo
                        </button>
                    </div>
                @endif
            </section>
        </form>

        {{-- ═════════ Aperçu ═════════ --}}
        <div class="space-y-6 2xl:sticky 2xl:top-4">
            <section class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                <div class="mb-4 flex items-start justify-between gap-3">
                    <div>
                        <h2 class="text-base font-semibold text-slate-900">Aperçu de l'écran de verrouillage</h2>
                        <p class="mt-1 text-sm text-slate-500">Ce que verront vos clients. Il change pendant que vous tapez.</p>
                    </div>
                    <span class="inline-flex shrink-0 items-center gap-1.5 text-xs font-medium text-slate-500">
                        <span class="relative flex h-2 w-2">
                            <span class="absolute inline-flex h-full w-full animate-ping rounded-full bg-violet-400 opacity-75"></span>
                            <span class="relative inline-flex h-2 w-2 rounded-full bg-violet-500"></span>
                        </span>
                        En direct
                    </span>
                </div>

                <div class="relative overflow-hidden rounded-2xl bg-slate-950 px-6 py-12 text-center text-slate-200 ring-1 ring-slate-800">
                    <div class="absolute inset-0 opacity-30" :style="`background: radial-gradient(circle at 70% 10%, ${couleurAffichee}, transparent 60%)`"></div>
                    <div class="relative">
                        <template x-if="logoUrl"><img :src="logoUrl" alt="" class="mx-auto mb-4 max-h-28 max-w-[200px] object-contain"></template>
                        <template x-if="!logoUrl">
                            <div class="mx-auto mb-4 flex h-20 w-20 items-center justify-center rounded-[22px]"
                                 :style="`background: linear-gradient(135deg, ${couleurAffichee}, #312e81)`">
                                <svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="4" width="18" height="12" rx="2"/><path d="M8 20h8M12 16v4"/></svg>
                            </div>
                        </template>
                        <div class="break-words text-3xl font-extrabold uppercase leading-tight tracking-[0.12em] text-white" x-text="nom.trim() || 'Cyber Manager'"></div>
                        <div x-show="slogan.trim()" x-cloak class="mt-2 text-[11px] uppercase tracking-[0.25em] text-slate-400" x-text="slogan"></div>
                        <div class="mt-6 inline-block rounded-xl border border-white/10 bg-white/10 px-5 py-1.5 text-sm font-bold">POSTE 1</div>
                    </div>
                </div>
                <p class="mt-3 text-center text-xs text-slate-500">Aperçu simplifié : la disposition exacte sur le poste peut légèrement varier.</p>
            </section>

            <section class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                <h2 class="text-base font-semibold text-slate-900">Où ces informations apparaissent</h2>
                <ul class="mt-3 space-y-3 text-sm text-slate-600">
                    <li class="flex gap-3">
                        <span class="mt-0.5 flex h-7 w-7 shrink-0 items-center justify-center rounded-lg bg-violet-50 text-violet-600"><x-icone nom="desktop" classe="h-4 w-4" /></span>
                        <span><strong class="text-slate-900">Écran de verrouillage des postes :</strong> nom, slogan, logo et couleur, envoyés automatiquement.</span>
                    </li>
                    <li class="flex gap-3">
                        <span class="mt-0.5 flex h-7 w-7 shrink-0 items-center justify-center rounded-lg bg-violet-50 text-violet-600"><x-icone nom="home" classe="h-4 w-4" /></span>
                        <span><strong class="text-slate-900">Menu de gauche de l'administration :</strong> le nom de votre cyber.</span>
                    </li>
                    <li class="flex gap-3">
                        <span class="mt-0.5 flex h-7 w-7 shrink-0 items-center justify-center rounded-lg bg-violet-50 text-violet-600"><x-icone nom="key" classe="h-4 w-4" /></span>
                        <span><strong class="text-slate-900">En haut à droite :</strong> <span x-text="adminAffiche" class="font-semibold text-slate-900"></span></span>
                    </li>
                </ul>
            </section>
        </div>
    </div>

    {{-- ═════════ Barre d'enregistrement ═════════ --}}
    <div class="sticky bottom-4 z-20 flex flex-wrap items-center justify-between gap-3 rounded-2xl border border-slate-200 bg-white/95 px-5 py-3 shadow-lg backdrop-blur">
        <p class="flex items-center gap-2 text-sm" :class="dirty ? 'font-semibold text-amber-700' : 'text-slate-500'" aria-live="polite">
            <span class="h-2 w-2 rounded-full" :class="dirty ? 'animate-pulse bg-amber-500' : 'bg-emerald-500'"></span>
            <span x-text="dirty ? 'Vous avez des changements non enregistrés' : 'Tout est enregistré'"></span>
        </p>
        <div class="flex items-center gap-2">
            <button type="button" @click="annuler()" :disabled="!dirty || enCours"
                    class="inline-flex h-10 items-center rounded-xl border border-slate-300 bg-white px-4 text-sm font-semibold text-slate-700 hover:bg-slate-50 disabled:cursor-not-allowed disabled:opacity-40">
                Annuler les changements
            </button>
            <button type="submit" form="form-identite" :disabled="!dirty || enCours || !couleurValide"
                    class="inline-flex h-10 items-center gap-2 rounded-xl bg-violet-600 px-5 text-sm font-semibold text-white shadow-sm hover:bg-violet-700 disabled:cursor-not-allowed disabled:opacity-40">
                <span x-show="enCours" x-cloak class="h-4 w-4 animate-spin rounded-full border-2 border-white border-t-transparent"></span>
                <span x-text="enCours ? 'Enregistrement…' : 'Enregistrer'"></span>
            </button>
        </div>
    </div>
</div>
@endsection