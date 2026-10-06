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

    {{-- KPI --}}
    <div class="mb-6 grid grid-cols-2 gap-4 lg:grid-cols-4">
        <x-kpi-card label="Total" :value="$stats['total']" :hint="$stats['proteges'] . ' protégé(s)'" couleur="slate" icone="key" />
        <x-kpi-card label="Disponibles" :value="$stats['disponible']" hint="Pas encore utilisés" couleur="emerald" icone="check" />
        <x-kpi-card label="En cours" :value="$stats['en_cours']" hint="Connectés en ce moment" couleur="amber" icone="clock" />
        <x-kpi-card label="Utilisés" :value="$stats['utilise']" hint="Déjà servi, pas encore supprimé" couleur="slate" icone="archive" />
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

    @if($errors->any())
        <div class="mb-6 rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800">
            @foreach($errors->all() as $err) <div>{{ $err }}</div> @endforeach
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

        <form method="GET" action="{{ route('hotspot.index') }}" class="relative min-w-[200px] flex-1 sm:max-w-xs">
            @foreach(['etat', 'reutilisable', 'protege'] as $cle)
                @if(!empty($filtres[$cle])) <input type="hidden" name="{{ $cle }}" value="{{ $filtres[$cle] }}"> @endif
            @endforeach
            <x-icone nom="search" class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" />
            <input type="search" name="q" value="{{ $filtres['q'] ?? '' }}" placeholder="Rechercher…"
                   class="h-9 w-full rounded-lg border border-slate-200 bg-white pl-9 pr-3 text-sm shadow-sm placeholder:text-slate-400 focus:border-slate-800 focus:outline-none focus:ring-2 focus:ring-slate-800/15">
        </form>

        @if($aDesFiltres)
            <a href="{{ route('hotspot.index') }}" class="inline-flex h-9 items-center gap-1 text-sm text-slate-500 hover:text-slate-800">
                <x-icone nom="x" class="h-4 w-4" /> Réinitialiser
            </a>
        @endif
    </div>

    {{-- Barre de suppression, en haut du tableau --}}
    <div class="mb-3 flex items-center justify-between rounded-xl border border-slate-200 bg-white px-4 py-2.5 shadow-sm">
        <span class="text-sm text-slate-500">
            <span x-text="lignes.length"></span> identifiant(s) affiché(s)
            <span x-show="selected.length > 0" x-cloak> · <span x-text="selected.length"></span> sélectionné(s)</span>
        </span>
        <form id="bulk-form" method="POST" action="{{ route('hotspot.supprimer-masse') }}"
              @submit.prevent="if (selected.length && confirm(messageConfirmation())) $el.submit()">
            @csrf
            <template x-for="id in selected" :key="id"><input type="hidden" name="ids[]" :value="id"></template>
            <button type="submit" :disabled="selected.length === 0"
                    :class="selected.length === 0 ? 'cursor-not-allowed bg-slate-200 text-slate-400' : 'bg-rose-600 text-white hover:bg-rose-500'"
                    class="inline-flex h-8 items-center gap-1.5 rounded-lg px-3 text-xs font-medium transition">
                <x-icone nom="trash" class="h-4 w-4" />
                <span>Supprimer<template x-if="selected.length"> (<span x-text="selected.length"></span>)</template></span>
            </button>
        </form>
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
                    </tr>
                </thead>
                <tbody>
                    <template x-if="lignes.length === 0">
                        <tr><td colspan="6" class="px-4 py-16 text-center text-slate-400">Aucun identifiant ne correspond à ces filtres.</td></tr>
                    </template>

                    <template x-for="ligne in lignes" :key="ligne.id">
                        <tr :class="ligne.classe_ligne" class="border-b border-slate-200 transition">
                            <td class="px-4 py-4">
                                <input type="checkbox" :value="String(ligne.id)" x-model="selected" :disabled="ligne.protege"
                                       :title="ligne.protege ? 'Protégé — déverrouillez avant de supprimer' : ''"
                                       class="rounded border-slate-300 text-slate-900 focus:ring-slate-800/30 disabled:opacity-30">
                            </td>
                            <td class="px-4 py-4">
                                <div class="flex items-center gap-1.5">
                                    <span class="font-mono text-sm font-medium text-slate-900" x-text="ligne.username"></span>
                                    <span x-show="!ligne.synced" title="Non synchronisé avec MikroTik" class="h-1.5 w-1.5 shrink-0 rounded-full bg-rose-500"></span>
                                    <span x-show="ligne.reutilisable" title="Réutilisable" class="h-1.5 w-1.5 shrink-0 rounded-full bg-violet-500"></span>
                                </div>
                                <div class="text-xs text-slate-500" x-show="ligne.nom" x-text="ligne.nom"></div>
                                <div class="mt-0.5 flex items-center gap-1.5 text-xs text-slate-500" x-data="{ show: false }">
                                    <span class="font-mono" x-show="!show">••••••••</span>
                                    <span class="font-mono" x-show="show" x-cloak x-text="ligne.password"></span>
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
                                    <span x-text="ligne.appareil || 'Nommer l’appareil…'" :class="!ligne.appareil ? 'italic text-slate-400' : ''"></span>
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
                                        :title="ligne.protege ? 'Déprotéger' : 'Protéger contre la suppression'"
                                        class="rounded-lg p-1.5 transition hover:bg-slate-100">
                                    <span x-show="ligne.protege"><x-icone nom="lock" class="h-5 w-5 text-amber-600" /></span>
                                    <span x-show="!ligne.protege"><x-icone nom="lock-open" class="h-5 w-5 text-slate-300" /></span>
                                </button>
                            </td>
                        </tr>
                    </template>
                </tbody>
            </table>
        </div>
    </div>

    {{-- Pagination (navigation classique) --}}
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
                        @foreach($jeux as $val => $lib)
                            <option value="{{ $val }}">{{ $lib }}</option>
                        @endforeach
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

</div>

<script>
function hotspotPage(cfg) {
    return {
        lignes: cfg.lignesInitiales,
        stats: cfg.statsInitiales,
        selected: [],
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
        get tousSelectionnes() {
            const dispo = this.lignes.filter(l => !l.protege).map(l => String(l.id));
            return dispo.length > 0 && dispo.every(id => this.selected.includes(id));
        },
        basculerTous() {
            const dispo = this.lignes.filter(l => !l.protege).map(l => String(l.id));
            this.selected = this.tousSelectionnes ? [] : dispo;
        },
        async basculerProtection(ligne) {
            try {
                const r = await window.cmFetch(cfg.urlProteger + '/' + ligne.id + '/proteger', { method: 'POST' });
                const d = await r.json();
                ligne.protege = d.protege;
                if (d.protege) this.selected = this.selected.filter(id => id !== String(ligne.id));
            } catch (e) { window.dispatchEvent(new CustomEvent('toast', { detail: 'Action impossible, réessayez.' })); }
        },
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
            } catch (e) { window.dispatchEvent(new CustomEvent('toast', { detail: 'Renommage impossible.' })); }
        },
        messageConfirmation() {
            const sel = this.lignes.filter(l => this.selected.includes(String(l.id)));
            const dispo = sel.filter(l => l.etat === 'disponible').length;
            const enCours = sel.filter(l => l.etat === 'en_cours').length;
            let msg = `Supprimer ${sel.length} identifiant(s) ?`;
            if (dispo > 0) msg += `\n⚠ ${dispo} n'ont jamais été utilisés.`;
            if (enCours > 0) msg += `\n⚠ ${enCours} sont en cours de connexion : leur client sera déconnecté.`;
            return msg;
        },
    };
}
</script>
@endsection