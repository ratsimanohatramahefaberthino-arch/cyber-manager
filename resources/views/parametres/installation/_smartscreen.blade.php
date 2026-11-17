<section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
    <div class="flex items-start gap-4">
        <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-slate-100 text-slate-600">
            <x-icone nom="shield" class="h-5 w-5" />
        </span>
        <div class="min-w-0 flex-1 space-y-3">
            <div>
                <h2 class="text-base font-bold text-slate-900">Windows ou l'antivirus affiche un avertissement ?</h2>
                <p class="mt-1 text-sm text-slate-600">
                    C'est normal : le programme n'a pas de certificat payant, donc Windows ne le reconnaît pas encore.
                    Voici quoi faire sur le poste, selon le message :
                </p>
            </div>

            <ol class="space-y-3 rounded-xl bg-slate-50 p-4 text-sm text-slate-700">
                <li class="flex gap-3">
                    <span class="flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-violet-100 text-xs font-bold text-violet-700">1</span>
                    <span><strong>« Windows a protégé votre ordinateur »</strong> : cliquez sur <strong>Informations complémentaires</strong>, puis sur <strong>Exécuter quand même</strong>.</span>
                </li>
                <li class="flex gap-3">
                    <span class="flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-violet-100 text-xs font-bold text-violet-700">2</span>
                    <span><strong>L'antivirus bloque le programme</strong> : ajoutez le dossier <code class="rounded bg-white px-1 text-xs">C:\Program Files\CyberManager</code> à ses exclusions, puis relancez l'installateur.</span>
                </li>
                <li class="flex gap-3">
                    <span class="flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-violet-100 text-xs font-bold text-violet-700">3</span>
                    <span><strong>Le fichier ne s'ouvre pas après la copie</strong> : clic droit sur le fichier .exe, <strong>Propriétés</strong>, cochez <strong>Débloquer</strong>, puis validez.</span>
                </li>
            </ol>
        </div>
    </div>
</section>