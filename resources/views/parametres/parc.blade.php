@extends('layouts.cyber-manager', ['title' => 'Parc — Cyber Manager'])

@section('content')
@php
    use App\Models\AgentVersion;
    $etats = ['disponible' => ['Disponible', 'vert'], 'occupe' => ['Occupé', 'bleu'], 'suspendu' => ['Suspendu', 'orange'], 'verrouille' => ['Verrouillé', 'gris'], 'desactive' => ['Désactivé', 'gris'], 'hors_ligne' => ['Hors ligne', 'rouge']];
@endphp
<div style="max-width: 1100px; margin: 0 auto;">
    <div style="margin-bottom: 18px;">
        <h1 class="text-2xl font-semibold tracking-tight text-slate-900">Paramètres</h1>
        <p class="text-sm text-slate-500">État de chaque poste : version installée, version visée, dernière mise à jour. Heures en heure de Madagascar.</p>
    </div>

    @include('parametres._onglets')

    @if (session('success')) <div class="cm-t-ok">{{ session('success') }}</div> @endif
    @if ($errors->any())
        <div class="cm-t-err">@foreach ($errors->all() as $e) <div>• {{ $e }}</div> @endforeach</div>
    @endif

    <div class="cm-t-card">
        <h2>Postes</h2>
        <p class="cm-t-aide">Un poste se met à jour seulement quand il est libre et verrouillé.</p>
        <div class="cm-t-scroll">
        <table class="cm-t-table">
            <thead><tr><th>Poste</th><th>État</th><th>Version installée</th><th>Version visée</th><th>Dernière mise à jour</th><th>Maintenance</th></tr></thead>
            <tbody>
            @forelse ($postes as $p)
                @php
                    [$lib, $couleur] = $etats[$p->etatAffiche()] ?? [$p->etatAffiche(), 'gris'];
                    $visee = $visees[$p->id] ?? null;
                @endphp
                <tr>
                    <td>
                        <form method="POST" action="{{ route('parametres.parc.renommer', $p) }}" style="display:flex; gap:6px;">
                            @csrf
                            <input type="text" name="nom" value="{{ $p->nom_poste }}" maxlength="100"
                                   style="border:1px solid #cbd5e1; border-radius:8px; padding:5px 9px; font-size:13px; width:150px;">
                            <button type="submit" class="cm-t-btn gris">Renommer</button>
                        </form>
                        @if ($p->nom_windows) <div style="font-size:11px; color:#94a3b8; margin-top:3px;">Windows : {{ $p->nom_windows }}</div> @endif
                    </td>
                    <td><span class="cm-t-badge {{ $couleur }}">{{ $lib }}</span></td>
                    <td>{{ $p->agent_version ?: '—' }}</td>
                    <td>
                        @if ($visee === null)
                            <span style="color:#94a3b8;">Aucune</span>
                        @elseif ($visee === $p->agent_version)
                            {{ $visee }} <span class="cm-t-badge vert">À jour</span>
                        @else
                            {{ $visee }} <span class="cm-t-badge orange">En attente</span>
                        @endif
                    </td>
                    <td>
                        @if ($p->derniere_maj_at)
                            {{ AgentVersion::heure($p->derniere_maj_at) }}
                            <span class="cm-t-badge {{ $p->derniere_maj_resultat === 'succes' ? 'vert' : 'rouge' }}">{{ $p->derniere_maj_resultat === 'succes' ? 'Réussie' : 'Échouée' }}</span>
                            @if ($p->derniere_maj_message) <div style="font-size:12px; color:#64748b;">{{ $p->derniere_maj_message }}</div> @endif
                        @else
                            <span style="color:#94a3b8;">—</span>
                        @endif
                    </td>
                    <td>
                        <form method="POST" action="{{ route('parametres.parc.maintenance', $p) }}">
                            @csrf
                            <button type="submit" class="cm-t-btn {{ $p->mode_maintenance ? 'vert' : 'gris' }}" title="Activée = restrictions levées sur ce poste">{{ $p->mode_maintenance ? 'Activée (restrictions levées)' : 'Désactivée' }}</button>
                        </form>
                    </td>
                </tr>
            @empty
                <tr><td colspan="6" style="color:#94a3b8;">Aucun poste enregistré.</td></tr>
            @endforelse
            </tbody>
        </table>
        </div>
    </div>

    <div class="cm-t-card">
        <h2>Profil de restrictions Windows</h2>
        <p class="cm-t-aide">Cochez ce qui doit être bloqué. Appliqué par le Gardien de chaque poste, sur le compte « Client » uniquement : le compte administrateur n'est jamais touché. Le profil s'applique à tous les postes ; le bouton « Maintenance » ci-dessus le lève pour un seul poste.</p>
        <form method="POST" action="{{ route('parametres.parc.restrictions') }}">
            @csrf
            @foreach ($cles as $cle => [$libelle, $aide])
                <label style="display:block; padding:6px 0; font-size:14px; color:#0f172a;">
                    <input type="checkbox" name="r[{{ $cle }}]" value="1" {{ ($profil[$cle] ?? true) ? 'checked' : '' }}>
                    <strong>{{ $libelle }}</strong>
                    <span style="color:#64748b; font-size:12px;"> : {{ $aide }}</span>
                </label>
            @endforeach
            <button type="submit" class="cm-t-btn" style="margin-top:12px;">Enregistrer le profil</button>
        </form>
        <p class="cm-t-aide" style="margin-top:14px; margin-bottom:0;">Après un changement, le Gardien écrit les réglages tout de suite, mais Windows ne relit certains d'entre eux (Exécuter, Panneau de configuration, Se déconnecter, PowerShell) qu'à la prochaine ouverture de session du compte Client. Pour la maintenance, redémarrez le poste : « Se déconnecter » étant bloqué, le redémarrage est le moyen le plus simple. Le Gestionnaire des tâches et le Verrouillage réagissent immédiatement.</p>
    </div>
</div>
@endsection