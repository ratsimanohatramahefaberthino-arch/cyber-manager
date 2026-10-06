<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ $title ?? 'Cyber Manager' }}</title>

    <style>[x-cloak]{display:none !important}</style>

    @vite(['resources/css/app.css', 'resources/js/app.js'])

    <script>
        window.cmFetch = function (url, opts = {}) {
            const token = document.querySelector('meta[name="csrf-token"]').content;
            return fetch(url, {
                ...opts,
                headers: { 'X-CSRF-TOKEN': token, 'Accept': 'application/json', ...(opts.headers || {}) },
            });
        };

        window.cmCopy = async function (texte) {
            try {
                await navigator.clipboard.writeText(texte);
            } catch (e) {
                const t = document.createElement('textarea');
                t.value = texte; t.style.position = 'fixed'; t.style.opacity = '0';
                document.body.appendChild(t); t.select();
                try { document.execCommand('copy'); } catch (_) {}
                t.remove();
            }
            window.dispatchEvent(new CustomEvent('toast', { detail: 'Copié dans le presse-papiers' }));
        };

        window.mikrotikStatut = function (url) {
            return {
                etat: 'chargement', env: '', titre: 'Vérification…',
                async charger() {
                    try {
                        const r = await fetch(url, { headers: { 'Accept': 'application/json' } });
                        const d = await r.json();
                        this.etat = d.connecte ? 'ok' : 'ko';
                        this.env = d.environnement || '';
                        this.titre = d.connecte ? (d.identity || '') + ' · RouterOS ' + (d.version || '') : (d.erreur || 'Injoignable');
                    } catch (e) { this.etat = 'ko'; this.titre = 'Statut indisponible'; }
                },
                init() { this.charger(); setInterval(() => this.charger(), 30000); },
            };
        };

        window.cmSidebar = function () {
            return {
                open: localStorage.getItem('cm_sidebar_open') !== '0',
                mobileOpen: false,
                toggle() {
                    this.open = !this.open;
                    localStorage.setItem('cm_sidebar_open', this.open ? '1' : '0');
                },
            };
        };
    </script>
</head>

@php
    $liens = [
        ['route' => 'dashboard',      'motif' => 'dashboard',   'label' => 'Dashboard', 'icone' => 'home'],
        ['route' => 'postes.index',   'motif' => 'postes.*',    'label' => 'Postes',    'icone' => 'desktop'],
        ['route' => 'sessions.index', 'motif' => 'sessions.*',  'label' => 'Sessions',  'icone' => 'clock'],
        ['route' => 'wifi.index',     'motif' => 'wifi.*',      'label' => 'Wi-Fi',     'icone' => 'wifi'],
        ['route' => 'hotspot.index',  'motif' => 'hotspot.*',   'label' => 'HotSpot',   'icone' => 'key'],
    ];
    $bientot = [['Tarifs', 'tag'], ['Historique', 'archive'], ['Statistiques', 'chart']];
@endphp

<body x-data="cmSidebar()" class="min-h-screen bg-slate-50 text-slate-900 antialiased">

    <div x-data="{ items: [] }"
         @toast.window="const id = Date.now(); items.push({ id, msg: $event.detail }); setTimeout(() => items = items.filter(i => i.id !== id), 2500)"
         class="pointer-events-none fixed right-4 top-4 z-[60] space-y-2">
        <template x-for="i in items" :key="i.id">
            <div x-transition class="pointer-events-auto rounded-lg bg-slate-900 px-4 py-2.5 text-sm text-white shadow-lg" x-text="i.msg"></div>
        </template>
    </div>

    <div class="flex h-screen overflow-hidden">

        {{-- Sidebar desktop --}}
        <aside :class="open ? 'w-64' : 'w-[72px]'"
               class="hidden shrink-0 flex-col bg-gradient-to-b from-indigo-950 to-indigo-900 transition-all duration-200 sm:flex">
            <div class="flex h-14 items-center gap-2 border-b border-white/10 px-4">
                <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-white/10">
                    <x-icone nom="wifi" class="h-5 w-5 text-white" />
                </span>
                <span x-show="open" x-cloak class="truncate text-base font-semibold tracking-wide text-white">Cyber Manager</span>
                <button type="button" @click="toggle()" title="Réduire / agrandir le menu"
                        class="ml-auto flex h-8 w-8 shrink-0 cursor-pointer items-center justify-center rounded-lg text-indigo-300 transition hover:bg-white/10 hover:text-white">
                    <x-icone nom="chevron-left" class="h-5 w-5 transition-transform duration-200" x-bind:class="!open ? 'rotate-180' : ''" />
                </button>
            </div>

            <nav class="flex-1 space-y-1 overflow-y-auto p-2">
                @foreach($liens as $lien)
                    @php $actif = request()->routeIs($lien['motif']); @endphp
                    <a href="{{ route($lien['route']) }}" title="{{ $lien['label'] }}"
                       @class([
                           'flex items-center gap-3 rounded-lg px-3 py-2.5 text-[15px] font-medium transition',
                           'bg-white text-indigo-900 shadow-sm' => $actif,
                           'text-indigo-200 hover:bg-white/10 hover:text-white' => !$actif,
                       ])>
                        <x-icone :nom="$lien['icone']" class="h-5 w-5 shrink-0" />
                        <span x-show="open" x-cloak class="truncate">{{ $lien['label'] }}</span>
                    </a>
                @endforeach

                @foreach($bientot as [$label, $icone])
                    <span title="Bientôt disponible"
                          class="flex cursor-not-allowed items-center gap-3 rounded-lg px-3 py-2.5 text-[15px] font-medium text-indigo-400/50">
                        <x-icone :nom="$icone" class="h-5 w-5 shrink-0" />
                        <span x-show="open" x-cloak class="truncate">{{ $label }}</span>
                    </span>
                @endforeach
            </nav>
        </aside>

        {{-- Sidebar mobile --}}
        <div x-show="mobileOpen" x-cloak class="fixed inset-0 z-50 sm:hidden">
            <div class="absolute inset-0 bg-slate-900/50" @click="mobileOpen = false" x-transition.opacity></div>
            <aside x-show="mobileOpen" x-transition:enter="transition ease-out duration-200"
                   x-transition:enter-start="-translate-x-full" x-transition:enter-end="translate-x-0"
                   class="relative flex h-full w-64 flex-col bg-gradient-to-b from-indigo-950 to-indigo-900 shadow-xl">
                <div class="flex h-14 items-center gap-2 border-b border-white/10 px-4">
                    <span class="flex h-8 w-8 items-center justify-center rounded-lg bg-white/10">
                        <x-icone nom="wifi" class="h-5 w-5 text-white" />
                    </span>
                    <span class="text-base font-semibold text-white">Cyber Manager</span>
                </div>
                <nav class="flex-1 space-y-1 overflow-y-auto p-2">
                    @foreach($liens as $lien)
                        @php $actif = request()->routeIs($lien['motif']); @endphp
                        <a href="{{ route($lien['route']) }}"
                           @class(['flex items-center gap-3 rounded-lg px-3 py-2.5 text-[15px] font-medium', 'bg-white text-indigo-900' => $actif, 'text-indigo-200 hover:bg-white/10' => !$actif])>
                            <x-icone :nom="$lien['icone']" class="h-5 w-5 shrink-0" /> {{ $lien['label'] }}
                        </a>
                    @endforeach
                </nav>
            </aside>
        </div>

        <div class="flex flex-1 flex-col overflow-hidden">
            <header class="flex h-14 shrink-0 items-center justify-between border-b border-slate-200 bg-white px-4 sm:px-6">
                <button type="button" @click="mobileOpen = true" class="rounded-lg p-2 text-slate-500 hover:bg-slate-100 sm:hidden">
                    <x-icone nom="menu" class="h-5 w-5" />
                </button>
                <div class="hidden sm:block"></div>
                <div class="flex items-center gap-3">
                    <div x-data="mikrotikStatut('{{ route('mikrotik.statut') }}')" :title="titre"
                         class="hidden items-center gap-1.5 rounded-full border px-3 py-1 text-xs font-medium sm:flex"
                         :class="{ 'border-emerald-200 bg-emerald-50 text-emerald-700': etat === 'ok', 'border-rose-200 bg-rose-50 text-rose-700': etat === 'ko', 'border-slate-200 bg-slate-50 text-slate-500': etat === 'chargement' }">
                        <span x-text="etat === 'ko' ? '○' : '●'"></span>
                        <span x-text="etat === 'ok' ? 'MikroTik connecté' : (etat === 'ko' ? 'MikroTik déconnecté' : 'MikroTik…')"></span>
                        <span x-show="env" x-cloak x-text="env.toUpperCase()" class="rounded bg-white/70 px-1 text-[10px] tracking-wide"></span>
                    </div>
                    <span class="text-sm text-slate-600">{{ Auth::user()->name }}</span>
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit" class="text-sm text-rose-600 hover:text-rose-800">Déconnexion</button>
                    </form>
                </div>
            </header>

            <main class="flex-1 overflow-y-auto p-4 sm:p-6">
                @yield('content')
            </main>
        </div>

    </div>

</body>
</html>