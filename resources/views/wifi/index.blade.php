@extends('layouts.cyber-manager', ['title' => 'Wi-Fi — Cyber Manager'])

@section('content')

@if($erreur)
    <div class="bg-red-50 border border-red-200 text-red-800 rounded-lg p-4 mb-6">
        <div class="font-semibold">MikroTik injoignable</div>
        <div class="text-sm mt-1 break-all">{{ $erreur }}</div>
    </div>
@endif

@if(session('success'))
    <div class="bg-green-50 border border-green-200 text-green-800 rounded-lg p-3 mb-4 text-sm">
        {{ session('success') }}
    </div>
@endif

@if($errors->any())
    <div class="bg-red-50 border border-red-200 text-red-800 rounded-lg p-3 mb-4 text-sm">
        @foreach($errors->all() as $err)
            <div>{{ $err }}</div>
        @endforeach
    </div>
@endif

<div class="flex justify-between items-center mb-3">
    <form method="POST" action="{{ route('wifi.sync') }}">
        @csrf
        <button type="submit"
                class="px-3 py-2 rounded-md bg-blue-600 text-white hover:bg-blue-700 text-sm">
            ⟳ Synchroniser maintenant
        </button>
    </form>
    <a href="{{ route('wifi.comptes') }}"
       class="text-sm text-blue-600 hover:text-blue-800">
        Voir tous les comptes HotSpot →
    </a>
</div>

<div class="flex justify-end mb-3">
    <a href="{{ route('wifi.comptes') }}"
       class="text-sm text-blue-600 hover:text-blue-800">
        Voir tous les comptes HotSpot →
    </a>
</div>

{{-- Cartes synthèse --}}
<div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-6">

    <div class="bg-white border border-gray-200 rounded-lg p-5">
        <div class="text-sm text-gray-500 mb-1">Clients connectés</div>
        <div class="text-3xl font-bold text-purple-600">{{ count($active) }}</div>
        <div class="text-xs text-gray-500 mt-2">Sessions HotSpot actives</div>
    </div>

    <div class="bg-white border border-gray-200 rounded-lg p-5">
        <div class="text-sm text-gray-500 mb-1">Appareils connus</div>
        <div class="text-3xl font-bold text-blue-600">{{ count($hosts) }}</div>
        <div class="text-xs text-gray-500 mt-2">Déjà vus par le HotSpot</div>
    </div>

    <div class="bg-white border border-gray-200 rounded-lg p-5">
        <div class="text-sm text-gray-500 mb-1">Baux DHCP</div>
        <div class="text-3xl font-bold text-amber-600">{{ count($leases) }}</div>
        <div class="text-xs text-gray-500 mt-2">Adresses IP attribuées</div>
    </div>

</div>


{{-- Onglets --}}
<div x-data="{ tab: 'active' }" class="bg-white border border-gray-200 rounded-lg">

    <div class="border-b border-gray-200 px-4 flex gap-4">
        <button @click="tab = 'active'"
                :class="tab === 'active'
                    ? 'border-b-2 border-purple-600 text-purple-700 font-semibold'
                    : 'text-gray-500 hover:text-gray-700'"
                class="py-3 text-sm">
            Clients connectés ({{ count($active) }})
        </button>
        <button @click="tab = 'hosts'"
                :class="tab === 'hosts'
                    ? 'border-b-2 border-blue-600 text-blue-700 font-semibold'
                    : 'text-gray-500 hover:text-gray-700'"
                class="py-3 text-sm">
            Appareils connus ({{ count($hosts) }})
        </button>
        <button @click="tab = 'leases'"
                :class="tab === 'leases'
                    ? 'border-b-2 border-amber-600 text-amber-700 font-semibold'
                    : 'text-gray-500 hover:text-gray-700'"
                class="py-3 text-sm">
            Baux DHCP ({{ count($leases) }})
        </button>
    </div>

    {{-- Tableau : clients actifs --}}
    <div x-show="tab === 'active'" class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="bg-gray-50 text-gray-600 text-left">
                <tr>
                    <th class="px-4 py-3">Utilisateur</th>
                    <th class="px-4 py-3">Adresse IP</th>
                    <th class="px-4 py-3">MAC</th>
                    <th class="px-4 py-3">Uptime</th>
                    <th class="px-4 py-3">↓ / ↑</th>
                    <th class="px-4 py-3">Commentaire</th>
                    <th class="px-4 py-3 text-right">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse($active as $row)
                    <tr class="hover:bg-gray-50">
                        <td class="px-4 py-3 font-mono">{{ $row['user'] ?? '—' }}</td>
                        <td class="px-4 py-3 font-mono">{{ $row['address'] ?? '—' }}</td>
                        <td class="px-4 py-3 font-mono text-xs">{{ $row['mac-address'] ?? '—' }}</td>
                        <td class="px-4 py-3">{{ $row['uptime'] ?? '—' }}</td>
                        <td class="px-4 py-3 text-xs text-gray-500">
                            {{ $row['bytes-in'] ?? '0' }} / {{ $row['bytes-out'] ?? '0' }}
                        </td>
                        <td class="px-4 py-3 text-xs text-gray-500">
                            {{ $row['comment'] ?? '—' }}
                        </td>
                        <td class="px-4 py-3 text-right">
                            <form method="POST" action="{{ route('wifi.deconnecter') }}"
                                onsubmit="return confirm('Déconnecter ce client ?');">
                                @csrf
                                <input type="hidden" name="mac" value="{{ $row['mac-address'] ?? '' }}">
                                <button class="text-red-600 hover:text-red-800 text-xs">
                                    Déconnecter
                                </button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="px-4 py-8 text-center text-gray-400">
                            Aucun client connecté au HotSpot.
                        </td>
                        <td class="px-4 py-3 text-right">
                            <form method="POST" action="{{ route('wifi.deconnecter') }}"
                                onsubmit="return confirm('Déconnecter ce client ?');">
                                @csrf
                                <input type="hidden" name="mac" value="{{ $row['mac-address'] ?? '' }}">
                                <button class="text-red-600 hover:text-red-800 text-xs">
                                    Déconnecter
                                </button>
                            </form>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{-- Tableau : hosts connus --}}
    <div x-show="tab === 'hosts'" class="overflow-x-auto" style="display: none;">
        <table class="w-full text-sm">
            <thead class="bg-gray-50 text-gray-600 text-left">
                <tr>
                    <th class="px-4 py-3">MAC</th>
                    <th class="px-4 py-3">Adresse IP</th>
                    <th class="px-4 py-3">To-Address</th>
                    <th class="px-4 py-3">Uptime</th>
                    <th class="px-4 py-3">Serveur</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse($hosts as $row)
                    <tr class="hover:bg-gray-50">
                        <td class="px-4 py-3 font-mono text-xs">{{ $row['mac-address'] ?? '—' }}</td>
                        <td class="px-4 py-3 font-mono">{{ $row['address'] ?? '—' }}</td>
                        <td class="px-4 py-3 font-mono text-xs">{{ $row['to-address'] ?? '—' }}</td>
                        <td class="px-4 py-3">{{ $row['uptime'] ?? '—' }}</td>
                        <td class="px-4 py-3 text-xs text-gray-500">{{ $row['server'] ?? '—' }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="px-4 py-8 text-center text-gray-400">
                            Aucun appareil connu.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{-- Tableau : baux DHCP --}}
    <div x-show="tab === 'leases'" class="overflow-x-auto" style="display: none;">
        <table class="w-full text-sm">
            <thead class="bg-gray-50 text-gray-600 text-left">
                <tr>
                    <th class="px-4 py-3">Adresse IP</th>
                    <th class="px-4 py-3">MAC</th>
                    <th class="px-4 py-3">Hostname</th>
                    <th class="px-4 py-3">Statut</th>
                    <th class="px-4 py-3">Expire dans</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse($leases as $row)
                    <tr class="hover:bg-gray-50">
                        <td class="px-4 py-3 font-mono">{{ $row['address'] ?? '—' }}</td>
                        <td class="px-4 py-3 font-mono text-xs">{{ $row['mac-address'] ?? '—' }}</td>
                        <td class="px-4 py-3">{{ $row['host-name'] ?? '—' }}</td>
                        <td class="px-4 py-3">
                            @php
                                $st = $row['status'] ?? '—';
                                $color = match($st) {
                                    'bound'   => 'bg-green-100 text-green-800',
                                    'waiting' => 'bg-amber-100 text-amber-800',
                                    default   => 'bg-gray-100 text-gray-700',
                                };
                            @endphp
                            <span class="px-2 py-1 rounded text-xs {{ $color }}">{{ $st }}</span>
                        </td>
                        <td class="px-4 py-3 text-xs text-gray-500">
                            {{ $row['expires-after'] ?? '—' }}
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="px-4 py-8 text-center text-gray-400">
                            Aucun bail DHCP.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

</div>

<p class="text-xs text-gray-400 mt-3">
    Les données sont lues en direct depuis MikroTik à chaque rechargement.
</p>

@endsection