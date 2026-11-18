@props(['f'])

<div class="flex h-full flex-col overflow-hidden rounded-2xl border {{ $f['present'] ? 'border-slate-200 bg-white' : 'border-amber-200 bg-amber-50/30' }} shadow-sm">
    <div class="flex items-start gap-3 border-b border-slate-100 p-4 sm:p-5">
        <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl {{ $f['present'] ? 'bg-violet-50 text-violet-600' : 'bg-amber-100 text-amber-700' }}">
            <x-icone :nom="$f['icone']" classe="h-5 w-5" />
        </span>
        <div class="min-w-0">
            <h3 class="font-bold text-slate-900">{{ $f['titre'] }}</h3>
            @if ($f['present'])
                <p class="text-xs text-slate-500">{{ $f['detail'] ?? 'Présent sur le serveur' }}</p>
            @else
                <p class="text-xs font-semibold text-amber-700">Manquant</p>
            @endif
        </div>
    </div>

    <div class="flex flex-1 flex-col justify-between gap-4 p-4 sm:p-5">
        <div class="space-y-3">
            <p class="text-sm text-slate-600">{{ $f['description'] }}</p>
            
            @if ($f['present'])
                <p class="text-xs text-slate-500">{{ $f['usage'] }}</p>
            @else
                <p class="rounded-lg bg-amber-100/50 p-2 text-xs text-amber-800">{{ $f['manquant'] }}</p>
                @if (!empty($f['chemin']))
                    <div class="relative" x-data="{ copie: false }">
                        <code class="block break-all rounded bg-white p-2 pr-10 font-mono text-[11px] text-slate-800 ring-1 ring-slate-200">{{ $f['chemin'] }}</code>
                        <button type="button" 
                                x-on:click="cmCopy(@js($f['chemin'])); copie = true; setTimeout(() => copie = false, 2000)"
                                class="absolute right-1 top-1 rounded p-1.5 text-slate-400 hover:bg-slate-100 hover:text-slate-700 transition"
                                title="Copier le chemin">
                            <x-icone nom="copy" classe="h-3.5 w-3.5" x-show="!copie" />
                            <x-icone nom="check" classe="h-3.5 w-3.5 text-emerald-600" x-show="copie" x-cloak />
                        </button>
                    </div>
                @endif
                @if (!empty($f['lien']))
                    <a href="{{ $f['lien'] }}" class="inline-flex items-center gap-1 text-xs font-semibold text-violet-700 underline hover:text-violet-900">
                        {{ $f['lien_texte'] }}
                    </a>
                @endif
            @endif
        </div>

        <div>
            {{ $slot }}
        </div>
    </div>
</div>