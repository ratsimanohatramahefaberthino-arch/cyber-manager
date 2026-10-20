@extends('layouts.cyber-manager', ['title' => 'Wi-Fi — Cyber Manager'])

@section('content')
@php
    $cfg = $config;

    // [clé de colonne, libellé, alignement, clé de tri]
    $colonnes = [
        ['c1', 'Heure',            '',            'date_debut'],
        ['c2', 'Appareil',         '',            null],
        ['c3', 'Réseau',           '',            null],
        ['c4', 'État',             '',            null],
        ['c5', 'Durée / Montant',  '',            null],
        ['c6', 'Volume / Débit',   '',            null],
        ['c7', 'Alarme',           'text-center', null],
        ['c8', 'Remarques',        '',            null],
        ['c9', 'Actions',          'text-right',  null],
    ];

    $filtres = [
        ['',           'Tous',        'bg-slate-900 text-white'],
        ['connecte',   'Connectés',   'bg-emerald-500 text-white'],
        ['deconnecte', 'Déconnectés', 'bg-amber-500 text-white'],
        ['termine',    'Terminés',    'bg-slate-600 text-white'],
    ];
@endphp

<x-flash />

<div x-data="wifiPage({{ \Illuminate\Support\Js::from($cfg) }})">

    {{-- ═══ En-tête ═══ --}}
    <div class="mb-6 flex flex-wrap items-center justify-between gap-3">
        <div>
            <h1 class="text-2xl font-semibold tracking-tight text-slate-900">Wi-Fi</h1>
            <p class="text-base text-slate-500">Sessions HotSpot en temps réel</p>
        </div>
        <div class="flex flex-wrap items-center gap-2">
            <button type="button" @click="ouvrirReglages()"
                    :title="'Réglages de l\'alarme — ' + (alarme.enabled ? 'son activé' : 'son désactivé')"
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

    {{-- ═══ Bandeaux d'état ═══ --}}
    <div x-show="sync && sync.ok === false" x-cloak
         class="mb-4 flex items-start gap-3 rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-base text-rose-800">
        <x-icone nom="warning" class="mt-0.5 h-5 w-5 shrink-0" />
        <div>
            <div class="font-semibold">MikroTik injoignable</div>
            <div class="text-sm">Les données affichées peuvent être obsolètes. Les alarmes continuent de fonctionner avec les dernières informations reçues.</div>
        </div>
    </div>

    <div x-show="erreurReseau" x-cloak
         class="mb-4 flex items-center gap-3 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-base text-amber-800">
        <x-icone nom="warning" class="h-5 w-5 shrink-0" />
        <span>Connexion au serveur perdue. Nouvelle tentative automatique…</span>
    </div>

    <div x-show="!notifAutorisee && alarme.enabled && notifSupportee" x-cloak
         class="mb-4 flex flex-wrap items-center justify-between gap-3 rounded-xl border border-sky-200 bg-sky-50 px-4 py-3 text-base text-sky-800">
        <div class="flex items-center gap-2">
            <x-icone nom="bell" class="h-5 w-5 shrink-0" />
            <span>Autorisez les notifications du navigateur pour être alerté même quand l'onglet est en arrière-plan.</span>
        </div>
        <button type="button" @click="demanderNotif()"
                class="rounded-lg bg-sky-600 px-4 py-2 text-sm font-medium text-white hover:bg-sky-700">Autoriser</button>
    </div>

    {{-- ═══ KPI ═══ --}}
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
            <div class="mt-2 text-3xl font-semibold text-violet-600" x-text="formatDebit(debitTotal)"></div>
            <div class="mt-1 text-sm text-slate-400">Volume : <span x-text="formatOctets(stats.volume_total)"></span></div>
        </div>
    </div>

    {{-- ═══ Filtres ═══ --}}
    <div class="mb-4 flex flex-wrap items-center gap-3">
        <div class="inline-flex rounded-lg border border-slate-200 bg-white p-1 text-base shadow-sm">
            @foreach($filtres as [$valeur, $libelle, $actif])
                <button type="button" @click="filtreEtat = '{{ $valeur }}'"
                        :class="filtreEtat === '{{ $valeur }}' ? '{{ $actif }}' : 'text-slate-600 hover:bg-slate-100'"
                        class="rounded-md px-4 py-2 font-medium">{{ $libelle }}</button>
            @endforeach
        </div>

        <div class="relative min-w-[200px] flex-1 sm:max-w-sm">
            <x-icone nom="search" class="pointer-events-none absolute left-3 top-1/2 h-5 w-5 -translate-y-1/2 text-slate-400" />
            <input type="search" x-model="recherche" placeholder="Rechercher…"
                   class="h-11 w-full rounded-lg border border-slate-200 bg-white pl-10 pr-10 text-base shadow-sm focus:border-slate-800 focus:outline-none">
            <button type="button" x-show="recherche.length > 0" x-cloak @click="recherche = ''" aria-label="Effacer la recherche"
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

    {{-- ═══ Tableau ═══ --}}
    <div class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
        <div class="max-h-[62vh] overflow-auto">
            <table class="w-full table-fixed text-sm">
                <colgroup>
                    @for($i = 0; $i < 10; $i++)
                        <col :style="'width: ' + colWidths.c{{ $i }} + 'px'">
                    @endfor
                </colgroup>
                <thead class="sticky top-0 z-[5] bg-slate-800 text-left text-xs font-semibold uppercase tracking-wide text-white">
                    <tr>
                        <th class="relative px-2 py-2.5 text-center">
                            <input type="checkbox" :checked="tousSelectionnes" @change="basculerTous()"
                                   aria-label="Tout sélectionner" class="h-4 w-4 rounded border-slate-400 text-slate-900">
                            <div class="absolute right-0 top-0 h-full w-1.5 cursor-col-resize hover:bg-slate-500" @mousedown.prevent="startResize($event, 'c0')"></div>
                        </th>
                        @foreach($colonnes as [$cle, $libelle, $align, $tri])
                            <th class="relative px-2 py-2.5 {{ $align }}">
                                @if($tri)
                                    <button type="button" @click="trier('{{ $tri }}')" class="inline-flex items-center gap-1 hover:text-white/80">
                                        {{ $libelle }}
                                        <span class="text-white/50" x-show="cleTri !== '{{ $tri }}'">↕</span>
                                        <span x-show="cleTri === '{{ $tri }}'" x-text="sensTri === 'asc' ? '↑' : '↓'"></span>
                                    </button>
                                @else
                                    {{ $libelle }}
                                @endif
                                <div class="absolute right-0 top-0 h-full w-1.5 cursor-col-resize hover:bg-slate-500" @mousedown.prevent="startResize($event, '{{ $cle }}')"></div>
                            </th>
                        @endforeach
                    </tr>
                </thead>

                <tbody>
                    <template x-if="lignesAffichees.length === 0">
                        <tr>
                            <td colspan="10" class="px-4 py-16 text-center text-base text-slate-400">
                                <span x-show="recherche || filtreEtat">Aucun résultat pour ces critères.</span>
                                <span x-show="!recherche && !filtreEtat">Aucune session Wi-Fi. Les appareils apparaissent dès qu'ils se connectent au hotspot.</span>
                            </td>
                        </tr>
                    </template>

                    <template x-for="(l, i) in lignesAffichees" :key="l.id">
                        <tr class="border-b border-slate-200 transition" :class="classeLigne(l)">

                            {{-- Sélection --}}
                            <td class="px-2 py-2.5">
                                <div class="flex items-center justify-center gap-1">
                                    <input type="checkbox" :value="String(l.id)" x-model="selected"
                                           class="h-4 w-4 rounded border-slate-300 text-slate-900">
                                    <span class="font-mono text-xs text-slate-400" x-text="i + 1"></span>
                                </div>
                            </td>

                            {{-- Heure --}}
                            <td class="px-2 py-2.5">
                                <div class="font-mono text-base font-bold leading-tight text-slate-900" x-text="heureExacte(l.date_debut)"></div>
                                <div class="text-xs leading-tight text-slate-500" x-text="dateLongueFr(l.date_debut)"></div>
                            </td>

                            {{-- Appareil --}}
                            <td class="px-2 py-2.5">
                                <div class="flex min-w-0 items-center gap-1.5">
                                    <span x-show="l.appareil_type === 'telephone'"><x-icone nom="phone" class="h-4 w-4 shrink-0 text-slate-400" /></span>
                                    <span x-show="l.appareil_type === 'tablette'"><x-icone nom="tablet" class="h-4 w-4 shrink-0 text-slate-400" /></span>
                                    <span x-show="!['telephone','tablette'].includes(l.appareil_type)"><x-icone nom="desktop" class="h-4 w-4 shrink-0 text-slate-400" /></span>
                                    <div class="min-w-0 flex-1">
                                        <div class="truncate text-sm font-semibold text-slate-900" x-html="surbriller(l.appareil)"></div>
                                        <div class="truncate font-mono text-xs text-slate-500" x-html="surbriller(l.ip || '—')"></div>
                                        <div class="truncate font-mono text-xs text-slate-400" x-html="surbriller(l.mac || '—')"></div>
                                    </div>
                                </div>
                            </td>

                            {{-- Réseau / voucher --}}
                            <td class="px-2 py-2.5">
                                <div class="mb-1 inline-flex rounded-md bg-violet-50 px-1.5 py-0.5 text-xs font-semibold text-violet-700 ring-1 ring-inset ring-violet-600/20"
                                     x-text="l.ssid || 'Inconnu'"></div>
                                <template x-if="l.voucher">
                                    <div>
                                        <div class="truncate font-mono text-xs text-slate-900" x-html="surbriller(l.voucher.username)"></div>
                                        <template x-if="l.voucher.protege">
                                            <div class="inline-flex items-center gap-0.5 text-[10px] text-amber-600"><x-icone nom="lock" class="h-3 w-3" /> Protégé</div>
                                        </template>
                                        <template x-if="l.voucher.permanent">
                                            <div class="text-[10px] text-violet-600">Réutilisable</div>
                                        </template>
                                    </div>
                                </template>
                            </td>

                            {{-- État --}}
                            <td class="px-2 py-2.5">
                                <span class="inline-flex items-center gap-1 rounded-md px-1.5 py-0.5 text-xs font-semibold ring-1 ring-inset" :class="badgeEtat(l)">
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

                            {{-- Durée / montant --}}
                            <td class="px-2 py-2.5">
                                <div class="font-mono text-base font-bold leading-tight"
                                     :class="l.etat_appareil === 'connecte' ? 'text-slate-900' : 'text-slate-500'"
                                     x-text="formatDureeSec(secondesConsommees(l))"></div>
                                <div class="text-xs leading-tight text-slate-500"
                                     x-text="l.alarme.active ? ('Limite ' + formatDuree(l.alarme.minutes)) : 'Sans limite'"></div>
                                <div class="mt-1 font-mono text-sm font-bold leading-tight"
                                     :class="l.etat_appareil === 'termine' ? 'text-emerald-700' : 'text-sky-700'"
                                     x-text="formatAr(l.etat_appareil === 'termine' ? montantFinalCalcule(l) : montantTempsReel(l))"></div>
                            </td>

                            {{-- Volume / débit --}}
                            <td class="px-2 py-2.5">
                                <div class="text-sm font-medium text-slate-800" x-text="formatOctets(l.volume_total)"></div>
                                <div class="font-mono text-xs leading-tight">
                                    <span class="text-emerald-600" x-text="'↓' + formatDebit(l.debit_entree)"></span>
                                    <span class="text-slate-300">·</span>
                                    <span class="text-sky-600" x-text="'↑' + formatDebit(l.debit_sortie)"></span>
                                </div>
                            </td>

                            {{-- ALARME : 1 colonne, 4 états (+ "aucune") --}}
                            <td class="px-2 py-2.5 text-center">

                                {{-- Session terminée --}}
                                <template x-if="l.etat_appareil === 'termine'">
                                    <span class="text-xs text-slate-400">Session terminée</span>
                                </template>

                                {{-- Aucune alarme --}}
                                <template x-if="l.etat_appareil !== 'termine' && etatAlarme(l) === 'aucune'">
                                    <button type="button" @click="ouvrirAlarme(l)"
                                            class="inline-flex items-center gap-1 rounded-md border border-slate-200 bg-white px-2 py-0.5 text-xs text-slate-600 hover:bg-slate-50">
                                        <x-icone nom="bell" class="h-3.5 w-3.5" /> Poser
                                    </button>
                                </template>

                                {{-- État 1 : décompte (ou en pause si l'appareil est hors ligne) --}}
                                <template x-if="etatAlarme(l) === 'decompte' || etatAlarme(l) === 'pause'">
                                    <div class="space-y-1">
                                        <div class="font-mono text-sm font-bold"
                                             :class="etatAlarme(l) === 'pause' ? 'text-slate-500' : 'text-amber-700'"
                                             x-text="chronoAlarme(l)"></div>
                                        <div class="h-1.5 w-full overflow-hidden rounded-full bg-slate-200">
                                            <div class="h-full rounded-full transition-all duration-1000 ease-linear"
                                                 :class="couleurBarre(l)" :style="'width: ' + progressionAlarme(l) + '%'"></div>
                                        </div>
                                        <div x-show="etatAlarme(l) === 'pause'" class="flex items-center justify-center gap-1 text-[10px] font-semibold text-slate-500">
                                            <x-icone nom="pause" class="h-3 w-3" /> Hors ligne
                                        </div>
                                        <div class="flex items-center justify-center gap-1">
                                            <button type="button" @click="ouvrirProlonger(l)" class="rounded px-1 text-[10px] font-medium text-emerald-700 hover:bg-emerald-50">+ Prolonger</button>
                                            <button type="button" @click="ouvrirAlarme(l, true)" class="rounded px-1 text-[10px] font-medium text-slate-600 hover:bg-slate-100">Modifier</button>
                                        </div>
                                    </div>
                                </template>

                                {{-- État 2 : sonne --}}
                                <template x-if="etatAlarme(l) === 'sonne'">
                                    <button type="button" @click="ouvrirProlonger(l)" title="Cliquer pour prolonger"
                                            class="inline-flex flex-col items-center gap-0.5 rounded-lg bg-rose-100 px-2.5 py-1 text-rose-700 hover:bg-rose-200">
                                        <span class="inline-flex animate-pulse items-center gap-1 text-xs font-bold">
                                            <x-icone nom="bell" class="h-3.5 w-3.5" /> Sonne
                                        </span>
                                        <span class="font-mono text-[11px] font-semibold" x-text="'+' + formatDureeSec(depassementSecondes(l))"></span>
                                    </button>
                                </template>

                                {{-- État 3 : acquittée / silencieuse (jamais "Poser") --}}
                                <template x-if="etatAlarme(l) === 'silencieuse'">
                                    <div class="space-y-1">
                                        <button type="button" @click="ouvrirProlonger(l)" title="Cliquer pour prolonger"
                                                class="block w-full space-y-1 rounded-md p-0.5 hover:bg-rose-50">
                                            <div class="font-mono text-sm font-bold text-rose-700"
                                                 x-text="depassementSecondes(l) > 0 ? '+' + formatDureeSec(depassementSecondes(l)) : chronoAlarme(l)"></div>
                                            <div class="h-1.5 w-full overflow-hidden rounded-full bg-slate-200">
                                                <div class="h-full rounded-full transition-all duration-1000 ease-linear"
                                                     :class="couleurBarre(l)" :style="'width: ' + progressionAlarme(l) + '%'"></div>
                                            </div>
                                            <div class="text-[10px] font-semibold text-slate-500">Silencieuse</div>
                                        </button>
                                        <div class="flex items-center justify-center gap-1">
                                            <button type="button" @click="ouvrirProlonger(l)" class="rounded px-1 text-[10px] font-medium text-emerald-700 hover:bg-emerald-50">+ Prolonger</button>
                                            <button type="button" @click="ouvrirAlarme(l, true)" class="rounded px-1 text-[10px] font-medium text-slate-600 hover:bg-slate-100">Modifier</button>
                                        </div>
                                    </div>
                                </template>
                            </td>

                            {{-- Remarques --}}
                            <td class="px-2 py-2.5">
                                <textarea x-model="l.remarque"
                                          @focus="l._focusRemarque = true; l._remarqueAvant = l.remarque || ''"
                                          @blur="sauverRemarque(l)"
                                          rows="3" maxlength="1000" placeholder="Tapez une remarque…"
                                          class="min-h-[64px] w-full resize-y rounded border border-slate-200 bg-white px-2 py-1.5 text-xs focus:border-slate-800 focus:outline-none"></textarea>
                            </td>

                            {{-- Actions --}}
                            <td class="px-2 py-2.5">
                                <div class="flex flex-col items-stretch gap-1">
                                    <template x-if="l.etat_appareil === 'connecte' || l.etat_appareil === 'deconnecte'">
                                        <button type="button" @click="confirmerArret(l)"
                                                class="inline-flex items-center justify-center gap-1 rounded-md border border-amber-300 bg-white px-2 py-1 text-xs font-semibold text-amber-700 hover:bg-amber-50"
                                                title="Figer la durée et le montant (le client reste connecté)">
                                            <x-icone nom="ban" class="h-3.5 w-3.5" /> Arrêter
                                        </button>
                                    </template>
                                    <template x-if="l.etat_appareil === 'termine'">
                                        <button type="button" @click="confirmerContinuer(l)"
                                                class="inline-flex items-center justify-center gap-1 rounded-md border border-emerald-300 bg-white px-2 py-1 text-xs font-semibold text-emerald-700 hover:bg-emerald-50"
                                                title="Reprendre la session depuis la même ligne">
                                            <x-icone nom="play" class="h-3.5 w-3.5" /> Continuer
                                        </button>
                                    </template>
                                    <button type="button" @click="confirmerSuppression(l)"
                                            class="inline-flex items-center justify-center gap-1 rounded-md border border-rose-300 bg-white px-2 py-1 text-xs font-semibold text-rose-700 hover:bg-rose-50"
                                            title="Supprimer définitivement">
                                        <x-icone nom="trash" class="h-3.5 w-3.5" /> Supprimer
                                    </button>
                                </div>
                            </td>
                        </tr>
                    </template>
                </tbody>
            </table>
        </div>
    </div>

    {{-- ═══ Identifiants disponibles ═══ --}}
    <div class="mt-6 rounded-xl border border-slate-200 bg-white shadow-sm">
        <div class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-100 px-5 py-3">
            <div class="flex items-center gap-2">
                <x-icone nom="key" class="h-6 w-6 text-slate-400" />
                <div>
                    <h2 class="text-lg font-semibold text-slate-900">Identifiants disponibles</h2>
                    <p class="text-sm text-slate-500">Prochains à donner à un nouveau client</p>
                </div>
            </div>
            <a :href="cfg.urlHotspot"
               class="inline-flex h-10 items-center gap-1.5 rounded-lg border border-slate-200 bg-white px-4 text-sm font-medium text-slate-700 hover:bg-slate-50">
                Voir tous les identifiants
                <x-icone nom="external-link" class="h-4 w-4" />
            </a>
        </div>

        <template x-if="vouchersWarning">
            <div class="flex items-center gap-1 border-b border-amber-200 bg-amber-50 px-5 py-2.5 text-sm font-medium text-amber-800">
                <x-icone nom="warning" class="inline h-4 w-4" />
                <span x-text="vouchersWarning"></span>
            </div>
        </template>

        <div class="overflow-x-auto">
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
                        <tr><td colspan="4" class="px-4 py-6 text-center text-base text-slate-400">Aucun identifiant disponible.</td></tr>
                    </template>
                    <template x-for="(v, i) in vouchersDisponibles" :key="v.id">
                        <tr class="border-t border-slate-100 transition-all duration-500" :class="v.transition ? 'bg-amber-50 opacity-40' : ''">
                            <td class="px-4 py-2 font-mono text-sm text-slate-400" x-text="i + 1"></td>
                            <td class="px-4 py-2 font-mono text-base text-slate-900" x-text="v.username"></td>
                            <td class="px-4 py-2">
                                <div class="flex items-center gap-2">
                                    <span class="font-mono text-base text-slate-600" x-text="v.masque ? '••••••••' : v.password"></span>
                                    <button type="button" @click="v.masque = !v.masque" class="rounded p-0.5 text-slate-400 hover:bg-slate-100"
                                            :aria-label="v.masque ? 'Afficher le mot de passe' : 'Masquer le mot de passe'">
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

    <p class="mt-3 text-sm text-slate-400">Actualisation automatique. Dernière mise à jour : <span x-text="derniereSync || '—'"></span></p>

    {{-- Modales (téléportées dans <body>) --}}
    @include('wifi.partials.modales')

    <audio x-ref="audio" preload="auto"></audio>
</div>

@include('wifi.partials.script')
@endsection