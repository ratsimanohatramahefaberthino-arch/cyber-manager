@extends('layouts.cyber-manager', ['title' => 'Historique — Cyber Manager'])

@section('content')
@php
    $periodes = ['tout' => 'Tout', 'aujourdhui' => "Aujourd'hui", 'semaine' => 'Cette semaine', 'mois' => 'Ce mois'];
    $badges = [
        'forfait'  => 'bg-indigo-50 text-indigo-700 ring-indigo-200',
        'postpaye' => 'bg-amber-50 text-amber-700 ring-amber-200',
        'libre'    => 'bg-slate-100 text-slate-600 ring-slate-200',
    ];
    $fmtAr = fn ($n) => number_format((int) $n, 0, ',', ' ') . ' Ar';
    $filtreActif = $filtres['periode'] !== 'tout' || $filtres['poste_id'] || $filtres['mode'];
@endphp

<div class="mx-auto max-w-[1500px]">

    {{-- En-tête --}}
    <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
        <div>
            <h1 class="text-2xl font-semibold tracking-tight text-slate-900">Historique des sessions</h1>
            <p class="text-sm text-slate-500">Sessions terminées : Forfait, Postpayé et Débloqué.</p>
        </div>
        <a href="{{ route('historique.export', request()->except('page')) }}"
           class="inline-flex items-center gap-2 rounded-lg bg-emerald-600 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-emerald-700">
            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v2a2 2 0 002 2h12a2 2 0 002-2v-2M7 10l5 5 5-5M12 15V3"/></svg>
            Exporter en CSV
        </a>
    </div>

    {{-- Cartes de résumé (sur le résultat filtré) --}}
    <div class="mb-4 grid grid-cols-1 gap-4 sm:grid-cols-3">
        <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <div class="text-sm text-slate-500">Sessions</div>
            <div class="mt-1 text-3xl font-bold text-slate-900">{{ number_format($totaux['sessions'], 0, ',', ' ') }}</div>
        </div>
        <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <div class="text-sm text-slate-500">Chiffre d'affaires</div>
            <div class="mt-1 text-3xl font-bold text-emerald-600">{{ $fmtAr($totaux['ca']) }}</div>
        </div>
        <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <div class="text-sm text-slate-500">Temps consommé</div>
            <div class="mt-1 text-3xl font-bold text-indigo-600">{{ $totaux['duree'] }}</div>
        </div>
    </div>

    {{-- Filtres --}}
    <div class="mb-4 rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
        <div class="mb-3 flex flex-wrap gap-2">
            @foreach($periodes as $cle => $label)
                <a href="{{ request()->fullUrlWithQuery(['periode' => $cle, 'du' => null, 'au' => null, 'page' => null]) }}"
                   @class([
                       'rounded-full px-4 py-1.5 text-sm font-medium transition',
                       'bg-indigo-600 text-white shadow-sm' => $filtres['periode'] === $cle,
                       'bg-slate-100 text-slate-600 hover:bg-slate-200' => $filtres['periode'] !== $cle,
                   ])>{{ $label }}</a>
            @endforeach
            @if($filtres['periode'] === 'personnalise')
                <span class="rounded-full bg-indigo-600 px-4 py-1.5 text-sm font-medium text-white shadow-sm">Période personnalisée</span>
            @endif
        </div>

        <form method="GET" action="{{ route('historique.index') }}" class="flex flex-wrap items-end gap-3">
            @if(in_array($filtres['periode'], ['aujourdhui', 'semaine', 'mois'], true))
                <input type="hidden" name="periode" value="{{ $filtres['periode'] }}">
            @endif

            <div>
                <label class="mb-1 block text-xs font-medium text-slate-500">Poste</label>
                <select name="poste_id" class="rounded-lg border-slate-300 text-sm">
                    <option value="">Tous les postes</option>
                    @foreach($postes as $p)
                        <option value="{{ $p->id }}" @selected((int) $filtres['poste_id'] === $p->id)>{{ $p->nom_poste }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label class="mb-1 block text-xs font-medium text-slate-500">Type</label>
                <select name="mode" class="rounded-lg border-slate-300 text-sm">
                    <option value="">Tous les types</option>
                    @foreach($modes as $cle => $label)
                        <option value="{{ $cle }}" @selected($filtres['mode'] === $cle)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label class="mb-1 block text-xs font-medium text-slate-500">Du</label>
                <input type="date" name="du" value="{{ $filtres['du'] }}" class="rounded-lg border-slate-300 text-sm">
            </div>
            <div>
                <label class="mb-1 block text-xs font-medium text-slate-500">Au</label>
                <input type="date" name="au" value="{{ $filtres['au'] }}" class="rounded-lg border-slate-300 text-sm">
            </div>

            <button type="submit" class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-indigo-700">Filtrer</button>
            @if($filtreActif || $filtres['du'] || $filtres['au'])
                <a href="{{ route('historique.index') }}" class="rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-600 transition hover:bg-slate-50">Réinitialiser</a>
            @endif
        </form>

        @if($errors->any())
            <p class="mt-3 text-sm font-medium text-rose-600">{{ $errors->first() }}</p>
        @endif
    </div>

    {{-- Tableau --}}
    <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
        <div class="overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">
                    <tr>
                        <th class="px-5 py-3">Date</th>
                        <th class="px-5 py-3">Poste</th>
                        <th class="px-5 py-3">Type</th>
                        <th class="px-5 py-3">Durée</th>
                        <th class="px-5 py-3 text-right">Montant</th>
                        <th class="px-5 py-3">Statut</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($sessions as $s)
                        <tr class="transition hover:bg-slate-50">
                            <td class="whitespace-nowrap px-5 py-3">
                                <div class="font-medium text-slate-900">{{ $s['date'] }}</div>
                                <div class="text-xs text-slate-500">{{ $s['debut'] }} → {{ $s['fin'] }}</div>
                            </td>
                            <td class="whitespace-nowrap px-5 py-3 font-semibold text-slate-800">{{ $s['poste'] }}</td>
                            <td class="whitespace-nowrap px-5 py-3">
                                <span class="inline-flex rounded-full px-2.5 py-0.5 text-xs font-semibold ring-1 ring-inset {{ $badges[$s['mode']] ?? $badges['libre'] }}">{{ $s['type'] }}</span>
                            </td>
                            <td class="whitespace-nowrap px-5 py-3 font-mono text-slate-700">{{ $s['duree'] }}</td>
                            <td class="whitespace-nowrap px-5 py-3 text-right font-mono font-bold {{ $s['mode'] === 'libre' ? 'text-slate-400' : 'text-slate-900' }}">
                                {{ $s['mode'] === 'libre' ? 'Sans facturation' : $fmtAr($s['montant']) }}
                            </td>
                            <td class="whitespace-nowrap px-5 py-3">
                                <span class="inline-flex rounded-full bg-emerald-50 px-2.5 py-0.5 text-xs font-semibold text-emerald-700 ring-1 ring-inset ring-emerald-200">{{ $s['statut'] }}</span>
                                @if($s['motif'])
                                    <div class="mt-0.5 text-[11px] text-slate-400">{{ $s['motif'] }}</div>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-5 py-14 text-center text-slate-400">
                                Aucune session terminée ne correspond à ces filtres.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($sessions->hasPages())
            <div class="border-t border-slate-100 bg-slate-50 px-5 py-3">
                {{ $sessions->links() }}
            </div>
        @endif
    </div>
</div>
@endsection