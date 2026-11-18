@props(['compact' => false])

@php
    $user = auth()->user();
@endphp

<div x-data="{ ouvert: false, chargement: false }"
     x-on:keydown.escape.window="ouvert = false"
     x-on:click.outside="ouvert = false"
     class="relative">

    {{-- Carte --}}
    <button type="button" x-on:click="ouvert = !ouvert"
            class="flex w-full items-center gap-3 rounded-xl border border-white/10 bg-white/5 p-2 text-left transition hover:bg-white/10 focus:outline-none focus-visible:ring-2 focus-visible:ring-violet-400"
            :aria-expanded="ouvert">
        <div class="relative shrink-0">
            <x-avatar :user="$user" taille="md" />
            <span class="absolute -bottom-0.5 -right-0.5 h-3 w-3 rounded-full bg-emerald-500 ring-2 ring-indigo-900" title="En ligne"></span>
        </div>
        <div class="min-w-0 flex-1">
            <p class="truncate text-sm font-semibold text-white">{{ $user->name }}</p>
            <p class="truncate text-xs text-indigo-300">{{ $user->libelleRole() }}</p>
        </div>
        <span class="text-indigo-300 transition-transform" :class="ouvert ? 'rotate-180' : ''">
            <x-icone nom="chevron" classe="h-4 w-4" />
        </span>
    </button>

    {{-- Menu déroulant --}}
    <div x-show="ouvert" x-cloak x-transition.opacity.duration.100ms
         class="absolute bottom-full left-0 right-0 z-40 mb-2 overflow-hidden rounded-xl border border-white/10 bg-indigo-950 shadow-xl ring-1 ring-black/20">
        
        <a href="{{ route('parametres.profil') }}"
           class="flex items-center gap-2.5 px-4 py-2.5 text-sm font-medium text-indigo-100 transition hover:bg-white/10">
            <x-icone nom="pencil" classe="h-4 w-4 text-indigo-300" />
            Mon profil
        </a>

        <form method="POST" action="{{ route('changer-compte') }}" x-on:submit="chargement = true">
            @csrf
            <button type="submit"
                    class="flex w-full items-center gap-2.5 px-4 py-2.5 text-left text-sm font-medium text-indigo-100 transition hover:bg-white/10 disabled:opacity-50"
                    x-bind:disabled="chargement">
                <svg x-show="chargement" x-cloak class="animate-spin h-4 w-4 text-indigo-300" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                <x-icone nom="refresh" classe="h-4 w-4 text-indigo-300" x-show="!chargement" />
                Changer de compte
            </button>
        </form>

        <form method="POST" action="{{ route('logout') }}">
            @csrf
            <button type="submit"
                    class="flex w-full items-center gap-2.5 px-4 py-2.5 text-left text-sm font-medium text-rose-300 transition hover:bg-rose-500/10">
                <x-icone nom="logout" classe="h-4 w-4" />
                Se déconnecter
            </button>
        </form>
    </div>
</div>