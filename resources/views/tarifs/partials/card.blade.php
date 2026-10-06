@php
    $fmtAr = fn ($n) => number_format((int) $n, 0, ',', ' ') . ' Ar';
    $couleur = $tarif->couleur();
    $classesPastille = [
        'slate'  => 'bg-slate-100 text-slate-600',
        'sky'    => 'bg-sky-50 text-sky-600',
        'violet' => 'bg-violet-50 text-violet-600',
    ][$couleur];
@endphp

<div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
    <div class="mb-4 flex items-start justify-between gap-3">
        <div class="flex items-center gap-3">
            <span class="flex h-10 w-10 items-center justify-center rounded-lg {{ $classesPastille }}">
                <x-icone :nom="$tarif->icone()" class="h-5 w-5" />
            </span>
            <div>
                <h2 class="text-base font-semibold text-slate-900">{{ $titre }}</h2>
                <p class="text-xs text-slate-500">{{ $tarif->description ?: 'Aucune description' }}</p>
            </div>
        </div>

        <button type="button"
                @click="$dispatch('editer-tarif', @js([
                    'titre' => $titre,
                    'update_url' => route('tarifs.update', $tarif),
                    'montant_par_minute' => $tarif->montant_par_minute,
                    'montant_minimum' => $tarif->montant_minimum,
                    'arrondi_actif' => (bool) $tarif->arrondi_actif,
                    'unite_arrondi' => $tarif->unite_arrondi,
                    'seuil_arrondi' => $tarif->seuil_arrondi,
                    'description' => $tarif->description,
                ]))"
                class="inline-flex h-8 items-center gap-1.5 rounded-lg border border-slate-200 bg-white px-3 text-xs font-medium text-slate-700 hover:bg-slate-50">
            <x-icone nom="pencil" class="h-3.5 w-3.5" /> Modifier
        </button>
    </div>

    <dl class="grid grid-cols-2 gap-4 text-sm sm:grid-cols-4">
        <div>
            <dt class="text-xs text-slate-500">Prix / minute</dt>
            <dd class="font-mono text-lg font-semibold text-slate-900">{{ $tarif->montant_par_minute }} Ar</dd>
        </div>
        <div>
            <dt class="text-xs text-slate-500">Minimum facturable</dt>
            <dd class="font-mono text-lg font-semibold text-slate-900">{{ $fmtAr($tarif->montant_minimum) }}</dd>
        </div>
        <div>
            <dt class="text-xs text-slate-500">Arrondi</dt>
            <dd class="text-sm font-semibold text-slate-900">
                @if($tarif->arrondi_actif)
                    {{ $tarif->unite_arrondi }} Ar <span class="text-xs font-normal text-slate-500">(seuil {{ $tarif->seuil_arrondi }})</span>
                @else
                    Désactivé
                @endif
            </dd>
        </div>
        <div>
            <dt class="text-xs text-slate-500">Raccourcis</dt>
            <dd class="text-sm font-semibold text-slate-900">{{ $tarif->raccourcis->count() }}</dd>
        </div>
    </dl>
</div>