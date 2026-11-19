@extends('layouts.cyber-manager', ['title' => 'Tableau de bord — Cyber Manager'])

@section('content')
<div class="space-y-6">

    {{-- En-tête : Salutations & Statut Système --}}
    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h1 class="text-2xl font-bold tracking-tight text-slate-900 dark:text-white">
                Tableau de bord
            </h1>
            <p class="text-sm text-slate-500 dark:text-slate-400">
                Aujourd'hui, {{ now()->locale('fr')->isoFormat('dddd D MMMM YYYY') }}
            </p>
        </div>

        <div class="flex items-center gap-2">
            @if($stats['mikrotik']['connecte'])
                <span class="inline-flex items-center gap-1.5 rounded-full border border-emerald-200 bg-emerald-50 px-3 py-1 text-xs font-semibold text-emerald-800 dark:border-emerald-800 dark:bg-emerald-950/40 dark:text-emerald-300">
                    <span class="h-2 w-2 rounded-full bg-emerald-500"></span>
                    {{ $stats['mikrotik']['identity'] }} en ligne
                </span>
            @else
                <span class="inline-flex items-center gap-1.5 rounded-full border border-rose-200 bg-rose-50 px-3 py-1 text-xs font-semibold text-rose-800 dark:border-rose-800 dark:bg-rose-950/40 dark:text-rose-300"
                      title="{{ $stats['mikrotik']['erreur'] }}">
                    <span class="h-2 w-2 rounded-full bg-rose-500"></span>
                    Routeur déconnecté
                </span>
            @endif
        </div>
    </div>

    {{-- 4 Grandes Cartes Principales (KPIs) --}}
    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">

        {{-- Carte 1 : Recette du jour --}}
        <div class="relative overflow-hidden rounded-2xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
            <div class="flex items-center justify-between">
                <span class="text-xs font-bold uppercase tracking-wider text-slate-400">Caisse du jour</span>
                <span class="flex h-9 w-9 items-center justify-center rounded-xl bg-emerald-50 text-emerald-600 dark:bg-emerald-950/50 dark:text-emerald-400">
                    <x-icone nom="tag" class="h-5 w-5" />
                </span>
            </div>
            <div class="mt-3">
                <div class="text-3xl font-extrabold text-slate-900 dark:text-white">
                    {{ number_format($stats['recette']['total'], 0, ',', ' ') }} <span class="text-base font-semibold text-emerald-600">Ar</span>
                </div>
                <div class="mt-2 flex items-center gap-3 text-xs text-slate-500 dark:text-slate-400">
                    <span>💻 PC : <strong>{{ number_format($stats['recette']['postes'], 0, ',', ' ') }} Ar</strong></span>
                    <span>•</span>
                    <span>📶 Wi-Fi : <strong>{{ number_format($stats['recette']['wifi'], 0, ',', ' ') }} Ar</strong></span>
                </div>
            </div>
        </div>

        {{-- Carte 2 : Occupation des Postes --}}
        <div class="relative overflow-hidden rounded-2xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
            <div class="flex items-center justify-between">
                <span class="text-xs font-bold uppercase tracking-wider text-slate-400">Postes PC</span>
                <span class="flex h-9 w-9 items-center justify-center rounded-xl bg-violet-50 text-violet-600 dark:bg-violet-950/50 dark:text-violet-400">
                    <x-icone nom="desktop" class="h-5 w-5" />
                </span>
            </div>
            <div class="mt-3">
                <div class="flex items-baseline gap-2">
                    <span class="text-3xl font-extrabold text-slate-900 dark:text-white">{{ $stats['postes']['occupes'] }}</span>
                    <span class="text-base font-semibold text-slate-400">/ {{ $stats['postes']['total'] }} occupés</span>
                </div>
                {{-- Petite jauge visuelle --}}
                <div class="mt-3 h-2 w-full overflow-hidden rounded-full bg-slate-100 dark:bg-slate-800">
                    <div class="h-full rounded-full bg-violet-600 transition-all duration-500" style="width: {{ $stats['postes']['taux'] }}%"></div>
                </div>
                <div class="mt-2 flex justify-between text-xs text-slate-500 dark:text-slate-400">
                    <span>{{ $stats['postes']['disponibles'] }} libres</span>
                    <span>{{ $stats['postes']['taux'] }}% d'occupation</span>
                </div>
            </div>
        </div>

        {{-- Carte 3 : Wi-Fi en direct --}}
        <div class="relative overflow-hidden rounded-2xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
            <div class="flex items-center justify-between">
                <span class="text-xs font-bold uppercase tracking-wider text-slate-400">Clients Wi-Fi</span>
                <span class="flex h-9 w-9 items-center justify-center rounded-xl bg-indigo-50 text-indigo-600 dark:bg-indigo-950/50 dark:text-indigo-400">
                    <x-icone nom="wifi" class="h-5 w-5" />
                </span>
            </div>
            <div class="mt-3">
                <div class="text-3xl font-extrabold text-slate-900 dark:text-white">
                    {{ $stats['wifi']['clients_actifs'] }}
                </div>
                <p class="mt-2 text-xs text-slate-500 dark:text-slate-400">
                    Appareils actuellement connectés au réseau
                </p>
            </div>
        </div>

        {{-- Carte 4 : Vouchers disponibles --}}
        <div class="relative overflow-hidden rounded-2xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
            <div class="flex items-center justify-between">
                <span class="text-xs font-bold uppercase tracking-wider text-slate-400">Tickets Wi-Fi</span>
                <span class="flex h-9 w-9 items-center justify-center rounded-xl bg-amber-50 text-amber-600 dark:bg-amber-950/50 dark:text-amber-400">
                    <x-icone nom="key" class="h-5 w-5" />
                </span>
            </div>
            <div class="mt-3">
                <div class="flex items-baseline gap-2">
                    <span class="text-3xl font-extrabold text-slate-900 dark:text-white">{{ $stats['vouchers']['disponibles'] }}</span>
                    <span class="text-xs text-slate-400">en stock</span>
                </div>
                <div class="mt-2 text-xs">
                    @if($stats['vouchers']['disponibles'] < 10)
                        <span class="font-semibold text-amber-600 dark:text-amber-400">⚠️ Stock bientôt bas</span>
                    @else
                        <span class="text-slate-500 dark:text-slate-400">Prêts pour la vente</span>
                    @endif
                </div>
            </div>
        </div>

    </div>

    {{-- Section Inférieure : Raccourcis Rapides + Dernières Activités --}}
    <div class="grid grid-cols-1 gap-6 lg:grid-cols-12">

        {{-- Colonne Gauche : Accès Rapides (5 colonnes) --}}
        <div class="space-y-4 lg:col-span-5">
            <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                <h2 class="text-base font-bold text-slate-900 dark:text-white">Accès directs</h2>
                <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">Passez à l'action en un clic.</p>

                <div class="mt-4 grid grid-cols-2 gap-3">
                    <a href="{{ route('postes.index') }}"
                       class="flex flex-col items-center justify-center gap-2 rounded-xl border border-slate-200 bg-slate-50 p-4 text-center transition hover:border-violet-300 hover:bg-violet-50/50 dark:border-slate-800 dark:bg-slate-800/60 dark:hover:border-violet-700">
                        <span class="flex h-10 w-10 items-center justify-center rounded-full bg-violet-600 text-white shadow-sm">
                            <x-icone nom="desktop" class="h-5 w-5" />
                        </span>
                        <span class="text-xs font-bold text-slate-800 dark:text-slate-200">Gérer les Postes</span>
                    </a>

                    <a href="{{ route('wifi.index') }}"
                       class="flex flex-col items-center justify-center gap-2 rounded-xl border border-slate-200 bg-slate-50 p-4 text-center transition hover:border-indigo-300 hover:bg-indigo-50/50 dark:border-slate-800 dark:bg-slate-800/60 dark:hover:border-indigo-700">
                        <span class="flex h-10 w-10 items-center justify-center rounded-full bg-indigo-600 text-white shadow-sm">
                            <x-icone nom="wifi" class="h-5 w-5" />
                        </span>
                        <span class="text-xs font-bold text-slate-800 dark:text-slate-200">Gérer le Wi-Fi</span>
                    </a>

                    <a href="{{ route('hotspot.index') }}"
                       class="flex flex-col items-center justify-center gap-2 rounded-xl border border-slate-200 bg-slate-50 p-4 text-center transition hover:border-amber-300 hover:bg-amber-50/50 dark:border-slate-800 dark:bg-slate-800/60 dark:hover:border-amber-700">
                        <span class="flex h-10 w-10 items-center justify-center rounded-full bg-amber-500 text-white shadow-sm">
                            <x-icone nom="key" class="h-5 w-5" />
                        </span>
                        <span class="text-xs font-bold text-slate-800 dark:text-slate-200">Tickets HotSpot</span>
                    </a>

                    <a href="{{ route('tarifs.index') }}"
                       class="flex flex-col items-center justify-center gap-2 rounded-xl border border-slate-200 bg-slate-50 p-4 text-center transition hover:border-slate-300 hover:bg-slate-100 dark:border-slate-800 dark:bg-slate-800/60 dark:hover:border-slate-700">
                        <span class="flex h-10 w-10 items-center justify-center rounded-full bg-slate-700 text-white shadow-sm dark:bg-slate-600">
                            <x-icone nom="tag" class="h-5 w-5" />
                        </span>
                        <span class="text-xs font-bold text-slate-800 dark:text-slate-200">Tarifs & Prix</span>
                    </a>
                </div>
            </div>
        </div>

        {{-- Colonne Droite : Dernières Sessions / Activité récente (7 colonnes) --}}
        <div class="lg:col-span-7">
            <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                <div class="flex items-center justify-between">
                    <div>
                        <h2 class="text-base font-bold text-slate-900 dark:text-white">Sessions récentes</h2>
                        <p class="text-xs text-slate-500 dark:text-slate-400">Derniers passages enregistrés.</p>
                    </div>
                    <a href="{{ route('historique.index') }}" class="text-xs font-semibold text-violet-600 hover:underline dark:text-violet-400">
                        Voir tout l'historique →
                    </a>
                </div>

                <div class="mt-4 divide-y divide-slate-100 dark:divide-slate-800">
                    @forelse($stats['recentes'] as $session)
                        <div class="flex items-center justify-between py-3">
                            <div class="flex items-center gap-3">
                                <span class="flex h-9 w-9 items-center justify-center rounded-lg {{ $session['type'] === 'ethernet' ? 'bg-violet-50 text-violet-600 dark:bg-violet-950/60 dark:text-violet-400' : 'bg-indigo-50 text-indigo-600 dark:bg-indigo-950/60 dark:text-indigo-400' }}">
                                    <x-icone :nom="$session['type'] === 'ethernet' ? 'desktop' : 'wifi'" class="h-4 w-4" />
                                </span>
                                <div>
                                    <p class="text-sm font-semibold text-slate-900 dark:text-white">{{ $session['nom'] }}</p>
                                    <p class="text-xs text-slate-400">{{ $session['date_formatee'] }} &middot; {{ $session['duree_min'] }} min</p>
                                </div>
                            </div>
                            <div class="text-right">
                                <p class="text-sm font-bold text-slate-900 dark:text-white">
                                    {{ number_format($session['montant'], 0, ',', ' ') }} Ar
                                </p>
                                <span class="inline-block rounded px-1.5 py-0.5 text-[10px] font-medium {{ $session['etat'] === 'en_cours' ? 'bg-emerald-50 text-emerald-700 dark:bg-emerald-950/50 dark:text-emerald-400' : 'bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-400' }}">
                                    {{ $session['etat'] === 'en_cours' ? 'En cours' : 'Terminé' }}
                                </span>
                            </div>
                        </div>
                    @empty
                        <div class="py-8 text-center text-sm text-slate-400">
                            Aucune session enregistrée aujourd'hui.
                        </div>
                    @endforelse
                </div>
            </div>
        </div>

    </div>

</div>
@endsection