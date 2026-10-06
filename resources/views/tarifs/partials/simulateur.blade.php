@php
    $ciblesDisponibles = [];
    if ($mode === 'unifie' && $unifie) {
        $ciblesDisponibles[] = ['id' => 'unifie', 'label' => 'Ethernet + Wi-Fi'];
    }
    if ($mode === 'separe') {
        if ($ethernet) $ciblesDisponibles[] = ['id' => 'ethernet', 'label' => 'Poste client'];
        if ($wifi)     $ciblesDisponibles[] = ['id' => 'wifi', 'label' => 'Wi-Fi'];
    }
@endphp

<div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm"
     x-data="simulateur({
        cibles: @js($ciblesDisponibles),
        url: '{{ route('tarifs.simuler') }}',
        csrf: '{{ csrf_token() }}',
     })">
    <div class="mb-4 flex items-center gap-2">
        <x-icone nom="chart" class="h-5 w-5 text-slate-400" />
        <div>
            <h2 class="text-base font-semibold text-slate-900">Simulateur</h2>
            <p class="text-xs text-slate-500">Vérifier un montant ou une durée</p>
        </div>
    </div>

    <div class="space-y-4">
        @if(count($ciblesDisponibles) > 1)
            <div>
                <label class="mb-1.5 block text-sm font-medium text-slate-700">Tarif</label>
                <select x-model="cible"
                        class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm">
                    <template x-for="c in cibles" :key="c.id">
                        <option :value="c.id" x-text="c.label"></option>
                    </template>
                </select>
            </div>
        @else
            <input type="hidden" x-model="cible">
        @endif

        <div>
            <label class="mb-1.5 block text-sm font-medium text-slate-700">Mode</label>
            <div class="inline-flex w-full rounded-lg border border-slate-200 bg-slate-50 p-0.5 text-sm">
                <button type="button" @click="mode = 'montant'"
                        :class="mode === 'montant' ? 'bg-white text-slate-900 shadow-sm' : 'text-slate-500'"
                        class="flex-1 rounded-md px-3 py-1.5 font-medium">Montant → Durée</button>
                <button type="button" @click="mode = 'duree'"
                        :class="mode === 'duree' ? 'bg-white text-slate-900 shadow-sm' : 'text-slate-500'"
                        class="flex-1 rounded-md px-3 py-1.5 font-medium">Durée → Montant</button>
            </div>
        </div>

        {{-- Entrée --}}
        <template x-if="mode === 'montant'">
            <div>
                <label class="mb-1.5 block text-sm font-medium text-slate-700">Montant (Ar)</label>
                <input type="number" min="0" x-model.number="entreeMontant"
                       class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2.5 font-mono text-sm">
            </div>
        </template>

        <template x-if="mode === 'duree'">
            <div>
                <label class="mb-1.5 block text-sm font-medium text-slate-700">Durée</label>
                <div class="flex items-center gap-2">
                    <input type="number" min="0" max="999" x-model.number="h" placeholder="h"
                           class="w-16 rounded-lg border border-slate-300 px-2 py-2 text-center font-mono text-sm">
                    <span class="text-sm text-slate-500">h</span>
                    <input type="number" min="0" max="59" x-model.number="m" placeholder="min"
                           class="w-16 rounded-lg border border-slate-300 px-2 py-2 text-center font-mono text-sm">
                    <span class="text-sm text-slate-500">min</span>
                    <input type="number" min="0" max="59" x-model.number="s" placeholder="s"
                           class="w-16 rounded-lg border border-slate-300 px-2 py-2 text-center font-mono text-sm">
                    <span class="text-sm text-slate-500">s</span>
                </div>
            </div>
        </template>

        <button type="button" @click="calculer()" :disabled="chargement"
                class="inline-flex h-10 w-full items-center justify-center gap-2 rounded-lg bg-slate-900 text-sm font-medium text-white hover:bg-slate-700 disabled:opacity-50">
            <span x-show="!chargement">Calculer</span>
            <span x-show="chargement">Calcul…</span>
        </button>

        <p x-show="erreur" x-text="erreur" class="text-sm text-rose-600"></p>

        {{-- Résultat --}}
        <div x-show="resultat" x-cloak class="rounded-lg border border-slate-200 bg-slate-50/60 p-4">
            <template x-if="resultat">
                <div>
                    <ul class="space-y-1.5 text-sm">
                        <template x-for="(e, i) in resultat.etapes" :key="i">
                            <li class="flex items-baseline justify-between gap-3 border-b border-dashed border-slate-200 pb-1.5 last:border-0">
                                <span class="text-slate-500" x-text="e.label"></span>
                                <span class="font-mono font-semibold text-slate-900" x-text="e.valeur"></span>
                            </li>
                        </template>
                    </ul>

                    <div class="mt-3 flex items-center justify-between rounded-lg bg-slate-900 px-3 py-2.5 text-white">
                        <span class="text-sm font-medium">Résultat</span>
                        <span class="font-mono text-base font-bold"
                              x-text="resultat.mode === 'montant'
                                  ? resultat.resultat.lisible
                                  : resultat.resultat.montant.toLocaleString('fr-FR') + ' Ar'"></span>
                    </div>
                </div>
            </template>
        </div>
    </div>
</div>

<script>
function simulateur(cfg) {
    return {
        cibles: cfg.cibles,
        url: cfg.url,
        csrf: cfg.csrf,
        cible: cfg.cibles[0]?.id ?? 'unifie',
        mode: 'montant',
        entreeMontant: 500,
        h: 0, m: 30, s: 0,
        chargement: false,
        resultat: null,
        erreur: '',

        async calculer() {
            this.erreur = '';
            this.resultat = null;

            let entree = 0;

            if (this.mode === 'montant') {
                entree = Math.max(0, parseInt(this.entreeMontant) || 0);
            } else {
                const h = Math.max(0, parseInt(this.h) || 0);
                const m = Math.max(0, Math.min(59, parseInt(this.m) || 0));
                const s = Math.max(0, Math.min(59, parseInt(this.s) || 0));
                entree = h * 60 + m + (s > 0 ? 1 : 0);
            }

            this.chargement = true;

            try {
                const r = await fetch(this.url, {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': this.csrf,
                        'Accept': 'application/json',
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({ cible: this.cible, mode: this.mode, entree }),
                });

                if (!r.ok) throw new Error('HTTP ' + r.status);
                this.resultat = await r.json();
            } catch (e) {
                this.erreur = 'Calcul impossible. Vérifiez la configuration.';
            }

            this.chargement = false;
        },
    };
}
</script>