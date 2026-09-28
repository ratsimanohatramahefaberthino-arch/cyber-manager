<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>{{ $title ?? 'Cyber Manager' }}</title>

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>

<body class="bg-gray-100 text-gray-900">

    <div class="min-h-screen">

        <!-- Barre supérieure -->
        <header class="bg-white border-b border-gray-200">
            <div class="max-w-7xl mx-auto px-6 py-4 flex items-center justify-between">

                <a href="{{ route('dashboard') }}"
                   class="text-xl font-bold text-gray-800">
                    Cyber Manager
                </a>

                <div class="flex items-center gap-4">

                    <span class="text-sm text-gray-600">
                        {{ Auth::user()->name }}
                    </span>

                    <form method="POST" action="{{ route('logout') }}">
                        @csrf

                        <button type="submit"
                                class="text-sm text-red-600 hover:text-red-800">
                            Déconnexion
                        </button>
                    </form>

                </div>

            </div>
        </header>


        <!-- Contenu principal -->
        <div class="max-w-7xl mx-auto px-6 py-8">

            <!-- Navigation -->
            <nav class="mb-8 bg-white border border-gray-200 rounded-lg p-4">

                <div class="flex flex-wrap gap-3">

                    <a href="{{ route('dashboard') }}"
                       class="px-4 py-2 rounded-md bg-gray-100 hover:bg-gray-200">
                        Dashboard
                    </a>

                    <a href="{{ route('postes.index') }}"
                       class="px-4 py-2 rounded-md bg-gray-100 hover:bg-gray-200">
                        Postes
                    </a>

<a href="{{ route('sessions.index') }}" class="px-4 py-2 rounded-md bg-gray-100 hover:bg-gray-200">
    Sessions
</a>

                    <span class="px-4 py-2 rounded-md bg-gray-50 text-gray-400">
                        Wi-Fi
                    </span>

                    <span class="px-4 py-2 rounded-md bg-gray-50 text-gray-400">
                        Vouchers
                    </span>

                    <span class="px-4 py-2 rounded-md bg-gray-50 text-gray-400">
                        Tarifs
                    </span>

                    <span class="px-4 py-2 rounded-md bg-gray-50 text-gray-400">
                        Historique
                    </span>

                    <span class="px-4 py-2 rounded-md bg-gray-50 text-gray-400">
                        Statistiques
                    </span>

                </div>

            </nav>


            <!-- Page -->
            <main>

                @yield('content')

            </main>

        </div>

    </div>

</body>
</html>