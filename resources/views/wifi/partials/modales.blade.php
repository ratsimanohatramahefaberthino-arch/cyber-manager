{{-- ═══════════════════════════════════════════════════════════════════
     1. ALERTE « TEMPS ÉCOULÉ » — affichée tant qu'au moins une alarme sonne.
        Non fermable par Échap/clic dehors : l'opérateur doit agir.
        La fenêtre est DÉDUITE de l'état des lignes : elle ne peut plus rester
        ouverte (ni se rouvrir) après « Arrêter l'alarme » ou une prolongation.
     ═══════════════════════════════════════════════════════════════════ --}}
<x-wifi-modal show="fenetresAlarme.length > 0" z="9100" maxw="max-w-md" :fermable="false">
    <x-slot name="header">
        <div class="shrink-0">
            <div class="flex items-center gap-4 bg-rose-600 px-6 py-4">
                <span class="relative flex h-12 w-12 shrink-0 items-center justify-center rounded-full bg-white/20">
                    <span class="absolute inset-0 animate-ping rounded-full bg-white/20"></span>
                    <x-icone nom="bell" class="relative h-6 w-6 text-white" />
                </span>
                <div class="min-w-0">
                    <h2 class="text-xl font-bold text-white">Temps écoulé</h2>
                    <p class="text-sm text-rose-100"
                       x-text="fenetresAlarme.length > 1 ? fenetresAlarme.length + ' alarmes sonnent en ce moment' : 'Le forfait de cet appareil est terminé'"></p>
                </div>
            </div>
            <div x-show="fenetresAlarme.length > 1" x-cloak class="flex gap-1.5 overflow-x-auto border-b border-rose-100 bg-rose-50 px-4 py-2">
                <template x-for="f in fenetresAlarme" :key="f.id">
                    <button type="button" @click="ongletAlarmeActif = f.id"
                            :class="ongletAlarme && ongletAlarme.id === f.id ? 'bg-rose-600 text-white' : 'bg-white text-rose-700 ring-1 ring-inset ring-rose-200 hover:bg-rose-100'"
                            class="shrink-0 rounded-full px-3 py-1 text-sm font-semibold transition" x-text="f.appareil"></button>
                </template>
            </div>
        </div>
    </x-slot>

    <template x-if="ongletAlarme">
        <div class="space-y-5">
            <div class="flex items-center gap-3">
                <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-slate-100 text-slate-500">
                    <x-icone nom="desktop" class="h-6 w-6" />
                </span>
                <div class="min-w-0">
                    <div class="truncate text-lg font-bold text-slate-900" x-text="ongletAlarme.appareil"></div>
                    <div class="truncate font-mono text-xs text-slate-500" x-text="ongletAlarme.ip || ongletAlarme.mac || ''"></div>
                </div>
            </div>

            <dl class="grid grid-cols-2 gap-3 text-sm">
                <div class="rounded-xl bg-slate-50 px-3 py-2.5">
                    <dt class="text-slate-500">Identifiant</dt>
                    <dd class="truncate font-mono font-semibold text-slate-900" x-text="ongletAlarme.voucher ? ongletAlarme.voucher.username : '—'"></dd>
                </div>
                <div class="rounded-xl bg-slate-50 px-3 py-2.5">
                    <dt class="text-slate-500">Forfait</dt>
                    <dd class="font-semibold text-slate-900" x-text="formatDuree(ongletAlarme.alarme.minutes)"></dd>
                </div>
                <div class="rounded-xl bg-slate-50 px-3 py-2.5">
                    <dt class="text-slate-500">Fin prévue</dt>
                    <dd class="font-mono font-semibold text-slate-900" x-text="heureExacte(ongletAlarme.alarme.fin)"></dd>
                </div>
                <div class="rounded-xl bg-rose-50 px-3 py-2.5 ring-1 ring-inset ring-rose-200">
                    <dt class="text-rose-700">Dépassement</dt>
                    <dd class="font-mono font-bold text-rose-700"
                        x-text="'+' + formatDureeSec(depassementSecondes(ongletAlarme)) + ' (≈ ' + formatAr(depassementMontant(ongletAlarme)) + ')'"></dd>
                </div>
            </dl>

            <div>
                <div class="mb-2 text-sm font-semibold text-slate-700">Le client continue ?</div>
                <div class="grid grid-cols-2 gap-2">
                    <template x-for="p in prolongationsRapides" :key="p.label">
                        <button type="button" @click="prolongerRapide(ongletAlarme, p)" :disabled="actionEnCours"
                                class="rounded-lg bg-emerald-600 px-3 py-2.5 text-base font-semibold text-white hover:bg-emerald-500 disabled:opacity-50"
                                x-text="p.label"></button>
                    </template>
                    <button type="button" @click="ouvrirProlonger(ongletAlarme)"
                            class="col-span-2 rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-base font-medium text-slate-700 hover:bg-slate-50">
                        Autre durée ou montant…
                    </button>
                </div>
            </div>
        </div>
    </template>

    <x-slot name="footer">
        <button type="button" @click="acquitter(ongletAlarme)" :disabled="!ongletAlarme"
                class="flex w-full items-center justify-center gap-2 rounded-xl bg-rose-600 px-6 py-3.5 text-lg font-bold text-white shadow-lg transition hover:bg-rose-500 active:scale-[0.98] disabled:opacity-50">
            <x-icone nom="bell-off" class="h-6 w-6" />
            Arrêter l'alarme
        </button>
        <p class="mt-2 text-center text-sm text-slate-500">Le son s'arrête. La session continue et reste dans le tableau.</p>
    </x-slot>
</x-modal>


{{-- ═══════════════════════════════════════════════════════════════════
     2. POSER / MODIFIER UNE ALARME
     ═══════════════════════════════════════════════════════════════════ --}}
<x-wifi-modal show="alarmeModal.ouvert" z="9300" maxw="max-w-md">
    <x-slot name="header">
        <div class="flex shrink-0 items-start justify-between gap-4 border-b border-slate-100 px-6 py-4">
            <div class="min-w-0">
                <h2 class="text-lg font-semibold text-slate-900"
                    x-text="alarmeModal.modifier ? 'Modifier l\'alarme' : 'Poser une alarme'"></h2>
                <p class="mt-0.5 truncate text-sm text-slate-500"
                   x-text="alarmeModal.ligne ? alarmeModal.ligne.appareil : ''"></p>
            </div>
            <button type="button" @click="alarmeModal.ouvert = false" aria-label="Fermer"
                    class="-mr-2 -mt-1 rounded-lg p-1.5 text-slate-400 hover:bg-slate-100 hover:text-slate-600">
                <x-icone nom="x" class="h-5 w-5" />
            </button>
        </div>
    </x-slot>

    <div class="space-y-5">
        <div>
            <label class="mb-2 block text-sm font-semibold text-slate-700">Type de limite</label>
            <div class="grid grid-cols-2 gap-1 rounded-lg bg-slate-100 p-1 text-base">
                <button type="button" @click="alarmeModal.type = 'duree'; majApercu()"
                        :class="alarmeModal.type === 'duree' ? 'bg-white text-slate-900 shadow-sm' : 'text-slate-600'"
                        class="rounded-md px-3 py-2 font-medium">Par durée</button>
                <button type="button" @click="alarmeModal.type = 'montant'; majApercu()"
                        :class="alarmeModal.type === 'montant' ? 'bg-white text-slate-900 shadow-sm' : 'text-slate-600'"
                        class="rounded-md px-3 py-2 font-medium">Par montant</button>
            </div>
        </div>

        <div>
            <label class="mb-2 block text-sm font-semibold text-slate-700"
                   x-text="alarmeModal.type === 'duree' ? 'Durée' : 'Montant (Ar)'"></label>
            <template x-if="alarmeModal.type === 'duree'">
                <div class="flex items-center gap-2">
                    <input type="number" inputmode="numeric" min="0" max="99" x-model.number="alarmeModal.h" @input="majApercu()"
                           class="w-20 rounded-lg border-2 border-slate-300 px-3 py-2.5 text-center font-mono text-lg focus:border-slate-800 focus:outline-none">
                    <span class="text-base text-slate-500">h</span>
                    <input type="number" inputmode="numeric" min="0" max="59" x-model.number="alarmeModal.m" @input="majApercu()"
                           class="w-20 rounded-lg border-2 border-slate-300 px-3 py-2.5 text-center font-mono text-lg focus:border-slate-800 focus:outline-none">
                    <span class="text-base text-slate-500">min</span>
                </div>
            </template>
            <template x-if="alarmeModal.type === 'montant'">
                <input type="number" inputmode="numeric" min="1" x-model.number="alarmeModal.montant" @input="majApercu()"
                       class="w-full rounded-lg border-2 border-slate-300 px-3 py-2.5 font-mono text-lg focus:border-slate-800 focus:outline-none">
            </template>
            <div class="mt-2 flex flex-wrap gap-2">
                <template x-for="r in alarmeModal.raccourcis.filter(x => x.type === alarmeModal.type)" :key="r.label">
                    <button type="button" @click="appliquerRaccourci(r)"
                            class="rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-sm font-medium text-slate-700 hover:bg-slate-50"
                            x-text="r.label"></button>
                </template>
            </div>
        </div>

        <div>
            <label class="mb-2 block text-sm font-semibold text-slate-700">Point de départ</label>
            <div class="space-y-2">
                <label class="flex cursor-pointer items-start gap-3 rounded-lg border-2 p-3 hover:bg-slate-50"
                       :class="alarmeModal.depart === 'debut' ? 'border-slate-900 bg-slate-50' : 'border-slate-200'">
                    <input type="radio" x-model="alarmeModal.depart" value="debut" @change="majApercu()" class="mt-1">
                    <div>
                        <div class="text-base font-semibold text-slate-900">Depuis le début de la connexion</div>
                        <div class="text-sm text-slate-500">Tient compte de ce qui est déjà consommé.</div>
                    </div>
                </label>
                <label class="flex cursor-pointer items-start gap-3 rounded-lg border-2 p-3 hover:bg-slate-50"
                       :class="alarmeModal.depart === 'maintenant' ? 'border-slate-900 bg-slate-50' : 'border-slate-200'">
                    <input type="radio" x-model="alarmeModal.depart" value="maintenant" @change="majApercu()" class="mt-1">
                    <div>
                        <div class="text-base font-semibold text-slate-900">À partir de maintenant</div>
                        <div class="text-sm text-slate-500">Le décompte repart de zéro.</div>
                    </div>
                </label>
            </div>
        </div>

        <div class="rounded-lg border border-sky-200 bg-sky-50 px-4 py-3 text-sm text-sky-900" x-text="alarmeModal.apercu"></div>

        <div x-show="alarmeModal.erreur" x-cloak class="rounded-lg border border-rose-200 bg-rose-50 px-4 py-2.5 text-sm text-rose-700" x-text="alarmeModal.erreur"></div>
    </div>

    <x-slot name="footer">
        <div class="flex items-center justify-between gap-3">
            <button type="button" x-show="alarmeModal.modifier" @click="annulerAlarme()"
                    class="rounded-lg border border-rose-200 bg-white px-4 py-2.5 text-base font-medium text-rose-700 hover:bg-rose-50">
                Supprimer l'alarme
            </button>
            <div class="ml-auto flex items-center gap-2">
                <button type="button" @click="alarmeModal.ouvert = false"
                        class="rounded-lg border border-slate-200 bg-white px-4 py-2.5 text-base font-medium text-slate-700 hover:bg-slate-50">Annuler</button>
                <button type="button" @click="confirmerAlarme()" :disabled="alarmeModal.enCours"
                        class="inline-flex items-center gap-2 rounded-lg bg-slate-900 px-5 py-2.5 text-base font-medium text-white hover:bg-slate-700 disabled:opacity-50">
                    <x-icone nom="bell" class="h-5 w-5" />
                    <span x-text="alarmeModal.enCours ? 'Enregistrement…' : (alarmeModal.modifier ? 'Enregistrer' : 'Activer')"></span>
                </button>
            </div>
        </div>
    </x-slot>
</x-modal>


{{-- ═══════════════════════════════════════════════════════════════════
     3. PROLONGER (le point de départ « depuis le début » n'existe plus ici)
     ═══════════════════════════════════════════════════════════════════ --}}
<x-wifi-modal show="prolongerModal.ouvert" z="9400" maxw="max-w-md">
    <x-slot name="header">
        <div class="flex shrink-0 items-start justify-between gap-4 border-b border-slate-100 px-6 py-4">
            <div class="min-w-0">
                <h2 class="text-lg font-semibold text-slate-900">Prolonger l'alarme</h2>
                <p class="mt-0.5 truncate text-sm text-slate-500"
                   x-text="prolongerModal.ligne ? prolongerModal.ligne.appareil : ''"></p>
            </div>
            <button type="button" @click="prolongerModal.ouvert = false" aria-label="Fermer"
                    class="-mr-2 -mt-1 rounded-lg p-1.5 text-slate-400 hover:bg-slate-100 hover:text-slate-600">
                <x-icone nom="x" class="h-5 w-5" />
            </button>
        </div>
    </x-slot>

    <template x-if="prolongerModal.ligne">
        <div class="space-y-5">
            <div class="flex items-center justify-between rounded-xl bg-slate-50 px-4 py-3">
                <div>
                    <div class="text-sm text-slate-500" x-text="(restantAlarme(prolongerModal.ligne) ?? 0) > 0 ? 'Temps restant' : 'Temps dépassé'"></div>
                    <div class="font-mono text-xl font-bold"
                         :class="(restantAlarme(prolongerModal.ligne) ?? 0) > 0 ? 'text-slate-900' : 'text-rose-600'"
                         x-text="(restantAlarme(prolongerModal.ligne) ?? 0) > 0 ? chronoAlarme(prolongerModal.ligne) : '+' + formatDureeSec(depassementSecondes(prolongerModal.ligne))"></div>
                </div>
                <div class="text-right">
                    <div class="text-sm text-slate-500">Limite actuelle</div>
                    <div class="font-semibold text-slate-900" x-text="formatDuree(prolongerModal.ligne.alarme.minutes)"></div>
                </div>
            </div>

            <div>
                <div class="mb-2 grid grid-cols-2 gap-1 rounded-lg bg-slate-100 p-1 text-base">
                    <button type="button" @click="prolongerModal.type = 'duree'"
                            :class="prolongerModal.type === 'duree' ? 'bg-white text-slate-900 shadow-sm' : 'text-slate-600'"
                            class="rounded-md px-3 py-2 font-medium">Durée</button>
                    <button type="button" @click="prolongerModal.type = 'montant'"
                            :class="prolongerModal.type === 'montant' ? 'bg-white text-slate-900 shadow-sm' : 'text-slate-600'"
                            class="rounded-md px-3 py-2 font-medium">Montant</button>
                </div>

                <template x-if="prolongerModal.type === 'duree'">
                    <div class="flex items-center gap-2">
                        <input type="number" inputmode="numeric" min="0" max="99" x-model.number="prolongerModal.h"
                               class="w-20 rounded-lg border-2 border-slate-300 px-3 py-2.5 text-center font-mono text-lg focus:border-slate-800 focus:outline-none">
                        <span class="text-base text-slate-500">h</span>
                        <input type="number" inputmode="numeric" min="0" max="59" x-model.number="prolongerModal.m"
                               class="w-20 rounded-lg border-2 border-slate-300 px-3 py-2.5 text-center font-mono text-lg focus:border-slate-800 focus:outline-none">
                        <span class="text-base text-slate-500">min</span>
                    </div>
                </template>
                <template x-if="prolongerModal.type === 'montant'">
                    <div class="flex items-center gap-2">
                        <input type="number" inputmode="numeric" min="1" x-model.number="prolongerModal.montant"
                               class="w-full rounded-lg border-2 border-slate-300 px-3 py-2.5 font-mono text-lg focus:border-slate-800 focus:outline-none">
                        <span class="text-base text-slate-500">Ar</span>
                    </div>
                </template>

                <div class="mt-2 flex flex-wrap gap-2">
                    <template x-for="r in prolongerModal.raccourcis.filter(x => x.type === prolongerModal.type)" :key="r.label">
                        <button type="button" @click="appliquerRaccourciProlongation(r)"
                                class="rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-sm font-medium text-slate-700 hover:bg-slate-50"
                                x-text="r.label"></button>
                    </template>
                </div>
            </div>

            {{-- Le choix n'a de sens que si l'alarme n'a pas encore expiré --}}
            <div x-show="(restantAlarme(prolongerModal.ligne) ?? 0) > 0" class="space-y-2">
                <label class="flex cursor-pointer items-start gap-3 rounded-lg border-2 p-3 hover:bg-slate-50"
                       :class="prolongerModal.depart === 'apres' ? 'border-slate-900 bg-slate-50' : 'border-slate-200'">
                    <input type="radio" x-model="prolongerModal.depart" value="apres" class="mt-1">
                    <div>
                        <div class="text-sm font-semibold text-slate-900">Ajouter au temps restant</div>
                        <div class="text-xs text-slate-500">Recommandé : aucune minute perdue.</div>
                    </div>
                </label>
                <label class="flex cursor-pointer items-start gap-3 rounded-lg border-2 p-3 hover:bg-slate-50"
                       :class="prolongerModal.depart === 'maintenant' ? 'border-slate-900 bg-slate-50' : 'border-slate-200'">
                    <input type="radio" x-model="prolongerModal.depart" value="maintenant" class="mt-1">
                    <div>
                        <div class="text-sm font-semibold text-slate-900">Recompter à partir de maintenant</div>
                        <div class="text-xs text-slate-500">Remplace le temps restant.</div>
                    </div>
                </label>
            </div>

            <div class="rounded-lg border border-sky-200 bg-sky-50 px-4 py-3 text-sm text-sky-900" x-text="apercuProlongation()"></div>

            <div x-show="prolongerModal.erreur" x-cloak class="rounded-lg border border-rose-200 bg-rose-50 px-4 py-2.5 text-sm text-rose-700" x-text="prolongerModal.erreur"></div>
        </div>
    </template>

    <x-slot name="footer">
        <div class="flex items-center justify-end gap-2">
            <button type="button" @click="prolongerModal.ouvert = false"
                    class="rounded-lg border border-slate-200 bg-white px-4 py-2.5 text-base font-medium text-slate-700 hover:bg-slate-50">Annuler</button>
            <button type="button" @click="confirmerProlongation()" :disabled="prolongerModal.enCours"
                    class="inline-flex items-center gap-2 rounded-lg bg-emerald-600 px-5 py-2.5 text-base font-semibold text-white hover:bg-emerald-500 disabled:opacity-50">
                <x-icone nom="plus" class="h-5 w-5" />
                <span x-text="prolongerModal.enCours ? 'Enregistrement…' : 'Prolonger'"></span>
            </button>
        </div>
    </x-slot>
</x-modal>


{{-- ═══════════════════════════════════════════════════════════════════
     4. CONFIRMATION GÉNÉRIQUE (arrêter, continuer, supprimer…)
     ═══════════════════════════════════════════════════════════════════ --}}
<x-wifi-modal show="confirmation.ouvert" z="9200" maxw="max-w-md">
    <x-slot name="header">
        <div class="flex shrink-0 items-start gap-4 px-6 pt-6">
            <span class="flex h-12 w-12 shrink-0 items-center justify-center rounded-full"
                  :class="confirmation.danger ? 'bg-rose-100 text-rose-600' : 'bg-amber-100 text-amber-600'">
                <x-icone nom="warning" class="h-6 w-6" />
            </span>
            <h2 class="pt-2.5 text-lg font-bold text-slate-900" x-text="confirmation.titre"></h2>
        </div>
    </x-slot>

    <div class="text-base leading-relaxed text-slate-700" x-html="confirmation.message"></div>

    <x-slot name="footer">
        <div class="flex items-center justify-end gap-2">
            <button type="button" @click="confirmation.ouvert = false"
                    class="rounded-lg border border-slate-200 bg-white px-5 py-2.5 text-base font-medium text-slate-700 hover:bg-slate-50">Annuler</button>
            <button type="button" @click="executerConfirmation()" :disabled="confirmation.enCours"
                    class="inline-flex items-center gap-2 rounded-lg px-5 py-2.5 text-base font-medium text-white shadow-sm disabled:opacity-50"
                    :class="confirmation.danger ? 'bg-rose-600 hover:bg-rose-500' : 'bg-slate-900 hover:bg-slate-700'">
                <x-icone nom="check" class="h-4 w-4" />
                <span x-text="confirmation.enCours ? 'En cours…' : confirmation.bouton"></span>
            </button>
        </div>
    </x-slot>
</x-modal>


{{-- ═══════════════════════════════════════════════════════════════════
     5. RÉGLAGES DE L'ALARME
     ═══════════════════════════════════════════════════════════════════ --}}
<x-wifi-modal show="reglages.ouvert" close="fermerReglages()" z="9300" maxw="max-w-2xl">
    <x-slot name="header">
        <div class="shrink-0 border-b border-slate-100 px-6 pt-4">
            <div class="flex items-start justify-between gap-4">
                <div>
                    <h2 class="text-lg font-semibold text-slate-900">Réglages de l'alarme</h2>
                    <p class="text-sm text-slate-500">Les modifications ne sont appliquées qu'après « Enregistrer ».</p>
                </div>
                <button type="button" @click="fermerReglages()" aria-label="Fermer"
                        class="-mr-2 -mt-1 rounded-lg p-1.5 text-slate-400 hover:bg-slate-100 hover:text-slate-600">
                    <x-icone nom="x" class="h-5 w-5" />
                </button>
            </div>
            <div class="mt-3 flex gap-4">
                <button type="button" @click="reglages.tab = 'general'"
                        :class="reglages.tab === 'general' ? 'border-slate-900 text-slate-900' : 'border-transparent text-slate-500 hover:text-slate-700'"
                        class="-mb-px border-b-2 px-1 pb-2.5 text-base font-medium">Général</button>
                <button type="button" @click="reglages.tab = 'sons'"
                        :class="reglages.tab === 'sons' ? 'border-slate-900 text-slate-900' : 'border-transparent text-slate-500 hover:text-slate-700'"
                        class="-mb-px border-b-2 px-1 pb-2.5 text-base font-medium">Sons</button>
            </div>
        </div>
    </x-slot>

    {{-- Onglet Général --}}
    <div x-show="reglages.tab === 'general'" class="space-y-5">
        <div class="flex items-center justify-between gap-4 rounded-xl border border-slate-200 p-4">
            <div>
                <div class="text-base font-semibold text-slate-900" x-text="reglages.form.enabled ? 'Alarme sonore activée' : 'Alarme sonore désactivée'"></div>
                <div class="text-sm text-slate-500">Désactivée, la fenêtre s'affiche mais rien ne sonne.</div>
            </div>
            <button type="button" @click="basculerActivation()" role="switch" :aria-checked="reglages.form.enabled"
                    :class="reglages.form.enabled ? 'bg-emerald-500' : 'bg-slate-300'"
                    class="relative h-8 w-14 shrink-0 rounded-full transition">
                <span class="absolute top-1 h-6 w-6 rounded-full bg-white shadow transition-all" :class="reglages.form.enabled ? 'left-7' : 'left-1'"></span>
            </button>
        </div>

        <div class="space-y-3 rounded-xl border border-slate-200 p-4">
            <div class="text-base font-semibold text-slate-700">Durée de la sonnerie</div>
            <label class="flex items-start gap-3">
                <input type="radio" x-model="reglages.form.modeDuree" value="auto" class="mt-1">
                <div>
                    <div class="font-medium text-slate-900">Automatique (recommandé)</div>
                    <div class="text-sm text-slate-500">Sonne jusqu'à ce que l'opérateur clique « Arrêter l'alarme ».</div>
                </div>
            </label>
            <label class="flex items-start gap-3">
                <input type="radio" x-model="reglages.form.modeDuree" value="manuel" class="mt-1">
                <div>
                    <div class="font-medium text-slate-900">Durée limitée</div>
                    <div class="text-sm text-slate-500">Le son s'arrête seul après la durée choisie (la fenêtre reste ouverte).</div>
                </div>
            </label>
            <div x-show="reglages.form.modeDuree === 'manuel'" x-cloak class="flex flex-wrap items-center gap-2 pl-7">
                <input type="number" min="0" max="10" x-model.number="reglages.form.dureeMin"
                       class="w-20 rounded-lg border-2 border-slate-300 px-3 py-2 text-center font-mono">
                <span class="text-sm text-slate-500">min</span>
                <input type="number" min="0" max="59" x-model.number="reglages.form.dureeSec"
                       class="w-20 rounded-lg border-2 border-slate-300 px-3 py-2 text-center font-mono">
                <span class="text-sm text-slate-500">s</span>
                <span class="text-xs text-slate-500">(de 10 s à 10 min)</span>
            </div>
        </div>

        <div class="rounded-xl border border-slate-200 p-4">
            <label class="mb-2 block text-base font-semibold text-slate-700">Taille maximale d'un fichier son</label>
            <div class="flex items-center gap-2">
                <input type="number" min="1" max="10" step="1" x-model.number="reglages.form.tailleMax"
                       class="w-24 rounded-lg border-2 border-slate-300 px-3 py-2 text-center font-mono">
                <span class="font-medium text-slate-600">Mo</span>
                <span class="text-sm text-slate-500">(entre 1 et 10 Mo)</span>
            </div>
        </div>
    </div>

    {{-- Onglet Sons --}}
    <div x-show="reglages.tab === 'sons'" x-cloak class="space-y-5">
        <div class="space-y-3 rounded-xl border border-slate-200 p-4">
            <div class="flex items-center justify-between">
                <label class="text-base font-semibold text-slate-700">Volume</label>
                <span class="font-mono text-base text-slate-900" x-text="(reglages.form.volume ?? 70) + ' %'"></span>
            </div>
            <div class="flex items-center gap-3">
                <input type="range" min="0" max="100" step="5" x-model.number="reglages.form.volume" class="flex-1">
                <input type="number" min="0" max="100" x-model.number="reglages.form.volume"
                       class="w-20 rounded-lg border-2 border-slate-300 px-2 py-1.5 text-center font-mono">
            </div>
            <p class="text-sm text-slate-500">Le volume s'applique aussi aux sons de test.</p>
        </div>

        <div class="rounded-xl border-2 border-emerald-300 bg-emerald-50 p-3">
            <div class="text-xs font-semibold text-emerald-700">Son actif (utilisé pour l'alarme)</div>
            <div class="mt-1 flex items-center justify-between gap-3">
                <div class="truncate text-base font-semibold text-slate-900" x-text="reglages.form.sonNom"></div>
                <button type="button" @click="testerSon('actif')"
                        class="inline-flex h-9 shrink-0 items-center gap-1.5 rounded-lg border border-slate-200 bg-white px-3 text-sm font-medium text-slate-700 hover:bg-slate-50">
                    <span x-show="!(testEnCours && testId === 'actif')"><x-icone nom="bell" class="h-4 w-4" /></span>
                    <span x-show="testEnCours && testId === 'actif'" x-cloak><x-icone nom="pause" class="h-4 w-4" /></span>
                    <span x-text="testEnCours && testId === 'actif' ? 'Stop' : 'Tester'"></span>
                </button>
            </div>
        </div>

        <div>
            <div class="mb-2 text-sm font-semibold text-slate-500">Sons intégrés</div>
            <ul class="space-y-2">
                <template x-for="s in cfg.sonsParDefaut" :key="s.id">
                    <li class="flex items-center justify-between gap-3 rounded-lg border border-slate-200 px-3 py-2.5" x-show="reglages.form.sonId !== s.id">
                        <div class="min-w-0">
                            <div class="text-base font-medium text-slate-900" x-text="s.nom"></div>
                            <div class="text-sm text-slate-500" x-text="s.desc"></div>
                        </div>
                        <div class="flex shrink-0 items-center gap-2">
                            <button type="button" @click="testerSon(s.id)"
                                    class="inline-flex h-9 items-center gap-1 rounded-lg border border-slate-200 bg-white px-3 text-sm font-medium text-slate-700 hover:bg-slate-50">
                                <span x-show="!(testEnCours && testId === s.id)"><x-icone nom="bell" class="h-4 w-4" /></span>
                                <span x-show="testEnCours && testId === s.id" x-cloak><x-icone nom="pause" class="h-4 w-4" /></span>
                                <span x-text="testEnCours && testId === s.id ? 'Stop' : 'Tester'"></span>
                            </button>
                            <button type="button" @click="choisirSonDefaut(s)"
                                    class="inline-flex h-9 items-center rounded-lg bg-slate-900 px-3 text-sm font-medium text-white hover:bg-slate-700">Choisir</button>
                        </div>
                    </li>
                </template>
            </ul>
        </div>

        <div>
            <div class="mb-2 text-sm font-semibold text-slate-500">Sons personnalisés</div>
            <p x-show="reglages.sonsUploades.length === 0" class="text-sm italic text-slate-500">Aucun son personnalisé.</p>
            <ul class="space-y-2">
                <template x-for="s in reglages.sonsUploades" :key="s.nom">
                    <li class="flex items-center justify-between gap-3 rounded-lg border border-slate-200 px-3 py-2.5" x-show="reglages.form.sonFichier !== s.nom">
                        <div class="min-w-0 truncate text-base font-medium text-slate-900" x-text="s.nom"></div>
                        <div class="flex shrink-0 items-center gap-2">
                            <button type="button" @click="testerSon(s.url)"
                                    class="inline-flex h-9 items-center gap-1 rounded-lg border border-slate-200 bg-white px-3 text-sm font-medium text-slate-700 hover:bg-slate-50">
                                <span x-show="!(testEnCours && testId === s.url)"><x-icone nom="bell" class="h-4 w-4" /></span>
                                <span x-show="testEnCours && testId === s.url" x-cloak><x-icone nom="pause" class="h-4 w-4" /></span>
                                <span x-text="testEnCours && testId === s.url ? 'Stop' : 'Tester'"></span>
                            </button>
                            <button type="button" @click="choisirSonUpload(s)"
                                    class="inline-flex h-9 items-center rounded-lg bg-slate-900 px-3 text-sm font-medium text-white hover:bg-slate-700">Choisir</button>
                            <button type="button" @click="demanderSupprSon(s)" aria-label="Supprimer ce son"
                                    class="inline-flex h-9 items-center rounded-lg border border-rose-200 bg-white px-3 text-rose-700 hover:bg-rose-50">
                                <x-icone nom="trash" class="h-4 w-4" />
                            </button>
                        </div>
                    </li>
                </template>
            </ul>
        </div>

        <div>
            <div class="mb-2 text-sm font-semibold text-slate-500">Ajouter un son</div>
            <div @dragover.prevent="dragActif = true" @dragleave.prevent="dragActif = false" @drop.prevent="dropSon($event)"
                 :class="dragActif ? 'border-emerald-500 bg-emerald-50' : 'border-slate-300 bg-slate-50'"
                 class="flex flex-col items-center justify-center rounded-xl border-2 border-dashed p-6 text-center transition">
                <x-icone nom="upload-cloud" class="mb-2 h-10 w-10 text-slate-400" />
                <div class="text-base font-medium text-slate-700">Glissez un fichier ici</div>
                <div class="my-2 text-sm text-slate-500">ou</div>
                <label class="cursor-pointer rounded-lg bg-slate-900 px-4 py-2 text-base font-medium text-white hover:bg-slate-700">
                    Choisir un fichier
                    <input type="file" accept="audio/*" @change="uploaderSon($event)" class="hidden">
                </label>
                <p class="mt-3 text-sm text-slate-500">MP3, WAV, OGG, M4A ou AAC — max <span x-text="(reglages.form.tailleMax ?? 10) + ' Mo'"></span></p>
            </div>
            <div x-show="reglages.uploadEnCours" class="mt-2 text-base font-medium text-sky-700">Envoi en cours…</div>
            <p x-show="reglages.uploadErreur" x-text="reglages.uploadErreur" class="mt-2 text-base text-rose-600"></p>
            <p x-show="reglages.uploadOk" x-text="reglages.uploadOk" class="mt-2 text-base text-emerald-600"></p>
        </div>
    </div>

    <x-slot name="footer">
        <div class="flex items-center justify-between gap-3">
            <button type="button" @click="demanderReinitialisation()"
                    class="inline-flex items-center gap-2 rounded-lg border border-amber-200 bg-white px-4 py-2.5 text-base font-medium text-amber-700 hover:bg-amber-50">
                <x-icone nom="refresh" class="h-5 w-5" /> Réinitialiser
            </button>
            <div class="flex items-center gap-2">
                <button type="button" @click="fermerReglages()"
                        class="rounded-lg border border-slate-200 bg-white px-4 py-2.5 text-base font-medium text-slate-700 hover:bg-slate-50">Annuler</button>
                <button type="button" @click="enregistrerReglages()" :disabled="reglages.enCours"
                        class="inline-flex items-center gap-2 rounded-lg bg-slate-900 px-5 py-2.5 text-base font-medium text-white hover:bg-slate-700 disabled:opacity-50">
                    <x-icone nom="check" class="h-5 w-5" />
                    <span x-text="reglages.enCours ? 'Enregistrement…' : 'Enregistrer'"></span>
                </button>
            </div>
        </div>
    </x-slot>
</x-modal>


{{-- ═══ 6. Sous-confirmations des réglages (au-dessus de la fenêtre Réglages) ═══ --}}
<x-wifi-modal show="supprSonModal.ouvert" z="9500" maxw="max-w-sm" titre="Supprimer ce son ?" sousJs="supprSonModal.nom">
    <p class="text-base text-slate-600">Le fichier sera retiré définitivement. S'il était actif, le bip classique sera utilisé.</p>
    <x-slot name="footer">
        <div class="flex justify-end gap-2">
            <button type="button" @click="supprSonModal.ouvert = false"
                    class="rounded-lg border border-slate-200 bg-white px-4 py-2.5 text-base font-medium text-slate-700 hover:bg-slate-50">Annuler</button>
            <button type="button" @click="confirmerSupprSon()"
                    class="inline-flex items-center gap-2 rounded-lg bg-rose-600 px-4 py-2.5 text-base font-medium text-white hover:bg-rose-500">
                <x-icone nom="trash" class="h-5 w-5" /> Supprimer
            </button>
        </div>
    </x-slot>
</x-modal>

<x-wifi-modal show="resetModal.ouvert" z="9500" maxw="max-w-sm" titre="Réinitialiser les réglages ?">
    <p class="text-base text-slate-600">
        Tous les réglages reviennent aux valeurs par défaut. <strong>Les sons personnalisés ne sont pas supprimés.</strong>
    </p>
    <x-slot name="footer">
        <div class="flex justify-end gap-2">
            <button type="button" @click="resetModal.ouvert = false"
                    class="rounded-lg border border-slate-200 bg-white px-4 py-2.5 text-base font-medium text-slate-700 hover:bg-slate-50">Annuler</button>
            <button type="button" @click="confirmerReinitialisation()"
                    class="inline-flex items-center gap-2 rounded-lg bg-amber-600 px-4 py-2.5 text-base font-medium text-white hover:bg-amber-500">
                <x-icone nom="refresh" class="h-5 w-5" /> Réinitialiser
            </button>
        </div>
    </x-slot>
</x-modal>