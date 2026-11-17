@props(['f'])

@php
    [$libelle, $classeEtat] = match (true) {
        $f['present']    => ['Présent',    'bg-emerald-50 text-emerald-700 ring-emerald-200'],
        $f['facultatif'] => ['Facultatif', 'bg-slate-100 text-slate-600 ring-slate-200'],
        default          => ['Manquant',   'bg-amber-50 text-amber-800 ring-amber-200'],
    };
@endphp

<div class="flex flex-col rounded-2xl border border-slate-200 bg-white p-5 shadow-sm transition hover:shadow-md">
    <div class="flex items-start justify-between gap-3">
        <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-violet-50 text-violet-700">
            <x-icone :nom="$f['icone']" classe="h-6 w-6" />
        </span>
        <span class="rounded-full px-2.5 py-0.5 text-xs font-semibold ring-1 {{ $classeEtat }}">{{ $libelle }}</span>
    </div>

    <h3 class="mt-3 text-sm font-bold leading-snug text-slate-900">{{ $f['titre'] }}</h3>
    @if (!empty($f['detail']))
        <p class="mt-0.5 text-xs text-slate-500">{{ $f['detail'] }}</p>
    @endif
    <p class="mt-2 text-xs leading-relaxed text-slate-600">{{ $f['description'] }}</p>

    @if (!empty($f['usage']))
        <p class="mt-2 rounded-lg bg-slate-50 px-2.5 py-2 text-xs leading-relaxed text-slate-600">
            <strong class="font-semibold text-slate-800">Le bouton :</strong> {{ $f['usage'] }}
        </p>
    @endif

    @unless ($f['present'])
        <div class="mt-2 space-y-2 text-xs leading-relaxed {{ $f['facultatif'] ? 'text-slate-500' : 'font-medium text-amber-800' }}">
            <p>
                {{ $f['manquant'] }}
                @if (!empty($f['lien']))
                    <a href="{{ $f['lien'] }}" class="underline hover:no-underline">{{ $f['lien_texte'] }}</a>
                @endif
            </p>
            @if (!empty($f['chemin']))
                <div class="flex items-start gap-2 rounded-lg bg-slate-900 p-2">
                    <code class="min-w-0 flex-1 break-all font-mono text-[11px] font-normal text-slate-100">{{ $f['chemin'] }}</code>
                    <button type="button" @click="cmCopy(@js($f['chemin']))" aria-label="Copier le chemin"
                            class="shrink-0 rounded p-1 text-slate-300 hover:bg-white/10 hover:text-white">
                        <x-icone nom="copy" classe="h-4 w-4" />
                    </button>
                </div>
            @endif
        </div>
    @endunless

    @if (trim((string) $slot) !== '')
        <div class="mt-auto pt-4">{{ $slot }}</div>
    @endif
</div>