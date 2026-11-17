@props(['couleur' => 'gris'])
@php
    $c = match ($couleur) {
        'vert'   => 'bg-emerald-50 text-emerald-700 ring-emerald-200',
        'orange' => 'bg-amber-50 text-amber-800 ring-amber-200',
        'rouge'  => 'bg-rose-50 text-rose-700 ring-rose-200',
        'bleu'   => 'bg-sky-50 text-sky-700 ring-sky-200',
        'violet' => 'bg-violet-50 text-violet-700 ring-violet-200',
        default  => 'bg-slate-100 text-slate-600 ring-slate-200',
    };
@endphp
<span {{ $attributes->merge(['class' => "inline-flex items-center gap-1 whitespace-nowrap rounded-full px-2.5 py-0.5 text-xs font-semibold ring-1 $c"]) }}>{{ $slot }}</span>