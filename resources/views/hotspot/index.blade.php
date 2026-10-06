@extends('layouts.cyber-manager', ['title' => 'HotSpot — Cyber Manager'])

@section('content')
@php
    $etats = ['disponible' => 'Disponible', 'en_cours' => 'En cours', 'utilise' => 'Utilisé'];
    $aDesFiltres = collect($filtres)->filter(fn ($v) => $v !== '' && $v !== null)->isNotEmpty();
    $input = 'w-full rounded-lg border bg-white px-3 py-2.5 text-sm shadow-sm placeholder:text-slate-400 focus:border-slate-800 focus:outline-none focus:ring-2 focus:ring-slate-800/15 border-slate-300';

    $url = fn (array $modif) => route('hotspot.index', array_filter(
        array_merge($filtres, $modif), fn ($v) => $v !== null && $v !== ''
    ));

    $jeux = [
        'minuscules'          => 'Lettres minuscules — ex. abcd',
        'majuscules'          => 'Lettres majuscules — ex. ABCD',
        'mixte'                => 'Lettres mixtes — ex. aBcD',
        'minuscules_chiffres'  => 'Minuscules + chiffres — ex. 5ab2c34d',
        'majuscules_chiffres'  => 'Majuscules + chiffres — ex. 5AB2C34D',
        'mixte_chiffres'       => 'Mixte + chiffres — ex. 5aB2c34D',
    ];
@endphp

<x-flash />

@if($lotGenere)
    <script>
        window.addEventListener('DOMContentLoaded', () => {
            window.open('{{ route('hotspot.imprimer.lot', $lotGenere) }}', '_blank');
        });
    </script>
@endif

<div x-data="hotspotPage({
        lignesInitiales: @js($lignes),
        statsInitiales: @js($stats),
        urlEtat: '{{ route('hotspot.etat') }}{{ request()->getQueryString() ? '?' . request()->getQueryString() : '' }}',
        urlProteger: '{{ url('/hotspot') }}',
        urlRenommer: '{{ url('/hotspot/appareils') }}',
        urlSupprimer: '{{ route('hotspot.supprimer-masse') }}',
        urlImprimerDisponibles: '{{ route('hotspot.imprimer.disponibles') }}',
     })"
     x-init="demarrer()"
     x-data.allow-override="{
        genererOuvert: {{ $errors->has('global') && old('quantite') ? 'true' : 'false' }},
        ajouterOuvert: {{ $errors->has('global') && old('username') ? 'true' : 'false' }},
        serveurs: ['all'],
        profils: ['default'],
        chargementOptions: false,
        async chargerOptions() {
            if (this.chargementOptions || this.serveurs.length > 1) return;
            this.chargementOptions = true;
            try {
                const r = await fetch('{{ route('hotspot.options-mikrotik') }}', { headers: { Accept: 'application/json' } });
                const d = await r.json();
                this.serveurs = d.serveurs.length ? d.serveurs : ['all'];
                this.profils = d.profils.length ? d.profils : ['default'];
            } catch (e) {}
            this.chargementOptions = false;
        },
     }">

    {{-- Barre d'actions fixe en haut de page --}}
    <div class="sticky top-0 z-30 -mx-4 mb-6 flex flex-wrap items-center justify-between gap-4 border-b border-slate-200 bg-slate-50/95 px-4 py-3 backdrop-blur sm:-mx-6 sm:px-6">
        <div>
            <h1 class="text-2xl font-semibold tracking-tight text-slate-900">HotSpot</h1>
            <p class="text-sm text-slate-500">Identifiants Wi-Fi du portail captif</p>
        </div>
        <div class="flex items-center gap-2">
            <button type="button" @click="ajouterOuvert = true; chargerOptions()"
                    class="inline-flex h-10 items-center gap-2 rounded-lg border border-slate-200 bg-white px-4 text-sm font-medium text-slate-700 shadow-sm hover:bg-slate-50">
                <x-icone nom="plus" class="h-4 w-4" /> Ajouter
            </button>
            <button type="button" @click="genererOuvert = true; chargerOptions()"
                    class="inline-flex h-10 items-center gap-2 rounded-lg bg-slate-900 px-4 text-sm font-medium text-white shadow-sm hover:bg-slate-700">
                <x-icone nom="key" class="h-4 w-4" /> Générer
            </button>
            <button type="button" @click="imprimerDisponibles()"
                    title="Imprimer les identifiants générés automatiquement et encore disponibles"
                    class="inline-flex h-10 items-center gap-2 rounded-lg border border-slate-200 bg-white px-4 text-sm font-medium text-slate-700 shadow-sm hover:bg-slate-50">
                <x-icone nom="print" class="h-4 w-4" /> Imprimer
            </button>
        </div>
    </div>

    {{-- Pool + mode --}}
    <div class="mb-6 flex flex-wrap items-center justify-between gap-3 rounded-xl border border-slate-200 bg-white px-4 py-3 text-sm shadow-sm">
        <div class="flex items-center gap-2 text-slate-600">
            <x-icone nom="key" class="h-4 w-4 text-slate-400" />
            Pool : <strong x-text="stats.pool_disponibles"></strong> disponible(s) sur un seuil de <strong>{{ $stats['pool_seuil'] }}</strong>
        </div>
        <form method="POST" action="{{ route('hotspot.pool-mode') }}" class="flex items-center gap-2">
            @csrf
            <span class="text-slate-500">Réapprovisionnement automatique</span>
            <input type="hidden" name="mode" value="{{ $stats['pool_mode'] === 'auto' ? 'manuel' : 'auto' }}">
            <button type="submit"
                    class="relative inline-flex h-6 w-11 items-center rounded-full transition {{ $stats['pool_mode'] === 'auto' ? 'bg-emerald-500' : 'bg-slate-300' }}">
                <span class="inline-block h-4 w-4 transform rounded-full bg-white transition {{ $stats['pool_mode'] === 'auto' ? 'translate-x-6' : 'translate-x-1' }}"></span>
            </button>
        </form>
    </div>

    {{-- KPI en temps réel --}}
    <div class="mb-6 grid grid-cols-2 gap-4 lg:grid-cols-4">
        <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
            <div class="flex items-center gap-2 text-sm font-medium text-slate-500">
                <x-icone nom="key" class="h-4 w-4 text-slate-400" />
                <span>Total</span>
            </div>
            <div class="mt-2 text-2xl font-semibold text-slate-900" x-text="stats.total"></div>
            <div class="mt-1 text-xs text-slate-400"><span x-text="stats.proteges"></span> protégé(s)</div>
        </div>
        <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
            <div class="flex items-center gap-2 text-sm font-medium text-slate-500">
                <x-icone nom="check" class="h-4 w-4 text-emerald-500" />
                <span>Disponibles</span>
            </div>
            <div class="mt-2 text-2xl font-semibold text-emerald-600" x-text="stats.disponible"></div>
            <div class="mt-1 text-xs text-slate-400">Pas encore utilisés</div>
        </div>
        <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
            <div class="flex items-center gap-2 text-sm font-medium text-slate-500">
                <x-icone nom="clock" class="h-4 w-4 text-amber-500" />
                <span>En cours</span>
            </div>
            <div class="mt-2 text-2xl font-semibold text-amber-600" x-text="stats.en_cours"></div>
            <div class="mt-1 text-xs text-slate-400">Connectés en ce moment</div>
        </div>
        <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
            <div class="flex items-center gap-2 text-sm font-medium text-slate-500">
                <x-icone nom="archive" class="h-4 w-4 text-slate-400" />
                <span>Utilisés</span>
            </div>
            <div class="mt-2 text-2xl font-semibold text-slate-700" x-text="stats.utilise"></div>
            <div class="mt-1 text-xs text-slate-400">Déjà servi, pas encore supprimé</div>
        </div>
    </div>

    @if($stats['non_sync'] > 0)
        <div class="mb-6 flex flex-wrap items-center justify-between gap-3 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">
            <div class="flex items-center gap-2">
                <x-icone nom="warning" class="h-5 w-5" />
                <span><strong>{{ $stats['non_sync'] }}</strong> identifiant(s) pas encore sur le MikroTik.</span>
            </div>
            <form method="POST" action="{{ route('hotspot.synchroniser-tout') }}">
                @csrf
                <button type="submit" class="inline-flex h-8 items-center gap-1.5 rounded-lg bg-amber-600 px-3 text-xs font-medium text-white hover:bg-amber-700">
                    <x-icone nom="refresh" class="h-4 w-4" /> Tout synchroniser
                </button>
            </form>
        </div>
    @endif

    {{-- Filtres --}}
    <div class="mb-4 flex flex-wrap items-center gap-3">
        <div class="inline-flex rounded-lg border border-slate-200 bg-white p-0.5 text-sm shadow-sm">
            <a href="{{ route('hotspot.index') }}" @class(['rounded-md px-3 py-1.5 font-medium transition', 'bg-slate-900 text-white' => empty($filtres['etat']), 'text-slate-600 hover:bg-slate-100' => !empty($filtres['etat'])])>Tous</a>
            @foreach($etats as $val => $lib)
                <a href="{{ $url(['etat' => $val, 'page' => null]) }}" @class(['rounded-md px-3 py-1.5 font-medium transition', 'bg-slate-900 text-white' => ($filtres['etat'] ?? '') === $val, 'text-slate-600 hover:bg-slate-100' => ($filtres['etat'] ?? '') !== $val])>{{ $lib }}</a>
            @endforeach
        </div>

        <div x-data="{ open: false }" @click.outside="open = false" class="relative">
            <button type="button" @click="open = !open"
                    class="inline-flex h-9 items-center gap-2 rounded-lg border border-slate-200 bg-white px-3 text-sm text-slate-700 shadow-sm hover:bg-slate-50">
                <x-icone nom="warning" class="h-4 w-4 text-slate-400" /> Plus de filtres
            </button>
            <div x-show="open" x-cloak x-transition.opacity.duration.100ms
                 class="absolute left-0 z-30 mt-2 w-64 space-y-3 rounded-xl border border-slate-200 bg-white p-3 text-sm shadow-lg">
                <div>
                    <p class="mb-1.5 text-xs font-semibold uppercase tracking-wide text-slate-400">Réutilisables</p>
                    <div class="flex gap-1.5">
                        @foreach(['' => 'Tous', 'oui' => 'Réutilisables', 'non' => 'Usage unique'] as $val => $lib)
                            <a href="{{ $url(['reutilisable' => $val, 'page' => null]) }}"
                               @class(['rounded-md border px-2.5 py-1 text-xs', 'border-slate-800 bg-slate-900 text-white' => ($filtres['reutilisable'] ?? '') === $val, 'border-slate-200 text-slate-600 hover:bg-slate-50' => ($filtres['reutilisable'] ?? '') !== $val])>{{ $lib }}</a>
                        @endforeach
                    </div>
                </div>
                <div>
                    <p class="mb-1.5 text-xs font-semibold uppercase tracking-wide text-slate-400">Protection</p>
                    <div class="flex gap-1.5">
                        @foreach(['' => 'Tous', 'oui' => 'Protégés'] as $val => $lib)
                            <a href="{{ $url(['protege' => $val, 'page' => null]) }}"
                               @class(['rounded-md border px-2.5 py-1 text-xs', 'border-amber-600 bg-amber-600 text-white' => ($filtres['protege'] ?? '') === $val, 'border-slate-200 text-slate-600 hover:bg-slate-50' => ($filtres['protege'] ?? '') !== $val])>{{ $lib }}</a>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>

        <div class="relative min-w-[200px] flex-1 sm:max-w-xs">
            <x-icone nom="search" class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400 z-10" />
            <input type="search" x-model="recherche" placeholder="Rechercher…"
                   class="h-9 w-full rounded-lg border border-slate-200 bg-white pl-9 pr-9 text-sm shadow-sm placeholder:text-slate-400 focus:border-slate-800 focus:outline-none focus:ring-2 focus:ring-slate-800/15">
            <button type="button" x-show="recherche.length > 0" x-cloak @click="recherche = ''"
                    class="absolute right-2 top-1/2 -translate-y-1/2 rounded p-1 text-slate-400 hover:bg-slate-100 hover:text-slate-700">
                <x-icone nom="x" class="h-4 w-4" />
            </button>
        </div>

        @if($aDesFiltres)
            <a href="{{ route('hotspot.index') }}" class="inline-flex h-9 items-center gap-1 text-sm text-slate-500 hover:text-slate-800">
                <x-icone nom="x" class="h-4 w-4" /> Réinitialiser
            </a>
        @endif
    </div>

    {{-- Barre d'actions du tableau --}}
    <div class="mb-3 flex flex-wrap items-center justify-between gap-3 rounded-xl border border-slate-200 bg-white px-4 py-2.5 shadow-sm">
        <span class="text-sm text-slate-500">
            <span x-text="lignesAffichees.length"></span> identifiant(s) affiché(s)
            <span x-show="selected.length > 0" x-cloak> · <span x-text="selected.length"></span> sélectionné(s)</span>
        </span>
        <button type="button" @click="ouvrirSuppression()" :disabled="selected.length === 0"
                :class="selected.length === 0 ? 'cursor-not-allowed bg-slate-200 text-slate-400' : 'bg-rose-600 text-white hover:bg-rose-500'"
                class="inline-flex h-8 items-center gap-1.5 rounded-lg px-3 text-xs font-medium transition">
            <x-icone nom="trash" class="h-4 w-4" />
            <span>Supprimer<template x-if="selected.length"><span> (<span x-text="selected.length"></span>)</span></template></span>
        </button>
    </div>

    {{-- Tableau --}}
    <div class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
        <div class="max-h-[65vh] overflow-y-auto">
            <table class="w-full text-sm">
                <thead class="sticky top-0 z-10 bg-slate-800 text-left text-xs font-semibold uppercase tracking-wide text-white">
                    <tr>
                        <th class="w-10 px-4 py-3">
                            <input type="checkbox" :checked="tousSelectionnes" @change="basculerTous()"
                                   class="rounded border-slate-400 text-slate-900 focus:ring-white/30">
                        </th>
                        <th class="px-4 py-3">Identifiants</th>
                        <th class="px-4 py-3">Appareil</th>
                        <th class="px-4 py-3">Volume</th>
                        <th class="px-4 py-3">État</th>
                        <th class="px-4 py-3 text-center">Protection</th>
                        <th class="px-4 py-3 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <template x-if="lignesAffichees.length === 0">
                        <tr>
                            <td colspan="7" class="px-4 py-16 text-center">
                                <template x-if="recherche.length > 0">
                                    <div>
                                        <p class="text-slate-400">Aucun résultat pour « <span class="font-medium text-slate-600" x-text="recherche"></span> ».</p>
                                        <p class="mt-1 text-xs text-slate-400">Essayez un autre terme ou effacez la recherche.</p>
                                    </div>
                                </template>
                                <template x-if="recherche.length === 0">
                                    <p class="text-slate-400">Aucun identifiant ne correspond à ces filtres.</p>
                                </template>
                            </td>
                        </tr>
                    </template>

                    <template x-for="ligne in lignesAffichees" :key="ligne.id">
                        <tr :class="ligne.classe_ligne" class="border-b border-slate-200 transition">
                            <td class="px-4 py-4">
                                <input type="checkbox" :value="String(ligne.id)" x-model="selected"
                                       :title="ligne.protege ? 'Protégé — la suppression demandera de taper son username' : ''"
                                       class="rounded border-slate-300 text-slate-900 focus:ring-slate-800/30">
                            </td>
                            <td class="px-4 py-4">
                                <div class="flex items-center gap-1.5">
                                    <span class="font-mono text-sm font-medium text-slate-900" x-html="surbriller(ligne.username)"></span>
                                    <span x-show="!ligne.synced" title="Non synchronisé avec MikroTik" class="h-1.5 w-1.5 shrink-0 rounded-full bg-rose-500"></span>
                                    <span x-show="ligne.reutilisable" title="Réutilisable" class="h-1.5 w-1.5 shrink-0 rounded-full bg-violet-500"></span>
                                </div>
                                <div class="text-xs text-slate-500" x-show="ligne.nom" x-html="surbriller(ligne.nom)"></div>
                                <div class="mt-0.5 flex items-center gap-1.5 text-xs text-slate-500" x-data="{ show: false }">
                                    <span class="font-mono" x-show="!show">••••••••</span>
                                    <span class="font-mono" x-show="show" x-cloak x-html="surbriller(ligne.password)"></span>
                                    <button type="button" @click="show = !show" class="rounded p-0.5 text-slate-400 hover:bg-slate-100 hover:text-slate-700">
                                        <span x-show="!show"><x-icone nom="eye" class="h-4 w-4" /></span>
                                        <span x-show="show" x-cloak><x-icone nom="eye-off" class="h-4 w-4" /></span>
                                    </button>
                                </div>
                            </td>
                            <td class="px-4 py-4">
                                <button type="button" @click="renommerAppareil(ligne)" class="inline-flex items-center gap-1.5 text-left text-slate-700 hover:text-slate-900">
                                    <span x-show="ligne.appareil_icone === 'telephone'"><x-icone nom="phone" class="h-4 w-4 shrink-0 text-slate-400" /></span>
                                    <span x-show="ligne.appareil_icone === 'tablette'"><x-icone nom="tablet" class="h-4 w-4 shrink-0 text-slate-400" /></span>
                                    <span x-show="ligne.appareil_icone === 'desktop'"><x-icone nom="desktop" class="h-4 w-4 shrink-0 text-slate-400" /></span>
                                    <span x-html="surbriller(ligne.appareil || 'Nommer l\u2019appareil…')" :class="!ligne.appareil ? 'italic text-slate-400' : ''"></span>
                                    <x-icone nom="pencil" class="h-3.5 w-3.5 shrink-0 text-slate-300" />
                                </button>
                            </td>
                            <td class="px-4 py-4 text-slate-600" x-text="ligne.volume"></td>
                            <td class="px-4 py-4">
                                <span class="inline-flex items-center gap-1.5 rounded-md px-2 py-1 text-xs font-medium ring-1 ring-inset"
                                      :class="{
                                        'bg-emerald-50 text-emerald-700 ring-emerald-600/20': ligne.etat_couleur === 'emerald',
                                        'bg-amber-50 text-amber-700 ring-amber-600/20': ligne.etat_couleur === 'amber',
                                        'bg-slate-100 text-slate-700 ring-slate-500/20': ligne.etat_couleur === 'slate',
                                      }">
                                    <span class="h-1.5 w-1.5 rounded-full"
                                          :class="{ 'bg-emerald-500': ligne.etat_couleur === 'emerald', 'bg-amber-500': ligne.etat_couleur === 'amber', 'bg-slate-400': ligne.etat_couleur === 'slate' }"></span>
                                    <span x-text="ligne.etat_libelle"></span>
                                </span>
                            </td>
                            <td class="px-4 py-4 text-center">
                                <button type="button" @click="basculerProtection(ligne)"
                                        :title="ligne.protege
                                            ? 'Déprotéger : cet identifiant pourra être supprimé'
                                            : 'Protéger : empêche la suppression accidentelle de cet identifiant'"
                                        class="rounded-lg p-1.5 transition hover:bg-slate-100">
                                    <span x-show="ligne.protege"><x-icone nom="lock" class="h-5 w-5 text-amber-600" /></span>
                                    <span x-show="!ligne.protege"><x-icone nom="lock-open" class="h-5 w-5 text-slate-300" /></span>
                                </button>
                            </td>
                            <td class="px-4 py-4 text-right">
                                <button type="button" @click="demanderSuppressionUnitaire(ligne)"
                                        class="inline-flex items-center gap-1.5 rounded-lg px-2.5 py-1.5 text-xs font-medium text-rose-600 transition hover:bg-rose-50"
                                        title="Supprimer cet identifiant">
                                    <x-icone nom="trash" class="h-4 w-4" />
                                    <span>Supprimer</span>
                                </button>
                            </td>
                        </tr>
                    </template>
                </tbody>
            </table>
        </div>
    </div>

    {{-- Pagination --}}
    @if($vouchers->hasPages())
        <div class="mt-4 flex items-center justify-end gap-2 text-sm text-slate-500">
            @if(!$vouchers->onFirstPage())
                <a href="{{ $vouchers->previousPageUrl() }}" class="inline-flex h-9 items-center rounded-lg border border-slate-200 bg-white px-3 text-slate-700 hover:bg-slate-50">Précédent</a>
            @endif
            <span>Page {{ $vouchers->currentPage() }} / {{ $vouchers->lastPage() }}</span>
            @if($vouchers->hasMorePages())
                <a href="{{ $vouchers->nextPageUrl() }}" class="inline-flex h-9 items-center rounded-lg border border-slate-200 bg-white px-3 text-slate-700 hover:bg-slate-50">Suivant</a>
            @endif
        </div>
    @endif

    {{-- Modale Générer --}}
    <div x-show="genererOuvert" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4">
        <div class="absolute inset-0 bg-slate-900/50" @click="genererOuvert = false" x-transition.opacity></div>
        <div x-show="genererOuvert" x-transition class="relative max-h-[90vh] w-full max-w-lg overflow-y-auto rounded-2xl bg-white p-6 shadow-xl">
            <h2 class="text-lg font-semibold text-slate-900">Générer des identifiants</h2>
            <p class="mt-1 text-sm text-slate-500">Pool automatique : usage unique, non protégés.</p>

            @if($errors->any())
                <div class="mt-4 flex items-start gap-2 rounded-lg border border-amber-200 bg-amber-50 px-3 py-2.5 text-sm text-amber-900">
                    <x-icone nom="warning" class="mt-0.5 h-4 w-4 shrink-0 text-amber-600" />
                    <div class="space-y-1">
                        @foreach($errors->all() as $err)<p>{{ $err }}</p>@endforeach
                    </div>
                </div>
            @endif

            <form method="POST" action="{{ route('hotspot.generer') }}" class="mt-4 space-y-4"
                  x-data="{ quantite: {{ (int) old('quantite', 10) }} }">
                @csrf

                @if($stats['pool_disponibles'] >= $stats['pool_seuil'])
                    <div class="rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-xs text-amber-800">
                        Il reste {{ $stats['pool_disponibles'] }} identifiant(s) disponible(s) (seuil {{ $stats['pool_seuil'] }}).
                    </div>
                @endif

                <div>
                    <label class="mb-1.5 block text-sm font-medium text-slate-700">Quantité</label>
                    <input type="number" name="quantite" x-model.number="quantite" min="1" max="200" required class="{{ $input }}">
                    <div class="mt-2 flex gap-1.5">
                        @foreach([5, 10, 20, 50] as $n)
                            <button type="button" @click="quantite = {{ $n }}" class="rounded-md border border-slate-200 px-2.5 py-1 text-xs text-slate-600 hover:bg-slate-50">{{ $n }}</button>
                        @endforeach
                    </div>
                </div>

                <div class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-slate-700">Serveur</label>
                        <select name="serveur" class="{{ $input }}">
                            <template x-for="s in serveurs" :key="s"><option :value="s" x-text="s"></option></template>
                        </select>
                    </div>
                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-slate-700">Profil</label>
                        <select name="profil" class="{{ $input }}">
                            <template x-for="p in profils" :key="p"><option :value="p" x-text="p"></option></template>
                        </select>
                    </div>
                </div>

                <div>
                    <label class="mb-1.5 block text-sm font-medium text-slate-700">Mot de passe</label>
                    <div class="flex gap-4 text-sm text-slate-600">
                        <label class="inline-flex items-center gap-1.5"><input type="radio" name="mode" value="distinct" checked class="text-slate-900"> Différent du username</label>
                        <label class="inline-flex items-center gap-1.5"><input type="radio" name="mode" value="identique" class="text-slate-900"> Identique au username</label>
                    </div>
                </div>

                <div class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-slate-700">Longueur</label>
                        <select name="longueur" class="{{ $input }}">
                            @for($i = 3; $i <= 8; $i++)
                                <option value="{{ $i }}" {{ $i === 4 ? 'selected' : '' }}>{{ $i }} caractères</option>
                            @endfor
                        </select>
                    </div>
                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-slate-700">Préfixe <span class="font-normal text-slate-400">(optionnel)</span></label>
                        <input type="text" name="prefixe" maxlength="16" class="{{ $input }} font-mono">
                    </div>
                </div>

                <div>
                    <label class="mb-1.5 block text-sm font-medium text-slate-700">Jeu de caractères</label>
                    <select name="jeu" class="{{ $input }}">
                        @foreach($jeux as $val => $lib)<option value="{{ $val }}">{{ $lib }}</option>@endforeach
                    </select>
                </div>

                <div class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-slate-700">Limite de temps (min) <span class="font-normal text-slate-400">— vide = illimité</span></label>
                        <input type="number" name="duree" min="0" class="{{ $input }}">
                    </div>
                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-slate-700">Limite de données (Mo) <span class="font-normal text-slate-400">— vide = illimité</span></label>
                        <input type="number" name="limite_data_mo" min="0" class="{{ $input }}">
                    </div>
                </div>

                <div>
                    <label class="mb-1.5 block text-sm font-medium text-slate-700">Commentaire <span class="font-normal text-slate-400">(optionnel)</span></label>
                    <input type="text" name="commentaire" maxlength="255" class="{{ $input }}">
                </div>

                <label class="flex items-center gap-2 text-sm text-slate-700">
                    <input type="checkbox" name="force" value="1" class="rounded border-slate-300 text-amber-600 focus:ring-amber-600/30">
                    Générer quand même, même s'il reste du stock
                </label>

                <div class="flex items-center justify-end gap-3 border-t border-slate-100 pt-4">
                    <button type="button" @click="genererOuvert = false" class="inline-flex h-10 items-center rounded-lg px-4 text-sm font-medium text-slate-600 hover:bg-slate-100">Annuler</button>
                    <button type="submit" class="inline-flex h-10 items-center rounded-lg bg-slate-900 px-5 text-sm font-medium text-white hover:bg-slate-700">Générer</button>
                </div>
            </form>
        </div>
    </div>

    {{-- Modale Ajouter --}}
    <div x-show="ajouterOuvert" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4">
        <div class="absolute inset-0 bg-slate-900/50" @click="ajouterOuvert = false" x-transition.opacity></div>
        <div x-show="ajouterOuvert" x-transition class="relative max-h-[90vh] w-full max-w-lg overflow-y-auto rounded-2xl bg-white p-6 shadow-xl">
            <h2 class="text-lg font-semibold text-slate-900">Ajouter un identifiant</h2>
            <p class="mt-1 text-sm text-slate-500">Pour un poste, un accès familial, ou un identifiant unique.</p>

            @if($errors->has('global') && old('username'))
                <div class="mt-4 flex items-start gap-2 rounded-lg border border-rose-200 bg-rose-50 px-3 py-2.5 text-sm text-rose-900">
                    <x-icone nom="warning" class="mt-0.5 h-4 w-4 shrink-0 text-rose-600" />
                    <p>{{ $errors->first('global') }}</p>
                </div>
            @endif

            <form method="POST" action="{{ route('hotspot.ajouter') }}" class="mt-4 space-y-4"
                  x-data="{
                      username: '{{ old('username', '') }}', password: '{{ old('password', '') }}',
                      suggerer() {
                          const a = 'abcdefghijklmnopqrstuvwxyz';
                          this.username = Array.from({length:4}, () => a[Math.floor(Math.random()*a.length)]).join('');
                          this.password = Array.from({length:4}, () => Math.floor(Math.random()*10)).join('');
                      }
                  }">
                @csrf

                <div>
                    <label class="mb-1.5 block text-sm font-medium text-slate-700">Nom <span class="font-normal text-slate-400">(libellé, optionnel)</span></label>
                    <input type="text" name="nom" value="{{ old('nom') }}" maxlength="100" placeholder="Ex : Poste 2, Mahefa, Famille Rabe" class="{{ $input }}">
                </div>

                <div class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-slate-700">Username</label>
                        <input type="text" name="username" x-model="username" required maxlength="64" autocomplete="off" class="{{ $input }} font-mono">
                    </div>
                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-slate-700">Mot de passe</label>
                        <input type="text" name="password" x-model="password" required maxlength="64" autocomplete="off" class="{{ $input }} font-mono">
                    </div>
                </div>
                <button type="button" @click="suggerer()" class="text-xs text-slate-500 underline underline-offset-2 hover:text-slate-800">Suggérer un identifiant aléatoire</button>

                <div class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-slate-700">Serveur</label>
                        <select name="serveur" class="{{ $input }}">
                            <template x-for="s in serveurs" :key="s"><option :value="s" x-text="s"></option></template>
                        </select>
                    </div>
                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-slate-700">Profil</label>
                        <select name="profil" class="{{ $input }}">
                            <template x-for="p in profils" :key="p"><option :value="p" x-text="p"></option></template>
                        </select>
                    </div>
                </div>

                <div class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-slate-700">Limite de temps (min) <span class="font-normal text-slate-400">— vide = illimité</span></label>
                        <input type="number" name="duree" min="0" class="{{ $input }}">
                    </div>
                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-slate-700">Limite de données (Mo) <span class="font-normal text-slate-400">— vide = illimité</span></label>
                        <input type="number" name="limite_data_mo" min="0" class="{{ $input }}">
                    </div>
                </div>

                <div>
                    <label class="mb-1.5 block text-sm font-medium text-slate-700">Commentaire <span class="font-normal text-slate-400">(optionnel)</span></label>
                    <input type="text" name="commentaire" value="{{ old('commentaire') }}" maxlength="255" class="{{ $input }}">
                </div>

                <div class="flex flex-wrap gap-5 border-t border-slate-100 pt-4">
                    <label class="inline-flex items-center gap-2 text-sm text-slate-700">
                        <input type="checkbox" name="reutilisable" value="1" {{ old('reutilisable') ? 'checked' : '' }} class="rounded border-slate-300 text-violet-600 focus:ring-violet-600/30">
                        Réutilisable
                    </label>
                    <label class="inline-flex items-center gap-2 text-sm text-slate-700">
                        <input type="checkbox" name="protege" value="1" {{ old('protege') ? 'checked' : '' }} class="rounded border-slate-300 text-amber-600 focus:ring-amber-600/30">
                        Protéger contre la suppression
                    </label>
                </div>

                <div class="flex items-center justify-end gap-3 border-t border-slate-100 pt-4">
                    <button type="button" @click="ajouterOuvert = false" class="inline-flex h-10 items-center rounded-lg px-4 text-sm font-medium text-slate-600 hover:bg-slate-100">Annuler</button>
                    <button type="submit" class="inline-flex h-10 items-center rounded-lg bg-slate-900 px-5 text-sm font-medium text-white hover:bg-slate-700">Créer</button>
                </div>
            </form>
        </div>
    </div>

    {{-- ═══ Modale : Confirmation de suppression (custom, gère aussi les protégés) ═══ --}}
    <div x-show="supprimerOuvert" x-cloak class="fixed inset-0 z-[55] flex items-center justify-center p-4">
        <div class="absolute inset-0 bg-slate-900/50" @click="supprimerOuvert = false" x-transition.opacity></div>
        <div x-show="supprimerOuvert" x-transition class="relative w-full max-w-md overflow-hidden rounded-2xl bg-white shadow-xl">
            <div class="flex items-start gap-3 border-b border-slate-100 px-6 pt-5 pb-4">
                <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-rose-100">
                    <x-icone nom="trash" class="h-5 w-5 text-rose-600" />
                </span>
                <div>
                    <h2 class="text-base font-semibold text-slate-900">Confirmer la suppression</h2>
                    <p class="text-sm text-slate-500" x-text="supprimerTitre"></p>
                </div>
            </div>

            <div class="space-y-3 px-6 py-4">
                <ul class="space-y-1.5 text-sm text-slate-700">
                    <template x-for="(avert, i) in supprimerAvertissements" :key="i">
                        <li class="flex items-start gap-2">
                            <span class="mt-1 h-1.5 w-1.5 shrink-0 rounded-full bg-amber-500"></span>
                            <span x-text="avert"></span>
                        </li>
                    </template>
                </ul>

                {{-- Protégés à déverrouiller --}}
                <template x-if="supprimerProteges.length > 0">
                    <div class="rounded-lg border border-amber-200 bg-amber-50 p-3 space-y-3">
                        <div class="flex items-start gap-2 text-sm text-amber-900">
                            <x-icone nom="lock" class="mt-0.5 h-4 w-4 shrink-0" />
                            <p><strong x-text="supprimerProteges.length"></strong> identifiant(s) protégé(s) dans la sélection. Tapez leur username exact pour confirmer :</p>
                        </div>
                        <template x-for="p in supprimerProteges" :key="p.id">
                            <div>
                                <label class="block font-mono text-xs font-medium text-amber-900" x-text="p.username"></label>
                                <input type="text" x-model="supprimerSaisiesProteges[p.id]" autocomplete="off" spellcheck="false"
                                       class="mt-1 w-full rounded-lg border border-amber-300 bg-white px-3 py-1.5 font-mono text-sm focus:border-amber-600 focus:outline-none focus:ring-2 focus:ring-amber-600/20"
                                       :placeholder="p.username">
                            </div>
                        </template>
                    </div>
                </template>

                <p class="rounded-lg bg-slate-50 px-3 py-2 text-xs text-slate-500">
                    Cette action est <strong class="text-slate-700">irréversible</strong> : les identifiants seront retirés de Cyber Manager et du MikroTik.
                </p>
            </div>

            <div class="flex items-center justify-end gap-2 border-t border-slate-100 bg-slate-50 px-6 py-3">
                <button type="button" @click="supprimerOuvert = false"
                        class="inline-flex h-9 items-center rounded-lg border border-slate-200 bg-white px-4 text-sm font-medium text-slate-700 hover:bg-slate-50">
                    Annuler
                </button>
                <button type="button" @click="confirmerSuppression()" :disabled="suppressionEnCours || !tousProtegesConfirmes"
                        class="inline-flex h-9 items-center gap-2 rounded-lg bg-rose-600 px-4 text-sm font-medium text-white hover:bg-rose-500 disabled:cursor-not-allowed disabled:opacity-50">
                    <x-icone nom="trash" class="h-4 w-4" />
                    <span x-text="suppressionEnCours ? 'Suppression…' : 'Supprimer définitivement'"></span>
                </button>
            </div>
        </div>
    </div>

    {{-- ═══ Modale : Déprotéger (cadenas, sans suppression) ═══ --}}
    <div x-show="deprotegerOuvert" x-cloak class="fixed inset-0 z-[55] flex items-center justify-center p-4">
        <div class="absolute inset-0 bg-slate-900/50" @click="deprotegerOuvert = false" x-transition.opacity></div>
        <div x-show="deprotegerOuvert" x-transition class="relative w-full max-w-md overflow-hidden rounded-2xl bg-white shadow-xl">
            <div class="flex items-start gap-3 border-b border-slate-100 px-6 pt-5 pb-4">
                <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-amber-100">
                    <x-icone nom="lock-open" class="h-5 w-5 text-amber-600" />
                </span>
                <div>
                    <h2 class="text-base font-semibold text-slate-900">Déprotéger cet identifiant ?</h2>
                    <p class="text-sm text-slate-500">La protection empêche une suppression accidentelle.</p>
                </div>
            </div>

            <div class="space-y-4 px-6 py-4">
                <div class="rounded-lg border border-amber-200 bg-amber-50 px-3 py-2.5 text-sm text-amber-900">
                    Une fois déprotégé, cet identifiant pourra être <strong>supprimé comme n'importe quel autre</strong>, y compris par erreur depuis la sélection multiple.
                </div>

                <div>
                    <label class="mb-1.5 block text-sm font-medium text-slate-700">
                        Pour confirmer, tapez le username exact :
                        <span class="ml-1 rounded bg-slate-100 px-1.5 py-0.5 font-mono text-xs text-slate-700" x-text="deprotegerLigne?.username"></span>
                    </label>
                    <input type="text" x-model="deprotegerSaisie" autocomplete="off" spellcheck="false"
                           @keydown.enter.prevent="if (peutDeproteger) confirmerDeproteger()"
                           class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 font-mono text-sm focus:border-slate-800 focus:outline-none focus:ring-2 focus:ring-slate-800/15"
                           :placeholder="deprotegerLigne?.username">
                </div>
            </div>

            <div class="flex items-center justify-end gap-2 border-t border-slate-100 bg-slate-50 px-6 py-3">
                <button type="button" @click="deprotegerOuvert = false"
                        class="inline-flex h-9 items-center rounded-lg border border-slate-200 bg-white px-4 text-sm font-medium text-slate-700 hover:bg-slate-50">
                    Annuler
                </button>
                <button type="button" @click="confirmerDeproteger()" :disabled="!peutDeproteger || deprotectionEnCours"
                        class="inline-flex h-9 items-center gap-2 rounded-lg bg-amber-600 px-4 text-sm font-medium text-white hover:bg-amber-500 disabled:cursor-not-allowed disabled:opacity-50">
                    <x-icone nom="lock-open" class="h-4 w-4" />
                    <span x-text="deprotectionEnCours ? 'En cours…' : 'Déprotéger'"></span>
                </button>
            </div>
        </div>
    </div>

</div>

<script>
function hotspotPage(cfg) {
    return {
        lignes: cfg.lignesInitiales,
        stats: cfg.statsInitiales,
        selected: [],
        recherche: '',

        supprimerOuvert: false,
        supprimerTitre: '',
        supprimerAvertissements: [],
        supprimerIds: [],
        supprimerProteges: [],
        supprimerSaisiesProteges: {},
        suppressionEnCours: false,

        deprotegerOuvert: false,
        deprotegerLigne: null,
        deprotegerSaisie: '',
        deprotectionEnCours: false,

        demarrer() { setInterval(() => this.rafraichir(), 6000); },

        async rafraichir() {
            try {
                const r = await fetch(cfg.urlEtat, { headers: { Accept: 'application/json' } });
                if (!r.ok) return;
                const d = await r.json();
                this.lignes = d.lignes;
                this.stats = d.stats;
                const presents = this.lignes.map(l => String(l.id));
                this.selected = this.selected.filter(id => presents.includes(id));
            } catch (e) {}
        },

        // ── Recherche temps réel + surlignage ──
        get lignesAffichees() {
            const terme = this.recherche.trim().toLowerCase();
            if (terme === '') return this.lignes;
            return this.lignes.filter(l => {
                const champs = [l.username, l.nom, l.password, l.appareil, l.etat_libelle, l.volume];
                return champs.some(c => c && String(c).toLowerCase().includes(terme));
            });
        },
        escapeHtml(s) {
            if (s === null || s === undefined) return '';
            return String(s)
                .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;').replace(/'/g, '&#39;');
        },
        surbriller(texte) {
            const safe = this.escapeHtml(texte);
            const terme = this.recherche.trim();
            if (terme === '') return safe;
            const termeEchappe = terme.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
            const regex = new RegExp(`(${termeEchappe})`, 'gi');
            return safe.replace(regex, '<mark class="rounded-sm bg-yellow-200 px-0.5 text-slate-900">$1</mark>');
        },

        // ── Sélection (protégés inclus) ──
        get tousSelectionnes() {
            const tous = this.lignesAffichees.map(l => String(l.id));
            return tous.length > 0 && tous.every(id => this.selected.includes(id));
        },
        basculerTous() {
            const tous = this.lignesAffichees.map(l => String(l.id));
            this.selected = this.tousSelectionnes ? [] : tous;
        },

        // ── Protection ──
        async basculerProtection(ligne) {
            if (ligne.protege) {
                this.deprotegerLigne = ligne;
                this.deprotegerSaisie = '';
                this.deprotegerOuvert = true;
                return;
            }
            try {
                const r = await window.cmFetch(cfg.urlProteger + '/' + ligne.id + '/proteger', { method: 'POST' });
                const d = await r.json();
                ligne.protege = d.protege;
                window.dispatchEvent(new CustomEvent('toast', { detail: 'Identifiant protégé' }));
            } catch (e) {
                window.dispatchEvent(new CustomEvent('toast', { detail: 'Action impossible, réessayez.' }));
            }
        },
        get peutDeproteger() {
            return this.deprotegerLigne && this.deprotegerSaisie === this.deprotegerLigne.username;
        },
        async confirmerDeproteger() {
            if (!this.peutDeproteger) return;
            this.deprotectionEnCours = true;
            try {
                const r = await window.cmFetch(cfg.urlProteger + '/' + this.deprotegerLigne.id + '/proteger', { method: 'POST' });
                const d = await r.json();
                this.deprotegerLigne.protege = d.protege;
                window.dispatchEvent(new CustomEvent('toast', { detail: 'Identifiant déprotégé' }));
                this.deprotegerOuvert = false;
                this.deprotegerLigne = null;
            } catch (e) {
                window.dispatchEvent(new CustomEvent('toast', { detail: 'Déprotection impossible.' }));
            }
            this.deprotectionEnCours = false;
        },

        // ── Suppression ──
        demanderSuppressionUnitaire(ligne) {
            this.supprimerIds = [ligne.id];
            this.supprimerTitre = `Supprimer l'identifiant « ${ligne.username} » ?`;
            this.supprimerAvertissements = this.calculerAvertissements([ligne]);
            this.supprimerProteges = ligne.protege ? [ligne] : [];
            this.supprimerSaisiesProteges = {};
            this.supprimerOuvert = true;
        },
        ouvrirSuppression() {
            if (this.selected.length === 0) return;
            const lignes = this.lignes.filter(l => this.selected.includes(String(l.id)));
            this.supprimerIds = lignes.map(l => l.id);
            this.supprimerTitre = `Supprimer ${lignes.length} identifiant(s) ?`;
            this.supprimerAvertissements = this.calculerAvertissements(lignes);
            this.supprimerProteges = lignes.filter(l => l.protege);
            this.supprimerSaisiesProteges = {};
            this.supprimerOuvert = true;
        },
        calculerAvertissements(lignes) {
            const av = [];
            const dispo   = lignes.filter(l => l.etat === 'disponible').length;
            const enCours = lignes.filter(l => l.etat === 'en_cours').length;
            const utilise = lignes.filter(l => l.etat === 'utilise').length;

            if (dispo > 0)   av.push(`${dispo} n'ont jamais été utilisés — ils seront perdus.`);
            if (enCours > 0) av.push(`${enCours} sont en cours de connexion — le client sera déconnecté.`);
            if (utilise > 0) av.push(`${utilise} ont déjà été utilisés — l'historique de session sera conservé.`);
            if (av.length === 0) av.push('Ces identifiants seront définitivement supprimés.');
            return av;
        },
        get tousProtegesConfirmes() {
            return this.supprimerProteges.every(p => this.supprimerSaisiesProteges[p.id] === p.username);
        },
        async confirmerSuppression() {
            this.suppressionEnCours = true;
            try {
                const body = new FormData();
                body.append('_token', document.querySelector('meta[name="csrf-token"]').content);
                this.supprimerIds.forEach(id => body.append('ids[]', id));
                if (this.supprimerProteges.length > 0) body.append('force', '1');

                const r = await fetch(cfg.urlSupprimer, {
                    method: 'POST',
                    headers: {
                        'X-CSRF-Token': document.querySelector('meta[name="csrf-token"]').content,
                        'Accept': 'application/json',
                    },
                    body,
                });

                if (!r.ok) throw new Error('HTTP ' + r.status);
                const d = await r.json();

                window.dispatchEvent(new CustomEvent('toast', { detail: d.message || 'Suppression effectuée.' }));
                this.supprimerOuvert = false;
                this.selected = [];
                setTimeout(() => window.location.reload(), 700);
            } catch (e) {
                window.dispatchEvent(new CustomEvent('toast', { detail: 'Suppression impossible.' }));
            }
            this.suppressionEnCours = false;
        },

        imprimerDisponibles() { window.open(cfg.urlImprimerDisponibles, '_blank'); },

        async renommerAppareil(ligne) {
            if (!ligne.appareil_id) {
                window.dispatchEvent(new CustomEvent('toast', { detail: 'Aucun appareil associé pour l’instant.' }));
                return;
            }
            const saisie = prompt('Nom à afficher pour cet appareil :', ligne.appareil || '');
            if (saisie === null || saisie.trim() === '') return;
            try {
                const r = await window.cmFetch(cfg.urlRenommer + '/' + ligne.appareil_id + '/renommer', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ nom: saisie.trim() }),
                });
                const d = await r.json();
                ligne.appareil = d.nom;
                window.dispatchEvent(new CustomEvent('toast', { detail: 'Appareil renommé' }));
            } catch (e) {
                window.dispatchEvent(new CustomEvent('toast', { detail: 'Renommage impossible.' }));
            }
        },
    };
}
</script>
@endsection