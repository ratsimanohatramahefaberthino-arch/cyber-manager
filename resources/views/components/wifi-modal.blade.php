@props([
    'show',                     // expression Alpine : ex. "prolongerModal.ouvert"
    'close'     => null,        // expression Alpine de fermeture (défaut : "<show> = false")
    'z'         => 9300,
    'maxw'      => 'max-w-lg',
    'titre'     => null,        // titre statique
    'titreJs'   => null,        // OU expression Alpine pour un titre dynamique
    'sousJs'    => null,        // sous-titre : expression Alpine
    'fermable'  => true,        // false = ni clic sur le fond ni Échap (alerte à traiter)
])

@php $fermer = $close ?? ($show . ' = false'); @endphp

{{--
    Structure : [en-tête fixe] [corps scrollable] [pied fixe]
    → le bouton d'action reste toujours visible, quelle que soit la hauteur du contenu.
    Slots : header (remplace l'en-tête standard), default (corps), footer (actions).
--}}
<template x-teleport="body">
    <div x-show="{{ $show }}" x-cloak role="dialog" aria-modal="true"
         class="fixed inset-0 flex items-center justify-center p-4 sm:p-8"
         style="z-index: {{ (int) $z }}"
         @if($fermable) @keydown.escape.window="if ({{ $show }}) { {{ $fermer }} }" @endif>

        <div class="absolute inset-0 bg-slate-900/60 backdrop-blur-[2px]"
             x-show="{{ $show }}" x-transition.opacity.duration.150ms
             @if($fermable) @click="{{ $fermer }}" @endif></div>

        <div x-show="{{ $show }}"
             x-transition:enter="transition ease-out duration-150"
             x-transition:enter-start="opacity-0 translate-y-2 scale-95"
             x-transition:enter-end="opacity-100 translate-y-0 scale-100"
             class="relative flex max-h-[85vh] w-full {{ $maxw }} flex-col overflow-hidden rounded-2xl bg-white shadow-2xl ring-1 ring-slate-900/10">

            @if(isset($header))
                {{ $header }}
            @elseif($titre || $titreJs)
                <div class="flex shrink-0 items-start justify-between gap-4 border-b border-slate-100 px-6 py-4">
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

            <div class="min-h-0 flex-1 overflow-y-auto px-6 py-5">
                {{ $slot }}
            </div>

            @if(isset($footer))
                <div class="shrink-0 border-t border-slate-100 bg-slate-50 px-6 py-4">
                    {{ $footer }}
                </div>
            @endif
        </div>
    </div>
</template>