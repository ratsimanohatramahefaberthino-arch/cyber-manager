<details class="group rounded-2xl border border-slate-200 bg-white shadow-sm">
    <summary class="flex cursor-pointer list-none items-center justify-between gap-3 px-5 py-4 text-sm font-semibold text-slate-800 sm:px-6">
        <span>Dépannage et options avancées <span class="font-normal text-slate-500">(pour votre technicien)</span></span>
        <x-icone nom="chevron" class="h-4 w-4 shrink-0 text-slate-400 transition-transform group-open:rotate-180" />
    </summary>

    <div class="space-y-6 border-t border-slate-100 px-5 py-5 text-sm text-slate-700 sm:px-6">

        <div class="space-y-2">
            <h3 class="font-bold text-slate-900">Préparer le serveur (une seule fois)</h3>
            <p class="text-slate-600">
                Dans PowerShell, <strong>en administrateur</strong>, sur le PC serveur. Cela ouvre le port {{ $port }} dans le pare-feu
                (qui reste actif), démarre Apache et MySQL avec Windows et programme une sauvegarde de la base chaque nuit à 03:00.
            </p>
            @php $cmdServeur = 'powershell -ExecutionPolicy Bypass -File scripts\\preparer-serveur.ps1 -Port ' . $port; @endphp
            <div class="flex items-start gap-2 rounded-xl bg-slate-900 p-3">
                <pre class="min-w-0 flex-1 overflow-x-auto font-mono text-xs text-slate-100">{{ $cmdServeur }}</pre>
                <button type="button" @click="cmCopy(@js($cmdServeur))" aria-label="Copier la commande"
                        class="shrink-0 rounded-lg p-1.5 text-slate-300 hover:bg-white/10 hover:text-white">
                    <x-icone nom="copy" class="h-4 w-4" />
                </button>
            </div>
        </div>

        <div class="space-y-2">
            <h3 class="font-bold text-slate-900">Installation en série (mode silencieux)</h3>
            <div class="rounded-xl bg-slate-900 p-3">
                <pre class="overflow-x-auto font-mono text-xs text-slate-100">{{ $nomExe }} /silent --server={{ $url }} --code=XXXX-XXXX [--nom="Poste 5"] [--sans-compte]</pre>
            </div>
            <p class="text-slate-600">
                Sans <code class="rounded bg-slate-100 px-1 text-xs">--nom</code>, le serveur attribue « Poste 1 », « Poste 2 »…
                Journal : <code class="rounded bg-slate-100 px-1 text-xs">C:\ProgramData\CyberManager\logs\installateur.log</code>
            </p>
        </div>

        <div class="space-y-2">
            <h3 class="font-bold text-slate-900">Un fichier est « Manquant » ?</h3>
            <p class="text-slate-600">
                Lancez <code class="rounded bg-slate-100 px-1 text-xs">fabriquer.ps1</code> sur le PC du développeur pour fabriquer
                l'installateur et le gardien, puis copiez-les dans <code class="rounded bg-slate-100 px-1 text-xs">storage/app/private/installateur/</code>.
                La version de l'écran se publie depuis l'onglet Versions. Ces fichiers ne sont jamais accessibles sans le code d'installation.
            </p>
        </div>
    </div>
</details>