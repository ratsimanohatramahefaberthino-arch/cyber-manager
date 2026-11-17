{{-- Liste des sections de Paramètres. Utilisée par le menu de gauche (≥ 1024 px) et par le menu déroulant (mobile). --}}
@props(['entrees', 'actif'])

<ul class="space-y-1">
    @foreach($entrees as [$cle, $libelle, $icone, $aide, $route])
        @php
            $estActif = $actif === $cle;
            $base = 'flex w-full items-center gap-2.5 rounded-lg border-l-4 px-3 py-2.5 text-sm font-semibold leading-tight transition';
        @endphp
        <li>
            @if($route)
                <a href="{{ route($route) }}" title="{{ $aide }}"
                   @if($estActif) aria-current="page" @endif
                   @class([
                       $base,
                       'focus:outline-none focus-visible:ring-2 focus-visible:ring-violet-500',
                       'border-violet-600 bg-violet-50 text-violet-700' => $estActif,
                       'border-transparent text-slate-600 hover:bg-slate-100 hover:text-slate-900' => !$estActif,
                   ])>
            @else
                <span aria-disabled="true" title="{{ $aide }}"
                      @class([$base, 'cursor-not-allowed border-transparent text-slate-400'])>
            @endif

                <x-icone :nom="$icone" classe="h-5 w-5 shrink-0" />
                <span class="min-w-0 flex-1">{{ $libelle }}</span>

                @if(!$route)
                    <span class="shrink-0 rounded-full bg-slate-200/70 px-2 py-0.5 text-[10px] font-semibold text-slate-500">Bientôt</span>
                @elseif($estActif)
                    <x-icone nom="chevron-left" classe="h-4 w-4 shrink-0 rotate-180 text-violet-500" />
                @endif

            @if($route) </a> @else </span> @endif
        </li>
    @endforeach
</ul>