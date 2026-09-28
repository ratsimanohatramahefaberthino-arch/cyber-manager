<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <div>
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                    Gestion des tarifs
                </h2>
                <p class="text-sm text-gray-500 mt-1">
                    Configurez les tarifs utilisés pour calculer automatiquement
                    les durées et les montants des sessions.
                </p>
            </div>
        </div>
    </x-slot>

    <div class="py-6">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">

            {{-- Messages --}}
            @if(session('success'))
                <div class="bg-green-100 border border-green-300 text-green-800 px-4 py-3 rounded-lg">
                    {{ session('success') }}
                </div>
            @endif

            @if(session('error'))
                <div class="bg-red-100 border border-red-300 text-red-800 px-4 py-3 rounded-lg">
                    {{ session('error') }}
                </div>
            @endif

            {{-- Formulaire --}}
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6">
                    <h3 class="text-lg font-semibold text-gray-800 mb-4">
                        Ajouter un tarif
                    </h3>

                    <form method="POST" action="{{ route('tarifs.store') }}">
                        @csrf

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">

                            <div>
                                <label class="block text-sm font-medium text-gray-700">
                                    Nom du tarif
                                </label>

                                <input
                                    type="text"
                                    name="nom"
                                    value="{{ old('nom') }}"
                                    placeholder="Ex : Tarif standard"
                                    required
                                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm"
                                >

                                @error('nom')
                                    <p class="text-red-600 text-sm mt-1">{{ $message }}</p>
                                @enderror
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-700">
                                    Montant par minute (Ar)
                                </label>

                                <input
                                    type="number"
                                    name="montant_par_minute"
                                    value="{{ old('montant_par_minute', 20) }}"
                                    min="1"
                                    required
                                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm"
                                >

                                @error('montant_par_minute')
                                    <p class="text-red-600 text-sm mt-1">{{ $message }}</p>
                                @enderror
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-700">
                                    Montant minimum (Ar)
                                </label>

                                <input
                                    type="number"
                                    name="montant_minimum"
                                    value="{{ old('montant_minimum', 300) }}"
                                    min="1"
                                    required
                                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm"
                                >

                                @error('montant_minimum')
                                    <p class="text-red-600 text-sm mt-1">{{ $message }}</p>
                                @enderror
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-700">
                                    Description
                                </label>

                                <input
                                    type="text"
                                    name="description"
                                    value="{{ old('description') }}"
                                    placeholder="Ex : 20 Ar par minute"
                                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm"
                                >
                            </div>

                        </div>

                        <div class="mt-5">
                            <button
                                type="submit"
                                class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700"
                            >
                                Ajouter le tarif
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            {{-- Tarifs --}}
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6">

                    <div class="flex items-center justify-between mb-4">
                        <div>
                            <h3 class="text-lg font-semibold text-gray-800">
                                Tarifs configurés
                            </h3>

                            <p class="text-sm text-gray-500">
                                Un seul tarif peut être actif à la fois.
                            </p>
                        </div>
                    </div>

                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">

                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">
                                        Nom
                                    </th>

                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">
                                        Prix/min
                                    </th>

                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">
                                        Minimum
                                    </th>

                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">
                                        Type
                                    </th>

                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">
                                        État
                                    </th>

                                    <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">
                                        Actions
                                    </th>
                                </tr>
                            </thead>

                            <tbody class="bg-white divide-y divide-gray-200">

                                @forelse($tarifs as $tarif)

                                    <tr>

                                        <td class="px-4 py-4">
                                            <div class="font-medium text-gray-900">
                                                {{ $tarif->nom }}
                                            </div>

                                            @if($tarif->description)
                                                <div class="text-sm text-gray-500">
                                                    {{ $tarif->description }}
                                                </div>
                                            @endif
                                        </td>

                                        <td class="px-4 py-4 text-gray-700">
                                            {{ number_format($tarif->montant_par_minute, 0, ',', ' ') }}
                                            Ar/min
                                        </td>

                                        <td class="px-4 py-4 text-gray-700">
                                            {{ number_format($tarif->montant_minimum, 0, ',', ' ') }}
                                            Ar
                                        </td>

                                        <td class="px-4 py-4">
                                            @if($tarif->personnalise)
                                                <span class="px-2 py-1 text-xs rounded-full bg-purple-100 text-purple-800">
                                                    Personnalisé
                                                </span>
                                            @else
                                                <span class="px-2 py-1 text-xs rounded-full bg-gray-100 text-gray-700">
                                                    Standard
                                                </span>
                                            @endif
                                        </td>

                                        <td class="px-4 py-4">
                                            @if($tarif->actif)
                                                <span class="px-2 py-1 text-xs rounded-full bg-green-100 text-green-800">
                                                    Actif
                                                </span>
                                            @else
                                                <span class="px-2 py-1 text-xs rounded-full bg-gray-100 text-gray-600">
                                                    Inactif
                                                </span>
                                            @endif
                                        </td>

                                        <td class="px-4 py-4 text-right">

                                            <div class="flex justify-end gap-2">

                                                @if(!$tarif->actif)
                                                    <form
                                                        method="POST"
                                                        action="{{ route('tarifs.activer', $tarif) }}"
                                                    >
                                                        @csrf

                                                        <button
                                                            type="submit"
                                                            class="px-3 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 text-sm"
                                                        >
                                                            Activer
                                                        </button>
                                                    </form>
                                                @endif

                                                @if(!$tarif->sessions()->exists())
                                                    <form
                                                        method="POST"
                                                        action="{{ route('tarifs.destroy', $tarif) }}"
                                                        onsubmit="return confirm('Supprimer ce tarif ?')"
                                                    >
                                                        @csrf
                                                        @method('DELETE')

                                                        <button
                                                            type="submit"
                                                            class="px-3 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700 text-sm"
                                                        >
                                                            Supprimer
                                                        </button>
                                                    </form>
                                                @endif

                                            </div>

                                        </td>

                                    </tr>

                                @empty

                                    <tr>
                                        <td
                                            colspan="6"
                                            class="px-4 py-8 text-center text-gray-500"
                                        >
                                            Aucun tarif configuré.
                                        </td>
                                    </tr>

                                @endforelse

                            </tbody>

                        </table>
                    </div>

                </div>
            </div>

        </div>
    </div>
</x-app-layout>