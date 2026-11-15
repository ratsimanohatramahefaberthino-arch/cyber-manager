@extends('layouts.cyber-manager', ['title' => 'Statistiques — Cyber Manager'])

@section('content')
@php
    $fmtAr = fn ($n) => number_format((int) $n, 0, ',', ' ') . ' Ar';
    $couleursModes = ['forfait' => '#6366f1', 'postpaye' => '#f59e0b', 'libre' => '#94a3b8'];
    $couleursTranches = ['#bae6fd', '#7dd3fc', '#38bdf8', '#0ea5e9', '#0369a1'];
    $totalModes    = collect($modes)->sum('n');
    $totalTranches = collect($tranches)->sum('n');
    $maxPoste  = max(1, collect($topPostes)->max('ca') ?? 1);
    $maxCompte = max(1, collect($topComptes)->max('ca') ?? 1);
@endphp

<div class="mx-auto max-w-[1500px]">

    {{-- En-tête + sélecteur de période --}}
    <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
        <div>
            <h1 class="text-2xl font-semibold tracking-tight text-slate-900">Statistiques</h1>
            <p class="text-sm text-slate-500">Revenus des postes et du Wi-Fi — période affichée : {{ $libellePeriode }}.</p>
        </div>
        <div class="flex gap-2">
            @foreach($periodes as $cle => $label)
                <a href="{{ route('statistiques.index', ['periode' => $cle]) }}"
                   @class([
                       'rounded-full px-4 py-1.5 text-sm font-medium transition',
                       'bg-indigo-600 text-white shadow-sm' => $periode === (string) $cle,
                       'bg-white text-slate-600 ring-1 ring-slate-200 hover:bg-slate-100' => $periode !== (string) $cle,
                   ])>{{ $label }}</a>
            @endforeach
        </div>
    </div>

    {{-- Cartes de résumé (totaux) --}}
    <div class="mb-4 grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <div class="text-sm text-slate-500">CA total du jour</div>
            <div class="mt-1 text-3xl font-bold text-emerald-600">{{ $fmtAr($resume['ca_jour']) }}</div>
            <div class="mt-2 text-xs text-slate-500">Postes + Wi-Fi</div>
        </div>
        <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <div class="text-sm text-slate-500">CA total du mois</div>
            <div class="mt-1 text-3xl font-bold text-slate-900">{{ $fmtAr($resume['ca_mois']) }}</div>
            <div class="mt-2 text-xs text-slate-500">Postes + Wi-Fi</div>
        </div>
        <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <div class="text-sm text-slate-500">Sessions du jour</div>
            <div class="mt-1 text-3xl font-bold text-indigo-600">{{ number_format($resume['n_jour'], 0, ',', ' ') }}</div>
            <div class="mt-2 text-xs text-slate-500">{{ number_format($resume['n_mois'], 0, ',', ' ') }} ce mois (postes + Wi-Fi)</div>
        </div>
        <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <div class="text-sm text-slate-500">Durée moyenne / session</div>
            <div class="mt-1 text-3xl font-bold text-amber-600">{{ $resume['duree_moyenne'] }}</div>
            <div class="mt-2 text-xs text-slate-500">Ce mois · {{ $resume['duree_totale'] }} consommées au total</div>
        </div>
    </div>

    {{-- Cartes par source --}}
    <div class="mb-4 grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <div class="rounded-2xl border border-indigo-100 bg-indigo-50/60 p-5 shadow-sm">
            <div class="flex items-center gap-2 text-sm font-medium text-indigo-700"><span class="h-2.5 w-2.5 rounded-full bg-indigo-600"></span>CA Postes — jour</div>
            <div class="mt-1 text-2xl font-bold text-slate-900">{{ $fmtAr($jour['postes']['ca']) }}</div>
            <div class="mt-2 text-xs text-slate-500">{{ $jour['postes']['n'] }} session(s) aujourd'hui</div>
        </div>
        <div class="rounded-2xl border border-sky-100 bg-sky-50/60 p-5 shadow-sm">
            <div class="flex items-center gap-2 text-sm font-medium text-sky-700"><span class="h-2.5 w-2.5 rounded-full bg-sky-500"></span>CA Wi-Fi — jour</div>
            <div class="mt-1 text-2xl font-bold text-slate-900">{{ $fmtAr($jour['wifi']['ca']) }}</div>
            <div class="mt-2 text-xs text-slate-500">{{ $jour['wifi']['n'] }} session(s) aujourd'hui</div>
        </div>
        <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <div class="flex items-center gap-2 text-sm font-medium text-indigo-700"><span class="h-2.5 w-2.5 rounded-full bg-indigo-600"></span>CA Postes — mois</div>
            <div class="mt-1 text-2xl font-bold text-slate-900">{{ $fmtAr($mois['postes']['ca']) }}</div>
            <div class="mt-2 text-xs text-slate-500">{{ $mois['postes']['n'] }} session(s) ce mois</div>
        </div>
        <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <div class="flex items-center gap-2 text-sm font-medium text-sky-700"><span class="h-2.5 w-2.5 rounded-full bg-sky-500"></span>CA Wi-Fi — mois</div>
            <div class="mt-1 text-2xl font-bold text-slate-900">{{ $fmtAr($mois['wifi']['ca']) }}</div>
            <div class="mt-2 text-xs text-slate-500">{{ $mois['wifi']['n'] }} session(s) ce mois</div>
        </div>
    </div>

    {{-- Graphique des revenus --}}
    <div class="mb-4 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
        <h2 class="mb-3 font-semibold text-slate-800">Revenus par jour — Postes vs Wi-Fi</h2>
        <div class="relative h-72"><canvas id="chartRevenus"></canvas></div>
    </div>

    {{-- Répartitions --}}
    <div class="mb-4 grid grid-cols-1 gap-4 lg:grid-cols-2">
        {{-- Postes --}}
        <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <h2 class="mb-3 font-semibold text-slate-800">Postes — répartition par type de session</h2>
            @if($totalModes > 0)
                <div class="flex flex-wrap items-center gap-6">
                    <div class="relative h-48 w-48 shrink-0"><canvas id="chartModes"></canvas></div>
                    <ul class="min-w-0 flex-1 space-y-2 text-sm">
                        @foreach($modes as $i => $m)
                            @php $cle = array_keys(['forfait' => 1, 'postpaye' => 1, 'libre' => 1])[$i]; @endphp
                            <li class="flex items-center justify-between gap-3">
                                <span class="flex items-center gap-2 text-slate-700"><span class="h-2.5 w-2.5 rounded-full" style="background: {{ $couleursModes[$cle] }}"></span>{{ $m['label'] }}</span>
                                <span class="text-right text-slate-500"><b class="text-slate-900">{{ $m['n'] }}</b> · {{ $fmtAr($m['ca']) }}</span>
                            </li>
                        @endforeach
                    </ul>
                </div>
            @else
                <p class="py-12 text-center text-sm text-slate-400">Aucune session de poste sur cette période.</p>
            @endif
        </div>

        {{-- Wi-Fi --}}
        <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <h2 class="mb-3 font-semibold text-slate-800">Wi-Fi — répartition par durée de session</h2>
            @if($totalTranches > 0)
                <div class="flex flex-wrap items-center gap-6">
                    <div class="relative h-48 w-48 shrink-0"><canvas id="chartTranches"></canvas></div>
                    <ul class="min-w-0 flex-1 space-y-2 text-sm">
                        @foreach($tranches as $i => $t)
                            <li class="flex items-center justify-between gap-3">
                                <span class="flex items-center gap-2 text-slate-700"><span class="h-2.5 w-2.5 rounded-full" style="background: {{ $couleursTranches[$i] }}"></span>{{ $t['label'] }}</span>
                                <span class="text-right text-slate-500"><b class="text-slate-900">{{ $t['n'] }}</b> · {{ $fmtAr($t['ca']) }}</span>
                            </li>
                        @endforeach
                    </ul>
                </div>
            @else
                <p class="py-12 text-center text-sm text-slate-400">Aucune session Wi-Fi terminée sur cette période.</p>
            @endif
        </div>
    </div>

    {{-- Tops --}}
    <div class="grid grid-cols-1 gap-4 lg:grid-cols-2">
        <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <h2 class="mb-3 font-semibold text-slate-800">Top 5 des postes par revenus</h2>
            @forelse($topPostes as $i => $p)
                <div class="mb-3 last:mb-0">
                    <div class="mb-1 flex items-center justify-between text-sm">
                        <span class="font-semibold text-slate-800">{{ $i + 1 }}. {{ $p['nom'] }}</span>
                        <span class="font-mono font-bold text-slate-900">{{ $fmtAr($p['ca']) }}</span>
                    </div>
                    <div class="h-2 overflow-hidden rounded-full bg-slate-100">
                        <div class="h-full rounded-full bg-indigo-500" style="width: {{ round($p['ca'] / $maxPoste * 100) }}%"></div>
                    </div>
                    <div class="mt-0.5 text-xs text-slate-400">{{ $p['n'] }} session(s) · {{ $p['duree_fmt'] }}</div>
                </div>
            @empty
                <p class="py-10 text-center text-sm text-slate-400">Aucune donnée sur cette période.</p>
            @endforelse
        </div>

        <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <h2 class="mb-3 font-semibold text-slate-800">Top 5 des comptes Wi-Fi</h2>
            @forelse($topComptes as $i => $c)
                <div class="mb-3 last:mb-0">
                    <div class="mb-1 flex items-center justify-between text-sm">
                        <span class="truncate font-semibold text-slate-800">{{ $i + 1 }}. {{ $c['nom'] }}</span>
                        <span class="font-mono font-bold text-slate-900">{{ $fmtAr($c['ca']) }}</span>
                    </div>
                    <div class="h-2 overflow-hidden rounded-full bg-slate-100">
                        <div class="h-full rounded-full bg-sky-500" style="width: {{ round($c['ca'] / $maxCompte * 100) }}%"></div>
                    </div>
                    <div class="mt-0.5 text-xs text-slate-400">{{ $c['n'] }} session(s) · {{ $c['duree_fmt'] }}</div>
                </div>
            @empty
                <p class="py-10 text-center text-sm text-slate-400">Aucune donnée sur cette période.</p>
            @endforelse
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<script>
(function () {
    if (typeof Chart === 'undefined') return;

    const fmtAr = n => (Number(n) || 0).toLocaleString('fr-FR') + ' Ar';
    const serie = @json($serie);

    new Chart(document.getElementById('chartRevenus'), {
        type: 'bar',
        data: {
            labels: serie.map(s => s.label),
            datasets: [
                { label: 'Postes', data: serie.map(s => s.postes), backgroundColor: '#4f46e5', borderRadius: 4 },
                { label: 'Wi-Fi',  data: serie.map(s => s.wifi),   backgroundColor: '#0ea5e9', borderRadius: 4 },
            ],
        },
        options: {
            responsive: true, maintainAspectRatio: false,
            interaction: { mode: 'index', intersect: false },
            scales: {
                x: { stacked: true, grid: { display: false } },
                y: { stacked: true, beginAtZero: true, ticks: { callback: v => v.toLocaleString('fr-FR') } },
            },
            plugins: {
                legend: { position: 'bottom' },
                tooltip: {
                    callbacks: {
                        label: c => c.dataset.label + ' : ' + fmtAr(c.parsed.y),
                        footer: items => 'Total : ' + fmtAr(items.reduce((t, i) => t + i.parsed.y, 0)),
                    },
                },
            },
        },
    });

    function donut(id, labels, data, colors) {
        const el = document.getElementById(id);
        if (!el) return;
        new Chart(el, {
            type: 'doughnut',
            data: { labels, datasets: [{ data, backgroundColor: colors, borderWidth: 2, borderColor: '#fff' }] },
            options: {
                responsive: true, maintainAspectRatio: false, cutout: '62%',
                plugins: {
                    legend: { display: false },
                    tooltip: { callbacks: { label: c => c.label + ' : ' + c.parsed + ' session(s)' } },
                },
            },
        });
    }

    const modes = @json($modes);
    donut('chartModes', modes.map(m => m.label), modes.map(m => m.n), ['#6366f1', '#f59e0b', '#94a3b8']);

    const tranches = @json($tranches);
    donut('chartTranches', tranches.map(t => t.label), tranches.map(t => t.n), ['#bae6fd', '#7dd3fc', '#38bdf8', '#0ea5e9', '#0369a1']);
})();
</script>
@endsection