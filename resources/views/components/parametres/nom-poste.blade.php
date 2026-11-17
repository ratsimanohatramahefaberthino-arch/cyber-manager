@props(['poste'])

<div x-data="{ edition: false }" class="min-w-0">
    <div x-show="!edition" class="flex items-center gap-2">
        <span class="truncate text-sm font-semibold text-slate-900">{{ $poste->nom_poste }}</span>
        <button type="button" @click="edition = true; $nextTick(() => $refs.champ.focus())"
                aria-label="Renommer {{ $poste->nom_poste }}" title="Renommer"
                class="shrink-0 rounded-lg p-1.5 text-slate-400 transition hover:bg-slate-100 hover:text-violet-700 focus:outline-none focus-visible:ring-2 focus-visible:ring-violet-500">
            <x-icone nom="pencil" class="h-4 w-4" />
        </button>
    </div>

    <form x-show="edition" x-cloak method="POST" action="{{ route('enregistrement.renommer', $poste) }}"
          @keydown.escape="edition = false" class="flex flex-wrap items-center gap-2">
        @csrf
        <input x-ref="champ" type="text" name="nom_poste" value="{{ $poste->nom_poste }}" maxlength="100" required
               aria-label="Nouveau nom du poste"
               class="min-w-0 flex-1 rounded-lg border-slate-300 py-1.5 text-sm focus:border-violet-500 focus:ring-violet-500">
        <button type="submit"
                class="rounded-lg bg-violet-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-violet-700">Enregistrer</button>
        <button type="button" @click="edition = false"
                class="rounded-lg px-3 py-1.5 text-xs font-semibold text-slate-600 hover:bg-slate-100">Annuler</button>
    </form>
</div>