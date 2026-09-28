@extends('layouts.cyber-manager')

@section('content')

    <div class="mb-8">
        <h1 class="text-3xl font-bold text-gray-800">
            Gestion des postes
        </h1>

        <p class="mt-2 text-gray-600">
            Gestion et surveillance des postes Ethernet du cybercafé.
        </p>
    </div>

    {{-- Messages de résultat --}}
    @if (session('success'))
        <div class="mb-6 rounded-lg bg-green-100 border border-green-200 px-4 py-3 text-green-800">
            {{ session('success') }}
        </div>
    @endif

    @if (session('error'))
        <div class="mb-6 rounded-lg bg-red-100 border border-red-200 px-4 py-3 text-red-800">
            {{ session('error') }}
        </div>
    @endif


    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6">

        @foreach ($postes as $poste)

            <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">

                <div class="flex items-center justify-between mb-5">

                    <h2 class="text-xl font-bold text-gray-800">
                        {{ $poste->nom_poste }}
                    </h2>

                    @if ($poste->etat === 'disponible')
                        <span class="px-3 py-1 text-xs font-semibold rounded-full bg-green-100 text-green-700">
                            Disponible
                        </span>
                    @else
                        <span class="px-3 py-1 text-xs font-semibold rounded-full bg-gray-100 text-gray-700">
                            {{ ucfirst($poste->etat) }}
                        </span>
                    @endif

                </div>


                <div class="space-y-3 text-sm">

                    <div>
                        <span class="text-gray-500">
                            Adresse IP
                        </span>

                        <p class="font-medium text-gray-800">
                            {{ $poste->adresse_ip ?? 'Non renseignée' }}
                        </p>
                    </div>


                    <div>
                        <span class="text-gray-500">
                            Adresse MAC
                        </span>

                        <p class="font-medium text-gray-800">
                            {{ $poste->adresse_mac ?? 'Non renseignée' }}
                        </p>
                    </div>


                    <div>
                        <span class="text-gray-500">
                            Connexion
                        </span>

                        <p class="font-medium text-gray-800">
                            {{ ucfirst($poste->type_connexion) }}
                        </p>
                    </div>


                    <div>
                        <span class="text-gray-500">
                            Actif
                        </span>

                        <p class="font-medium text-gray-800">
                            {{ $poste->actif ? 'Oui' : 'Non' }}
                        </p>
                    </div>


                    <div>
                        <span class="text-gray-500">
                            Agent Windows
                        </span>

                        <p class="font-medium text-gray-800">
                            @if ($poste->agent_url)
                                Configuré
                            @else
                                Non configuré
                            @endif
                        </p>
                    </div>


                    <div>
                        <span class="text-gray-500">
                            Dernière communication
                        </span>

                        <p class="font-medium text-gray-800">
                            @if ($poste->derniere_communication)
                                {{ $poste->derniere_communication->format('d/m/Y H:i:s') }}
                            @else
                                Jamais
                            @endif
                        </p>
                    </div>

                </div>


                <div class="mt-6 space-y-2">

                    @if ($poste->agent_url)

                        <form
                            method="POST"
                            action="{{ route('postes.tester-agent', $poste) }}"
                        >
                            @csrf

                            <button
                                type="submit"
                                class="w-full px-4 py-2 rounded-lg bg-blue-600 text-white font-medium hover:bg-blue-700 transition">
                                Tester l'agent Windows
                            </button>
                            @if($poste->agent_url)
    <div class="mt-3 flex gap-2">
        <form method="POST" action="{{ route('postes.verrouiller', $poste) }}">
            @csrf

            <button
                type="submit"
                class="px-3 py-2 bg-red-600 text-white rounded hover:bg-red-700"
            >
                Verrouiller
            </button>
        </form>

        <form method="POST" action="{{ route('postes.deverrouiller', $poste) }}">
            @csrf

            <button
                type="submit"
                class="px-3 py-2 bg-green-600 text-white rounded hover:bg-green-700"
            >
                Déverrouiller
            </button>
        </form>
    </div>
@endif
                        </form>

                    @else

                        <button
                            type="button"
                            class="w-full px-4 py-2 rounded-lg bg-gray-200 text-gray-500 cursor-not-allowed">
                            Agent Windows non configuré
                        </button>

                    @endif

                </div>

            </div>

        @endforeach

    </div>

@endsection