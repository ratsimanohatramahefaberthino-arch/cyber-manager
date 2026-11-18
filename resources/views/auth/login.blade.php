@php
    $cyber = \App\Models\CyberSetting::tous();
    $couleur = $cyber['couleur_principale'] ?? '#7c3aed';
    $logoUrl = \App\Models\CyberSetting::logoExiste() ? route('agent.logo') : null;
    
    // Récupérer les comptes récents depuis le cookie
    $comptesRecents = json_decode(request()->cookie('comptes_recents', '[]'), true) ?: [];
@endphp

<x-guest-layout>
    <div class="w-full max-w-md">
        {{-- En-tête avec logo --}}
        <div class="text-center mb-8">
            @if($logoUrl)
                <img src="{{ $logoUrl }}" alt="{{ $cyber['nom_cyber'] }}" class="mx-auto mb-4 h-20 w-auto object-contain drop-shadow-md">
            @else
                <div class="mx-auto mb-4 flex h-16 w-16 items-center justify-center rounded-2xl text-white shadow-lg" style="background-color: {{ $couleur }};">
                    <x-icone nom="wifi" classe="h-8 w-8" />
                </div>
            @endif
            <h1 class="text-2xl font-bold text-slate-900">{{ $cyber['nom_cyber'] ?? 'Cyber Manager' }}</h1>
            <p class="text-sm text-slate-500 mt-1">Connectez-vous pour gérer votre cybercafé.</p>
        </div>

        {{-- Carte de connexion --}}
        <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-xl sm:p-8">
            @if (session('info'))
                <div class="mb-4 rounded-xl border border-sky-200 bg-sky-50 px-4 py-3 text-sm text-sky-800">
                    {{ session('info') }}
                </div>
            @endif

            {{-- Session Status --}}
            @if (session('status'))
                <div class="mb-4 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">
                    {{ session('status') }}
                </div>
            @endif

            {{-- Comptes récents (Étape 8) --}}
            @if(count($comptesRecents) > 0)
                <div class="mb-6">
                    <p class="text-xs font-semibold uppercase tracking-wider text-slate-400 mb-2">Comptes récents</p>
                    <div class="space-y-2">
                        @foreach($comptesRecents as $compte)
                            <button type="button" 
                                    onclick="remplirCompte('{{ $compte['email'] }}')"
                                    class="flex w-full items-center gap-3 rounded-xl border border-slate-200 bg-slate-50 p-2 text-left hover:bg-slate-100 transition cursor-pointer">
                                @if(!empty($compte['avatar_url']))
                                    <img src="{{ $compte['avatar_url'] }}" class="h-8 w-8 rounded-full object-cover">
                                @else
                                    <span class="inline-flex h-8 w-8 items-center justify-center rounded-full text-xs font-bold text-white" 
                                          style="background-color: {{ $compte['couleur'] ?? '#7c3aed' }}">
                                        {{ $compte['initiales'] ?? '?' }}
                                    </span>
                                @endif
                                <div class="min-w-0">
                                    <p class="text-sm font-semibold text-slate-800 truncate">{{ $compte['name'] }}</p>
                                    <p class="text-xs text-slate-500 truncate">{{ $compte['email'] }}</p>
                                </div>
                            </button>
                        @endforeach
                    </div>
                    <div class="relative mt-4 mb-2">
                        <div class="absolute inset-0 flex items-center"><div class="w-full border-t border-slate-200"></div></div>
                        <div class="relative flex justify-center text-xs"><span class="bg-white px-2 text-slate-400">ou</span></div>
                    </div>
                </div>
            @endif

            <form method="POST" action="{{ route('login') }}" class="space-y-4" x-data="{ voir: false, chargement: false }" x-on:submit="chargement = true">
                @csrf

                {{-- Email --}}
                <div>
                    <label for="email" class="mb-1.5 block text-sm font-semibold text-slate-800">Adresse e-mail</label>
                    <input id="email" type="email" name="email" value="{{ old('email') }}" required autofocus autocomplete="username"
                           class="h-11 w-full rounded-xl border border-slate-300 bg-white px-3.5 text-sm text-slate-900 shadow-sm focus:border-violet-500 focus:ring-2 focus:ring-violet-500/20">
                    @if ($errors->has('email'))
                        <p class="mt-1 text-xs text-rose-600">{{ $errors->first('email') }}</p>
                    @endif
                </div>

                {{-- Mot de passe --}}
                <div>
                    <div class="flex items-center justify-between mb-1.5">
                        <label for="password" class="block text-sm font-semibold text-slate-800">Mot de passe</label>
                        @if (Route::has('password.request'))
                            <a href="{{ route('password.request') }}" class="text-xs font-semibold text-violet-600 hover:text-violet-800">
                                Mot de passe oublié ?
                            </a>
                        @endif
                    </div>
                    <div class="relative">
                        <input id="password" :type="voir ? 'text' : 'password'" name="password" required autocomplete="current-password"
                               class="h-11 w-full rounded-xl border border-slate-300 bg-white pl-3.5 pr-10 text-sm text-slate-900 shadow-sm focus:border-violet-500 focus:ring-2 focus:ring-violet-500/20">
                        <button type="button" x-on:click="voir = !voir" class="absolute right-2 top-1/2 -translate-y-1/2 rounded p-1 text-slate-400 hover:bg-slate-100 hover:text-slate-600">
                            <x-icone nom="eye" classe="h-4 w-4" x-show="!voir" />
                            <x-icone nom="eye-off" classe="h-4 w-4" x-show="voir" x-cloak />
                        </button>
                    </div>
                    @if ($errors->has('password'))
                        <p class="mt-1 text-xs text-rose-600">{{ $errors->first('password') }}</p>
                    @endif
                </div>

                {{-- Se souvenir de moi --}}
                <div class="flex items-center justify-between pt-1">
                    <label class="flex items-center gap-2 text-sm text-slate-600 cursor-pointer">
                        <input type="checkbox" name="remember" class="h-4 w-4 rounded border-slate-300 text-violet-600 focus:ring-violet-500">
                        Se souvenir de moi
                    </label>
                </div>

                {{-- Bouton --}}
                <div class="pt-2">
                    <button type="submit" 
                            class="w-full h-11 rounded-xl text-sm font-semibold text-white shadow-sm transition hover:-translate-y-0.5 hover:shadow-md focus:outline-none focus-visible:ring-2 focus-visible:ring-offset-2 disabled:opacity-70 disabled:cursor-not-allowed flex items-center justify-center gap-2"
                            style="background-color: {{ $couleur }};"
                            x-bind:disabled="chargement">
                        <svg x-show="chargement" x-cloak class="animate-spin -ml-1 mr-2 h-4 w-4 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                        <span x-text="chargement ? 'Connexion...' : 'Se connecter'"></span>
                    </button>
                </div>
            </form>
        </div>

        <p class="mt-6 text-center text-xs text-slate-400">
            &copy; {{ date('Y') }} {{ $cyber['nom_cyber'] ?? 'Cyber Manager' }}. Tous droits réservés.
        </p>
    </div>

    <script>
        function remplirCompte(email) {
            const champEmail = document.getElementById('email');
            const champPassword = document.getElementById('password');
            
            if (champEmail && champPassword) {
                champEmail.value = email;
                champPassword.focus();
            }
        }
    </script>
</x-guest-layout>