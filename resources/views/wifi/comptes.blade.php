@extends('layouts.cyber-manager', ['title' => 'Comptes HotSpot — Cyber Manager'])

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

<div class="flex items-center justify-between mb-4">
    <div>
        <h1 class="text-xl font-bold">Comptes HotSpot MikroTik</h1>
        <p class="text-sm text-gray-500 mt-1">
            {{ count($comptes) }} comptes au total
            &middot; {{ $comptes->where('est_cyber_manager', true)->count() }} créés par Cyber Manager
        </p>
    </div>
    <a href="{{ route('wifi.index') }}"
       class="text-sm text-blue-600 hover:text-blue-800">
        ← Retour Wi-Fi
    </a>
</div>

<div class="bg-white border border-gray-200 rounded-lg overflow-hidden">
    <table class="w-full text-sm">
        <thead class="bg-gray-50 text-gray-600 text-left">
            <tr>
                <th class="px-4 py-3">Source</th>
                <th class="px-4 py-3">Utilisateur</th>
                <th class="px-4 py-3">Mot de passe</th>
                <th class="px-4 py-3">Profil</th>
                <th class="px-4 py-3">Limite</th>
                <th class="px-4 py-3">Utilisé</th>
                <th class="px-4 py-3">Statut</th>
                <th class="px-4 py-3 text-right">Actions</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-100">
            @forelse($comptes as $compte)
                <tr class="hover:bg-gray-50">
                    <td class="px-4 py-3">
                        @if($compte['est_cyber_manager'])
                            <span class="px-2 py-1 rounded text-xs bg-blue-100 text-blue-800">
                                Cyber Manager
                            </span>
                        @else
                            <span class="px-2 py-1 rounded text-xs bg-gray-100 text-gray-600">
                                Hérité
                            </span>
                        @endif
                    </td>
                    <td class="px-4 py-3 font-mono">{{ $compte['name'] ?? '—' }}</td>
                    <td class="px-4 py-3 font-mono text-gray-600">
                        {{ $compte['password'] ?? '—' }}
                    </td>
                    <td class="px-4 py-3 text-gray-600">{{ $compte['profile'] ?? '—' }}</td>
                    <td class="px-4 py-3 text-gray-600 text-xs">
                        {{ $compte['limit-uptime'] ?? '—' }}
                    </td>
                    <td class="px-4 py-3 text-gray-600 text-xs">
                        {{ $compte['uptime'] ?? '0s' }}
                    </td>
                    <td class="px-4 py-3">
                        @if(($compte['disabled'] ?? 'false') === 'true')
                            <span class="px-2 py-1 rounded text-xs bg-red-100 text-red-800">
                                désactivé
                            </span>
                        @else
                            <span class="px-2 py-1 rounded text-xs bg-green-100 text-green-800">
                                actif
                            </span>
                        @endif
                    </td>
                    <td class="px-4 py-3 text-right">
                        <div class="flex gap-3 justify-end">
                            <form method="POST"
                                action="{{ route('wifi.comptes.toggle', $compte['name']) }}">
                                @csrf
                                <button class="text-amber-600 hover:text-amber-800 text-xs">
                                    {{ ($compte['disabled'] ?? 'false') === 'true' ? 'Réactiver' : 'Désactiver' }}
                                </button>
                            </form>

                            <form method="POST"
                                action="{{ route('wifi.comptes.supprimer', $compte['name']) }}"
                                onsubmit="return confirm('Supprimer définitivement ce compte ?');">
                                @csrf
                                @method('DELETE')
                                <button class="text-red-600 hover:text-red-800 text-xs">
                                    Supprimer
                                </button>
                            </form>
                        </div>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="8" class="px-4 py-8 text-center text-gray-400">
                        Aucun compte HotSpot.
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>

@endsection