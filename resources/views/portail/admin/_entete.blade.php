<div class="flex flex-col gap-4 xl:flex-row xl:items-start xl:justify-between">
    <div class="min-w-0">
        <div class="flex flex-wrap items-center gap-2">
            <h1 class="text-2xl font-semibold tracking-tight text-slate-900">Portail Captif Wi-Fi</h1>

            @if ($routeur['connecte'])
                <span class="inline-flex items-center gap-1.5 rounded-full border border-emerald-200 bg-emerald-50 px-3 py-1 text-xs font-semibold text-emerald-800">
                    <span class="h-2 w-2 rounded-full bg-emerald-500"></span>
                    MikroTik connecté{{ $routeur['identity'] ? ' · ' . $routeur['identity'] : '' }}{{ $routeur['version'] ? ' · RouterOS ' . $routeur['version'] : '' }}
                </span>
            @else
                <span class="inline-flex items-center gap-1.5 rounded-full border border-rose-200 bg-rose-50 px-3 py-1 text-xs font-semibold text-rose-800"
                      title="{{ $routeur['erreur'] }}">
                    <span class="h-2 w-2 rounded-full bg-rose-500"></span>
                    MikroTik injoignable
                </span>
            @endif

            <span x-show="dirty" x-cloak class="inline-flex items-center gap-1.5 rounded-full border border-amber-300 bg-amber-50 px-2.5 py-1 text-xs font-semibold text-amber-800">
                <span class="h-1.5 w-1.5 animate-pulse rounded-full bg-amber-500"></span> Pas encore enregistré
            </span>
        </div>
        <p class="mt-1 text-sm text-slate-500">La page que vos clients voient quand ils se connectent au Wi-Fi.</p>
    </div>

    <div class="flex flex-wrap items-center gap-2">
        <a href="{{ route('portail.login') }}" target="_blank" rel="noopener" class="{{ $bouton2 }}">
            <x-icone nom="external-link" class="h-4 w-4" /> Ouvrir la page client
        </a>

        <button type="button" x-show="dirty" x-cloak @click="annuler()" class="{{ $bouton2 }}">Annuler les changements</button>

        <button type="submit" :disabled="envoi" class="{{ $bouton2 }}"
                title="Les clients voient les nouveaux textes et couleurs tout de suite, même si le routeur est éteint.">
            <x-icone nom="check" class="h-4 w-4" /> Enregistrer
        </button>

        <button type="submit" name="diffuser" value="1" :disabled="envoi"
                class="inline-flex items-center justify-center gap-2 rounded-xl bg-violet-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-violet-700 focus:outline-none focus-visible:ring-2 focus-visible:ring-violet-500 focus-visible:ring-offset-2 disabled:cursor-wait disabled:opacity-60"
                title="Enregistre, puis met à jour l'adresse du portail dans le routeur. Les clients connectés ne sont pas coupés.">
            <x-icone nom="upload-cloud" class="h-4 w-4" />
            <span x-text="envoi ? 'Envoi en cours…' : 'Enregistrer et diffuser au routeur'">Enregistrer et diffuser au routeur</span>
        </button>
    </div>
</div>