@extends('layouts.cyber-manager', ['title' => 'Enregistrement des postes — Cyber Manager'])

@section('content')
<style>
    .cm-e-wrap { max-width: 1100px; margin: 0 auto; }
    .cm-e-card { background: #fff; border: 1px solid #e2e8f0; border-radius: 16px; padding: 24px; box-shadow: 0 1px 2px rgba(0,0,0,.04); margin-bottom: 24px; }
    .cm-e-card h2 { font-size: 16px; font-weight: 700; color: #0f172a; margin-bottom: 4px; }
    .cm-e-aide { font-size: 12px; color: #64748b; margin-bottom: 16px; }
    .cm-e-code { font-family: Consolas, monospace; font-size: 34px; font-weight: 800; letter-spacing: 6px; color: #4338ca; background: #eef2ff; border: 1px solid #c7d2fe; border-radius: 12px; padding: 12px 22px; display: inline-block; user-select: all; }
    .cm-e-btn { border: 0; border-radius: 10px; padding: 9px 16px; font-size: 13px; font-weight: 700; color: #fff; background: #4f46e5; cursor: pointer; }
    .cm-e-btn:hover { background: #4338ca; }
    .cm-e-btn-gris { background: #fff; color: #334155; border: 1px solid #cbd5e1; }
    .cm-e-btn-gris:hover { background: #f1f5f9; }
    .cm-e-btn-rouge { background: #dc2626; }
    .cm-e-btn-rouge:hover { background: #b91c1c; }
    .cm-e-ok { background: #ecfdf5; border: 1px solid #a7f3d0; color: #047857; padding: 10px 14px; border-radius: 10px; font-size: 14px; margin-bottom: 16px; }
    .cm-e-err { background: #fef2f2; border: 1px solid #fecaca; color: #b91c1c; padding: 10px 14px; border-radius: 10px; font-size: 13px; margin-bottom: 16px; }
    .cm-e-alerte { background: #fef2f2; border: 1px solid #fecaca; border-radius: 12px; padding: 16px 18px; margin-bottom: 16px; }
    .cm-e-alerte h3 { color: #b91c1c; font-size: 15px; font-weight: 700; margin-bottom: 4px; }
    .cm-e-alerte p { color: #7f1d1d; font-size: 13px; margin-bottom: 10px; line-height: 1.5; }
    .cm-e-cmd { font-family: Consolas, monospace; font-size: 13px; background: #0f172a; color: #e2e8f0; border-radius: 10px; padding: 12px 14px; word-break: break-all; user-select: all; }
    .cm-e-warn { background: #fffbeb; border: 1px solid #fde68a; color: #92400e; padding: 10px 14px; border-radius: 10px; font-size: 13px; margin-top: 12px; line-height: 1.5; }
    .cm-e-table { width: 100%; border-collapse: collapse; font-size: 13px; }
    .cm-e-table th { text-align: left; font-size: 11px; text-transform: uppercase; letter-spacing: .5px; color: #64748b; padding: 8px 10px; border-bottom: 1px solid #e2e8f0; }
    .cm-e-table td { padding: 10px; border-bottom: 1px solid #f1f5f9; vertical-align: middle; }
    .cm-e-ligne { display: flex; gap: 8px; align-items: center; }
    .cm-e-ligne input[type=text] { border: 1px solid #cbd5e1; border-radius: 8px; padding: 6px 10px; font-size: 13px; width: 180px; }
    .cm-e-badge { display: inline-block; border-radius: 999px; padding: 2px 10px; font-size: 11px; font-weight: 600; background: #f1f5f9; color: #475569; }
    .cm-e-badge-rouge { background: #fee2e2; color: #b91c1c; }
</style>

@php
    $fuseau = 'Indian/Antananarivo';
    $libelles = [
        'disponible' => 'Disponible', 'occupe' => 'Occupé', 'suspendu' => 'Suspendu',
        'verrouille' => 'Verrouillé', 'desactive' => 'Désactivé', 'hors_ligne' => 'Hors ligne',
    ];
    $hoteLocal = in_array(request()->getHost(), ['localhost', '127.0.0.1', '::1', '[::1]'], true);
    $conflits = $postes->filter(fn ($p) => $p->aConflitIdentite());
@endphp

<div class="cm-e-wrap">
    <div style="margin-bottom: 18px;">
        <h1 class="text-2xl font-semibold tracking-tight text-slate-900">Enregistrement des postes</h1>
        <p class="text-sm text-slate-500">Code d'installation, noms des postes et alertes d'identité.</p>
    </div>

    @if (session('success')) <div class="cm-e-ok">{{ session('success') }}</div> @endif
    @if (session('error')) <div class="cm-e-err">{{ session('error') }}</div> @endif
    @if ($errors->any())
        <div class="cm-e-err">@foreach ($errors->all() as $e) <div>• {{ $e }}</div> @endforeach</div>
    @endif

    {{-- Conflits d'identité (PC clonés) --}}
    @foreach ($conflits as $p)
        <div class="cm-e-alerte">
            <h3>⚠ Conflit d'identité : « {{ $p->nom_poste }} »</h3>
            <p>
                Deux ordinateurs semblent utiliser l'identité de ce poste (signalé le
                {{ $p->conflit_identite_at->timezone($fuseau)->format('d/m/Y à H:i:s') }}).
                C'est typique d'un disque cloné : le programme a été copié sur un autre PC avec sa configuration.
                <br><strong>Séparer</strong> : l'ordinateur en trop reçoit une nouvelle identité et apparaît comme un nouveau poste (à renommer).
                Si la séparation ne marche pas, sur l'ordinateur en trop supprimez le dossier
                <code>%LocalAppData%\CyberManagerAgent</code> puis relancez l'agent avec le code d'installation.
            </p>
            <div class="cm-e-ligne">
                <form method="POST" action="{{ route('enregistrement.separer', $p) }}">
                    @csrf
                    <button type="submit" class="cm-e-btn cm-e-btn-rouge">Séparer les ordinateurs</button>
                </form>
                <form method="POST" action="{{ route('enregistrement.ignorer', $p) }}">
                    @csrf
                    <button type="submit" class="cm-e-btn cm-e-btn-gris">Effacer l'alerte</button>
                </form>
            </div>
        </div>
    @endforeach

    {{-- Code d'installation --}}
    <div class="cm-e-card">
        <h2>Code d'installation</h2>
        <p class="cm-e-aide">Nécessaire pour enregistrer un NOUVEAU poste. Il empêche un appareil quelconque du réseau (un client en Wi-Fi, par exemple) de s'enregistrer. Les postes déjà enregistrés n'en ont pas besoin.</p>

        <div style="margin-bottom: 14px;"><span class="cm-e-code" id="cm-code">{{ $code }}</span></div>

        <div class="cm-e-ligne">
            <button type="button" class="cm-e-btn" onclick="cmCopy('{{ $code }}')">Copier le code</button>
            <form method="POST" action="{{ route('enregistrement.code') }}"
                  onsubmit="return confirm('Générer un nouveau code ? L\'ancien ne permettra plus d\'enregistrer de nouveaux postes. Les postes déjà enregistrés ne sont pas affectés.');">
                @csrf
                <button type="submit" class="cm-e-btn cm-e-btn-gris">Générer un nouveau code</button>
            </form>
        </div>
    </div>

    {{-- Connecter un nouveau poste --}}
    <div class="cm-e-card">
        <h2>Connecter un nouveau poste</h2>
        <p class="cm-e-aide">En attendant l'installateur en un clic (étape 4), lancez l'agent avec ces deux options :</p>
        <div class="cm-e-cmd" id="cm-cmd">--server={{ $urlServeur }} --code={{ $code }}</div>
        <div style="margin-top: 10px;">
            <button type="button" class="cm-e-btn cm-e-btn-gris" onclick="cmCopy(document.getElementById('cm-cmd').innerText)">Copier</button>
        </div>
        @if ($hoteLocal)
            <div class="cm-e-warn">
                Vous avez ouvert cette page avec « {{ request()->getHost() }} » : cette adresse ne fonctionne que sur le PC serveur.
                Pour les autres postes, remplacez-la par l'adresse du PC serveur sur le réseau (par exemple <code>http://192.168.x.x:8000</code>).
            </div>
        @endif
    </div>

    {{-- Liste des postes + renommer --}}
    <div class="cm-e-card">
        <h2>Postes enregistrés</h2>
        <p class="cm-e-aide">Le nouveau nom est envoyé au poste et affiché sur son écran de verrouillage.</p>

        @if ($postes->isEmpty())
            <p class="text-sm text-slate-500">Aucun poste enregistré pour le moment.</p>
        @else
            <div style="overflow-x: auto;">
                <table class="cm-e-table">
                    <thead>
                        <tr>
                            <th>Nom</th><th>État</th><th>Adresse</th><th>Version</th><th>Dernier signe de vie</th><th>Identité</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($postes as $p)
                            <tr>
                                <td>
                                    <form method="POST" action="{{ route('enregistrement.renommer', $p) }}" class="cm-e-ligne">
                                        @csrf
                                        <input type="text" name="nom_poste" value="{{ $p->nom_poste }}" maxlength="100" required>
                                        <button type="submit" class="cm-e-btn cm-e-btn-gris">Renommer</button>
                                    </form>
                                </td>
                                <td><span class="cm-e-badge">{{ $libelles[$p->etatAffiche()] ?? $p->etatAffiche() }}</span></td>
                                <td>{{ $p->adresse_ip ?: '—' }}</td>
                                <td>{{ $p->agent_version ?: '—' }}</td>
                                <td>{{ $p->dernier_heartbeat ? $p->dernier_heartbeat->timezone($fuseau)->format('d/m/Y H:i:s') : '—' }}</td>
                                <td>
                                    @if ($p->aConflitIdentite())
                                        <span class="cm-e-badge cm-e-badge-rouge">Conflit</span>
                                    @else
                                        <span class="cm-e-badge">OK</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
</div>
@endsection