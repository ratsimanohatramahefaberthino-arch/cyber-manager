<section class="space-y-3">
    <h2 class="text-lg font-bold text-slate-900">Installer un poste, pas à pas</h2>

    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 2xl:grid-cols-4">

        {{-- Étape 1 --}}
        <div class="flex flex-col rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <span class="flex h-8 w-8 items-center justify-center rounded-full bg-violet-100 text-sm font-bold text-violet-700">1</span>
            <h3 class="mt-3 text-sm font-bold text-slate-900">Copiez l'installateur sur une clé USB</h3>
            <p class="mt-2 text-xs leading-relaxed text-slate-600">
                Téléchargez le fichier, faites un clic droit dessus puis « Extraire tout ».
                Copiez ensuite le dossier obtenu sur la clé. Ne lancez rien depuis le .zip lui-même.
            </p>
            @if ($peutTelecharger)
                <a href="{{ route('parametres.installation.telecharger') }}"
                   class="mt-auto inline-flex items-center justify-center gap-2 rounded-lg bg-slate-100 px-3 py-2 text-xs font-semibold text-violet-700 transition hover:bg-slate-200">
                    <x-icone nom="download" class="h-4 w-4" /> Télécharger
                </a>
            @endif
        </div>

        {{-- Étape 2 --}}
        <div class="flex flex-col rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <span class="flex h-8 w-8 items-center justify-center rounded-full bg-violet-100 text-sm font-bold text-violet-700">2</span>
            <h3 class="mt-3 text-sm font-bold text-slate-900">Lancez-le sur le nouveau poste</h3>
            <p class="mt-2 text-xs leading-relaxed text-slate-600">
                Branchez la clé sur le poste Windows. Faites un clic droit sur
                <code class="rounded bg-slate-100 px-1.5 py-0.5 text-[11px] font-semibold text-slate-800">{{ $nomExe }}</code>
                puis choisissez <strong>Exécuter en tant qu'administrateur</strong>.
            </p>
        </div>

        {{-- Étape 3 --}}
        <div class="flex flex-col rounded-2xl border-2 border-violet-200 bg-white p-5 shadow-sm">
            <span class="flex h-8 w-8 items-center justify-center rounded-full bg-violet-600 text-sm font-bold text-white">3</span>
            <h3 class="mt-3 text-sm font-bold text-slate-900">Saisissez le code d'installation</h3>
            <p class="mt-2 text-xs leading-relaxed text-slate-600">
                Quand le programme le demande, tapez ce code :
            </p>

            @if ($code)
                <div x-data="{ vu: false }"
                     class="mt-2 flex items-center justify-between gap-2 rounded-xl border border-slate-200 bg-slate-50 px-3 py-2">
                    <span class="min-w-0 truncate font-mono text-base font-bold tracking-widest text-violet-700"
                          x-text="vu ? @js($code) : '••••-••••'"></span>
                    <span class="flex shrink-0 items-center gap-1">
                        <button type="button" @click="vu = !vu" :aria-label="vu ? 'Masquer le code' : 'Afficher le code'"
                                class="rounded-lg p-1.5 text-slate-500 hover:bg-slate-200 hover:text-slate-800">
                            <x-icone nom="eye" class="h-4 w-4" x-show="!vu" />
                            <x-icone nom="eye-off" class="h-4 w-4" x-show="vu" x-cloak />
                        </button>
                        <button type="button" @click="cmCopy(@js($code))" aria-label="Copier le code"
                                class="rounded-lg p-1.5 text-slate-500 hover:bg-slate-200 hover:text-slate-800">
                            <x-icone nom="copy" class="h-4 w-4" />
                        </button>
                    </span>
                </div>
                <p class="mt-2 text-xs text-slate-500">Le même code sert pour tous les postes. Ne le montrez pas aux clients.</p>
            @else
                <a href="{{ route('enregistrement.index') }}"
                   class="mt-2 inline-flex items-center justify-center gap-2 rounded-lg bg-slate-100 px-3 py-2 text-xs font-semibold text-violet-700 hover:bg-slate-200">
                    <x-icone nom="key" class="h-4 w-4" /> Voir le code
                </a>
            @endif

            <a href="{{ route('enregistrement.index') }}" class="mt-auto pt-3 text-xs text-slate-500 underline hover:text-slate-800">
                Changer le code
            </a>
        </div>

        {{-- Étape 4 --}}
        <div class="flex flex-col rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <span class="flex h-8 w-8 items-center justify-center rounded-full bg-emerald-100 text-sm font-bold text-emerald-700">4</span>
            <h3 class="mt-3 text-sm font-bold text-slate-900">Le poste apparaît dans la liste</h3>
            <p class="mt-2 text-xs leading-relaxed text-slate-600">
                Il reçoit automatiquement un nom (« Poste 1 », « Poste 2 »…).
                Vous pourrez le renommer dans l'onglet Enregistrement.
            </p>
            <a href="{{ route('postes.index') }}"
               class="mt-auto inline-flex items-center justify-center gap-2 rounded-lg bg-slate-100 px-3 py-2 text-xs font-semibold text-violet-700 transition hover:bg-slate-200">
                <x-icone nom="desktop" class="h-4 w-4" /> Voir les postes
            </a>
        </div>
    </div>
</section>