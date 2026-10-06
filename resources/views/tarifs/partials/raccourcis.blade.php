@php
    $erreurCle = "raccourci_tarif_{$tarif->id}";
    $erreurRaccourci = $errors->first($erreurCle);

    // Précalcul pour JS
    $raccourcisPourJs = $tarif->raccourcis->map(fn ($r) => [
        'id' => $r->id,
        'montant' => $r->montant,
        'duree' => $r->duree,
        'libelle' => $r->libelle,
        'url_supprimer' => route('tarifs.raccourcis.destroy', [$tarif, $r]),
    ])->values()->all();

    // Config complète, envoyée en un seul @js() → pas de guillemets cassants
    $configJs = [
        'erreurInitiale' => $erreurRaccourci,
        'data' => $raccourcisPourJs,
    ];
@endphp

<div class="rounded-xl border border-slate-200 bg-white shadow-sm"
     x-data="raccourcisBlock({{ Illuminate\Support\Js::from($configJs) }})">

    {{-- En-tête --}}
    <div class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-100 px-5 py-3">
        <div>
            <h2 class="text-base font-semibold text-slate-900">{{ $titre }}</h2>
            <p class="text-xs text-slate-500">
                Boutons rapides pour le personnel. Le montant est fixe ; la durée se recalcule automatiquement selon le prix/minute.
            </p>
        </div>
        <button type="button" @click="ouvrirAjout = true; erreurAjout = ''"
                class="inline-flex h-8 items-center gap-1.5 rounded-lg bg-slate-900 px-3 text-xs font-medium text-white hover:bg-slate-700">
            <x-icone nom="plus" class="h-3.5 w-3.5" /> Ajouter
        </button>
    </div>

    {{-- Tableau --}}
    @if($tarif->raccourcis->isEmpty())
        <div class="px-5 py-8 text-center text-sm text-slate-400">
            Aucun raccourci. Ajoutez-en un pour accélérer la saisie.
        </div>
    @else
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                    <tr>
                        <th class="px-4 py-2.5">
                            <button type="button" @click="trier('montant')"
                                    class="inline-flex items-center gap-1 hover:text-slate-900">
                                Montant
                                <span class="text-slate-400" x-show="cleTri !== 'montant'">↕</span>
                                <span class="text-slate-900" x-show="cleTri === 'montant'"
                                      x-text="sensTri === 'asc' ? '↑' : '↓'"></span>
                            </button>
                        </th>
                        <th class="px-4 py-2.5">
                            <button type="button" @click="trier('duree')"
                                    class="inline-flex items-center gap-1 hover:text-slate-900">
                                Durée
                                <span class="text-slate-400" x-show="cleTri !== 'duree'">↕</span>
                                <span class="text-slate-900" x-show="cleTri === 'duree'"
                                      x-text="sensTri === 'asc' ? '↑' : '↓'"></span>
                            </button>
                        </th>
                        <th class="px-4 py-2.5">Libellé</th>
                        <th class="px-4 py-2.5 text-right"></th>
                    </tr>
                </thead>
                <tbody>
                    <template x-for="r in raccourcisTries" :key="r.id">
                        <tr class="border-t border-slate-100">
                            <td class="px-4 py-2.5 font-mono font-medium text-slate-900"
                                x-text="formatAr(r.montant)"></td>
                            <td class="px-4 py-2.5 text-slate-700">
                                <span x-text="formatDuree(r.duree)"></span>
                                <span class="ml-1 text-xs text-slate-400"
                                      x-text="'(' + r.duree + ' min)'"></span>
                            </td>
                            <td class="px-4 py-2.5 text-slate-500" x-text="r.libelle || '—'"></td>
                            <td class="px-4 py-2.5 text-right">
                                <button type="button" @click="demanderSuppression(r)"
                                        class="rounded p-1 text-rose-600 hover:bg-rose-50"
                                        title="Supprimer ce raccourci">
                                    <x-icone nom="trash" class="h-4 w-4" />
                                </button>
                            </td>
                        </tr>
                    </template>
                </tbody>
            </table>
        </div>
    @endif

    {{-- ═══ Modale Ajouter ═══ --}}
    <div x-show="ouvrirAjout" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4">
        <div class="absolute inset-0 bg-slate-900/50" @click="ouvrirAjout = false" x-transition.opacity></div>
        <div x-show="ouvrirAjout" x-transition
             class="relative w-full max-w-md overflow-hidden rounded-2xl bg-white shadow-xl">
            <div class="border-b border-slate-100 px-5 py-4">
                <h2 class="text-base font-semibold text-slate-900">Ajouter un raccourci</h2>
                <p class="text-xs text-slate-500">{{ $titre }}</p>
            </div>

            <form action="{{ route('tarifs.raccourcis.store', $tarif) }}" method="POST"
                  class="space-y-4 p-5" @submit="erreurAjout = ''">
                @csrf

                <template x-if="erreurInitiale && !erreurAjout">
                    <div class="flex items-start gap-2 rounded-lg border border-rose-200 bg-rose-50 px-3 py-2 text-sm text-rose-800">
                        <x-icone nom="warning" class="mt-0.5 h-4 w-4 shrink-0" />
                        <p x-text="erreurInitiale"></p>
                    </div>
                </template>

                <template x-if="erreurAjout">
                    <div class="flex items-start gap-2 rounded-lg border border-rose-200 bg-rose-50 px-3 py-2 text-sm text-rose-800">
                        <x-icone nom="warning" class="mt-0.5 h-4 w-4 shrink-0" />
                        <p x-text="erreurAjout"></p>
                    </div>
                </template>

                <div>
                    <label class="mb-1.5 block text-sm font-medium text-slate-700">Mode de saisie</label>
                    <div class="inline-flex rounded-lg border border-slate-200 bg-slate-100 p-1 text-sm">
                        <button type="button" @click="mode = 'montant'"
                                :class="mode === 'montant' ? 'bg-slate-900 text-white shadow-sm' : 'text-slate-600 hover:bg-white'"
                                class="rounded-md px-3 py-1.5 font-medium transition">
                            Par montant
                        </button>
                        <button type="button" @click="mode = 'duree'"
                                :class="mode === 'duree' ? 'bg-slate-900 text-white shadow-sm' : 'text-slate-600 hover:bg-white'"
                                class="rounded-md px-3 py-1.5 font-medium transition">
                            Par durée
                        </button>
                    </div>
                    <input type="hidden" name="mode" value="montant" x-model="mode">
                </div>

                <template x-if="mode === 'montant'">
                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-slate-700">Montant (Ar)</label>
                        <input type="number" name="montant" min="1" max="1000000"
                               class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2.5 font-mono text-sm"
                               placeholder="Ex : 500">
                        <p class="mt-1 text-xs text-slate-500">La durée sera calculée selon le tarif.</p>
                    </div>
                </template>

                <template x-if="mode === 'duree'">
                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-slate-700">Durée</label>
                        <div class="flex items-center gap-2">
                            <input type="number" name="h" min="0" max="999" placeholder="h"
                                   class="w-20 rounded-lg border border-slate-300 px-3 py-2.5 text-center font-mono text-sm">
                            <span class="text-slate-500">h</span>
                            <input type="number" name="m" min="0" max="59" placeholder="min"
                                   class="w-20 rounded-lg border border-slate-300 px-3 py-2.5 text-center font-mono text-sm">
                            <span class="text-slate-500">min</span>
                            <input type="number" name="s" min="0" max="59" placeholder="s"
                                   class="w-20 rounded-lg border border-slate-300 px-3 py-2.5 text-center font-mono text-sm">
                            <span class="text-slate-500">s</span>
                        </div>
                        <p class="mt-1 text-xs text-slate-500">Le montant sera calculé selon le tarif.</p>
                    </div>
                </template>

                <div>
                    <label class="mb-1.5 block text-sm font-medium text-slate-700">Libellé (optionnel)</label>
                    <input type="text" name="libelle" maxlength="100"
                           class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm"
                           placeholder="Ex : Forfait 30 min">
                </div>

                <div class="flex items-center justify-end gap-3 border-t border-slate-100 pt-4">
                    <button type="button" @click="ouvrirAjout = false"
                            class="inline-flex h-10 items-center rounded-lg px-4 text-sm font-medium text-slate-600 hover:bg-slate-100">
                        Annuler
                    </button>
                    <button type="submit"
                            class="inline-flex h-10 items-center rounded-lg bg-slate-900 px-5 text-sm font-medium text-white hover:bg-slate-700">
                        Ajouter
                    </button>
                </div>
            </form>
        </div>
    </div>

    {{-- ═══ Modale Suppression ═══ --}}
    <div x-show="suppressionOuverte" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4">
        <div class="absolute inset-0 bg-slate-900/50" @click="suppressionOuverte = false" x-transition.opacity></div>
        <div x-show="suppressionOuverte" x-transition
             class="relative w-full max-w-md overflow-hidden rounded-2xl bg-white shadow-xl">
            <div class="flex items-start gap-3 border-b border-slate-100 px-6 pt-5 pb-4">
                <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-rose-100">
                    <x-icone nom="trash" class="h-5 w-5 text-rose-600" />
                </span>
                <div>
                    <h2 class="text-base font-semibold text-slate-900">Supprimer ce raccourci ?</h2>
                    <p class="text-sm text-slate-500" x-text="raccourciASupprimer
                        ? formatAr(raccourciASupprimer.montant) + ' → ' + formatDuree(raccourciASupprimer.duree)
                        : ''"></p>
                </div>
            </div>

            <div class="px-6 py-4">
                <p class="rounded-lg bg-slate-50 px-3 py-2 text-xs text-slate-500">
                    Cette action est <strong class="text-slate-700">irréversible</strong>. Le raccourci sera retiré du tableau.
                </p>
            </div>

            <div class="flex items-center justify-end gap-2 border-t border-slate-100 bg-slate-50 px-6 py-3">
                <button type="button" @click="suppressionOuverte = false"
                        class="inline-flex h-9 items-center rounded-lg border border-slate-200 bg-white px-4 text-sm font-medium text-slate-700 hover:bg-slate-50">
                    Annuler
                </button>

                <form :action="urlSuppression" method="POST">
                    @csrf
                    @method('DELETE')
                    <button type="submit"
                            class="inline-flex h-9 items-center gap-2 rounded-lg bg-rose-600 px-4 text-sm font-medium text-white hover:bg-rose-500">
                        <x-icone nom="trash" class="h-4 w-4" />
                        Supprimer
                    </button>
                </form>
            </div>
        </div>
    </div>

</div>

<script>
function raccourcisBlock(cfg) {
    return {
        raccourcis: cfg.data ?? [],
        cleTri: 'montant',
        sensTri: 'asc',

        ouvrirAjout: false,
        mode: 'montant',
        erreurAjout: '',
        erreurInitiale: cfg.erreurInitiale || '',

        suppressionOuverte: false,
        raccourciASupprimer: null,
        urlSuppression: '',

        get raccourcisTries() {
            const arr = [...this.raccourcis];
            const cle = this.cleTri;
            const sens = this.sensTri === 'asc' ? 1 : -1;

            return arr.sort((a, b) => {
                const va = cle === 'montant' ? a.montant : a.duree;
                const vb = cle === 'montant' ? b.montant : b.duree;
                if (va === vb) return a.montant - b.montant;
                return (va - vb) * sens;
            });
        },

        trier(cle) {
            if (this.cleTri === cle) {
                this.sensTri = this.sensTri === 'asc' ? 'desc' : 'asc';
            } else {
                this.cleTri = cle;
                this.sensTri = 'asc';
            }
        },

        demanderSuppression(r) {
            this.raccourciASupprimer = r;
            this.urlSuppression = r.url_supprimer;
            this.suppressionOuverte = true;
        },

        formatAr(n) {
            return Number(n).toLocaleString('fr-FR') + ' Ar';
        },

        formatDuree(m) {
            m = parseInt(m, 10) || 0;
            if (m < 60) return m + ' min';
            const h = Math.floor(m / 60);
            const r = m % 60;
            return r === 0 ? h + ' h' : h + ' h ' + String(r).padStart(2, '0') + ' min';
        },
    };
}
</script>