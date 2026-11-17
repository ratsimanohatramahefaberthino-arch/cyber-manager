@extends('layouts.cyber-manager', ['title' => 'Portail Captif — Cyber Manager'])

@section('content')
@php
    // Onglet à rouvrir : celui de la première erreur, sinon celui d'où l'on vient
    $clesGeneral = ['titre', 'titre_taille', 'message', 'titre_couleur', 'message_couleur', 'annonce_texte', 'annonce_fond', 'annonce_texte_color', 'footer_texte', 'logo'];
    $clesDesign  = ['police', 'fond_type', 'fond_couleur_1', 'fond_couleur_2', 'carte_couleur', 'carte_texte', 'bouton_fond', 'bouton_texte', 'bouton_rayon', 'accent_couleur'];
    $clesReseau  = ['portail_domaine', 'portail_ip_serveur', 'mikrotik_host', 'mikrotik_port', 'mikrotik_user', 'mikrotik_password'];

    $onglet = session('onglet', 'general');
    if ($errors->any()) {
        $onglet = $errors->hasAny($clesGeneral) ? 'general' : ($errors->hasAny($clesDesign) ? 'design' : ($errors->hasAny($clesReseau) ? 'reseau' : 'general'));
    }
    if (!in_array($onglet, ['general', 'design', 'reseau'], true)) {
        $onglet = 'general';
    }

    // Valeurs du formulaire (anciennes valeurs si la validation vient d'échouer)
    $form = [];
    foreach (\App\Services\PortailConfig::CHAMPS as $cle) {
        $form[$cle] = old($cle, $config[$cle]);
    }
    $form['titre_taille']    = (int) $form['titre_taille'];
    $form['bouton_rayon']    = (int) $form['bouton_rayon'];
    $form['annonce_active']  = (string) $form['annonce_active'] === '1';
    $form['portail_domaine'] = old('portail_domaine', $domaine);

    $payload = [
        'tab'     => $onglet,
        'form'    => $form,
        'logoUrl' => $logoUrl,
        'tarif'   => $tarifTexte,
        'port'    => $port,
    ];

    $champ = 'w-full rounded-lg border-slate-300 bg-white text-sm text-slate-900 shadow-sm placeholder:text-slate-400 focus:border-violet-500 focus:ring-violet-500';
    $label = 'mb-1.5 block text-[13px] font-medium text-slate-700';
    $bouton2 = 'inline-flex items-center justify-center gap-2 rounded-xl border border-slate-300 bg-white px-4 py-2.5 text-sm font-semibold text-slate-800 shadow-sm transition hover:bg-slate-50 focus:outline-none focus-visible:ring-2 focus-visible:ring-violet-500 disabled:cursor-wait disabled:opacity-60';
@endphp

<script>
document.addEventListener('alpine:init', () => {
    Alpine.data('studioPortail', (init) => ({
        tab: init.tab,
        mode: 'mobile',
        envoi: false,
        partant: false,
        form: init.form,
        initial: '',
        logoUrl: init.logoUrl,
        logoInitial: init.logoUrl,
        logoNom: '',
        logoErreur: '',
        supprimerLogo: false,
        tarif: init.tarif,
        port: init.port,
        echelle: 1,

        get cadre() {
            return this.mode === 'mobile' ? { w: 399, h: 764 } : { w: 1000, h: 680 };
        },
        get rayonPreset() {
            const r = Number(this.form.bouton_rayon);
            return r <= 8 ? 'carre' : (r >= 40 ? 'pilule' : 'doux');
        },
        get urlAffichee() {
            return 'http://' + (this.form.portail_domaine || 'portail') + (this.port !== 80 ? ':' + this.port : '') + '/portail';
        },
        get fondEcran() {
            const f = this.form;
            return {
                fontFamily: f.police,
                background: f.fond_type === 'gradient'
                    ? 'radial-gradient(120% 90% at 50% 0, ' + f.fond_couleur_2 + ' 0, transparent 70%), ' + f.fond_couleur_1
                    : f.fond_couleur_1,
            };
        },
        get dirty() {
            return JSON.stringify(this.form) !== this.initial || this.logoNom !== '' || this.supprimerLogo;
        },

        init() {
            this.initial = JSON.stringify(this.form);
            this.$nextTick(() => {
                this.ajuster();
                if (window.ResizeObserver && this.$refs.scene) {
                    new ResizeObserver(() => this.ajuster()).observe(this.$refs.scene);
                }
            });
            this.$watch('mode', () => this.$nextTick(() => this.ajuster()));
            window.addEventListener('beforeunload', (e) => {
                if (this.dirty && !this.partant) { e.preventDefault(); e.returnValue = ''; }
            });
        },

        ajuster() {
            const s = this.$refs.scene;
            if (!s || !s.clientWidth) return;
            this.echelle = Math.min(1, s.clientWidth / this.cadre.w);
        },

        changerLogo(e) {
            const f = e.target.files[0];
            if (!f) return;
            if (f.size > 2 * 1024 * 1024) {
                this.logoErreur = 'Image trop lourde (2 Mo au plus).';
                e.target.value = '';
                return;
            }
            this.logoErreur = '';
            this.logoNom = f.name;
            this.logoUrl = URL.createObjectURL(f);
            this.supprimerLogo = false;
        },

        retirerLogo() {
            this.logoUrl = '';
            this.logoNom = '';
            this.logoErreur = '';
            this.supprimerLogo = true;
            if (this.$refs.logoInput) this.$refs.logoInput.value = '';
        },

        annuler() {
            this.form = JSON.parse(this.initial);
            this.logoUrl = this.logoInitial;
            this.logoNom = '';
            this.logoErreur = '';
            this.supprimerLogo = false;
            if (this.$refs.logoInput) this.$refs.logoInput.value = '';
        },
    }));
});
</script>

<form id="form-portail" method="POST" action="{{ route('portail.admin.save') }}" enctype="multipart/form-data" novalidate
      x-data="studioPortail(@js($payload))"
      @submit="partant = true; setTimeout(() => envoi = true, 0)"
      @pageshow.window="envoi = false"
      class="space-y-6">
    @csrf
    <input type="hidden" name="onglet" :value="tab">
    <input type="hidden" name="supprimer_logo" :value="supprimerLogo ? '1' : '0'">
    <input type="hidden" name="annonce_active" :value="form.annonce_active ? '1' : '0'">

    @include('portail.admin._entete')

    <x-flash />

    @if ($errors->any())
        <div class="rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800">
            <p class="font-semibold">Rien n'a été enregistré. Corrigez ceci :</p>
            <ul class="mt-1 list-disc space-y-0.5 pl-5">
                @foreach ($errors->all() as $e)
                    <li>{{ $e }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    @if ($sync['ip_changee'])
        <div class="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">
            <strong class="font-semibold">L'adresse de ce PC a changé</strong>
            (avant : <code class="font-mono">{{ $sync['ip_synchronisee'] }}</code>, maintenant : <code class="font-mono">{{ $ipServeur }}</code>).
            Le routeur envoie encore les clients vers l'ancienne adresse : cliquez sur « Enregistrer et diffuser au routeur ».
        </div>
    @endif

    {{-- Sous-onglets + état de la synchronisation --}}
    <div class="flex items-center gap-1 border-b border-slate-200" role="tablist">
        @foreach ([['general', 'Textes', 'Général & Textes', 'pencil'], ['design', 'Design', 'Design & Couleurs', 'palette'], ['reseau', 'Réseau', 'Réseau & Routeur', 'wifi']] as [$id, $court, $long, $ic])
            <button type="button" role="tab" @click="tab = '{{ $id }}'" :aria-selected="tab === '{{ $id }}'"
                    :class="tab === '{{ $id }}' ? 'border-violet-600 text-violet-700' : 'border-transparent text-slate-500 hover:text-slate-800'"
                    class="-mb-px inline-flex items-center gap-2 border-b-2 px-3 py-3 text-sm font-semibold transition focus:outline-none focus-visible:ring-2 focus-visible:ring-violet-500 sm:px-4">
                <x-icone :nom="$ic" class="h-4 w-4" />
                <span class="sm:hidden">{{ $court }}</span>
                <span class="hidden sm:inline">{{ $long }}</span>
            </button>
        @endforeach

        <div class="ml-auto hidden items-center gap-1.5 pb-1 text-xs lg:flex"
             @if ($sync['message']) title="{{ $sync['message'] }}" @endif>
            @if ($sync['jamais'])
                <span class="text-slate-500">Routeur jamais synchronisé</span>
            @elseif ($sync['ok'])
                <x-icone nom="check" class="h-4 w-4 text-emerald-600" />
                <span class="text-slate-600">Routeur synchronisé {{ $sync['il_y_a'] }}</span>
            @else
                <x-icone nom="warning" class="h-4 w-4 text-rose-600" />
                <span class="text-rose-700">Dernière synchronisation échouée {{ $sync['il_y_a'] }}</span>
            @endif
        </div>
    </div>

    <div class="grid grid-cols-1 items-start gap-6 2xl:grid-cols-12">

        {{-- Réglages --}}
        <div class="min-w-0 space-y-6 2xl:col-span-7" x-ref="haut">
            @include('portail.admin._general')
            @include('portail.admin._design')
            @include('portail.admin._reseau')
        </div>

        {{-- Aperçu --}}
        <div class="min-w-0 2xl:sticky 2xl:top-4 2xl:col-span-5" x-ref="apercu">
            @include('portail.admin._apercu')
        </div>
    </div>

    {{-- Raccourcis sur petits écrans --}}
    <button type="button" @click="$refs.apercu.scrollIntoView({ behavior: 'smooth', block: 'start' })"
            class="fixed bottom-4 right-4 z-30 inline-flex items-center gap-2 rounded-full bg-violet-600 px-4 py-3 text-sm font-semibold text-white shadow-lg 2xl:hidden">
        <x-icone nom="eye" class="h-5 w-5" /> Voir l'aperçu
    </button>
</form>

{{-- Formulaire séparé (les formulaires ne peuvent pas être imbriqués) --}}
<form id="form-reset" method="POST" action="{{ route('portail.admin.reset') }}">@csrf</form>
@endsection