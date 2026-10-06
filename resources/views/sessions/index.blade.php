@extends('layouts.cyber-manager')

@section('content')

<div class="mb-8">
    <h1 class="text-3xl font-bold text-gray-800">Gestion des sessions</h1>
    <p class="mt-2 text-gray-600">
        Activation et suivi des sessions Internet des postes Ethernet.
    </p>
</div>

@if (session('success')) <div class="mb-6 rounded-lg bg-green-100 border border-green-200 px-4 py-3 text-green-800">
{{ session('success') }} </div>
@endif

@if ($errors->any()) <div class="mb-6 rounded-lg bg-red-100 border border-red-200 px-4 py-3 text-red-800"> <ul class="list-disc list-inside">
@foreach ($errors->all() as $error) <li>{{ $error }}</li>
@endforeach </ul> </div>
@endif

<div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-10">

<div class="lg:col-span-1 bg-white rounded-xl shadow-sm border border-gray-200 p-6">
    <h2 class="text-xl font-bold text-gray-800 mb-5">
        Activer une session
    </h2>

    @if ($postesDisponibles->isEmpty())
        <div class="rounded-lg bg-gray-100 px-4 py-3 text-gray-600">
            Aucun poste n'est actuellement disponible.
        </div>
    @else
        <form method="POST" action="{{ route('sessions.store') }}" class="space-y-5">
            @csrf

            <div>
                <label for="poste_id" class="block text-sm font-medium text-gray-700 mb-2">
                    Poste
                </label>

                <select
                    id="poste_id"
                    name="poste_id"
                    class="w-full rounded-lg border-gray-300 focus:border-indigo-500 focus:ring-indigo-500"
                    required
                >
                    <option value="">Sélectionner un poste</option>

                    @foreach ($postesDisponibles as $poste)
                        <option
                            value="{{ $poste->id }}"
                            {{ old('poste_id') == $poste->id ? 'selected' : '' }}
                        >
                            {{ $poste->nom_poste }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div>
                <label for="montant" class="block text-sm font-medium text-gray-700 mb-2">
                    Montant
                </label>

                <input
                    type="number"
                    id="montant"
                    name="montant"
                    min="300"
                    step="100"
                    value="{{ old('montant', 500) }}"
                    class="w-full rounded-lg border-gray-300 focus:border-indigo-500 focus:ring-indigo-500"
                    required
                >

                <p class="mt-1 text-xs text-gray-500">
                    Minimum : 300 Ar — Tarif : 20 Ar/minute.
                </p>
            </div>

            <div>
                <label for="description" class="block text-sm font-medium text-gray-700 mb-2">
                    Description
                </label>

                <textarea
                    id="description"
                    name="description"
                    rows="3"
                    class="w-full rounded-lg border-gray-300 focus:border-indigo-500 focus:ring-indigo-500"
                    placeholder="Ex. Session client, navigation, recherche..."
                >{{ old('description') }}</textarea>
            </div>

            <button
                type="submit"
                class="w-full rounded-lg bg-indigo-600 px-4 py-3 font-semibold text-white hover:bg-indigo-700"
            >
                Activer la session
            </button>
        </form>
    @endif
</div>

<div class="lg:col-span-2">
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
        <div class="px-6 py-5 border-b border-gray-200">
            <h2 class="text-xl font-bold text-gray-800">
                Sessions enregistrées
            </h2>
        </div>

        @if ($sessions->isEmpty())
            <div class="p-6 text-gray-500">
                Aucune session enregistrée.
            </div>
        @else
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase">
                                Poste
                            </th>
                            <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase">
                                Type
                            </th>
                            <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase">
                                État
                            </th>
                            <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase">
                                Temps
                            </th>
                            <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase">
                                Montant
                            </th>
                        </tr>
                    </thead>

                    <tbody class="divide-y divide-gray-200 bg-white">
                        @foreach ($sessions as $session)
                            <tr>
                                {{-- Poste ou identifiant Wi-Fi --}}
                                <td class="px-6 py-4 whitespace-nowrap font-medium text-gray-800">
                                    @if ($session->type_session === 'wifi')
                                        <div>Wi-Fi · {{ $session->hotspot_username ?? '—' }}</div>
                                        @if ($session->client_ip)
                                            <div class="text-xs text-gray-500 font-mono">
                                                {{ $session->client_ip }}
                                                @if ($session->client_mac)
                                                    · {{ $session->client_mac }}
                                                @endif
                                            </div>
                                        @endif
                                    @else
                                        {{ $session->poste?->nom_poste ?? '—' }}
                                    @endif
                                </td>

                                {{-- Type --}}
                                <td class="px-6 py-4 whitespace-nowrap text-sm">
                                    @if ($session->type_session === 'wifi')
                                        <span class="px-2 py-1 rounded text-xs bg-purple-100 text-purple-800">
                                            Wi-Fi
                                        </span>
                                    @else
                                        <span class="px-2 py-1 rounded text-xs bg-blue-100 text-blue-800">
                                            Ethernet
                                        </span>
                                    @endif
                                </td>

                                {{-- État --}}
                                <td class="px-6 py-4 whitespace-nowrap">
                                    @if ($session->etat === 'en_cours')
                                        <span class="px-3 py-1 text-xs font-semibold rounded-full bg-green-100 text-green-700">
                                            En cours
                                        </span>
                                    @elseif ($session->etat === 'suspendue')
                                        <span class="px-3 py-1 text-xs font-semibold rounded-full bg-yellow-100 text-yellow-700">
                                            Suspendue
                                        </span>
                                    @elseif ($session->etat === 'expiree')
                                        <span class="px-3 py-1 text-xs font-semibold rounded-full bg-red-100 text-red-700">
                                            Expirée
                                        </span>
                                    @elseif ($session->etat === 'terminee')
                                        <span class="px-3 py-1 text-xs font-semibold rounded-full bg-gray-100 text-gray-700">
                                            Terminée
                                        </span>
                                    @else
                                        <span class="px-3 py-1 text-xs font-semibold rounded-full bg-gray-100 text-gray-700">
                                            {{ ucfirst($session->etat) }}
                                        </span>
                                    @endif
                                </td>

                                {{-- Temps --}}
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600">
                                    {{ $session->duree_consommee }} min /
                                    {{ $session->duree_prevue }} min
                                </td>

                                {{-- Montant --}}
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600">
                                    {{ $session->montant_consomme }} Ar /
                                    {{ $session->montant_total }} Ar
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
</div>

</div>
@endsection
