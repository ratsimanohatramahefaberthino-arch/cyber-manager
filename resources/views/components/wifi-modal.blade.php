@props([
    'show',                     
    'close'     => null,        
    'z'         => 9300,
    'maxw'      => 'max-w-lg',
    'titre'     => null,        
    'titreJs'   => null,        
    'sousJs'    => null,        
    'fermable'  => true,        
])

@php $fermer = $close ?? ($show . ' = false'); @endphp

<template x-teleport="body">
    <div x-show="{{ $show }}" x-cloak role="dialog" aria-modal="true"
         class="fixed inset-0 flex items-center justify-center p-2 sm:p-4"
         style="z-index: {{ (int) $z }}"
         @if($fermable) @keydown.escape.window="if ({{ $show }}) { {{ $fermer }} }" @endif>

        <div class="absolute inset-0 bg-slate-900/60 backdrop-blur-sm"
             x-show="{{ $show }}" x-transition.opacity.duration.150ms
             @if($fermable) @click="{{ $fermer }}" @endif></div>

        {{-- Ajout d'un contour visible (ring + border) et d'une limite de hauteur stricte --}}
        <div x-show="{{ $show }}"
             x-transition:enter="transition ease-out duration-150"
             x-transition:enter-start="opacity-0 translate-y-4 scale-95"
             x-transition:enter-end="opacity-100 translate-y-0 scale-100"
             class="relative flex max-h-[90vh] w-full {{ $maxw }} flex-col overflow-hidden rounded-2xl bg-white shadow-2xl ring-1 ring-slate-900/20 border border-slate-200">

            @if(isset($header))
                <div class="shrink-0">{{ $header }}</div>
            @elseif($titre || $titreJs)
                <div class="flex shrink-0 items-start justify-between gap-4 border-b border-slate-100 px-4 py-3 sm:px-6 sm:py-4">
                    <div class="min-w-0">
                        <h2 class="text-lg font-semibold text-slate-900"
                            @if($titreJs) x-text="{{ $titreJs }}" @endif>{{ $titre }}</h2>
                        @if($sousJs)
                            <p class="mt-0.5 truncate text-sm text-slate-500" x-text="{{ $sousJs }}"></p>
                        @endif
                    </div>
                    @if($fermable)
                        <button type="button" @click="{{ $fermer }}" aria-label="Fermer"
                                class="-mr-2 -mt-1 rounded-lg p-1.5 text-slate-400 hover:bg-slate-100 hover:text-slate-600">
                            <x-icone nom="x" class="h-5 w-5" />
                        </button>
                    @endif
                </div>
            @endif

            {{-- Le coeur du correctif : min-h-0 + overflow-y-auto permet au contenu de scroller indépendamment --}}
            <div class="min-h-0 flex-1 overflow-y-auto px-4 py-4 sm:px-6 sm:py-5">
                {{ $slot }}
            </div>

            @if(isset($footer))
                <div class="shrink-0 border-t border-slate-200 bg-slate-50 px-4 py-3 sm:px-6 sm:py-4">
                    {{ $footer }}
                </div>
            @endif
        </div>
    </div>
</template>