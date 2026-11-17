{{--
    Coque des pages Paramètres. Appliquée automatiquement par layouts/cyber-manager.blade.php.
    - ≥ 1024 px : menu vertical fixe à gauche.
    - < 1024 px : un bouton qui déroule la liste (aucun défilement horizontal).
--}}
@php
    $actif = match (true) {
        request()->routeIs('parametres.installation.*') => 'installation',
        request()->routeIs('parametres.versions.*')     => 'versions',
        request()->routeIs('parametres.parc.*')         => 'parc',
        request()->routeIs('parametres.personnel.*')    => 'personnel',
        request()->routeIs('enregistrement.*')          => 'enregistrement',
        request()->routeIs('portail.*')                 => 'portail',
        default                                         => 'identite',
    };

    // [clé, libellé, icône, infobulle, route]
    $entrees = [
        ['identite',       'Identité',             'tag',          'Nom, logo et couleur du cyber',         'parametres.index'],
        ['installation',   'Installation',         'upload-cloud', 'Ajouter un nouveau poste',              'parametres.installation.index'],
        ['versions',       'Versions',             'archive',      'Mises à jour des postes',               'parametres.versions.index'],
        ['parc',           'Parc et restrictions', 'desktop',      'État des postes et blocages Windows',   'parametres.parc.index'],
        ['personnel',      'Personnel',            'lock',         'Comptes du personnel et leurs droits',  'parametres.personnel.index'],
        ['enregistrement', 'Enregistrement',       'check',        'Code d\'installation, noms des postes', 'enregistrement.index'],
        ['portail',        'Portail Captif',       'link',         'Page de connexion Wi-Fi',               'portail.admin'],
    ];

    $courant = collect($entrees)->firstWhere(0, $actif);
@endphp

<div class="mx-auto max-w-[1700px] lg:grid lg:grid-cols-[15rem_minmax(0,1fr)] lg:items-start lg:gap-6">

    {{-- ═══ Mobile et tablette : menu déroulant ═══ --}}
    <div x-data="{ ouvert: false }" @keydown.escape.window="ouvert = false" @click.outside="ouvert = false"
         class="relative mb-5 lg:hidden">
        <button type="button" @click="ouvert = !ouvert" :aria-expanded="ouvert" aria-haspopup="true"
                class="flex w-full items-center gap-3 rounded-xl border border-slate-200 bg-white px-4 py-3 text-left shadow-sm transition hover:bg-slate-50 focus:outline-none focus-visible:ring-2 focus-visible:ring-violet-500">
            <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-violet-600 text-white">
                <x-icone :nom="$courant[2]" classe="h-5 w-5" />
            </span>
            <span class="min-w-0 flex-1">
                <span class="block text-xs text-slate-500">Paramètres</span>
                <span class="block truncate text-sm font-semibold text-slate-900">{{ $courant[1] }}</span>
            </span>
            <x-icone nom="chevron" classe="h-4 w-4 shrink-0 text-slate-400 transition-transform" x-bind:class="ouvert ? 'rotate-180' : ''" />
        </button>

        <div x-show="ouvert" x-cloak x-transition.opacity.duration.100ms
             class="absolute inset-x-0 top-full z-30 mt-2 rounded-2xl border border-slate-200 bg-white p-2 shadow-xl">
            <x-parametres.menu :entrees="$entrees" :actif="$actif" />
        </div>
    </div>

    {{-- ═══ Desktop : menu fixe à gauche ═══ --}}
    <aside class="hidden rounded-2xl border border-slate-200 bg-slate-50 p-2 shadow-sm lg:sticky lg:top-4 lg:block">
        <div class="flex items-center gap-2.5 px-3 pb-3 pt-2">
            <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-violet-100 text-violet-700">
                <x-icone nom="tag" classe="h-5 w-5" />
            </span>
            <div class="min-w-0">
                <p class="text-base font-bold leading-tight text-slate-900">Paramètres</p>
                <p class="text-xs text-slate-500">Administration du cyber</p>
            </div>
        </div>
        <x-parametres.menu :entrees="$entrees" :actif="$actif" />
    </aside>

    <div class="min-w-0">
        {{ $slot }}
    </div>
</div>