@props(['type' => 'slate', 'label' => null, 'dot' => false])

@php
    // Classes écrites en toutes lettres : Tailwind ne détecte pas les noms construits dynamiquement
    $classes = [
        'emerald' => 'bg-emerald-50 text-emerald-700 ring-emerald-600/20',
        'amber'   => 'bg-amber-50 text-amber-700 ring-amber-600/20',
        'rose'    => 'bg-rose-50 text-rose-700 ring-rose-600/20',
        'slate'   => 'bg-slate-100 text-slate-700 ring-slate-500/20',
        'violet'  => 'bg-violet-50 text-violet-700 ring-violet-600/20',
        'sky'     => 'bg-sky-50 text-sky-700 ring-sky-600/20',
        'zinc'    => 'bg-zinc-100 text-zinc-600 ring-zinc-500/20',
        'gray'    => 'bg-gray-100 text-gray-500 ring-gray-400/20',
    ];
    $points = [
        'emerald' => 'bg-emerald-500',
        'amber'   => 'bg-amber-500',
        'rose'    => 'bg-rose-500',
        'slate'   => 'bg-slate-400',
        'violet'  => 'bg-violet-500',
        'sky'     => 'bg-sky-500',
        'zinc'    => 'bg-zinc-400',
        'gray'    => 'bg-gray-400',
    ];
@endphp

<span {{ $attributes->merge(['class' => 'inline-flex items-center gap-1.5 rounded-md px-2 py-1 text-xs font-medium ring-1 ring-inset ' . ($classes[$type] ?? $classes['slate'])]) }}>
    @if($dot)
        <span class="h-1.5 w-1.5 rounded-full {{ $points[$type] ?? $points['slate'] }}"></span>
    @endif
    {{ $label ?? $slot }}
</span>