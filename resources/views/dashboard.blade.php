@extends('layouts.cyber-manager', ['title' => 'Dashboard — Cyber Manager'])

@section('content')

{{-- ============================================================= --}}
{{-- Bandeau état MikroTik --}}
{{-- ============================================================= --}}
@if($stats['mikrotik']['connecte'])

    <div class="bg-green-50 border border-green-200 rounded-lg p-4 mb-6">
        <div class="flex items-center gap-2 text-green-800 font-semibold">
            <span class="w-2 h-2 rounded-full bg-green-500"></span>
            MikroTik connecté —
            {{ $stats['mikrotik']['identity'] }}
            (v{{ $stats['mikrotik']['version'] }})
        </div>
        <div class="text-sm text-green-700 mt-1">
            Board : {{ $stats['mikrotik']['board'] }}
            &middot; Uptime : {{ $stats['mikrotik']['uptime'] }}
        </div>
    </div>

@else

    <div class="bg-red-50 border border-red-200 rounded-lg p-4 mb-6">
        <div class="flex items-center gap-2 text-red-800 font-semibold">
            <span class="w-2 h-2 rounded-full bg-red-500"></span>
            MikroTik injoignable
        </div>
        <div class="text-sm text-red-700 mt-1 break-all">
            {{ $stats['mikrotik']['erreur'] }}
        </div>
        <div class="text-xs text-red-600 mt-2">
            Les statistiques HotSpot ci-dessous sont indisponibles.
            Les statistiques locales (postes, vouchers, sessions) restent valides.
        </div>
    </div>

@endif


{{-- ============================================================= --}}
{{-- Cartes synthèse --}}
{{-- ============================================================= --}}
<div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">

    {{-- Postes --}}
    <div class="bg-white border border-gray-200 rounded-lg p-5">
        <div class="text-sm text-gray-500 mb-1">Postes</div>
        <div class="text-3xl font-bold text-gray-800">
            {{ $stats['postes']['en_utilisation'] }}
            <span class="text-base font-normal text-gray-400">
                / {{ $stats['postes']['total'] }}
            </span>
        </div>
        <div class="text-xs text-gray-500 mt-2">
            {{ $stats['postes']['disponible'] }} disponibles
            &middot; {{ $stats['postes']['suspendu'] }} suspendus
        </div>
    </div>

    {{-- Sessions en cours --}}
    <div class="bg-white border border-gray-200 rounded-lg p-5">
        <div class="text-sm text-gray-500 mb-1">Sessions en cours</div>
        <div class="text-3xl font-bold text-blue-600">
            {{ $stats['sessions']['en_cours'] }}
        </div>
        <div class="text-xs text-gray-500 mt-2">
            {{ $stats['sessions']['suspendue'] }} suspendues
            &middot; {{ $stats['sessions']['aujourdhui'] }} aujourd'hui
        </div>
    </div>

    {{-- HotSpot actifs --}}
    <div class="bg-white border border-gray-200 rounded-lg p-5">
        <div class="text-sm text-gray-500 mb-1">Clients Wi-Fi actifs</div>
        <div class="text-3xl font-bold text-purple-600">
            @if($stats['mikrotik']['connecte'])
                {{ $stats['mikrotik']['active'] }}
            @else
                <span class="text-gray-300">—</span>
            @endif
        </div>
        <div class="text-xs text-gray-500 mt-2">
            @if($stats['mikrotik']['connecte'])
                {{ $stats['hotspot']['users_total'] }} comptes configurés
            @else
                indisponible
            @endif
        </div>
    </div>

    {{-- Vouchers --}}
    <div class="bg-white border border-gray-200 rounded-lg p-5">
        <div class="text-sm text-gray-500 mb-1">Vouchers disponibles</div>
        <div class="text-3xl font-bold text-amber-600">
            {{ $stats['vouchers']['disponible'] }}
        </div>
        <div class="text-xs text-gray-500 mt-2">
            {{ $stats['vouchers']['total'] }} au total
            @if($stats['vouchers']['non_sync'] > 0)
                &middot;
                <span class="text-red-600 font-semibold">
                    {{ $stats['vouchers']['non_sync'] }} non synchronisés
                </span>
            @endif
        </div>
    </div>

</div>


{{-- ============================================================= --}}
{{-- Deux colonnes : détail postes / détail vouchers+hotspot --}}
{{-- ============================================================= --}}
<div class="grid grid-cols-1 lg:grid-cols-2 gap-4">

    {{-- Postes --}}
    <div class="bg-white border border-gray-200 rounded-lg p-5">
        <h2 class="font-semibold text-gray-800 mb-3">État des postes</h2>

        <dl class="space-y-2 text-sm">
            <div class="flex justify-between">
                <dt class="text-gray-600">Disponibles</dt>
                <dd class="font-semibold text-green-600">
                    {{ $stats['postes']['disponible'] }}
                </dd>
            </div>
            <div class="flex justify-between">
                <dt class="text-gray-600">En utilisation</dt>
                <dd class="font-semibold text-blue-600">
                    {{ $stats['postes']['en_utilisation'] }}
                </dd>
            </div>
            <div class="flex justify-between">
                <dt class="text-gray-600">Suspendus</dt>
                <dd class="font-semibold text-amber-600">
                    {{ $stats['postes']['suspendu'] }}
                </dd>
            </div>
            <div class="flex justify-between">
                <dt class="text-gray-600">Désactivés</dt>
                <dd class="font-semibold text-gray-400">
                    {{ $stats['postes']['inactif'] }}
                </dd>
            </div>
        </dl>

        <div class="mt-4 pt-4 border-t border-gray-100 text-right">
            <a href="{{ route('postes.index') }}"
               class="text-sm text-blue-600 hover:text-blue-800">
                Voir les postes →
            </a>
        </div>
    </div>

    {{-- HotSpot + Vouchers --}}
    <div class="bg-white border border-gray-200 rounded-lg p-5">
        <h2 class="font-semibold text-gray-800 mb-3">HotSpot &amp; Vouchers</h2>

        @if($stats['mikrotik']['connecte'])
            <dl class="space-y-2 text-sm">
                <div class="flex justify-between">
                    <dt class="text-gray-600">Comptes HotSpot (MikroTik)</dt>
                    <dd class="font-semibold">{{ $stats['hotspot']['users_total'] }}</dd>
                </div>
                <div class="flex justify-between">
                    <dt class="text-gray-600">&nbsp;&nbsp;dont créés par Cyber Manager</dt>
                    <dd class="font-semibold text-blue-600">
                        {{ $stats['hotspot']['users_cm'] }}
                    </dd>
                </div>
                <div class="flex justify-between">
                    <dt class="text-gray-600">&nbsp;&nbsp;dont hérités (Mikhmon / manuels)</dt>
                    <dd class="font-semibold text-gray-500">
                        {{ $stats['hotspot']['users_autres'] }}
                    </dd>
                </div>
                <div class="flex justify-between">
                    <dt class="text-gray-600">Profils HotSpot</dt>
                    <dd class="font-semibold">{{ $stats['mikrotik']['profiles'] }}</dd>
                </div>
            </dl>
        @else
            <p class="text-sm text-gray-400 italic">
                Informations MikroTik indisponibles.
            </p>
        @endif

        <div class="mt-4 pt-4 border-t border-gray-100">
            <dl class="space-y-2 text-sm">
                <div class="flex justify-between">
                    <dt class="text-gray-600">Vouchers disponibles</dt>
                    <dd class="font-semibold text-green-600">
                        {{ $stats['vouchers']['disponible'] }}
                    </dd>
                </div>
                <div class="flex justify-between">
                    <dt class="text-gray-600">Vouchers utilisés</dt>
                    <dd class="font-semibold">{{ $stats['vouchers']['utilise'] }}</dd>
                </div>
                <div class="flex justify-between">
                    <dt class="text-gray-600">Vouchers annulés</dt>
                    <dd class="font-semibold text-gray-400">
                        {{ $stats['vouchers']['annule'] }}
                    </dd>
                </div>
                @if($stats['vouchers']['non_sync'] > 0)
                    <div class="flex justify-between">
                        <dt class="text-red-600">Non synchronisés vers MikroTik</dt>
                        <dd class="font-semibold text-red-600">
                            {{ $stats['vouchers']['non_sync'] }}
                        </dd>
                    </div>
                @endif
            </dl>
        </div>
    </div>

</div>

@endsection