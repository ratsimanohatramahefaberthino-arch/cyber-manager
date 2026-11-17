@extends('layouts.cyber-manager', ['title' => 'Parc — Cyber Manager'])

@section('content')
@php
    use App\Models\AgentVersion;
    $etats = ['disponible' => ['Disponible', 'vert'], 'occupe' => ['Occupé', 'bleu'], 'suspendu' => ['Suspendu', 'orange'], 'verrouille' => ['Verrouillé', 'gris'], 'desactive' => ['Désactivé', 'gris'], 'hors_ligne' => ['Hors ligne', 'rouge']];

    $total     = $postes->count();
    $enLigne   = $postes->filter(fn ($p) => $p->estEnLigne())->count();
    $aJour     = $postes->filter(fn ($p) => ($visees[$p->id] ?? null) && $visees[$p->id] === $p->agent_version)->count();
    $enAttente = $postes->filter(fn ($p) => ($visees[$p->id] ?? null) && $visees[$p->id] !== $p->agent_version)->count();
    $btn = 'inline-flex h-9 items-center justify-center gap-1.5 rounded-lg px-3 text-sm font-semibold shadow-sm transition focus:outline-none focus-visible:ring-2 focus-visible:ring-violet-500';
@endphp

<div class="space-y-6">
    <div>
        <h1 class="text-2xl font-semibold tracking-tight text-slate-900">Parc et restrictions</h1>
        <p class="text-sm text-slate-500">État de chaque poste : version installée, version visée, dernière mise à jour. Heures en heure de Madagascar.</p>
    </div>

    <x-flash />

    @if ($errors->any() && !$errors->has('global'))
        <div class="rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800" role="alert">
            <ul class="list-disc space-y-0.5 pl-5">@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
        </div>
    @endif

    {{-- Compteurs --}}
    <div class="grid grid-cols-2 gap-4 lg:grid-cols-4">
        @foreach ([['Postes', $total, 'desktop', 'violet'], ['En ligne', $enLigne, 'wifi', 'vert'], ['À jour', $aJour, 'check', 'vert'], ['Mise à jour en attente', $enAttente, 'refresh', 'orange']] as [$titre, $nb, $ic, $col])
            <div class="flex items-center gap-3 rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
                <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl {{ $col === 'vert' ? 'bg-emerald-50 text-emerald-700' : ($col === 'orange' ? 'bg-amber-50 text-amber-700' : 'bg-violet-50 text-violet-700') }}">
                    <x-icone :nom="$ic" classe="h-5 w-5" />
                </span>
                <div><p class="text-2xl font-extrabold leading-none text-slate-900">{{ $nb }}</p><p class="mt-1 text-xs text-slate-500">{{ $titre }}</p></div>
            </div>
        @endforeach
    </div>

    {{-- Postes --}}
    <x-parametres.section titre="Postes" icone="desktop" aide="Un poste se met à jour seulement quand il est libre et verrouillé.">
        <div class="overflow-x-auto rounded-xl border border-slate-200">
            <table class="min-w-full divide-y divide-slate-200 text-sm">
                <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">
                    <tr><th class="px-4 py-2.5">Poste</th><th class="px-4 py-2.5">État</th><th class="px-4 py-2.5">Installée</th><th class="px-4 py-2.5">Visée</th><th class="px-4 py-2.5">Dernière mise à jour</th><th class="px-4 py-2.5">Maintenance</th></tr>
                </thead>
                <tbody class="divide-y divide-slate-100 bg-white">
                @forelse ($postes as $p)
                    @php
                        [$lib, $couleur] = $etats[$p->etatAffiche()] ?? [$p->etatAffiche(), 'gris'];
                        $visee = $visees[$p->id] ?? null;
                    @endphp
                    <tr class="align-top">
                        <td class="px-4 py-3">
                            <form method="POST" action="{{ route('parametres.parc.renommer', $p) }}" class="flex gap-1.5">
                                @csrf
                                <input type="text" name="nom" value="{{ $p->nom_poste }}" maxlength="100" aria-label="Nom du poste"
                                       class="h-9 w-36 rounded-lg border border-slate-300 px-2.5 text-sm focus:border-violet-500 focus:ring-2 focus:ring-violet-500/20">
                                <button type="submit" class="{{ $btn }} border border-slate-300 bg-white text-slate-700 hover:bg-slate-50" title="Renommer"><x-icone nom="pencil" classe="h-4 w-4" /></button>
                            </form>
                            @if ($p->nom_windows) <p class="mt-1 text-[11px] text-slate-400">Windows : {{ $p->nom_windows }}</p> @endif
                        </td>
                        <td class="px-4 py-3"><x-parametres.badge :couleur="$couleur">{{ $lib }}</x-parametres.badge></td>
                        <td class="px-4 py-3 font-medium text-slate-800">{{ $p->agent_version ?: '—' }}</td>
                        <td class="px-4 py-3">
                            @if ($visee === null)
                                <span class="text-slate-400">Aucune</span>
                            @elseif ($visee === $p->agent_version)
                                {{ $visee }} <x-parametres.badge couleur="vert">À jour</x-parametres.badge>
                            @else
                                {{ $visee }} <x-parametres.badge couleur="orange">En attente</x-parametres.badge>
                            @endif
                        </td>
                        <td class="px-4 py-3">
                            @if ($p->derniere_maj_at)
                                <span class="text-slate-700">{{ AgentVersion::heure($p->derniere_maj_at) }}</span>
                                <x-parametres.badge :couleur="$p->derniere_maj_resultat === 'succes' ? 'vert' : 'rouge'">{{ $p->derniere_maj_resultat === 'succes' ? 'Réussie' : 'Échouée' }}</x-parametres.badge>
                                @if ($p->derniere_maj_message) <p class="mt-1 text-xs text-slate-500">{{ $p->derniere_maj_message }}</p> @endif
                            @else
                                <span class="text-slate-400">—</span>
                            @endif
                        </td>
                        <td class="px-4 py-3">
                            <form method="POST" action="{{ route('parametres.parc.maintenance', $p) }}">
                                @csrf
                                <button type="submit" title="Activée = restrictions levées sur ce poste"
                                        class="{{ $btn }} {{ $p->mode_maintenance ? 'bg-emerald-600 text-white hover:bg-emerald-700' : 'border border-slate-300 bg-white text-slate-600 hover:bg-slate-50' }}">
                                    <x-icone :nom="$p->mode_maintenance ? 'lock-open' : 'lock'" classe="h-4 w-4" />
                                    {{ $p->mode_maintenance ? 'Activée (restrictions levées)' : 'Désactivée' }}
                                </button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="px-4 py-8 text-center text-slate-400">Aucun poste enregistré.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </x-parametres.section>

    {{-- Restrictions --}}
    <x-parametres.section titre="Profil de restrictions Windows" icone="shield"
        aide="Cochez ce qui doit être bloqué. Appliqué par le Gardien sur le compte « Client » uniquement : le compte administrateur n'est jamais touché. Le bouton « Maintenance » lève le profil pour un seul poste.">
        <form method="POST" action="{{ route('parametres.parc.restrictions') }}">
            @csrf
            <div class="grid gap-3 md:grid-cols-2">
                @foreach ($cles as $cle => [$libelle, $aide])
                    <label class="flex cursor-pointer items-start gap-3 rounded-xl border border-slate-200 bg-slate-50/50 p-3 transition hover:border-violet-300 hover:bg-violet-50/40">
                        <input type="checkbox" name="r[{{ $cle }}]" value="1" {{ ($profil[$cle] ?? true) ? 'checked' : '' }}
                               class="mt-0.5 h-5 w-5 rounded border-slate-300 text-violet-600 focus:ring-violet-500">
                        <span><span class="block text-sm font-semibold text-slate-900">{{ $libelle }}</span><span class="block text-xs text-slate-500">{{ $aide }}</span></span>
                    </label>
                @endforeach
            </div>
            <button type="submit" class="mt-4 inline-flex h-10 items-center rounded-xl bg-violet-600 px-5 text-sm font-semibold text-white shadow-sm hover:bg-violet-700">Enregistrer le profil</button>
        </form>
        <p class="mt-4 rounded-xl bg-slate-50 px-4 py-3 text-xs leading-relaxed text-slate-500">
            Après un changement, le Gardien écrit les réglages tout de suite, mais Windows ne relit certains d'entre eux (Exécuter, Panneau de configuration, Se déconnecter, PowerShell) qu'à la prochaine ouverture de session du compte Client. Pour la maintenance, redémarrez le poste. Le Gestionnaire des tâches et le Verrouillage réagissent immédiatement.
        </p>
    </x-parametres.section>
</div>
@endsection