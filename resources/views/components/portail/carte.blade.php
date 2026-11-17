@props(['titre', 'aide' => null, 'icone' => 'tag'])

<section {{ $attributes->merge(['class' => 'rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6']) }}>
    <div class="mb-5 flex items-start gap-3 border-b border-slate-100 pb-4">
        <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-violet-50 text-violet-700">
            <x-icone :nom="$icone" class="h-5 w-5" />
        </span>
        <div class="min-w-0 flex-1">
            <h2 class="text-base font-bold text-slate-900">{{ $titre }}</h2>
            @if ($aide)
                <p class="text-sm text-slate-500">{{ $aide }}</p>
            @endif
        </div>
        @isset($action)
            <div class="shrink-0">{{ $action }}</div>
        @endisset
    </div>

    {{ $slot }}
</section>