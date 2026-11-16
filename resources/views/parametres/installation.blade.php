@extends('layouts.cyber-manager', ['title' => 'Installation — Cyber Manager'])

@section('content')
<div style="max-width: 1100px; margin: 0 auto;">
    <div style="margin-bottom: 18px;">
        <h1 class="text-2xl font-semibold tracking-tight text-slate-900">Paramètres</h1>
        <p class="text-sm text-slate-500">Installer un nouveau poste en quelques clics.</p>
    </div>

    @include('parametres._onglets')

    @if (session('success')) <div class="cm-t-ok">{{ session('success') }}</div> @endif
    @if ($errors->any())
        <div class="cm-t-err">@foreach ($errors->all() as $e) <div>• {{ $e }}</div> @endforeach</div>
    @endif

    <div class="cm-t-card">
        <h2>1. Adresse du serveur sur le réseau</h2>
        <p class="cm-t-aide">Détectée par le serveur lui-même. C'est cette adresse (et non « localhost ») que les postes utiliseront.</p>
        <p style="font-size: 22px; font-weight: 800; font-family: monospace; color: #0f172a;">{{ $url }}</p>
        @if (count($ips) > 1)
            <p class="cm-t-aide" style="margin-top: 8px;">Cet ordinateur a plusieurs adresses : {{ implode(' · ', $ips) }}. Si l'adresse ci-dessus n'est pas celle du réseau des postes, ouvrez cette page depuis un autre PC du réseau en tapant la bonne adresse.</p>
        @endif
        @if ($localhost)
            <p class="cm-t-aide" style="color:#c2410c; margin-top: 8px;">Vous consultez cette page via « localhost » : l'installateur téléchargé contiendra bien l'adresse réseau ci-dessus, pas « localhost ».</p>
        @endif
        <p class="cm-t-aide" style="margin-top: 8px; margin-bottom: 0;">Port utilisé : {{ $port }}. Il doit être ouvert dans le pare-feu du serveur (voir le bloc 5).</p>
    </div>

    <div class="cm-t-card">
        <h2>2. Télécharger l'installateur</h2>
        <p class="cm-t-aide">Un petit fichier .zip : l'installateur + l'adresse du serveur. Copiez-le sur une clé USB.</p>
        <a href="{{ route('parametres.installation.telecharger') }}" class="cm-t-btn">Télécharger l'installateur</a>
    </div>

    <div class="cm-t-card">
        <h2>3. Code d'installation</h2>
        <p class="cm-t-aide">L'installateur le demande : sans lui, un poste ne peut ni télécharger les fichiers ni s'enregistrer.</p>
        <a href="{{ route('enregistrement.index') }}" class="cm-t-btn gris">Voir / régénérer le code</a>
    </div>

    <div class="cm-t-card">
        <h2>4. Fichiers prêts sur le serveur</h2>
        <p class="cm-t-aide">Ces fichiers sont stockés dans un espace privé et servis seulement avec le code d'installation.</p>
        <table class="cm-t-table"><tbody>
            @foreach ($fichiers as $nom => $ok)
                <tr><td>{{ $nom }}</td><td><span class="cm-t-badge {{ $ok ? 'vert' : 'orange' }}">{{ $ok ? 'Présent' : 'Manquant' }}</span></td></tr>
            @endforeach
        </tbody></table>
        <p class="cm-t-aide" style="margin-top: 10px; margin-bottom: 0;">Manquant ? Lancez <code>fabriquer.ps1</code> sur le PC du développeur ; la version de l'Écran s'envoie depuis l'onglet Versions.</p>
    </div>

    <div class="cm-t-card">
        <h2>5. Préparation du serveur (une seule fois)</h2>
        <p class="cm-t-aide">Dans PowerShell, en administrateur, sur le PC serveur :</p>
        <p class="cm-t-mono" style="color:#0f172a;">powershell -ExecutionPolicy Bypass -File scripts\preparer-serveur.ps1 -Port {{ $port }}</p>
        <p class="cm-t-aide" style="margin-top: 8px; margin-bottom: 0;">Il ouvre ce port pour tous les types de réseau (le pare-feu reste actif), démarre Apache et MySQL avec Windows et planifie une sauvegarde de la base chaque nuit à 03:00.</p>
    </div>

    <div class="cm-t-card">
        <h2>6. Si Windows ou l'antivirus affiche un avertissement</h2>
        <p class="cm-t-aide">Le programme n'a pas de certificat payant : c'est normal. Sur le poste :</p>
        <ol style="font-size: 13px; color: #334155; padding-left: 20px; line-height: 1.7;">
            <li>« Windows a protégé votre PC » : cliquer sur <strong>Informations complémentaires</strong>, puis <strong>Exécuter quand même</strong>.</li>
            <li>Antivirus qui bloque : ajouter le dossier <code>C:\Program Files\CyberManager</code> aux exclusions, puis relancer l'installateur.</li>
            <li>Fichier bloqué après copie : clic droit sur le .exe, Propriétés, cocher <strong>Débloquer</strong>.</li>
        </ol>
    </div>

    <div class="cm-t-card">
        <h2>Mode silencieux (déploiement en série)</h2>
        <p class="cm-t-mono" style="color:#0f172a;">CyberManagerInstall.exe /silent --server={{ $url }} --code=XXXX-XXXX [--nom="Poste 5"] [--sans-compte]</p>
        <p class="cm-t-aide" style="margin-top: 8px; margin-bottom: 0;">Sans <code>--nom</code>, le serveur attribue « Poste 1 », « Poste 2 »… Journal : <code>C:\ProgramData\CyberManager\logs\installateur.log</code>.</p>
    </div>
</div>
@endsection