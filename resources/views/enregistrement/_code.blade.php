@php
    $commande = $nomExe . ' /silent --server=' . $urlServeur . ' --code=' . $code;
@endphp

<section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
    <div class="flex flex-col gap-5 lg:flex-row lg:items-center lg:justify-between">

        <div class="min-w-0 space-y-2">
            <p class="flex items-center gap-2 text-xs font-bold uppercase tracking-wider text-violet-700">
                <x-icone nom="key" class="h-4 w-4" /> Code d'installation
            </p>
            <p class="select-all font-mono text-3xl font-extrabold tracking-[0.2em] text-violet-700 sm:text-4xl">{{ $code }}</p>
            <p class="max-w-xl text-sm text-slate-600">
                À taper sur un <strong>nouveau</strong> poste quand le programme d'installation le demande.
                Il empêche un appareil quelconque du réseau (un client en Wi-Fi, par exemple) de s'enregistrer.
                Les postes déjà enregistrés n'en ont pas besoin.
            </p>
            <p class="text-xs text-slate-500">Ne montrez pas ce code aux clients.</p>
        </div>

        <div class="flex flex-col gap-2 sm:flex-row lg:flex-col">
            <button type="button" @click="cmCopy(@js($code))"
                    class="inline-flex items-center justify-center gap-2 rounded-xl bg-violet-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-violet-700 focus:outline-none focus-visible:ring-2 focus-visible:ring-violet-500 focus-visible:ring-offset-2">
                <x-icone nom="copy" class="h-4 w-4" /> Copier le code
            </button>

            <form method="POST" action="{{ route('enregistrement.code') }}"
                  @submit="if (! confirm(@js('Créer un nouveau code ? L\'ancien ne permettra plus d\'ajouter de nouveaux postes. Les postes déjà enregistrés ne sont pas touchés.'))) $event.preventDefault()">
                @csrf
                <button type="submit"
                        class="inline-flex w-full items-center justify-center gap-2 rounded-xl border border-slate-300 bg-white px-4 py-2.5 text-sm font-semibold text-slate-800 shadow-sm transition hover:bg-slate-50 focus:outline-none focus-visible:ring-2 focus-visible:ring-violet-500">
                    <x-icone nom="refresh" class="h-4 w-4" /> Créer un nouveau code
                </button>
            </form>
        </div>
    </div>

    <p class="mt-4 rounded-lg bg-slate-50 px-3 py-2 text-sm text-slate-600">
        Pas encore de fichier d'installation ? Il se télécharge dans l'onglet
        <a href="{{ route('parametres.installation.index') }}" class="font-semibold text-violet-700 underline hover:no-underline">Installation</a>.
    </p>

    <details class="mt-4 rounded-xl border border-slate-200">
        <summary class="cursor-pointer px-4 py-3 text-sm font-semibold text-slate-700">
            Installation en série <span class="font-normal text-slate-500">(pour votre technicien)</span>
        </summary>
        <div class="space-y-3 border-t border-slate-100 px-4 py-4 text-sm text-slate-600">
            <p>Pour installer sans écran de saisie, lancez le programme avec cette commande :</p>
            <div class="flex items-start gap-2 rounded-xl bg-slate-900 p-3">
                <pre class="min-w-0 flex-1 overflow-x-auto font-mono text-xs text-slate-100">{{ $commande }}</pre>
                <button type="button" @click="cmCopy(@js($commande))" aria-label="Copier la commande"
                        class="shrink-0 rounded-lg p-1.5 text-slate-300 hover:bg-white/10 hover:text-white">
                    <x-icone nom="copy" class="h-4 w-4" />
                </button>
            </div>
            @if (count($ips) > 1)
                <p class="rounded-lg bg-amber-50 px-3 py-2 text-amber-800">
                    Cet ordinateur a plusieurs adresses ({{ implode(' · ', $ips) }}).
                    Vérifiez que celle de la commande est bien celle du réseau des postes.
                </p>
            @endif
        </div>
    </details>
</section>