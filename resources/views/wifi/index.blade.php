@extends('layouts.cyber-manager', ['title' => 'Wi-Fi — Cyber Manager'])

@section('content')
@php $cfg = $config; @endphp
<x-flash />

<div x-data="wifiPage({{ Illuminate\Support\Js::from($cfg) }})" x-init="init()">

    {{-- ═══ FENÊTRE D'ALARME PERSISTANTE ═══ --}}
    <template x-teleport="body">
    <div x-show="fenetresAlarme.length > 0" x-cloak class="fixed inset-0 z-[9100] flex items-center justify-center p-4 bg-slate-900/80">
        <div class="w-full max-w-2xl space-y-3 max-h-[90vh] overflow-y-auto">
            <template x-for="f in fenetresAlarme" :key="f.id">
                <div class="rounded-2xl bg-white shadow-2xl border-4 border-rose-500 overflow-hidden">
                    <div class="bg-rose-600 px-6 py-4 flex items-center gap-3 animate-pulse">
                        <x-icone nom="bell" class="h-8 w-8 text-white shrink-0" />
                        <h2 class="text-2xl font-bold text-white">Temps écoulé</h2>
                    </div>
                    <div class="p-6 space-y-4 text-lg">
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <div class="text-sm font-medium text-slate-500">Appareil</div>
                                <div class="text-xl font-bold text-slate-900" x-text="f.appareil"></div>
                            </div>
                            <div>
                                <div class="text-sm font-medium text-slate-500">Voucher / Code</div>
                                <div class="font-mono text-xl font-bold text-slate-900" x-text="f.voucher || '—'"></div>
                            </div>
                            <div>
                                <div class="text-sm font-medium text-slate-500">Forfait</div>
                                <div class="text-xl font-bold text-slate-900" x-text="f.forfait"></div>
                            </div>
                            <div>
                                <div class="text-sm font-medium text-slate-500">Heure de fin</div>
                                <div class="font-mono text-xl font-bold text-rose-600" x-text="f.heureFin"></div>
                            </div>
                        </div>
                        <div x-show="f.depassementSecondes > 0" class="rounded-lg bg-rose-50 border border-rose-200 px-4 py-3 text-rose-800">
                            <div class="text-sm font-medium">Dépassement en cours</div>
                            <div class="font-mono text-2xl font-bold"
                                 x-text="'+' + formatDureeSec(f.depassementSecondes) + ' (≈ +' + formatAr(f.depassementMontant) + ')'"></div>
                        </div>
                        <div class="border-t border-slate-100 pt-4">
                            <div class="text-base font-semibold text-slate-700 mb-2">Le client continue ?</div>
                            <div class="flex flex-wrap gap-2">
                                <template x-for="p in f.prolongationsRapides" :key="p.label">
                                    <button type="button" @click="prolongerDepuisFenetre(f, p)"
                                            class="rounded-lg bg-emerald-600 px-4 py-2.5 text-base font-semibold text-white hover:bg-emerald-500">
                                        <span x-text="p.label"></span>
                                    </button>
                                </template>
                                <button type="button" @click="ouvrirProlongerDepuisFenetre(f)"
                                        class="rounded-lg border border-slate-300 bg-white px-4 py-2.5 text-base font-medium text-slate-700 hover:bg-slate-50">
                                    Autre valeur…
                                </button>
                            </div>
                        </div>
                        <div class="border-t border-slate-100 pt-4">
                            <button type="button" @click="acquitterAlarme(f)"
                                    class="w-full rounded-xl bg-rose-600 px-6 py-4 text-lg font-bold text-white hover:bg-rose-500 shadow-lg active:scale-[0.98] transition">
                                <x-icone nom="bell" class="h-6 w-6 inline mr-2" />
                                Arrêter l'alarme
                            </button>
                            <p class="mt-2 text-sm text-center text-slate-500">
                                La session continue. Utilisez « Arrêter » dans le tableau pour figer la durée/montant.
                            </p>
                        </div>
                    </div>
                </div>
            </template>
        </div>
    </div>
    </template>

    {{-- En-tête --}}
    <div class="mb-6 flex flex-wrap items-center justify-between gap-3">
        <div>
            <h1 class="text-2xl font-semibold tracking-tight text-slate-900">Wi-Fi</h1>
            <p class="text-base text-slate-500">Sessions HotSpot temps réel</p>
        </div>
        <div class="flex flex-wrap items-center gap-2">
            <button type="button" @click="ouvrirReglages()"
                    :title="'Réglages de l\'alarme — ' + (alarme.enabled ? 'activée' : 'désactivée')"
                    :class="alarme.enabled ? 'border-emerald-300 bg-emerald-50 text-emerald-800' : 'border-rose-300 bg-rose-50 text-rose-800'"
                    class="inline-flex h-11 items-center gap-2 rounded-lg border px-4 text-base font-medium shadow-sm hover:bg-white">
                <x-icone nom="bell" class="h-5 w-5" />
                <span>Réglages de l'alarme</span>
                <span class="ml-1 h-2.5 w-2.5 rounded-full" :class="alarme.enabled ? 'bg-emerald-500' : 'bg-rose-500'"></span>
            </button>
            <button type="button" @click="rafraichir(true)"
                    class="inline-flex h-11 items-center gap-2 rounded-lg border border-slate-200 bg-white px-4 text-base font-medium text-slate-700 shadow-sm hover:bg-slate-50">
                <x-icone nom="refresh" class="h-5 w-5" /> Actualiser
            </button>
        </div>
    </div>

    {{-- KPI --}}
    <div class="mb-6 grid grid-cols-2 gap-4 lg:grid-cols-4">
        <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
            <div class="flex items-center gap-2 text-base font-medium text-slate-500">
                <span class="h-2.5 w-2.5 rounded-full bg-emerald-500"></span><span>Connectés</span>
            </div>
            <div class="mt-2 text-3xl font-semibold text-emerald-600" x-text="stats.connectes"></div>
        </div>
        <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
            <div class="flex items-center gap-2 text-base font-medium text-slate-500">
                <span class="h-2.5 w-2.5 rounded-full bg-amber-500"></span><span>Déconnectés</span>
            </div>
            <div class="mt-2 text-3xl font-semibold text-amber-600" x-text="stats.deconnectes"></div>
        </div>
        <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
            <div class="flex items-center gap-2 text-base font-medium text-slate-500">
                <span class="h-2.5 w-2.5 rounded-full bg-slate-400"></span><span>Terminés</span>
            </div>
            <div class="mt-2 text-3xl font-semibold text-slate-700" x-text="stats.termines"></div>
        </div>
        <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
            <div class="flex items-center gap-2 text-base font-medium text-slate-500">
                <x-icone nom="chart" class="h-5 w-5 text-violet-500" /><span>Débit total</span>
            </div>
            <div class="mt-2 text-3xl font-semibold text-violet-600" x-text="formatDebit(debitTotalTotal)"></div>
            <div class="mt-1 text-sm text-slate-400">Volume : <span x-text="formatOctets(stats.volume_total)"></span></div>
        </div>
    </div>

    {{-- Bandeau notifications --}}
    <div x-show="!notifAutorisee && alarme.enabled" x-cloak
         class="mb-4 flex flex-wrap items-center justify-between gap-3 rounded-xl border border-sky-200 bg-sky-50 px-4 py-3 text-base text-sky-800">
        <div class="flex items-center gap-2">
            <x-icone nom="bell" class="h-5 w-5 shrink-0" />
            <span>Autorisez les notifications navigateur pour être alerté même onglet en arrière-plan.</span>
        </div>
        <button type="button" @click="demanderNotif()"
                class="rounded-lg bg-sky-600 px-4 py-2 text-sm font-medium text-white hover:bg-sky-700">Autoriser</button>
    </div>

    {{-- Filtres --}}
    <div class="mb-4 flex flex-wrap items-center gap-3">
        <div class="inline-flex rounded-lg border border-slate-200 bg-white p-1 text-base shadow-sm">
            <button type="button" @click="filtreEtat = ''" :class="filtreEtat === '' ? 'bg-slate-900 text-white' : 'text-slate-600 hover:bg-slate-100'" class="rounded-md px-4 py-2 font-medium">Tous</button>
            <button type="button" @click="filtreEtat = 'connecte'" :class="filtreEtat === 'connecte' ? 'bg-emerald-500 text-white' : 'text-slate-600 hover:bg-slate-100'" class="rounded-md px-4 py-2 font-medium">Connectés</button>
            <button type="button" @click="filtreEtat = 'deconnecte'" :class="filtreEtat === 'deconnecte' ? 'bg-amber-500 text-white' : 'text-slate-600 hover:bg-slate-100'" class="rounded-md px-4 py-2 font-medium">Déconnectés</button>
            <button type="button" @click="filtreEtat = 'termine'" :class="filtreEtat === 'termine' ? 'bg-slate-600 text-white' : 'text-slate-600 hover:bg-slate-100'" class="rounded-md px-4 py-2 font-medium">Terminés</button>
        </div>

        <div class="relative min-w-[200px] flex-1 sm:max-w-sm">
            <x-icone nom="search" class="pointer-events-none absolute left-3 top-1/2 h-5 w-5 -translate-y-1/2 text-slate-400 z-10" />
            <input type="search" x-model="recherche" placeholder="Rechercher…"
                   class="h-11 w-full rounded-lg border border-slate-200 bg-white pl-10 pr-10 text-base shadow-sm focus:border-slate-800 focus:outline-none">
            <button type="button" x-show="recherche.length > 0" x-cloak @click="recherche = ''"
                    class="absolute right-2 top-1/2 -translate-y-1/2 rounded p-1 text-slate-400 hover:bg-slate-100">
                <x-icone nom="x" class="h-5 w-5" />
            </button>
        </div>

        <button type="button" x-show="selected.length > 0" x-cloak @click="demanderSuppressionMasse()"
                class="inline-flex h-11 items-center gap-1.5 rounded-lg bg-rose-600 px-4 text-base font-medium text-white hover:bg-rose-500">
            <x-icone nom="trash" class="h-5 w-5" />
            <span>Supprimer (<span x-text="selected.length"></span>)</span>
        </button>
    </div>

    {{-- Tableau --}}
    <div class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
        <div class="max-h-[62vh] overflow-y-auto">
            <table class="w-full table-fixed text-sm">
                <colgroup>
                    <col style="width: 40px">
                    <col style="width: 78px">
                    <col style="width: 200px">
                    <col style="width: 110px">
                    <col style="width: 80px">
                    <col style="width: 110px">
                    <col style="width: 120px">
                    <col style="width: 90px">
                    <col style="width: 160px">
                    <col style="width: 130px">
                </colgroup>
                <thead class="sticky top-0 z-[5] bg-slate-800 text-left text-xs font-semibold uppercase tracking-wide text-white">
                    <tr>
                        <th class="px-2 py-2.5 text-center">
                            <input type="checkbox" :checked="tousSelectionnes" @change="basculerTous()"
                                   class="h-4 w-4 rounded border-slate-400 text-slate-900">
                        </th>
                        <th class="px-2 py-2.5">
                            <button type="button" @click="trier('date_debut')" class="inline-flex items-center gap-1 hover:text-white/80">
                                Heure
                                <span class="text-white/50" x-show="cleTri !== 'date_debut'">↕</span>
                                <span x-show="cleTri === 'date_debut'" x-text="sensTri === 'asc' ? '↑' : '↓'"></span>
                            </button>
                        </th>
                        <th class="px-2 py-2.5">Appareil</th>
                        <th class="px-2 py-2.5">Réseau</th>
                        <th class="px-2 py-2.5">État</th>
                        <th class="px-2 py-2.5">Durée / Montant</th>
                        <th class="px-2 py-2.5">Volume / Débit</th>
                        <th class="px-2 py-2.5 text-center">Alarme</th>
                        <th class="px-2 py-2.5">Remarques</th>
                        <th class="px-2 py-2.5 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <template x-if="lignesAffichees.length === 0">
                        <tr>
                            <td colspan="10" class="px-4 py-16 text-center text-slate-400 text-base">
                                <span x-show="recherche">Aucun résultat.</span>
                                <span x-show="!recherche">Aucune session Wi-Fi.</span>
                            </td>
                        </tr>
                    </template>

                    <template x-for="(l, i) in lignesAffichees" :key="l.id">
                        <tr class="border-b border-slate-200 transition" :class="classeLigne(l)">
                            <td class="px-2 py-2.5">
                                <div class="flex items-center justify-center gap-1">
                                    <input type="checkbox" :value="String(l.id)" x-model="selected"
                                           class="h-4 w-4 rounded border-slate-300 text-slate-900">
                                    <span class="text-xs font-mono text-slate-400" x-text="i+1"></span>
                                </div>
                            </td>
                            <td class="px-2 py-2.5">
                                <div class="font-mono text-base font-bold text-slate-900 leading-tight" x-text="heureExacte(l.date_debut)"></div>
                                <div class="text-xs text-slate-500 leading-tight" x-text="dateLongueFr(l.date_debut)"></div>
                            </td>
                            <td class="px-2 py-2.5">
                                <div class="flex items-center gap-1.5 min-w-0">
                                    <span x-show="l.appareil_type === 'telephone'"><x-icone nom="phone" class="h-4 w-4 text-slate-400 shrink-0" /></span>
                                    <span x-show="l.appareil_type === 'tablette'"><x-icone nom="tablet" class="h-4 w-4 text-slate-400 shrink-0" /></span>
                                    <span x-show="!['telephone','tablette'].includes(l.appareil_type)"><x-icone nom="desktop" class="h-4 w-4 text-slate-400 shrink-0" /></span>
                                    <div class="min-w-0 flex-1">
                                        <div class="font-semibold text-slate-900 text-sm truncate" x-html="surbriller(l.appareil)"></div>
                                        <div class="font-mono text-xs text-slate-500 truncate" x-html="surbriller(l.ip || '—')"></div>
                                        <div class="font-mono text-xs text-slate-400 truncate" x-html="surbriller(l.mac || '—')"></div>
                                    </div>
                                </div>
                            </td>
                            <td class="px-2 py-2.5">
                                <div class="inline-flex rounded-md bg-violet-50 px-1.5 py-0.5 text-xs font-semibold text-violet-700 ring-1 ring-inset ring-violet-600/20 mb-1"
                                     x-text="l.ssid || 'Inconnu'"></div>
                                <template x-if="l.voucher">
                                    <div>
                                        <div class="font-mono text-xs text-slate-900 truncate" x-html="surbriller(l.voucher.username)"></div>
                                        <template x-if="l.voucher.protege">
                                            <div class="inline-flex items-center gap-0.5 text-[10px] text-amber-600"><x-icone nom="lock" class="h-3 w-3" /> Protégé</div>
                                        </template>
                                        <template x-if="l.voucher.permanent">
                                            <div class="text-[10px] text-violet-600">Réutilisable</div>
                                        </template>
                                    </div>
                                </template>
                            </td>
                            <td class="px-2 py-2.5">
                                <span class="inline-flex items-center gap-1 rounded-md px-1.5 py-0.5 text-xs font-semibold ring-1 ring-inset"
                                      :class="badgeEtat(l)">
                                    <span class="h-1.5 w-1.5 rounded-full" :class="pointEtat(l)"></span>
                                    <span x-text="libelleEtat(l)"></span>
                                </span>
                                <template x-if="l.etat_appareil === 'termine' && l.client_encore_actif">
                                    <div class="mt-1 inline-flex items-center gap-1 rounded-md bg-amber-100 px-1.5 py-0.5 text-[10px] font-semibold leading-tight text-amber-800 ring-1 ring-inset ring-amber-600/30"
                                         title="La session est figée, mais l'appareil est toujours connecté au Wi-Fi. Utilisez Supprimer pour le déconnecter.">
                                        <span class="h-1.5 w-1.5 shrink-0 animate-pulse rounded-full bg-amber-500"></span>
                                        Client encore connecté
                                    </div>
                                </template>
                            </td>
                            <td class="px-2 py-2.5">
                                <div class="font-mono text-base font-bold leading-tight"
                                     :class="(l.etat_appareil === 'connecte') ? 'text-slate-900' : 'text-slate-500'"
                                     x-text="formatDureeSec(secondesConsommees(l))"></div>
                                <div class="text-xs text-slate-500 leading-tight"
                                     x-text="l.alarme.active ? ('Limite ' + formatDuree(l.alarme.minutes)) : 'Sans limite'"></div>
                                <div class="mt-1 font-mono text-sm font-bold leading-tight"
                                     :class="(l.etat_appareil === 'termine') ? 'text-emerald-700' : 'text-sky-700'"
                                     x-text="formatAr((l.etat_appareil === 'termine') ? montantFinalCalcule(l) : montantTempsReel(l))"></div>
                            </td>
                            <td class="px-2 py-2.5">
                                <div class="font-medium text-sm text-slate-800" x-text="formatOctets(l.volume_total)"></div>
                                <div class="font-mono text-xs leading-tight">
                                    <span class="text-emerald-600" x-text="'↓' + debitLigne(l, 'entree')"></span>
                                    <span class="text-slate-300">·</span>
                                    <span class="text-sky-600" x-text="'↑' + debitLigne(l, 'sortie')"></span>
                                </div>
                            </td>
                            <td class="px-2 py-2.5 text-center">
                                {{-- 1) Alarme qui sonne (non acquittée) --}}
                                <template x-if="l.alarme.active && l.alarme.declenchee && !l.alarme.acquittee && l.etat_appareil !== 'termine'">
                                    <span class="inline-flex items-center gap-1 rounded-full bg-rose-100 px-2 py-0.5 text-xs font-bold text-rose-700 animate-pulse">
                                        <x-icone nom="bell" class="h-3.5 w-3.5" /> Sonne
                                    </span>
                                </template>

                                {{-- 2) Alarme posée, en cours de décompte (toujours visible, à chaque prolongation) --}}
                                <template x-if="l.alarme.active && !l.alarme.declenchee && l.etat_appareil !== 'termine'">
                                    <div class="space-y-1">
                                        <template x-if="l.etat_appareil === 'connecte'">
                                            <div class="font-mono text-sm font-bold text-amber-700" x-text="chronoRestantTexte(l)"></div>
                                        </template>
                                        <template x-if="l.etat_appareil !== 'connecte'">
                                            <div class="text-xs font-bold text-slate-500 flex items-center justify-center gap-1">
                                                <x-icone nom="pause" class="h-3 w-3" /> Alarme en pause
                                            </div>
                                        </template>

                                        <div class="h-1 w-full overflow-hidden rounded-full bg-slate-200">
                                            <div class="h-full bg-amber-500 transition-all"
                                                 :style="'width: ' + progressionAlarme(l) + '%'"></div>
                                        </div>
                                        <div class="flex items-center justify-center gap-1">
                                            <button type="button" @click="ouvrirProlonger(l)"
                                                    class="rounded text-[10px] font-medium text-emerald-700 hover:bg-emerald-50 px-1">+ Prolonger</button>
                                            <button type="button" @click="ouvrirAlarme(l, true)"
                                                    class="rounded text-[10px] font-medium text-slate-600 hover:bg-slate-100 px-1">Modifier</button>
                                        </div>
                                    </div>
                                </template>

                                {{-- 3) Session terminée (figée) --}}
                                <template x-if="l.etat_appareil === 'termine'">
                                    <span class="text-xs text-slate-400">Session terminée</span>
                                </template>

                                {{-- 4) Aucune alarme active → bouton « Poser »
                                       Ce cas couvre AUSSI le retour après acquittement
                                       (le backend remet alarme_active=false). --}}
                                <template x-if="(!l.alarme.active || l.alarme.acquittee) && l.etat_appareil !== 'termine'">
                                    <button type="button" @click="ouvrirAlarme(l)"
                                            class="inline-flex items-center gap-1 rounded-md border border-slate-200 bg-white px-2 py-0.5 text-xs text-slate-600 hover:bg-slate-50">
                                        <x-icone nom="bell" class="h-3.5 w-3.5" /> Poser
                                    </button>
                                </template>
                            </td>
                            {{-- Remarques : zone toujours éditable, sauvegarde au blur --}}
                            <td class="px-2 py-2.5">
                                <textarea x-model="l.remarque"
                                          @focus="l._focusRemarque = true"
                                          @blur="l._focusRemarque = false; sauverRemarque(l)"
                                          rows="3" maxlength="1000"
                                          placeholder="Tapez une remarque…"
                                          class="w-full text-xs rounded border border-slate-200 px-2 py-1.5 focus:border-slate-800 focus:outline-none resize-y min-h-[64px] bg-white"></textarea>
                            </td>
                            <td class="px-2 py-2.5">
                                <div class="flex flex-col items-stretch gap-1">
                                    {{-- ARRÊTER : fige durée + montant (le client reste connecté) --}}
                                    <template x-if="l.etat_appareil === 'connecte' || l.etat_appareil === 'deconnecte'">
                                        <button type="button" @click="confirmerArret(l)"
                                                class="inline-flex items-center justify-center gap-1 rounded-md border border-amber-300 bg-white px-2 py-1 text-xs font-semibold text-amber-700 hover:bg-amber-50"
                                                title="Figer la durée et le montant (le client reste connecté)">
                                            <x-icone nom="ban" class="h-3.5 w-3.5" />
                                            Arrêter
                                        </button>
                                    </template>
                                    {{-- CONTINUER : reprend une session terminée --}}
                                    <template x-if="l.etat_appareil === 'termine'">
                                        <button type="button" @click="confirmerContinuer(l)"
                                                class="inline-flex items-center justify-center gap-1 rounded-md border border-emerald-300 bg-white px-2 py-1 text-xs font-semibold text-emerald-700 hover:bg-emerald-50"
                                                title="Reprendre la session depuis la même ligne">
                                            <x-icone nom="play" class="h-3.5 w-3.5" />
                                            Continuer
                                        </button>
                                    </template>
                                    {{-- SUPPRIMER --}}
                                    <button type="button" @click="confirmerSuppression(l)"
                                            class="inline-flex items-center gap-1 rounded-md border border-rose-300 bg-white px-2 py-1 text-xs font-semibold text-rose-700 hover:bg-rose-50"
                                            title="Supprimer définitivement">
                                        <x-icone nom="trash" class="h-3.5 w-3.5" />
                                        Supprimer
                                    </button>
                                </div>
                            </td>
                        </tr>
                    </template>
                </tbody>
            </table>
        </div>
    </div>

    {{-- Identifiants disponibles --}}
    <div class="mt-6 rounded-xl border border-slate-200 bg-white shadow-sm">
        <div class="flex items-center justify-between border-b border-slate-100 px-5 py-3">
            <div class="flex items-center gap-2">
                <x-icone nom="key" class="h-6 w-6 text-slate-400" />
                <div>
                    <h2 class="text-lg font-semibold text-slate-900">Identifiants disponibles</h2>
                    <p class="text-sm text-slate-500">Prochains à utiliser pour un nouveau client</p>
                </div>
            </div>
            <a :href="cfg.urlHotspot" class="inline-flex h-10 items-center gap-1.5 rounded-lg border border-slate-200 bg-white px-4 text-sm font-medium text-slate-700 hover:bg-slate-50">
                Voir tous les identifiants
                <x-icone nom="external-link" class="h-4 w-4" />
            </a>
        </div>
        <template x-if="cfg.vouchersWarning">
            <div class="border-b border-amber-200 bg-amber-50 px-5 py-2.5 text-sm font-medium text-amber-800">
                <x-icone nom="warning" class="h-4 w-4 inline mr-1" />
                <span x-text="cfg.vouchersWarning"></span>
            </div>
        </template>
        <div>
            <table class="w-full text-base">
                <thead class="bg-slate-50 text-left text-sm font-semibold uppercase tracking-wide text-slate-500">
                    <tr>
                        <th class="px-4 py-2">#</th>
                        <th class="px-4 py-2">Username</th>
                        <th class="px-4 py-2">Mot de passe</th>
                        <th class="px-4 py-2">État</th>
                    </tr>
                </thead>
                <tbody>
                    <template x-if="vouchersDisponibles.length === 0">
                        <tr><td colspan="4" class="px-4 py-6 text-center text-slate-400 text-base">Aucun identifiant disponible.</td></tr>
                    </template>
                    <template x-for="(v, i) in vouchersDisponibles" :key="v.id">
                        <tr class="border-t border-slate-100 transition-all duration-500"
                            :class="v.transition ? 'bg-amber-50 opacity-40' : ''">
                            <td class="px-4 py-2 text-slate-400 font-mono text-sm" x-text="i + 1"></td>
                            <td class="px-4 py-2 font-mono text-base text-slate-900" x-text="v.username"></td>
                            <td class="px-4 py-2">
                                <div class="flex items-center gap-2">
                                    <span class="font-mono text-base text-slate-600" x-text="v.masque ? '••••••••' : v.password"></span>
                                    <button type="button" @click="v.masque = !v.masque" class="rounded p-0.5 text-slate-400 hover:bg-slate-100">
                                        <span x-show="v.masque"><x-icone nom="eye-off" class="h-4 w-4" /></span>
                                        <span x-show="!v.masque" x-cloak><x-icone nom="eye" class="h-4 w-4" /></span>
                                    </button>
                                </div>
                            </td>
                            <td class="px-4 py-2">
                                <template x-if="v.transition">
                                    <span class="inline-flex items-center gap-1 rounded-md bg-amber-100 px-2 py-0.5 text-sm font-medium text-amber-800 ring-1 ring-inset ring-amber-600/20">Utilisé</span>
                                </template>
                                <template x-if="!v.transition">
                                    <span class="inline-flex items-center gap-1 rounded-md bg-emerald-50 px-2 py-0.5 text-sm font-medium text-emerald-700 ring-1 ring-inset ring-emerald-600/20">
                                        <span class="h-1.5 w-1.5 rounded-full bg-emerald-500"></span> Disponible
                                    </span>
                                </template>
                            </td>
                        </tr>
                    </template>
                </tbody>
            </table>
        </div>
    </div>

    <p class="mt-3 text-sm text-slate-400">Actualisation temps réel. Dernière sync : <span x-text="derniereSync || '—'"></span></p>

    {{-- ═══ Fenêtre de confirmation unique et dynamique ═══ --}}
    <template x-teleport="body">
    <div x-show="confirmation.ouvert" x-cloak class="fixed inset-0 z-[9200] flex items-center justify-center p-4">
        <div class="absolute inset-0 bg-slate-900/70" @click="confirmation.ouvert = false" x-transition.opacity></div>
        <div x-show="confirmation.ouvert" x-transition class="relative w-full max-w-lg overflow-hidden rounded-2xl bg-white shadow-2xl">
            <div class="flex items-start gap-3 border-b border-slate-100 px-6 pt-5 pb-4">
                <span class="flex h-12 w-12 shrink-0 items-center justify-center rounded-full"
                      :class="confirmation.danger ? 'bg-rose-100' : 'bg-amber-100'">
                    <x-icone nom="warning" class="h-6 w-6"
                             x-bind:class="confirmation.danger ? 'text-rose-600' : 'text-amber-600'" />
                </span>
                <div class="min-w-0 flex-1">
                    <h2 class="text-lg font-bold text-slate-900" x-text="confirmation.titre"></h2>
                    <div class="mt-2 text-base text-slate-700 leading-relaxed" x-html="confirmation.message"></div>
                </div>
            </div>
            <div class="flex items-center justify-end gap-3 border-t border-slate-100 bg-slate-50 px-6 py-4">
                <button type="button" @click="confirmation.ouvert = false"
                        class="inline-flex h-11 items-center rounded-lg border border-slate-200 bg-white px-5 text-base font-medium text-slate-700 hover:bg-slate-50">
                    Annuler
                </button>
                <button type="button" @click="executerConfirmation()" :disabled="confirmation.enCours"
                        class="inline-flex h-11 items-center gap-2 rounded-lg px-5 text-base font-medium text-white shadow-sm disabled:opacity-50"
                        :class="confirmation.danger ? 'bg-rose-600 hover:bg-rose-500' : 'bg-slate-900 hover:bg-slate-700'">
                    <x-icone nom="check" class="h-4 w-4" />
                    <span x-text="confirmation.enCours ? 'En cours…' : confirmation.bouton"></span>
                </button>
            </div>
        </div>
    </div>
    </template>

    {{-- Modale Poser / Modifier alarme --}}
    <template x-teleport="body">
    <div x-show="alarmeModal.ouvert" x-cloak class="fixed inset-0 z-[9300] flex items-center justify-center p-4">
        <div class="absolute inset-0 bg-slate-900/60" @click="alarmeModal.ouvert = false" x-transition.opacity></div>
        <div x-show="alarmeModal.ouvert" x-transition class="relative w-full max-w-lg overflow-hidden rounded-2xl bg-white shadow-2xl">
            <div class="border-b border-slate-100 px-6 py-4">
                <h2 class="text-xl font-bold text-slate-900" x-text="alarmeModal.modifier ? 'Modifier l\'alarme' : 'Poser une alarme'"></h2>
                <p class="text-base text-slate-500 mt-1" x-text="alarmeModal.ligne?.appareil"></p>
            </div>
            <div class="p-6 space-y-5 max-h-[70vh] overflow-y-auto">
                <div>
                    <label class="mb-2 block text-base font-semibold text-slate-700">Type de limite</label>
                    <div class="inline-flex rounded-lg border border-slate-200 bg-slate-100 p-1 text-base">
                        <button type="button" @click="alarmeModal.type = 'duree'; majApercu()"
                                :class="alarmeModal.type === 'duree' ? 'bg-slate-900 text-white shadow-sm' : 'text-slate-600'"
                                class="rounded-md px-4 py-2 font-medium">Par durée</button>
                        <button type="button" @click="alarmeModal.type = 'montant'; majApercu()"
                                :class="alarmeModal.type === 'montant' ? 'bg-slate-900 text-white shadow-sm' : 'text-slate-600'"
                                class="rounded-md px-4 py-2 font-medium">Par montant</button>
                    </div>
                </div>
                <div>
                    <label class="mb-2 block text-base font-semibold text-slate-700">
                        <span x-text="alarmeModal.type === 'duree' ? 'Durée (h / min)' : 'Montant (Ar)'"></span>
                    </label>
                    <template x-if="alarmeModal.type === 'duree'">
                        <div class="flex items-center gap-2">
                            <input type="number" min="0" max="99" x-model.number="alarmeModal.h" @input="majApercu()"
                                   class="w-20 rounded-lg border-2 border-slate-300 px-3 py-2.5 text-center font-mono text-lg">
                            <span class="text-base text-slate-500">h</span>
                            <input type="number" min="0" max="59" x-model.number="alarmeModal.m" @input="majApercu()"
                                   class="w-20 rounded-lg border-2 border-slate-300 px-3 py-2.5 text-center font-mono text-lg">
                            <span class="text-base text-slate-500">min</span>
                        </div>
                    </template>
                    <template x-if="alarmeModal.type === 'montant'">
                        <input type="number" min="1" x-model.number="alarmeModal.montant" @input="majApercu()"
                               class="w-full rounded-lg border-2 border-slate-300 px-3 py-2.5 font-mono text-lg">
                    </template>
                    <div class="mt-2 flex flex-wrap gap-2">
                        <template x-for="r in alarmeModal.raccourcis" :key="r.label">
                            <button type="button" @click="appliquerRaccourci(r)"
                                    class="rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-sm font-medium text-slate-700 hover:bg-slate-50">
                                <span x-text="r.label"></span>
                            </button>
                        </template>
                    </div>
                </div>
                <div>
                    <label class="mb-2 block text-base font-semibold text-slate-700">Point de départ</label>
                    <div class="space-y-2">
                        <label class="flex items-start gap-3 rounded-lg border-2 border-slate-200 p-3 hover:bg-slate-50 cursor-pointer"
                               :class="alarmeModal.depart === 'debut' ? 'border-slate-900 bg-slate-50' : ''">
                            <input type="radio" x-model="alarmeModal.depart" value="debut" @change="majApercu()" class="mt-1">
                            <div>
                                <div class="text-base font-semibold text-slate-900">Depuis le début de la connexion</div>
                                <div class="text-sm text-slate-500">Tient compte de ce qui est déjà consommé.</div>
                            </div>
                        </label>
                        <label class="flex items-start gap-3 rounded-lg border-2 border-slate-200 p-3 hover:bg-slate-50 cursor-pointer"
                               :class="alarmeModal.depart === 'maintenant' ? 'border-slate-900 bg-slate-50' : ''">
                            <input type="radio" x-model="alarmeModal.depart" value="maintenant" @change="majApercu()" class="mt-1">
                            <div>
                                <div class="text-base font-semibold text-slate-900">À partir de maintenant</div>
                                <div class="text-sm text-slate-500">Compte à partir de ce moment.</div>
                            </div>
                        </label>
                    </div>
                </div>
                <div class="rounded-lg border border-sky-200 bg-sky-50 px-4 py-3 text-base text-sky-900">
                    <div x-text="alarmeModal.apercu"></div>
                </div>
                <template x-if="alarmeModal.erreur">
                    <div class="rounded-lg border border-rose-200 bg-rose-50 px-4 py-2.5 text-base text-rose-700" x-text="alarmeModal.erreur"></div>
                </template>
            </div>
            <div class="flex items-center justify-between border-t border-slate-100 bg-slate-50 px-6 py-4">
                <button type="button" x-show="alarmeModal.modifier" @click="annulerAlarme()"
                        class="rounded-lg border border-rose-200 bg-white px-4 py-2.5 text-base font-medium text-rose-700 hover:bg-rose-50">
                    Annuler l'alarme
                </button>
                <div class="ml-auto flex items-center gap-3">
                    <button type="button" @click="alarmeModal.ouvert = false"
                            class="rounded-lg border border-slate-200 bg-white px-5 py-2.5 text-base font-medium text-slate-700 hover:bg-slate-50">Annuler</button>
                    <button type="button" @click="confirmerAlarme()" :disabled="alarmeModal.enCours"
                            class="inline-flex items-center gap-2 rounded-lg bg-slate-900 px-5 py-2.5 text-base font-medium text-white hover:bg-slate-700 disabled:opacity-50">
                        <x-icone nom="bell" class="h-5 w-5" />
                        <span x-text="alarmeModal.enCours ? 'Enregistrement…' : (alarmeModal.modifier ? 'Enregistrer' : 'Activer l\'alarme')"></span>
                    </button>
                </div>
            </div>
        </div>
    </div>
    </template>

    {{-- Modale Prolonger --}}
    <template x-teleport="body">
    <div x-show="prolongerModal.ouvert" x-cloak class="fixed inset-0 z-[9400] flex items-center justify-center p-4">
        <div class="absolute inset-0 bg-slate-900/60" @click="prolongerModal.ouvert = false" x-transition.opacity></div>
        <div x-show="prolongerModal.ouvert" x-transition class="relative w-full max-w-lg overflow-hidden rounded-2xl bg-white shadow-2xl">
            <div class="border-b border-slate-100 px-6 py-4">
                <h2 class="text-xl font-bold text-slate-900">Prolonger l'alarme</h2>
                <p class="text-base text-slate-500" x-text="prolongerModal.ligne?.appareil"></p>
            </div>
            <div class="p-6 space-y-4">
                <div class="rounded-lg bg-slate-50 px-4 py-2.5 text-base">
                    <div class="text-slate-500">Limite actuelle</div>
                    <div class="font-bold text-slate-900" x-text="prolongerModal.ligne ? (formatDuree(prolongerModal.ligne.alarme.minutes) + ' (' + formatAr(prolongerModal.ligne.alarme.montant || 0) + ')') : ''"></div>
                    <div class="text-xs text-slate-500 mt-1" x-show="prolongerModal.ligne?.alarme?.derniere_fin">
                        Fin prévue actuelle : <span x-text="prolongerModal.ligne ? heureExacte(prolongerModal.ligne.alarme.derniere_fin || prolongerModal.ligne.alarme.fin) : ''"></span>
                    </div>
                </div>

                <div>
                    <label class="mb-2 block text-base font-semibold text-slate-700">Ajouter</label>
                    <div class="flex gap-2">
                        <button type="button" @click="prolongerModal.type = 'duree'"
                                :class="prolongerModal.type === 'duree' ? 'bg-slate-900 text-white' : 'bg-slate-100 text-slate-600'"
                                class="flex-1 rounded-lg px-4 py-2.5 text-base font-medium">Durée</button>
                        <button type="button" @click="prolongerModal.type = 'montant'"
                                :class="prolongerModal.type === 'montant' ? 'bg-slate-900 text-white' : 'bg-slate-100 text-slate-600'"
                                class="flex-1 rounded-lg px-4 py-2.5 text-base font-medium">Montant</button>
                    </div>
                </div>

                <div>
                    <template x-if="prolongerModal.type === 'duree'">
                        <div class="flex items-center gap-2">
                            <input type="number" min="0" max="99" x-model.number="prolongerModal.h"
                                   class="w-20 rounded-lg border-2 border-slate-300 px-3 py-2.5 text-center font-mono text-lg">
                            <span class="text-base text-slate-500">h</span>
                            <input type="number" min="0" max="59" x-model.number="prolongerModal.m"
                                   class="w-20 rounded-lg border-2 border-slate-300 px-3 py-2.5 text-center font-mono text-lg">
                            <span class="text-base text-slate-500">min</span>
                        </div>
                    </template>
                    <template x-if="prolongerModal.type === 'montant'">
                        <input type="number" min="1" x-model.number="prolongerModal.montant"
                               class="w-full rounded-lg border-2 border-slate-300 px-3 py-2.5 font-mono text-lg">
                    </template>
                </div>

                <div>
                    <label class="mb-2 block text-base font-semibold text-slate-700">Point de départ</label>
                    <div class="space-y-1.5">
                        <label class="flex items-start gap-3 rounded-lg border-2 border-slate-200 p-2.5 hover:bg-slate-50 cursor-pointer"
                               :class="prolongerModal.depart === 'apres' ? 'border-slate-900 bg-slate-50' : ''">
                            <input type="radio" x-model="prolongerModal.depart" value="apres" class="mt-1">
                            <div>
                                <div class="text-sm font-semibold text-slate-900">À la suite de l'alarme précédente <span class="text-xs text-emerald-700">(recommandé)</span></div>
                                <div class="text-xs text-slate-500">S'ajoute à la fin prévue : pas de minute perdue.</div>
                            </div>
                        </label>
                        <label class="flex items-start gap-3 rounded-lg border-2 border-slate-200 p-2.5 hover:bg-slate-50 cursor-pointer"
                               :class="prolongerModal.depart === 'maintenant' ? 'border-slate-900 bg-slate-50' : ''">
                            <input type="radio" x-model="prolongerModal.depart" value="maintenant" class="mt-1">
                            <div>
                                <div class="text-sm font-semibold text-slate-900">À partir de maintenant</div>
                                <div class="text-xs text-slate-500">Compte à partir de l'instant où vous validez.</div>
                            </div>
                        </label>
                        <label class="flex items-start gap-3 rounded-lg border-2 border-slate-200 p-2.5 hover:bg-slate-50 cursor-pointer"
                               :class="prolongerModal.depart === 'debut' ? 'border-slate-900 bg-slate-50' : ''">
                            <input type="radio" x-model="prolongerModal.depart" value="debut" class="mt-1">
                            <div>
                                <div class="text-sm font-semibold text-slate-900">Durée totale depuis la connexion</div>
                                <div class="text-xs text-slate-500">La valeur saisie est la durée totale (ex : 72 = 72 min depuis le début).</div>
                            </div>
                        </label>
                    </div>
                </div>

                <div class="flex flex-wrap gap-2">
                    <template x-for="r in prolongerModal.raccourcis" :key="r.label">
                        <button type="button" @click="appliquerRaccourciProlongation(r)"
                                class="rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-sm font-medium text-slate-700 hover:bg-slate-50"
                                x-text="r.label"></button>
                    </template>
                </div>

                <template x-if="prolongerModal.erreur">
                    <div class="rounded-lg border border-rose-200 bg-rose-50 px-4 py-2.5 text-base text-rose-700" x-text="prolongerModal.erreur"></div>
                </template>
            </div>
            <div class="flex items-center justify-end gap-3 border-t border-slate-100 bg-slate-50 px-6 py-4">
                <button type="button" @click="prolongerModal.ouvert = false"
                        class="rounded-lg border border-slate-200 bg-white px-5 py-2.5 text-base font-medium text-slate-700 hover:bg-slate-50">Annuler</button>
                <button type="button" @click="confirmerProlongation()" :disabled="prolongerModal.enCours"
                        class="inline-flex items-center gap-2 rounded-lg bg-emerald-600 px-5 py-2.5 text-base font-medium text-white hover:bg-emerald-500 disabled:opacity-50">
                    <x-icone nom="plus" class="h-5 w-5" />
                    <span x-text="prolongerModal.enCours ? 'Enregistrement…' : 'Prolonger'"></span>
                </button>
            </div>
        </div>
    </div>
    </template>

    {{-- Modale Réglages --}}
    <template x-teleport="body">
    <div x-show="reglages.ouvert" x-cloak class="fixed inset-0 z-[9300] flex items-center justify-center p-4">
        <div class="absolute inset-0 bg-slate-900/60" x-transition.opacity></div>
        <div x-show="reglages.ouvert" x-transition class="relative w-full max-w-3xl overflow-hidden rounded-2xl bg-white shadow-2xl">
            <div class="flex items-center justify-between border-b border-slate-100 px-6 py-4">
                <div>
                    <h2 class="text-xl font-bold text-slate-900">Réglages de l'alarme</h2>
                    <p class="text-sm text-slate-500">Les modifications ne sont prises en compte qu'après "Enregistrer"</p>
                </div>
                <button type="button" @click="fermerReglages()" class="rounded p-1 text-slate-400 hover:bg-slate-100">
                    <x-icone nom="x" class="h-6 w-6" />
                </button>
            </div>
            <div class="flex gap-2 border-b border-slate-100 px-6 py-2">
                <button type="button" @click="reglages.tab = 'general'"
                        :class="reglages.tab === 'general' ? 'bg-slate-100 text-slate-900' : 'text-slate-500'"
                        class="rounded-md px-4 py-2 text-base font-medium">Général</button>
                <button type="button" @click="reglages.tab = 'sons'"
                        :class="reglages.tab === 'sons' ? 'bg-slate-100 text-slate-900' : 'text-slate-500'"
                        class="rounded-md px-4 py-2 text-base font-medium">Sons</button>
            </div>
            <div class="max-h-[60vh] overflow-y-auto p-6 text-base">
                {{-- Onglet Général --}}
                <div x-show="reglages.tab === 'general'" class="space-y-6">
                    <div class="flex items-center justify-between rounded-lg border border-slate-200 p-4">
                        <div>
                            <div class="text-base font-semibold text-slate-900"
                                 x-text="reglages.form.enabled ? 'Alarme sonore activée' : 'Alarme sonore désactivée'"></div>
                            <div class="text-sm text-slate-500">Désactiver l'alarme empêche toute sonnerie.</div>
                        </div>
                        <button type="button" @click="basculerActivation()"
                                :class="reglages.form.enabled ? 'bg-emerald-500' : 'bg-rose-500'"
                                class="relative h-8 w-14 rounded-full transition">
                            <span class="absolute top-1 h-6 w-6 rounded-full bg-white transition-all"
                                  :class="reglages.form.enabled ? 'left-7' : 'left-1'"></span>
                        </button>
                    </div>
                    <div class="rounded-lg border border-slate-200 p-4 space-y-3">
                        <div class="text-base font-semibold text-slate-700">Durée de la sonnerie</div>
                        <label class="flex items-center gap-3">
                            <input type="radio" x-model="reglages.form.modeDuree" value="auto">
                            <div>
                                <div class="font-medium text-slate-900">Automatique (recommandé)</div>
                                <div class="text-sm text-slate-500">Tant que l'opérateur n'a pas cliqué « Arrêter l'alarme ».</div>
                            </div>
                        </label>
                        <label class="flex items-center gap-3">
                            <input type="radio" x-model="reglages.form.modeDuree" value="manuel">
                            <div>
                                <div class="font-medium text-slate-900">Durée limitée</div>
                                <div class="text-sm text-slate-500">Le son s'arrête tout seul après la durée définie.</div>
                            </div>
                        </label>
                        <div x-show="reglages.form.modeDuree === 'manuel'" class="flex items-center gap-2 pl-7">
                            <input type="number" min="0" max="10" x-model.number="reglages.form.dureeMin"
                                   class="w-20 rounded-lg border-2 border-slate-300 px-3 py-2 text-center font-mono text-base">
                            <span class="text-sm text-slate-500">min</span>
                            <input type="number" min="0" max="59" x-model.number="reglages.form.dureeSec"
                                   class="w-20 rounded-lg border-2 border-slate-300 px-3 py-2 text-center font-mono text-base">
                            <span class="text-sm text-slate-500">s</span>
                            <span class="text-xs text-slate-500 ml-2">(10 s à 10 min)</span>
                        </div>
                    </div>
                    <div>
                        <label class="mb-2 block text-base font-semibold text-slate-700">Taille maximale d'un fichier son</label>
                        <div class="flex items-center gap-2">
                            <input type="number" min="1" max="10" step="1" x-model.number="reglages.form.tailleMax"
                                   class="w-24 rounded-lg border-2 border-slate-300 px-3 py-2 text-center font-mono text-base">
                            <span class="text-base text-slate-600 font-medium">Mo</span>
                            <span class="text-sm text-slate-500">(entre 1 et 10 Mo)</span>
                        </div>
                    </div>
                </div>

                {{-- Onglet Sons --}}
                <div x-show="reglages.tab === 'sons'" class="space-y-5">
                    <div class="rounded-lg border border-slate-200 p-4 space-y-3">
                        <div class="flex items-center justify-between">
                            <label class="text-base font-semibold text-slate-700">Volume</label>
                            <span class="text-base font-mono text-slate-900"
                                  x-text="(reglages.form.volume ?? 70) + ' %'"></span>
                        </div>
                        <div class="flex items-center gap-3">
                            <input type="range" min="0" max="100" step="5" x-model.number="reglages.form.volume" class="flex-1">
                            <input type="number" min="0" max="100" x-model.number="reglages.form.volume"
                                   class="w-20 rounded-lg border-2 border-slate-300 px-2 py-1.5 text-center font-mono text-base">
                            <span class="text-base text-slate-500">%</span>
                        </div>
                        <p class="text-sm text-slate-500">Le volume s'applique aussi aux sons de test.</p>
                    </div>

                    <div class="rounded-lg border-2 border-emerald-300 bg-emerald-50 p-3">
                        <div class="text-xs font-semibold uppercase tracking-wide text-emerald-700">Son actif (utilisé pour l'alarme)</div>
                        <div class="mt-1 flex items-center justify-between gap-3">
                            <div class="min-w-0">
                                <div class="text-base font-semibold text-slate-900 truncate"
                                     x-text="reglages.form.sonNom || 'Bip par défaut'"></div>
                            </div>
                            <button type="button" @click="testerSon('actif')"
                                    class="inline-flex h-9 items-center gap-1.5 rounded-lg border border-slate-200 bg-white px-3 text-sm font-medium text-slate-700 hover:bg-slate-50">
                                <span x-show="!(testEnCours && testId === 'actif')"><x-icone nom="bell" class="h-4 w-4" /></span>
                                <span x-show="testEnCours && testId === 'actif'" x-cloak><x-icone nom="pause" class="h-4 w-4" /></span>
                                <span x-text="testEnCours && testId === 'actif' ? 'Stop' : 'Tester'"></span>
                            </button>
                        </div>
                    </div>

                    <div>
                        <div class="text-xs font-semibold uppercase tracking-wide text-slate-400 mb-2">Sons par défaut</div>
                        <ul class="space-y-2">
                            <template x-for="s in cfg.sonsParDefaut" :key="s.id">
                                <li class="flex items-center justify-between rounded-lg border border-slate-200 px-3 py-2.5"
                                    x-show="!(reglages.form.sonId === s.id)">
                                    <div>
                                        <div class="text-base font-medium text-slate-900" x-text="s.nom"></div>
                                        <div class="text-sm text-slate-500" x-text="s.desc"></div>
                                    </div>
                                    <div class="flex items-center gap-2">
                                        <button type="button" @click="testerSon(s.id)"
                                                class="inline-flex h-9 items-center gap-1 rounded-lg border border-slate-200 bg-white px-3 text-sm font-medium text-slate-700 hover:bg-slate-50">
                                            <span x-show="!(testEnCours && testId === s.id)"><x-icone nom="bell" class="h-4 w-4" /></span>
                                            <span x-show="testEnCours && testId === s.id" x-cloak><x-icone nom="pause" class="h-4 w-4" /></span>
                                            <span x-text="testEnCours && testId === s.id ? 'Stop' : 'Tester'"></span>
                                        </button>
                                        <button type="button" @click="choisirSonDefaut(s)"
                                                class="inline-flex h-9 items-center rounded-lg bg-slate-900 px-3 text-sm font-medium text-white hover:bg-slate-700">
                                            Choisir
                                        </button>
                                    </div>
                                </li>
                            </template>
                        </ul>
                    </div>

                    <div>
                        <div class="text-xs font-semibold uppercase tracking-wide text-slate-400 mb-2">Sons personnalisés</div>
                        <template x-if="reglages.sonsUploades.length === 0">
                            <p class="text-sm text-slate-500 italic">Aucun son personnalisé.</p>
                        </template>
                        <ul class="space-y-2">
                            <template x-for="s in reglages.sonsUploades" :key="s.nom">
                                <li class="flex items-center justify-between rounded-lg border border-slate-200 px-3 py-2.5"
                                    x-show="reglages.form.sonUrl !== s.url">
                                    <div class="min-w-0">
                                        <div class="text-base font-medium text-slate-900 truncate" x-text="s.nom"></div>
                                    </div>
                                    <div class="flex items-center gap-2">
                                        <button type="button" @click="testerSon(s.url)"
                                                class="inline-flex h-9 items-center gap-1 rounded-lg border border-slate-200 bg-white px-3 text-sm font-medium text-slate-700 hover:bg-slate-50">
                                            <span x-show="!(testEnCours && testId === s.url)"><x-icone nom="bell" class="h-4 w-4" /></span>
                                            <span x-show="testEnCours && testId === s.url" x-cloak><x-icone nom="pause" class="h-4 w-4" /></span>
                                            <span x-text="testEnCours && testId === s.url ? 'Stop' : 'Tester'"></span>
                                        </button>
                                        <button type="button" @click="choisirSonUpload(s)"
                                                class="inline-flex h-9 items-center rounded-lg bg-slate-900 px-3 text-sm font-medium text-white hover:bg-slate-700">
                                            Choisir
                                        </button>
                                        <button type="button" @click="demanderSupprSon(s)"
                                                class="inline-flex h-9 items-center gap-1 rounded-lg border border-rose-200 bg-white px-3 text-sm font-medium text-rose-700 hover:bg-rose-50">
                                            <x-icone nom="trash" class="h-4 w-4" />
                                        </button>
                                    </div>
                                </li>
                            </template>
                        </ul>
                    </div>

                    <div>
                        <div class="text-xs font-semibold uppercase tracking-wide text-slate-400 mb-2">Ajouter un son</div>
                        <div @dragover.prevent="dragActif = true" @dragleave.prevent="dragActif = false" @drop.prevent="dropSon($event)"
                             :class="dragActif ? 'border-emerald-500 bg-emerald-50' : 'border-slate-300 bg-slate-50'"
                             class="flex flex-col items-center justify-center rounded-xl border-4 border-dashed p-8 text-center transition">
                            <x-icone nom="upload-cloud" class="h-12 w-12 text-slate-400 mb-2" />
                            <div class="text-base font-medium text-slate-700">Glissez un fichier ici</div>
                            <div class="text-sm text-slate-500 my-2">ou</div>
                            <label class="cursor-pointer rounded-lg bg-slate-900 px-4 py-2 text-base font-medium text-white hover:bg-slate-700">
                                Choisir un fichier
                                <input type="file" accept="audio/*" @change="uploaderSon($event)" class="hidden">
                            </label>
                            <p class="text-sm text-slate-500 mt-3">
                                Formats : MP3, WAV, OGG, M4A, AAC — max <span x-text="(reglages.form.tailleMax ?? 10) + ' Mo'"></span>
                            </p>
                        </div>
                        <div x-show="reglages.uploadEnCours" class="mt-2 text-base font-medium text-sky-700">Envoi en cours…</div>
                        <p x-show="reglages.uploadErreur" x-text="reglages.uploadErreur" class="mt-2 text-base text-rose-600"></p>
                        <p x-show="reglages.uploadOk" x-text="reglages.uploadOk" class="mt-2 text-base text-emerald-600"></p>
                    </div>
                </div>
            </div>
            <div class="flex items-center justify-between border-t border-slate-100 bg-slate-50 px-6 py-4">
                <button type="button" @click="demanderReinitialisation()"
                        class="inline-flex items-center gap-2 rounded-lg border border-amber-200 bg-white px-4 py-2.5 text-base font-medium text-amber-700 hover:bg-amber-50">
                    <x-icone nom="refresh" class="h-5 w-5" /> Réinitialiser
                </button>
                <div class="flex items-center gap-3">
                    <button type="button" @click="fermerReglages()"
                            class="rounded-lg border border-slate-200 bg-white px-5 py-2.5 text-base font-medium text-slate-700 hover:bg-slate-50">Annuler</button>
                    <button type="button" @click="enregistrerReglages()" :disabled="reglages.enCours"
                            class="inline-flex items-center gap-2 rounded-lg bg-slate-900 px-5 py-2.5 text-base font-medium text-white hover:bg-slate-700 disabled:opacity-50">
                        <x-icone nom="check" class="h-5 w-5" />
                        <span x-text="reglages.enCours ? 'Enregistrement…' : 'Enregistrer'"></span>
                    </button>
                </div>
            </div>
        </div>
    </div>
    </template>

    {{-- Modale confirmation suppression son --}}
    <div x-show="supprSonModal.ouvert" x-cloak class="fixed inset-0 z-[220] flex items-center justify-center p-4">
        <div class="absolute inset-0 bg-slate-900/60" @click="supprSonModal.ouvert = false" x-transition.opacity></div>
        <div x-show="supprSonModal.ouvert" x-transition class="relative w-full max-w-md overflow-hidden rounded-2xl bg-white shadow-2xl">
            <div class="border-b border-slate-100 px-6 py-4">
                <h2 class="text-xl font-bold text-slate-900">Supprimer ce son ?</h2>
                <p class="text-base text-slate-500 mt-1" x-text="supprSonModal.nom"></p>
            </div>
            <div class="px-6 py-4 text-base text-slate-600">
                Le fichier sera retiré définitivement. Si c'était le son actif, le bip par défaut sera utilisé.
            </div>
            <div class="flex items-center justify-end gap-3 border-t border-slate-100 bg-slate-50 px-6 py-4">
                <button type="button" @click="supprSonModal.ouvert = false"
                        class="rounded-lg border border-slate-200 bg-white px-5 py-2.5 text-base font-medium text-slate-700 hover:bg-slate-50">Annuler</button>
                <button type="button" @click="confirmerSupprSon()"
                        class="inline-flex items-center gap-2 rounded-lg bg-rose-600 px-5 py-2.5 text-base font-medium text-white hover:bg-rose-500">
                    <x-icone nom="trash" class="h-5 w-5" /> Supprimer
                </button>
            </div>
        </div>
    </div>

    {{-- Modale réinitialisation --}}
    <div x-show="resetModal.ouvert" x-cloak class="fixed inset-0 z-[220] flex items-center justify-center p-4">
        <div class="absolute inset-0 bg-slate-900/60" @click="resetModal.ouvert = false" x-transition.opacity></div>
        <div x-show="resetModal.ouvert" x-transition class="relative w-full max-w-md overflow-hidden rounded-2xl bg-white shadow-2xl">
            <div class="border-b border-slate-100 px-6 py-4">
                <h2 class="text-xl font-bold text-slate-900">Réinitialiser les réglages ?</h2>
            </div>
            <div class="px-6 py-4 text-base text-slate-600">
                Tous les réglages reviendront aux valeurs par défaut. <strong>Les sons personnalisés ne seront pas supprimés.</strong>
            </div>
            <div class="flex items-center justify-end gap-3 border-t border-slate-100 bg-slate-50 px-6 py-4">
                <button type="button" @click="resetModal.ouvert = false"
                        class="rounded-lg border border-slate-200 bg-white px-5 py-2.5 text-base font-medium text-slate-700 hover:bg-slate-50">Annuler</button>
                <button type="button" @click="confirmerReinitialisation()"
                        class="inline-flex items-center gap-2 rounded-lg bg-amber-600 px-5 py-2.5 text-base font-medium text-white hover:bg-amber-500">
                    <x-icone nom="refresh" class="h-5 w-5" /> Réinitialiser
                </button>
            </div>
        </div>
    </div>

    <audio x-ref="audio" preload="auto"></audio>
</div>

<script>
function wifiPage(cfg) {
    return {
        cfg,
        lignes: cfg.lignesInitiales ?? [],
        stats: cfg.statsInitiales ?? {connectes:0, deconnectes:0, termines:0, volume_total:0},
        vouchersDisponibles: (cfg.vouchersDisponibles ?? []).map(v => ({...v, masque: true, transition: false})),
        alarme: { ...cfg.alarme },
        derniereSync: '',
        notifAutorisee: (typeof Notification !== 'undefined') && Notification.permission === 'granted',

        filtreEtat: '', recherche: '', cleTri: 'date_debut', sensTri: 'desc', selected: [],

        confirmation: { ouvert: false, titre: '', message: '', bouton: '', danger: false, enCours: false, action: null },

        reglages: {
            ouvert: false, tab: 'general', enCours: false,
            form: {
                enabled: true, volume: 70, modeDuree: 'auto',
                dureeMin: 0, dureeSec: 30,
                tailleMax: 10,
                sonId: 'bip-classique', sonNom: 'Bip par défaut', sonUrl: null,
            },
            sonsUploades: [],
            uploadEnCours: false, uploadErreur: '', uploadOk: '',
        },

        alarmeModal: {
            ouvert: false, ligne: null, modifier: false,
            type: 'duree', h: 0, m: 30, montant: 1000, depart: 'maintenant',
            raccourcis: [
                { label: '15 min', type: 'duree', h: 0, m: 15 },
                { label: '30 min', type: 'duree', h: 0, m: 30 },
                { label: '1 h', type: 'duree', h: 1, m: 0 },
                { label: '500 Ar', type: 'montant', montant: 500 },
                { label: '1000 Ar', type: 'montant', montant: 1000 },
            ],
            apercu: '', erreur: '', enCours: false,
        },

        prolongerModal: {
            ouvert: false, ligne: null, type: 'duree', depart: 'apres', h: 0, m: 15, montant: 500,
            raccourcis: [
                { label: '+15 min', type: 'duree', h: 0, m: 15 },
                { label: '+30 min', type: 'duree', h: 0, m: 30 },
                { label: '+1 h', type: 'duree', h: 1, m: 0 },
                { label: '+500 Ar', type: 'montant', montant: 500 },
                { label: '+1000 Ar', type: 'montant', montant: 1000 },
            ],
            erreur: '', enCours: false ouvrirProlonger(l, de, depuisFenetre: false,
        },

        supprSonModal: { ouvert: false, nom: '', son: null },
        resetModal: { ouvert: false },

        fenetresAlarme: [],

        dragActif: false,

        testEnCours: false,
        testId: null,
        _audioTimeout: null,
        _lastBeep: 0,
        _ctxAudio: null,
        _titreInterval: null,
        _alarmesFermees: new Set(),

        volumeCache: {},
        now: Date.now(),

        init() {
            if (this.alarme.son_url && this.$refs.audio) {
                this.$refs.audio.src = this.alarme.son_url;
            }
            setInterval(() => { this.now = Date.now(); this.verifierAlarmes(); }, 1000);
            setInterval(() => this.rafraichir(), 2000);
            document.addEventListener('click', () => this.debloquerAudio(), { once: true });
            this.chargerReglages();
        },

        async rafraichir(manuel = false) {
            try {
                const r = await fetch(cfg.urlEtat, { headers: { 'Accept': 'application/json' } });
                if (!r.ok) return;
                const d = await r.json();
                const anciens = new Map(this.lignes.map(x => [x.id, x]));
                this.lignes = (d.lignes ?? []).map(nl => {
                    const old = anciens.get(nl.id);
                    if (old && old._focusRemarque) {
                        // L'utilisateur est en train de taper : on garde sa valeur.
                        nl.remarque = old.remarque;
                        nl._focusRemarque = true;
                    }
                    return nl;
                });
                this.stats = d.stats ?? this.stats;
                this.mettreAJourVouchers(d.vouchers_disponibles ?? []);
                this.derniereSync = new Date().toLocaleTimeString('fr-FR');
                const ids = this.lignes.map(l => String(l.id));
                this.selected = this.selected.filter(id => ids.includes(id));
            } catch (e) {}
        },

        mettreAJourVouchers(nouveaux) {
            const idsNouveaux = new Set(nouveaux.map(v => v.id));

            // Retirer ceux qui ne sont plus dans la réponse (utilisés depuis)
            this.vouchersDisponibles.forEach(v => {
                if (!idsNouveaux.has(v.id) && !v.transition) {
                    v.transition = true;
                    setTimeout(() => {
                        this.vouchersDisponibles = this.vouchersDisponibles.filter(x => x.id !== v.id);
                        // Ajouter les nouveaux qui ne sont pas encore là
                        nouveaux.forEach(nv => {
                            if (!this.vouchersDisponibles.find(x => x.id === nv.id)) {
                                this.vouchersDisponibles.push({...nv, masque: true, transition: false});
                            }
                        });
                    }, 2000);
                }
            });

            // Ajouter les nouveaux immédiatement
            nouveaux.forEach(nv => {
                if (!this.vouchersDisponibles.find(x => x.id === nv.id)) {
                    this.vouchersDisponibles.push({...nv, masque: true, transition: false});
                }
            });
        },

        verifierAlarmes() {
            this.lignes.forEach(l => {
                const fenetreOuverte = this.fenetresAlarme.find(f => f.id === l.id);

                // Fermer si l'utilisateur a cliqué "Arrêter l'alarme"
                if (this._alarmesFermees.has(l.id)) {
                    if (fenetreOuverte) this.fermerFenetre(l.id);
                    return;
                }

                // Si l'alarme est acquittée côté serveur (ex. autre onglet) → fermer
                if (l.alarme.acquittee && fenetreOuverte) {
                    this.fermerFenetre(l.id);
                    this._alarmesFermees.add(l.id);
                    return;
                }

                // Alarme posée et toujours décomptée
                if (l.alarme.active && l.alarme.fin) {
                    const fin = new Date(l.alarme.fin).getTime();

                    // Déclenchement
                    if (this.now >= fin && !fenetreOuverte && l.etat_appareil === 'connecte') {
                        this.ouvrirFenetreAlarme(l);
                    }

                    // Si la fin a été repoussée (prolongation), fermer l'ancienne fenêtre
                    if (this.now < fin && fenetreOuverte) {
                        this.fermerFenetre(l.id);
                    }
                }
            });

            // Dépassement affiché dans les fenêtres ouvertes
            this.fenetresAlarme.forEach(f => {
                const l = this.lignes.find(x => x.id === f.id);
                if (l && l.alarme.fin) {
                    const fin = new Date(l.alarme.fin).getTime();
                    f.depassementSecondes = Math.max(0, Math.floor((this.now - fin) / 1000));
                    if (l.tarif) {
                        f.depassementMontant = Math.floor((f.depassementSecondes / 60) * l.tarif.montant_par_minute);
                    }
                }
            });

            // --- SONNERIE : respecter alarme.duree_secondes ---
            if (this.fenetresAlarme.length > 0 && this.alarme.enabled) {
                const dureeMax = parseInt(this.alarme.duree_secondes ?? 0, 10);

                // Filtre : fenêtres dont la sonnerie n'a pas expiré
                const fenetresActives = this.fenetresAlarme.filter(f => {
                    if (!f._debutSonnerie) f._debutSonnerie = this.now;
                    if (!dureeMax || dureeMax <= 0) return true; // "Automatique" : sans limite
                    return (this.now - f._debutSonnerie) < dureeMax * 1000;
                });

                if (fenetresActives.length > 0) {
                    if (this.now - this._lastBeep > 2500) {
                        this._lastBeep = this.now;
                        this.jouerSon();
                    }
                }
            }
        },

        ouvrirFenetreAlarme(l) {
            this.fenetresAlarme.push({
                id: l.id,
                appareil: l.appareil,
                voucher: l.voucher?.username || null,
                forfait: l.alarme.type === 'duree'
                    ? this.formatDuree(l.alarme.minutes)
                    : this.formatAr(l.alarme.montant),
                heureFin: this.heureExacte(l.alarme.fin),
                depassementSecondes: 0,
                depassementMontant: 0,
                _debutSonnerie: Date.now(),   // <-- ajout
                prolongationsRapides: [
                    { label: '+15 min', type: 'duree', h: 0, m: 15 },
                    { label: '+30 min', type: 'duree', h: 0, m: 30 },
                    { label: '+500 Ar', type: 'montant', montant: 500 },
                    { label: '+1000 Ar', type: 'montant', montant: 1000 },
                ],
            });

            this.marquerDeclenchee(l.id);
            if (l.alarme) l.alarme.declenchee = true;

            if (typeof Notification !== 'undefined' && Notification.permission === 'granted') {
                try {
                    new Notification('Cyber Manager — Alarme', {
                        body: `${l.appareil} a atteint son forfait.`,
                        icon: '/favicon.ico',
                        tag: 'wifi-alarm-' + l.id,
                        requireInteraction: true,
                    });
                } catch (e) {}
            }
            this.clignoterTitre(true);
        },

        fermerFenetre(id) {
            this.fenetresAlarme = this.fenetresAlarme.filter(f => f.id !== id);
            if (this.fenetresAlarme.length === 0) {
                this.arreterSon();
                this.clignoterTitre(false);
            }
        },

        clignoterTitre(on) {
            if (on) {
                if (!this._titreInterval) {
                    const base = 'Wi-Fi — Cyber Manager';
                    let toggle = false;
                    this._titreInterval = setInterval(() => {
                        document.title = toggle ? 'ALARME - Temps écoulé' : base;
                        toggle = !toggle;
                    }, 800);
                }
            } else if (this._titreInterval) {
                clearInterval(this._titreInterval);
                this._titreInterval = null;
                document.title = 'Wi-Fi — Cyber Manager';
            }
        },

        async marquerDeclenchee(id) {
            try {
                await window.cmFetch(`${cfg.urlSession}/${id}/alarme/declencher`, { method: 'POST' });
            } catch (e) {}
        },

        async acquitterAlarme(f) {
            this.arreterSon();
            this._alarmesFermees.add(f.id);
            this.fermerFenetre(f.id);
            try {
                const r = await window.cmFetch(`${cfg.urlSession}/${f.id}/alarme/acquitter`, { method: 'POST' });
                const d = await r.json();
                // Le serveur renvoie les lignes à jour : on rafraîchit tout de suite.
                if (d && d.lignes) {
                    this.lignes = d.lignes;
                    this.stats = d.stats ?? this.stats;
                }
            } catch (e) {}
            await this.rafraichir();
        },

        async arreterEtFermer(f) {
            this.arreterSon();
            this._alarmesFermees.add(f.id);
            this.fermerFenetre(f.id);
            try {
                const r = await window.cmFetch(`${cfg.urlSession}/${f.id}/arreter`, { method: 'POST' });
                const d = await r.json();
                window.dispatchEvent(new CustomEvent('toast', { detail: d.message || 'OK' }));
            } catch (e) {}
            await this.rafraichir();
        },

        async prolongerDepuisFenetre(f, p) {
            try {
                const r = await window.cmFetch(`${cfg.urlSession}/${f.id}/alarme/prolonger`, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ type: p.type, valeur: p.montant || (p.h * 60 + p.m) }),
                });
                const d = await r.json();
                if (!r.ok || !d.ok) {
                    window.dispatchEvent(new CustomEvent('toast', { detail: d.message || 'Erreur.' }));
                    return;
                }
                window.dispatchEvent(new CustomEvent('toast', { detail: d.message }));
                this.fermerFenetre(f.id);
                this._alarmesFermees.delete(f.id);
                if (d.lignes) {
                    this.lignes = d.lignes;
                    this.stats = d.stats ?? this.stats;
                }
                await this.rafraichir();
            } catch (e) {}
        },

        ouvrirProlongerDepuisFenetre(f) {
            const l = this.lignes.find(x => x.id === f.id);
            if (l) this.ouvrirProlonger(l, true);
        },

        ouvrirAlarme(l, modifier = false) {
            this.alarmeModal = {
                ouvert: true, ligne: l, modifier: modifier,
                type: l.alarme.type || 'duree',
                h: 0, m: l.alarme.minutes || 30,
                montant: l.alarme.montant || 1000,
                depart: 'maintenant',
                raccourcis: this.alarmeModal.raccourcis,
                apercu: '', erreur: '', enCours: false,
            };
            this.majApercu();
        },

        appliquerRaccourci(r) {
            if (r.type === 'duree') {
                this.alarmeModal.type = 'duree';
                this.alarmeModal.h = r.h;
                this.alarmeModal.m = r.m;
            } else {
                this.alarmeModal.type = 'montant';
                this.alarmeModal.montant = r.montant;
            }
            this.majApercu();
        },

        majApercu() {
            const l = this.alarmeModal.ligne;
            if (!l) return;
            const secDeja = this.secondesConsommees(l);
            const minutesDeja = Math.floor(secDeja / 60);
            let minutesCible = 0;
            if (this.alarmeModal.type === 'duree') {
                minutesCible = this.alarmeModal.h * 60 + this.alarmeModal.m;
            } else {
                minutesCible = l.tarif
                    ? Math.floor(this.alarmeModal.montant / l.tarif.montant_par_minute)
                    : Math.floor(this.alarmeModal.montant / 20);
            }
            const minutesRestantes = this.alarmeModal.depart === 'debut'
                ? minutesCible - minutesDeja
                : minutesCible;
            if (minutesRestantes <= 0) {
                this.alarmeModal.apercu = "Cette limite est déjà dépassée.";
                return;
            }
            const fin = new Date(this.now + minutesRestantes * 60000);
            const heureFin = String(fin.getHours()).padStart(2,'0') + ':' +
                             String(fin.getMinutes()).padStart(2,'0');
            this.alarmeModal.apercu = `Déjà consommé : ${this.formatDureeSec(secDeja)} (${this.formatAr(this.montantTempsReel(l))}). L'alarme sonnera à ${heureFin}, dans ${this.formatDureeSec(minutesRestantes * 60)}.`;
        },

        async confirmerAlarme() {
            const l = this.alarmeModal.ligne;
            if (!l) return;
            this.alarmeModal.enCours = true;
            this.alarmeModal.erreur = '';
            const body = {
                type: this.alarmeModal.type,
                depart: this.alarmeModal.depart,
                valeur: this.alarmeModal.type === 'duree'
                    ? (this.alarmeModal.h * 60 + this.alarmeModal.m)
                    : this.alarmeModal.montant,
            };
            try {
                const r = await window.cmFetch(`${cfg.urlSession}/${l.id}/alarme`, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(body),
                });
                const d = await r.json();
                if (!r.ok || !d.ok) {
                    this.alarmeModal.erreur = d.message || 'Erreur.';
                    this.alarmeModal.enCours = false;
                    return;
                }
                window.dispatchEvent(new CustomEvent('toast', { detail: d.message }));
                this.alarmeModal.ouvert = false;
                this._alarmesFermees.delete(l.id);
                await this.rafraichir();
            } catch (e) {
                this.alarmeModal.erreur = 'Erreur réseau.';
            }
            this.alarmeModal.enCours = false;
        },

        async annulerAlarme() {
            const l = this.alarmeModal.ligne;
            if (!l) return;
            try {
                await window.cmFetch(`${cfg.urlSession}/${l.id}/alarme/annuler`, { method: 'POST' });
                window.dispatchEvent(new CustomEvent('toast', { detail: 'Alarme annulée.' }));
                this.alarmeModal.ouvert = false;
                this._alarmesFermees.delete(l.id);
                await this.rafraichir();
            } catch (e) {}
        },

        ouvrirProlonger(l, depuisFenetre = false) {
            this.prolongerModal = {
                ouvert: true, ligne: l, depuisFenetre: depuisFenetre,
                type: 'duree', depart: 'apres', h: 0, m: 15, montant: 500,
                raccourcis: [
                    { label: '+15 min', type: 'duree', h: 0, m: 15 },
                    { label: '+30 min', type: 'duree', h: 0, m: 30 },
                    { label: '+1 h', type: 'duree', h: 1, m: 0 },
                    { label: '+500 Ar', type: 'montant', montant: 500 },
                    { label: '+1000 Ar', type: 'montant', montant: 1000 },
                ],
                erreur: '', enCours: false,
            };
        },

        appliquerRaccourciProlongation(r) {
            if (r.type === 'duree') {
                this.prolongerModal.type = 'duree';
                this.prolongerModal.h = r.h;
                this.prolongerModal.m = r.m;
            } else {
                this.prolongerModal.type = 'montant';
                this.prolongerModal.montant = r.montant;
            }
        },

        async confirmerProlongation() {
            const l = this.prolongerModal.ligne;
            if (!l) return;
            this.prolongerModal.enCours = true;
            const body = {
                type: this.prolongerModal.type,
                depart: this.prolongerModal.depart,
                valeur: this.prolongerModal.type === 'duree'
                    ? (this.prolongerModal.h * 60 + this.prolongerModal.m)
                    : this.prolongerModal.montant,
            };
            try {
                const r = await window.cmFetch(`${cfg.urlSession}/${l.id}/alarme/prolonger`, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(body),
                });
                const d = await r.json();
                if (!r.ok || !d.ok) {
                    this.prolongerModal.erreur = d.message || 'Erreur.';
                    this.prolongerModal.enCours = false;
                    return;
                }
                window.dispatchEvent(new CustomEvent('toast', { detail: d.message }));
                this.prolongerModal.ouvert = false;
                if (this.prolongerModal.depuisFenetre) {
                    this._alarmesFermees.delete(l.id);
                    this.fermerFenetre(l.id);
                }
                await this.rafraichir();
            } catch (e) {
                this.prolongerModal.erreur = 'Erreur réseau.';
            }
            this.prolongerModal.enCours = false;
        },

        // -----------------------------------------------------------------
        // ═══ CONFIRMATIONS DYNAMIQUES ═══
        // -----------------------------------------------------------------
        ouvrirConfirmation(opts) {
            this.confirmation = {
                ouvert: true, enCours: false,
                titre: '', message: '', bouton: '', danger: false, action: null,
                ...opts,
            };
        },

        async executerConfirmation() {
            const action = this.confirmation.action;
            if (!action) { this.confirmation.ouvert = false; return; }
            this.confirmation.enCours = true;
            try { await action(); }
            finally {
                this.confirmation.enCours = false;
                this.confirmation.ouvert = false;
            }
        },

        confirmerArret(l) {
            this.ouvrirConfirmation({
                titre: 'Arrêter la session ?',
                message: `La durée et le montant de <strong>${this.escapeHtml(l.appareil)}</strong> seront figés immédiatement.<br><br>Le client reste connecté au Wi-Fi ; la déconnexion se fera via l'action Supprimer.`,
                bouton: 'Arrêter la session',
                danger: false,
                action: async () => {
                    const r = await window.cmFetch(`${cfg.urlSession}/${l.id}/arreter`, { method: 'POST' });
                    const d = await r.json();
                    if (!r.ok || !d.ok) {
                        window.dispatchEvent(new CustomEvent('toast', { detail: d.message || 'Erreur.' }));
                        return;
                    }
                    window.dispatchEvent(new CustomEvent('toast', { detail: d.message }));
                    await this.rafraichir();
                }
            });
        },

                confirmerContinuer(l) {
            this.ouvrirConfirmation({
                titre: 'Continuer cette session ?',
                message: `La durée et le montant de <strong>${this.escapeHtml(l.appareil)}</strong> vont reprendre.<br><br>Le temps passé hors ligne n'est pas facturé.`,
                bouton: 'Continuer',
                danger: false,
                action: async () => {
                    const r = await window.cmFetch(`${cfg.urlContinuer}/${l.id}/continuer`, { method: 'POST' });
                    const d = await r.json();
                    if (!r.ok || !d.ok) {
                        window.dispatchEvent(new CustomEvent('toast', { detail: d.message || 'Erreur.' }));
                        return;
                    }
                    window.dispatchEvent(new CustomEvent('toast', { detail: d.message }));
                    await this.rafraichir();
                }
            });
        },
        
        confirmerSuppression(l) {
            const montant = this.montantFinalCalcule(l);
            const montantTxt = Number(montant || 0).toLocaleString('fr-FR');
            const voucherTxt = l.voucher?.username
                ? `Son identifiant <strong>${this.escapeHtml(l.voucher.username)}</strong> sera effacé du MikroTik.`
                : '';
            const alarmeTxt = l.alarme?.active
                ? `<br><br><span class="text-amber-700 font-semibold">⚠ Une alarme est active sur cet appareil.</span>`
                : '';

            this.ouvrirConfirmation({
                titre: 'Supprimer cet appareil ?',
                message: `L'appareil <strong>${this.escapeHtml(l.appareil)}</strong> sera retiré du tableau.<br><br>${voucherTxt}${alarmeTxt}<br><br>Montant à encaisser : <strong>${montantTxt} Ar</strong>.<br><br><span class="text-rose-600 font-semibold">Action définitive et irréversible.</span>`,
                bouton: 'Supprimer définitivement',
                danger: true,
                action: async () => {
                    await this.executerSuppression(l, false);
                }
            });
        },

        async executerSuppression(l, force) {
            const url = `${cfg.urlSession}/${l.id}` + (force ? '?force=1' : '');
            const r = await window.cmFetch(url, { method: 'DELETE' });
            const d = await r.json();

            // 409 : le voucher est encore utilisé ailleurs → 2e modale d'avertissement
            if (r.status === 409 && d.besoin_force) {
                const liste = (d.autres || [])
                    .map(x => `<li class="font-mono">${this.escapeHtml(x.mac || '—')}</li>`)
                    .join('');
                this.ouvrirConfirmation({
                    titre: '⚠ Cet identifiant est encore utilisé',
                    message:
                        `Le voucher <strong>${this.escapeHtml(l.voucher?.username || '')}</strong> ` +
                        `est actuellement associé à <strong>${d.autres.length}</strong> autre(s) connexion(s) active(s) :` +
                        `<ul class="list-disc pl-6 mt-2 text-sm">${liste}</ul><br>` +
                        `Sa suppression va interrompre ces connexions et couper leur accès Internet.<br><br>` +
                        `<strong>Voulez-vous vraiment continuer ?</strong>`,
                    bouton: 'Supprimer et déconnecter',
                    danger: true,
                    action: async () => {
                        await this.executerSuppression(l, true);
                    }
                });
                return;
            }

            if (!r.ok || !d.ok) {
                window.dispatchEvent(new CustomEvent('toast', { detail: d.message || 'Erreur.' }));
                if (d.redirect) window.location.href = d.redirect;
                return;
            }
            window.dispatchEvent(new CustomEvent('toast', { detail: d.message }));
            await this.rafraichir();
        },

        // Remarques (édition inline)
        async sauverRemarque(l) {
            const val = (l.remarque || '').trim();
            // Rien à sauver si inchangé
            if ((l._derniereRemarque ?? null) === val) return;
            try {
                const r = await window.cmFetch(`${cfg.urlRemarque}/${l.id}/remarque`, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ remarque: val || null }),
                });
                const d = await r.json();
                if (!r.ok || !d.ok) {
                    window.dispatchEvent(new CustomEvent('toast', { detail: d.message || 'Erreur.' }));
                    return;
                }
                l.remarque = d.remarque;
                l._editionRemarque = false;
                window.dispatchEvent(new CustomEvent('toast', { detail: 'Remarque enregistrée.' }));
            } catch (e) {
                window.dispatchEvent(new CustomEvent('toast', { detail: 'Erreur réseau.' }));
            }
        },

        demanderSuppressionMasse() {
            if (this.selected.length === 0) return;
            const nb = this.selected.length;
            this.ouvrirConfirmation({
                titre: `Supprimer ${nb} appareil(s) ?`,
                message: `Les ${nb} appareils sélectionnés seront retirés du tableau et leurs identifiants seront effacés du MikroTik.<br><br><span class="text-rose-600 font-semibold">Action définitive.</span>`,
                bouton: `Supprimer ${nb}`,
                danger: true,
                action: async () => {
                    const r = await window.cmFetch(cfg.urlSupprimerMasse, {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ ids: this.selected.map(i => parseInt(i, 10)) }),
                    });
                    const d = await r.json();
                    window.dispatchEvent(new CustomEvent('toast', { detail: d.message || 'Terminé.' }));
                    this.selected = [];
                    await this.rafraichir();
                }
            });
        },

        // -----------------------------------------------------------------
        // Réglages
        // -----------------------------------------------------------------
        async chargerReglages() {
            try {
                const r = await fetch(cfg.urlReglagesGet, { headers: { 'Accept': 'application/json' } });
                if (!r.ok) return;
                const d = await r.json();
                const tailleMo = d.taille_max_mo ?? 10;
                const volume   = (typeof d.volume === 'number' && !isNaN(d.volume)) ? d.volume : 70;
                const dureeSec = d.duree_secondes ?? 0;

                this.reglages.form = {
                    enabled: !!d.enabled,
                    volume: volume,
                    modeDuree: dureeSec > 0 ? 'manuel' : 'auto',
                    dureeMin: Math.floor(dureeSec / 60),
                    dureeSec: dureeSec % 60,
                    tailleMax: tailleMo,
                    sonId: d.son_nom ? null : 'bip-classique',
                    sonNom: d.son_nom || 'Bip par défaut',
                    sonUrl: d.son_url,
                };
                this.reglages.sonsUploades = d.sons_uploades ?? [];
                this.alarme.enabled = !!d.enabled;
                this.alarme.volume = volume;
                this.alarme.son_url = d.son_url;
                this.alarme.son_nom = d.son_nom;
                if (d.son_url && this.$refs.audio) {
                    this.$refs.audio.src = d.son_url;
                }
            } catch (e) {}
        },

        ouvrirReglages() {
            this.reglages.ouvert = true;
            this.reglages.tab = 'general';
            this.reglages.uploadErreur = '';
            this.reglages.uploadOk = '';
            this.chargerReglages();
        },

        fermerReglages() {
            this.reglages.ouvert = false;
            this.arreterSon();
            this.chargerReglages();
        },

        basculerActivation() {
            this.reglages.form.enabled = !this.reglages.form.enabled;
        },

        choisirSonDefaut(s) {
            this.reglages.form.sonId = s.id;
            this.reglages.form.sonNom = s.nom;
            this.reglages.form.sonUrl = null;
        },

        choisirSonUpload(s) {
            this.reglages.form.sonId = null;
            this.reglages.form.sonNom = s.nom;
            this.reglages.form.sonUrl = s.url;
        },

        async enregistrerReglages() {
            this.reglages.enCours = true;
            try {
                let dureeSec = 0;
                if (this.reglages.form.modeDuree === 'manuel') {
                    dureeSec = (this.reglages.form.dureeMin || 0) * 60 + (this.reglages.form.dureeSec || 0);
                    if (dureeSec < 10) dureeSec = 10;
                    if (dureeSec > 600) dureeSec = 600;
                }
                let tailleMo = parseInt(this.reglages.form.tailleMax ?? 10, 10);
                if (isNaN(tailleMo) || tailleMo < 1) tailleMo = 1;
                if (tailleMo > 10) tailleMo = 10;
                let volume = parseInt(this.reglages.form.volume ?? 70, 10);
                if (isNaN(volume) || volume < 0) volume = 0;
                if (volume > 100) volume = 100;

                const r1 = await window.cmFetch(cfg.urlReglagesSave, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        enabled: this.reglages.form.enabled ? 1 : 0,
                        volume: volume,
                        duree_secondes: dureeSec,
                        taille_max_mo: tailleMo,
                    }),
                });
                if (!r1.ok) {
                    window.dispatchEvent(new CustomEvent('toast', { detail: 'Erreur serveur (réglages).' }));
                    this.reglages.enCours = false;
                    return;
                }

                // Choix du son actif
                if (this.reglages.form.sonUrl && this.reglages.form.sonUrl.startsWith('/sounds/')) {
                    const nom = this.reglages.form.sonUrl.split('/').pop();
                    await window.cmFetch(cfg.urlChoisirSon, {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ nom: nom }),
                    });
                } else if (this.reglages.form.sonId) {
                    await window.cmFetch(cfg.urlChoisirSon, {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ nom: '__default__' }),
                    }).catch(() => {});
                }

                this.alarme.enabled = this.reglages.form.enabled;
                this.alarme.volume = volume;
                this.alarme.son_url = this.reglages.form.sonUrl;
                this.alarme.son_nom = this.reglages.form.sonNom;
                if (this.alarme.son_url && this.$refs.audio) {
                    this.$refs.audio.src = this.alarme.son_url;
                }

                window.dispatchEvent(new CustomEvent('toast', { detail: 'Réglages enregistrés.' }));
                this.reglages.ouvert = false;
                await this.chargerReglages();
            } catch (e) {
                window.dispatchEvent(new CustomEvent('toast', { detail: 'Erreur réseau.' }));
            }
            this.reglages.enCours = false;
        },

        demanderReinitialisation() {
            this.resetModal.ouvert = true;
        },

        async confirmerReinitialisation() {
            try {
                await window.cmFetch(cfg.urlReinitialiser, { method: 'POST' });
                this.resetModal.ouvert = false;
                await this.chargerReglages();
                window.dispatchEvent(new CustomEvent('toast', { detail: 'Réglages réinitialisés.' }));
            } catch (e) {}
        },

        // -----------------------------------------------------------------
        // Upload son
        // -----------------------------------------------------------------
        dropSon(event) {
            this.dragActif = false;
            const f = event.dataTransfer?.files?.[0];
            if (f) this.envoyerSon(f);
        },

        uploaderSon(event) {
            const f = event.target.files?.[0];
            if (f) this.envoyerSon(f);
            event.target.value = '';
        },

        async envoyerSon(file) {
            this.reglages.uploadErreur = '';
            this.reglages.uploadOk = '';
            this.reglages.uploadEnCours = true;

            const tailleMaxMo = this.reglages.form.tailleMax ?? 10;
            const tailleMaxOctets = tailleMaxMo * 1024 * 1024;

            if (file.size > tailleMaxOctets) {
                this.reglages.uploadErreur = `Fichier trop volumineux (max ${tailleMaxMo} Mo).`;
                this.reglages.uploadEnCours = false;
                return;
            }
            const ext = file.name.split('.').pop().toLowerCase();
            if (!['mp3','wav','ogg','m4a','aac'].includes(ext)) {
                this.reglages.uploadErreur = 'Format non accepté (MP3, WAV, OGG, M4A, AAC).';
                this.reglages.uploadEnCours = false;
                return;
            }

            const fd = new FormData();
            fd.append('son', file);

            try {
                const r = await fetch(cfg.urlUploaderSon, {
                    method: 'POST',
                    headers: { 'X-CSRF-TOKEN': cfg.csrf, 'Accept': 'application/json' },
                    body: fd,
                });
                const d = await r.json();
                if (!r.ok) {
                    this.reglages.uploadErreur = d.message || (d.errors?.son?.[0]) || 'Erreur d\'upload.';
                    this.reglages.uploadEnCours = false;
                    return;
                }
                this.reglages.sonsUploades.push({ nom: d.nom, url: d.url });
                this.reglages.uploadOk = 'Fichier ajouté. Choisissez-le dans la liste pour l\'utiliser.';
            } catch (e) {
                this.reglages.uploadErreur = 'Erreur réseau.';
            }
            this.reglages.uploadEnCours = false;
        },

        demanderSupprSon(s) {
            this.supprSonModal = { ouvert: true, nom: s.nom, son: s };
        },

        async confirmerSupprSon() {
            const s = this.supprSonModal.son;
            if (!s) return;
            try {
                await window.cmFetch(cfg.urlSupprimerSon, {
                    method: 'DELETE',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ nom: s.nom }),
                });
                this.reglages.sonsUploades = this.reglages.sonsUploades.filter(x => x.nom !== s.nom);
                if (this.reglages.form.sonUrl === s.url) {
                    this.reglages.form.sonId = 'bip-classique';
                    this.reglages.form.sonUrl = null;
                    this.reglages.form.sonNom = 'Bip par défaut';
                }
                this.supprSonModal.ouvert = false;
            } catch (e) {}
        },

        // -----------------------------------------------------------------
        // Son
        // -----------------------------------------------------------------
        jouerSon() {
            if (this.alarme.son_url) {
                const a = this.$refs.audio;
                if (!a) return;
                try {
                    a.src = this.alarme.son_url;
                    a.volume = Math.max(0, Math.min(1, (this.alarme.volume ?? 70) / 100));
                    a.currentTime = 0;
                    a.play().catch(() => {});
                } catch (e) {}
                return;
            }
            this.bipParDefaut('bip-classique', this.alarme.volume ?? 70);
        },

        arreterSon() {
            try {
                if (this.$refs.audio) {
                    this.$refs.audio.pause();
                    this.$refs.audio.currentTime = 0;
                }
                if (this._ctxAudio) {
                    this._ctxAudio.close().catch(() => {});
                    this._ctxAudio = null;
                }
                this.testEnCours = false;
                this.testId = null;
                clearTimeout(this._audioTimeout);
            } catch (e) {}
        },

        bipParDefaut(id = 'bip-classique', volumePct = 70) {
            try {
                const Ctx = window.AudioContext || window.webkitAudioContext;
                if (!Ctx) return;
                if (this._ctxAudio) this._ctxAudio.close().catch(() => {});
                const ctx = new Ctx();
                this._ctxAudio = ctx;
                const vol = Math.max(0, Math.min(1, volumePct / 100));
                const gain = ctx.createGain();
                gain.gain.value = vol * 0.35;
                gain.connect(ctx.destination);
                const sequences = {
                    'bip-classique': [880, 880, 880],
                    'carillon': [523, 659, 784, 1047],
                    'urgent': [1200, 1200, 1200, 1200, 1200],
                };
                const freqs = sequences[id] || sequences['bip-classique'];
                freqs.forEach((f, i) => {
                    const osc = ctx.createOscillator();
                    osc.type = id === 'urgent' ? 'sawtooth' : 'sine';
                    osc.frequency.value = f;
                    osc.connect(gain);
                    const start = ctx.currentTime + i * 0.28;
                    osc.start(start);
                    osc.stop(start + 0.20);
                });
            } catch (e) {}
        },

        testerSon(idOrUrl) {
            if (this.testEnCours && this.testId === idOrUrl) {
                this.arreterSon();
                return;
            }
            this.arreterSon();
            this.testEnCours = true;
            this.testId = idOrUrl;
            const volumePct = this.reglages.form.volume ?? 70;
            let source = idOrUrl;
            if (idOrUrl === 'actif') {
                source = this.reglages.form.sonUrl || this.reglages.form.sonId || 'bip-classique';
            }
            if (typeof source === 'string' && source.startsWith('/')) {
                const a = this.$refs.audio;
                if (a) {
                    a.src = source;
                    a.volume = Math.max(0, Math.min(1, volumePct / 100));
                    a.currentTime = 0;
                    a.play().catch(() => {});
                }
            } else {
                this.bipParDefaut(source, volumePct);
            }
            this._audioTimeout = setTimeout(() => this.arreterSon(), 6000);
        },

        debloquerAudio() {
            try {
                const Ctx = window.AudioContext || window.webkitAudioContext;
                if (!Ctx) return;
                const ctx = new Ctx();
                ctx.resume().then(() => ctx.close()).catch(() => {});
            } catch (e) {}
        },

        demanderNotif() {
            if (typeof Notification === 'undefined') return;
            Notification.requestPermission().then(p => this.notifAutorisee = p === 'granted');
        },

        // -----------------------------------------------------------------
        // Formats
        // -----------------------------------------------------------------
        formatAr(n) { return Number(n || 0).toLocaleString('fr-FR') + ' Ar'; },
        formatDuree(m) {
            m = parseInt(m, 10) || 0;
            if (m < 60) return m + ' min';
            const h = Math.floor(m / 60), r = m % 60;
            return r === 0 ? h + ' h' : h + ' h ' + String(r).padStart(2, '0') + ' min';
        },
        formatDureeSec(sec) {
            sec = Math.max(0, Math.floor(sec));
            const h = Math.floor(sec / 3600);
            const m = Math.floor((sec % 3600) / 60);
            const s = sec % 60;
            const pad = n => String(n).padStart(2, '0');
            return h > 0 ? `${pad(h)}:${pad(m)}:${pad(s)}` : `${pad(m)}:${pad(s)}`;
        },
        formatOctets(o) {
            o = parseInt(o, 10) || 0;
            if (o < 1024) return o + ' o';
            if (o < 1048576) return (o / 1024).toFixed(1) + ' Ko';
            if (o < 1073741824) return (o / 1048576).toFixed(1) + ' Mo';
            return (o / 1073741824).toFixed(2) + ' Go';
        },
        formatDebit(mbps) {
            if (!mbps || mbps < 0.001) return '0 Ko/s';
            if (mbps < 1) return (mbps * 125).toFixed(1) + ' Ko/s';
            return mbps.toFixed(2) + ' Mo/s';
        },
        heureExacte(iso) {
            if (!iso) return '—';
            const d = new Date(iso);
            return String(d.getHours()).padStart(2,'0') + ':' +
                   String(d.getMinutes()).padStart(2,'0') + ':' +
                   String(d.getSeconds()).padStart(2,'0');
        },
        dateLongueFr(iso) {
            if (!iso) return '—';
            const d = new Date(iso);
            const mois = ['janvier','février','mars','avril','mai','juin','juillet','août','septembre','octobre','novembre','décembre'];
            return String(d.getDate()).padStart(2,'0') + ' ' + mois[d.getMonth()] + ' ' + d.getFullYear();
        },

        secondesConsommees(l) {
            if (!l.date_debut) return 0;
            const debut = new Date(l.date_debut).getTime();
            let fin;
            if (l.etat_appareil === 'en_pause' && l.pause_at) {
                fin = new Date(l.pause_at).getTime();
            } else if (l.etat_appareil === 'deconnecte' && l.deconnecte_at) {
                fin = new Date(l.deconnecte_at).getTime();
            } else if (l.etat_appareil === 'termine') {
                // Session figée : on ne regarde JAMAIS l'heure courante.
                fin = l.date_fin_reelle
                    ? new Date(l.date_fin_reelle).getTime()
                    : (l.deconnecte_at ? new Date(l.deconnecte_at).getTime() : debut);
            } else {
                fin = this.now;
            }
            let total = Math.floor((fin - debut) / 1000);
            total -= (l.secondes_hors_ligne || 0);
            total -= (l.secondes_pause || 0);
            return Math.max(0, total);
        },

        montantTempsReel(l) {
            if (!l.tarif) return 0;
            const sec = this.secondesConsommees(l);
            const brut = Math.floor((sec / 60) * l.tarif.montant_par_minute);
            const mini = l.tarif.montant_minimum || 0;
            return Math.max(brut, mini);
        },

        montantFinalCalcule(l) {
            if (l.etat_appareil === 'termine' && l.montant_final != null) return l.montant_final;
            if (!l.tarif) return 0;
            const sec = this.secondesConsommees(l);
            const minutes = Math.ceil(sec / 60);
            let brut = minutes * l.tarif.montant_par_minute;
            if (brut < l.tarif.montant_minimum) brut = l.tarif.montant_minimum;
            if (l.tarif.arrondi_actif && l.tarif.unite_arrondi > 0) {
                const u = l.tarif.unite_arrondi, s = l.tarif.seuil_arrondi, r = brut % u;
                if (r !== 0) brut = r < s ? brut - r : brut + (u - r);
            }
            return brut;
        },

        chronoRestantTexte(l) {
            if (!l.alarme.fin) return 'En pause'; // Si pas de date de fin, c'est en pause
            const fin = new Date(l.alarme.fin).getTime();
            const sec = Math.max(0, Math.floor((fin - this.now) / 1000));
            return this.formatDureeSec(sec);
        },

        progressionAlarme(l) {
            if (!l.alarme.minutes || !l.date_debut) return 0;
            const secDeja = this.secondesConsommees(l);
            const secTotal = l.alarme.minutes * 60;
            return Math.max(0, Math.min(100, (secDeja / secTotal) * 100));
        },

        debitLigne(l, sens) {
            const key = l.id + '-' + sens;
            const volume = sens === 'entree' ? l.volume_entree : l.volume_sortie;
            const now = Date.now();
            let c = this.volumeCache[key];
            if (!c) {
                c = this.volumeCache[key] = { echantillons: [], mbps: 0, volActuel: volume };
                return '0 Ko/s';
            }
            // Détection reset (compteur qui recule)
            if (volume < c.volActuel) c.echantillons = [];
            c.volActuel = volume;
            // Ajouter un échantillon si volume bouge OU toutes les 2s
            const dernier = c.echantillons[c.echantillons.length - 1];
            if (!dernier || now - dernier.ts >= 2000 || volume !== dernier.vol) {
                c.echantillons.push({ vol: volume, ts: now });
                if (c.echantillons.length > 5) c.echantillons.shift();
            }
            // Calcul sur fenêtre glissante
            if (c.echantillons.length >= 2) {
                const premier = c.echantillons[0];
                const dOctets = volume - premier.vol;
                const dSec = (now - premier.ts) / 1000;
                if (dSec >= 1 && dOctets >= 0) {
                    c.mbps = (dOctets * 8) / (dSec * 1_000_000);
                }
            }
            return this.formatDebit(c.mbps);
        },
        get debitTotalTotal() {
            return Object.values(this.volumeCache).reduce((a, c) => a + (c.mbps || 0), 0);
        },

        libelleEtat(l) {
            return { connecte: 'Connecté', deconnecte: 'Déconnecté', en_pause: 'En pause', termine: 'Terminé' }[l.etat_appareil] || l.etat_appareil;
        },
        badgeEtat(l) {
            return {
                'bg-emerald-50 text-emerald-700 ring-emerald-600/30': l.etat_appareil === 'connecte',
                'bg-amber-100 text-amber-800 ring-amber-600/30': l.etat_appareil === 'deconnecte',
                'bg-sky-50 text-sky-700 ring-sky-600/30': l.etat_appareil === 'en_pause',
                'bg-slate-100 text-slate-700 ring-slate-500/30': l.etat_appareil === 'termine',
            };
        },
        pointEtat(l) {
            return {
                'bg-emerald-500': l.etat_appareil === 'connecte',
                'bg-amber-500': l.etat_appareil === 'deconnecte',
                'bg-sky-500': l.etat_appareil === 'en_pause',
                'bg-slate-400': l.etat_appareil === 'termine',
            };
        },
        classeLigne(l) {
            if (l.alarme.declenchee && !l.alarme.acquittee && l.etat_appareil !== 'termine') return 'bg-rose-50 border-l-4 border-rose-500';
            return {
                'bg-emerald-50/60 border-l-4 border-emerald-500': l.etat_appareil === 'connecte',
                'bg-amber-100/70 border-l-4 border-amber-500': l.etat_appareil === 'deconnecte',
                'bg-sky-50 border-l-4 border-sky-400': l.etat_appareil === 'en_pause',
                'bg-slate-100 border-l-4 border-slate-400': l.etat_appareil === 'termine',
            };
        },

        get lignesAffichees() {
            let arr = [...this.lignes];
            if (this.filtreEtat) arr = arr.filter(l => l.etat_appareil === this.filtreEtat);
            const terme = this.recherche.trim().toLowerCase();
            if (terme) {
                arr = arr.filter(l => {
                    const t = [l.appareil, l.ip, l.mac, l.ssid, l.voucher?.username].filter(Boolean).join(' ').toLowerCase();
                    return t.includes(terme);
                });
            }
            const cle = this.cleTri, sens = this.sensTri === 'asc' ? 1 : -1;
            arr.sort((a, b) => {
                const va = a[cle] ?? '', vb = b[cle] ?? '';
                if (va === vb) return 0;
                return va > vb ? sens : -sens;
            });
            return arr;
        },
        trier(cle) {
            if (this.cleTri === cle) this.sensTri = this.sensTri === 'asc' ? 'desc' : 'asc';
            else { this.cleTri = cle; this.sensTri = 'asc'; }
        },
        get tousSelectionnes() {
            const dispo = this.lignesAffichees.map(l => String(l.id));
            return dispo.length > 0 && dispo.every(id => this.selected.includes(id));
        },
        basculerTous() {
            const dispo = this.lignesAffichees.map(l => String(l.id));
            this.selected = this.tousSelectionnes ? [] : dispo;
        },
        escapeHtml(s) {
            if (s == null) return '';
            return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;').replace(/'/g,'&#39;');
        },
        surbriller(texte) {
            const safe = this.escapeHtml(texte);
            const terme = this.recherche.trim();
            if (!terme) return safe;
            const t = terme.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
            return safe.replace(new RegExp(`(${t})`, 'gi'), '<mark class="rounded-sm bg-yellow-200 px-0.5 text-slate-900">$1</mark>');
        },
    };
}
</script>
@endsection