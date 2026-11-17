<section id="fichiers" class="space-y-3">
    <div>
        <h2 class="text-lg font-bold text-slate-900">Fichiers de l'installation</h2>
        <p class="text-sm text-slate-500">
            Pour installer un poste, vous n'avez besoin que de l'installateur : les autres fichiers sont récupérés tout seuls pendant l'installation.
            Chaque fichier reste téléchargeable ici si vous devez dépanner un poste à la main.
        </p>
    </div>

    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 2xl:grid-cols-4">
        @foreach ($fichiers as $f)
            <x-parametres.carte-fichier :f="$f">
                @if ($f['telechargeable'])
                    <a href="{{ $f['url'] }}"
                       class="inline-flex w-full items-center justify-center gap-2 rounded-xl bg-violet-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-violet-700 focus:outline-none focus-visible:ring-2 focus-visible:ring-violet-500 focus-visible:ring-offset-2">
                        <x-icone nom="download" classe="h-4 w-4" /> Télécharger
                    </a>
                @elseif ($f['cle'] === 'installateur' && $f['present'] && !$zipOk)
                    <p class="text-xs font-medium text-amber-800">
                        Téléchargement impossible : l'extension PHP « zip » n'est pas activée. Prévenez votre technicien.
                    </p>
                @else
                    <span class="inline-flex w-full cursor-not-allowed items-center justify-center gap-2 rounded-xl bg-slate-100 px-4 py-2.5 text-sm font-semibold text-slate-400">
                        <x-icone nom="download" classe="h-4 w-4" /> Indisponible
                    </span>
                @endif
            </x-parametres.carte-fichier>
        @endforeach
    </div>
</section>