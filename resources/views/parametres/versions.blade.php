@extends('layouts.cyber-manager', ['title' => 'Versions — Cyber Manager'])

@section('content')
@php
    use App\Models\AgentVersion;
    $etiquettes = ['brouillon' => ['Brouillon', 'gris'], 'pilote' => ['Pilote', 'orange'], 'general' => ['Publiée (tous)', 'vert'], 'archivee' => ['Archivée', 'bleu']];
    $generale = $versions->firstWhere('statut', 'general');
@endphp
<div style="max-width: 1100px; margin: 0 auto;">
    <div style="margin-bottom: 18px;">
        <h1 class="text-2xl font-semibold tracking-tight text-slate-900">Paramètres</h1>
        <p class="text-sm text-slate-500">Versions de l'agent : envoi, test sur un poste pilote, publication, retour arrière. Heures affichées en heure de Madagascar.</p>
    </div>

    @include('parametres._onglets')

    @if (session('success')) <div class="cm-t-ok">{{ session('success') }}</div> @endif
    @if ($errors->any())
        <div class="cm-t-err">@foreach ($errors->all() as $e) <div>• {{ $e }}</div> @endforeach</div>
    @endif

    <div class="cm-t-card">
        <h2>Version actuellement publiée pour tous</h2>
        <p style="font-size: 20px; font-weight: 800; color: #0f172a;">{{ $generale ? $generale->version : 'Aucune' }}</p>
        @if ($generale)
            <p class="cm-t-aide" style="margin-bottom: 0;">Publiée le {{ AgentVersion::heure($generale->publiee_generale_at) }}</p>
        @endif
    </div>

    <div class="cm-t-card">
        <h2>Envoyer un nouveau paquet</h2>
        <p class="cm-t-aide">Fichier .zip fabriqué sur le PC du développeur. Le serveur calcule son empreinte SHA-256. Le paquet reste en brouillon tant qu'il n'est pas publié.</p>
        <form method="POST" action="{{ route('parametres.versions.televerser') }}" enctype="multipart/form-data">
            @csrf
            <div class="cm-t-ligne">
                <div class="cm-t-champ" style="width: 160px;">
                    <label for="version">Version</label>
                    <input type="text" id="version" name="version" maxlength="32" placeholder="2.0.1" value="{{ old('version') }}" required>
                </div>
                <div class="cm-t-champ" style="flex: 1; min-width: 220px;">
                    <label for="paquet">Paquet (.zip)</label>
                    <input type="file" id="paquet" name="paquet" accept=".zip" required>
                </div>
                <div class="cm-t-champ" style="flex: 1; min-width: 220px;">
                    <label for="notes">Notes (facultatif)</label>
                    <input type="text" id="notes" name="notes" maxlength="500" value="{{ old('notes') }}">
                </div>
                <button type="submit" class="cm-t-btn">Envoyer</button>
            </div>
        </form>
    </div>

    <div class="cm-t-card">
        <h2>Historique des versions</h2>
        <p class="cm-t-aide">Pilote d'abord : testez sur un poste, puis publiez pour tous. « Publier pour tous » sur une ancienne version = retour arrière.</p>
        <div class="cm-t-scroll">
        <table class="cm-t-table">
            <thead><tr><th>Version</th><th>État</th><th>Taille</th><th>Envoyée le</th><th>Actions</th></tr></thead>
            <tbody>
            @forelse ($versions as $v)
                @php [$lib, $couleur] = $etiquettes[$v->statut] ?? [$v->statut, 'gris']; @endphp
                <tr>
                    <td>
                        <strong>{{ $v->version }}</strong>
                        @if ($v->notes) <div style="color:#64748b; font-size:12px;">{{ $v->notes }}</div> @endif
                        <div class="cm-t-mono">{{ $v->sha256 }}</div>
                        @if (!$v->fichierExiste()) <div style="color:#dc2626; font-size:12px; font-weight:600;">Fichier introuvable sur le serveur</div> @endif
                    </td>
                    <td>
                        <span class="cm-t-badge {{ $couleur }}">{{ $lib }}</span>
                        @if ($v->statut === 'pilote')
                            <div style="font-size:12px; color:#64748b; margin-top:4px;">
                                {{ $postes->whereIn('id', $v->pilotes ?? [])->pluck('nom_poste')->join(', ') ?: '—' }}
                            </div>
                        @endif
                    </td>
                    <td>{{ AgentVersion::tailleLisible((int) $v->taille) }}</td>
                    <td>{{ AgentVersion::heure($v->created_at) }}</td>
                    <td>
                        <details style="margin-bottom: 6px;">
                            <summary class="cm-t-btn gris" style="display:inline-block; list-style:none;">Tester sur des postes pilotes…</summary>
                            <form method="POST" action="{{ route('parametres.versions.pilote', $v) }}" style="margin-top:8px;">
                                @csrf
                                @foreach ($postes as $p)
                                    <label style="display:block; font-size:13px;">
                                        <input type="checkbox" name="postes[]" value="{{ $p->id }}" {{ in_array($p->id, $v->pilotes ?? [], true) ? 'checked' : '' }}> {{ $p->nom_poste }}
                                    </label>
                                @endforeach
                                <button type="submit" class="cm-t-btn" style="margin-top:6px;">Publier sur ces postes</button>
                            </form>
                        </details>

                        @if ($v->statut !== 'general')
                            <form method="POST" action="{{ route('parametres.versions.general', $v) }}" class="cm-t-inline"
                                  onsubmit="return confirm('Publier la version {{ $v->version }} pour TOUS les postes ?')">
                                @csrf
                                <button type="submit" class="cm-t-btn vert">{{ $v->statut === 'archivee' ? 'Rétablir (retour arrière)' : 'Publier pour tous' }}</button>
                            </form>
                        @endif

                        @if (in_array($v->statut, ['pilote', 'general'], true))
                            <form method="POST" action="{{ route('parametres.versions.depublier', $v) }}" class="cm-t-inline"
                                  onsubmit="return confirm('Retirer cette version ?')">
                                @csrf
                                <button type="submit" class="cm-t-btn gris">Retirer</button>
                            </form>
                        @else
                            <form method="POST" action="{{ route('parametres.versions.supprimer', $v) }}" class="cm-t-inline"
                                  onsubmit="return confirm('Supprimer définitivement la version {{ $v->version }} ?')">
                                @csrf @method('DELETE')
                                <button type="submit" class="cm-t-btn rouge">Supprimer</button>
                            </form>
                        @endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="5" style="color:#94a3b8;">Aucune version. Envoyez un premier paquet ci-dessus.</td></tr>
            @endforelse
            </tbody>
        </table>
        </div>
    </div>

    <div class="cm-t-card">
        <h2>Derniers résultats de mise à jour</h2>
        <p class="cm-t-aide">Les 30 derniers retours envoyés par les postes.</p>
        <div class="cm-t-scroll">
        <table class="cm-t-table">
            <thead><tr><th>Heure</th><th>Poste</th><th>Version</th><th>Résultat</th><th>Message</th></tr></thead>
            <tbody>
            @forelse ($historique as $h)
                <tr>
                    <td>{{ AgentVersion::heure($h->created_at, 'd/m/Y H:i:s') }}</td>
                    <td>{{ $h->poste->nom_poste ?? '—' }}</td>
                    <td>{{ $h->version }}</td>
                    <td><span class="cm-t-badge {{ $h->succes ? 'vert' : 'rouge' }}">{{ $h->succes ? 'Réussie' : 'Échouée' }}</span></td>
                    <td style="color:#64748b;">{{ $h->message }}</td>
                </tr>
            @empty
                <tr><td colspan="5" style="color:#94a3b8;">Aucune mise à jour effectuée pour l'instant.</td></tr>
            @endforelse
            </tbody>
        </table>
        </div>
    </div>
</div>
@endsection