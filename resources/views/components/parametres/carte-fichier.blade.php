@props(['f'])

@php
    [$libelle, $classeEtat] = match (true) {
        $f['present']    => ['Présent',    'bg-emerald-50 text-emerald-700 ring-emerald-200'],
        $f['facultatif'] => ['Facultatif', 'bg-slate-100 text-slate-600 ring-slate-200'],
        default          => ['Manquant',   'bg-amber-50 text-amber-800 ring-amber-200'],
    };
@endphp

<div class="flex flex-col rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
    <div class="flex items-start justify-between gap-3">
        <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-violet-50 text-violet-700">
            <x-icone :nom="$f['icone']" class="h-6 w-6" />
        </span>
        <span class="rounded-full px-2.5 py-0.5 text-xs font-semibold ring-1 {{ $classeEtat }}">{{ $libelle }}</span>
    </div>

    <h3 class="mt-3 text-sm font-bold leading-snug text-slate-900">{{ $f['titre'] }}</h3>
    @if (!empty($f['detail']))
        <p class="mt-0.5 text-xs text-slate-500">{{ $f['detail'] }}</p>
    @endif
    <p class="mt-2 text-xs leading-relaxed text-slate-600">{{ $f['description'] }}</p>

    @unless ($f['present'])
        <p class="mt-2 text-xs leading-relaxed {{ $f['facultatif'] ? 'text-slate-500' : 'font-medium text-amber-800' }}">
            {{ $f['manquant'] }}
            @if (!empty($f['lien']))
                <a href="{{ $f['lien'] }}" class="underline hover:no-underline">{{ $f['lien_texte'] }}</a>
            @endif
        </p>
    @endunless

    @if (trim((string) $slot) !== '')
        <div class="mt-auto pt-4">{{ $slot }}</div>
    @endif
</div>