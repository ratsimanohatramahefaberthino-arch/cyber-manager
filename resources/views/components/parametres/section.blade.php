@props(['titre', 'aide' => null, 'icone' => 'tag'])
<section {{ $attributes->merge(['class' => 'rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6']) }}>
    <div class="mb-4 flex items-start gap-3">
        <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-violet-50 text-violet-700">
            <x-icone :nom="$icone" classe="h-5 w-5" />
        </span>
        <div class="min-w-0">
            <h2 class="text-base font-bold text-slate-900">{{ $titre }}</h2>
            @if ($aide)
                <p class="mt-0.5 text-sm text-slate-500">{{ $aide }}</p>
            @endif
        </div>
    </div>
    {{ $slot }}
</section>