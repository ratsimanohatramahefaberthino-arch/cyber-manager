@props(['label', 'value', 'hint' => null, 'couleur' => 'slate', 'icone' => 'ticket'])

@php
    $pastilles = [
        'emerald' => 'bg-emerald-50 text-emerald-600',
        'amber'   => 'bg-amber-50 text-amber-600',
        'rose'    => 'bg-rose-50 text-rose-600',
        'slate'   => 'bg-slate-100 text-slate-600',
        'violet'  => 'bg-violet-50 text-violet-600',
    ];
    $indices = [
        'emerald' => 'text-emerald-600',
        'amber'   => 'text-amber-600',
        'rose'    => 'text-rose-600',
        'slate'   => 'text-slate-500',
        'violet'  => 'text-violet-600',
    ];
@endphp

<div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm transition hover:shadow-md">
    <div class="flex items-center justify-between">
        <p class="text-sm font-medium text-slate-500">{{ $label }}</p>
        <span class="flex h-9 w-9 items-center justify-center rounded-lg {{ $pastilles[$couleur] ?? $pastilles['slate'] }}">
            <x-icone :nom="$icone" class="h-5 w-5" />
        </span>
    </div>
    <p class="mt-3 text-3xl font-semibold tracking-tight text-slate-900">{{ $value }}</p>
    @if($hint)
        <p class="mt-1 text-xs font-medium {{ $indices[$couleur] ?? $indices['slate'] }}">{{ $hint }}</p>
    @endif
</div>