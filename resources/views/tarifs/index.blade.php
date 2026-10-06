@extends('layouts.cyber-manager', ['title' => 'Tarifs — Cyber Manager'])

@section('content')
@php
    $fmtAr = fn ($n) => number_format((int) $n, 0, ',', ' ') . ' Ar';
    $tarifActifEth  = $mode === 'unifie' ? $unifie : $ethernet;
    $tarifActifWifi = $mode === 'unifie' ? $unifie : $wifi;
@endphp

<x-flash />

<div x-data="tarifsPage({
        mode: '{{ $mode }}',
        urlSimuler: '{{ route('tarifs.simuler') }}',
        csrf: '{{ csrf_token() }}',
    })"
    @editer-tarif.window="ouvrirEdition($event.detail)">

    {{-- EN-TÊTE --}}
    <div class="mb-6 flex flex-wrap items-center justify-between gap-3">
        <div>
            <h1 class="text-2xl font-semibold tracking-tight text-slate-900">Tarifs</h1>
            <p class="text-sm text-slate-500">
                Règles de facturation appliquées aux sessions Postes Clients et Wi-Fi.
            </p>
        </div>
        <button type="button" @click="ouvrirReinitialisation()"
                class="inline-flex h-9 items-center gap-1.5 rounded-lg border border-rose-200 bg-white px-3 text-xs font-medium text-rose-700 shadow-sm hover:bg-rose-50">
            <x-icone nom="refresh" class="h-4 w-4" /> Réinitialiser
        </button>
    </div>

    {{-- LAYOUT 2 COLONNES --}}
    <div class="grid gap-6 lg:grid-cols-3">

        {{-- ══════════════ COLONNE GAUCHE (infos + raccourcis) ══════════════ --}}
        <div class="space-y-6 lg:col-span-2">

            {{-- Sélecteur de mode --}}
            <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
                <p class="mb-3 text-xs font-semibold uppercase tracking-wide text-slate-400">Mode de tarification</p>
                <div class="inline-flex w-full rounded-lg border border-slate-200 bg-slate-100 p-1 text-sm">
                    <button type="button" @click="demanderMode('unifie')"
                            @class([
                                'flex-1 rounded-md px-3 py-2.5 font-medium transition',
                                'bg-slate-900 text-white shadow-sm' => $mode === 'unifie',
                                'text-slate-600 hover:bg-white hover:text-slate-900' => $mode !== 'unifie',
                            ])>
                        Tarif unique
                    </button>
                    <button type="button" @click="demanderMode('separe')"
                            @class([
                                'flex-1 rounded-md px-3 py-2.5 font-medium transition',
                                'bg-slate-900 text-white shadow-sm' => $mode === 'separe',
                                'text-slate-600 hover:bg-white hover:text-slate-900' => $mode !== 'separe',
                            ])>
                        Tarifs séparés
                    </button>
                </div>
                <p class="mt-2 text-xs text-slate-500">
                    @if($mode === 'unifie')
                        Une seule règle s'applique aux accès <strong>Postes clients</strong> et <strong>Wi-Fi</strong>.
                    @else
                        Deux règles distinctes : l'une pour <strong>Postes clients</strong>, l'autre pour <strong>Wi-Fi</strong>.
                    @endif
                </p>
            </div>

            {{-- Cards des tarifs --}}
            @if($mode === 'unifie' && $unifie)
                @include('tarifs.partials.card', ['tarif' => $unifie, 'titre' => 'Poste client + Wi-Fi'])
            @endif

            @if($mode === 'separe' && $ethernet)
                @include('tarifs.partials.card', ['tarif' => $ethernet, 'titre' => 'Poste client'])
            @endif

            @if($mode === 'separe' && $wifi)
                @include('tarifs.partials.card', ['tarif' => $wifi, 'titre' => 'Tarif Wi-Fi'])
            @endif

            {{-- Raccourcis --}}
            <div class="space-y-4">
                @if($mode === 'unifie' && $unifie)
                    @include('tarifs.partials.raccourcis', ['tarif' => $unifie, 'titre' => 'Raccourcis Poste client + Wi-Fi'])
                @endif

                @if($mode === 'separe' && $ethernet)
                    @include('tarifs.partials.raccourcis', ['tarif' => $ethernet, 'titre' => 'Raccourcis Poste client'])
                @endif

                @if($mode === 'separe' && $wifi)
                    @include('tarifs.partials.raccourcis', ['tarif' => $wifi, 'titre' => 'Raccourcis Wi-Fi'])
                @endif
            </div>
        </div>

        {{-- ══════════════ COLONNE DROITE (simulateur) ══════════════ --}}
        <div class="lg:col-span-1">
            <div class="sticky top-20">
                @include('tarifs.partials.simulateur', [
                    'mode' => $mode,
                    'unifie' => $unifie,
                    'ethernet' => $ethernet,
                    'wifi' => $wifi,
                ])
            </div>
        </div>
    </div>

    {{-- ═══ Modale Modifier un tarif ═══ --}}
    <div x-show="editionOuverte" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4">
        <div class="absolute inset-0 bg-slate-900/50" @click="editionOuverte = false" x-transition.opacity></div>
        <div x-show="editionOuverte" x-transition
             class="relative w-full max-w-lg overflow-hidden rounded-2xl bg-white shadow-xl">
            <div class="flex items-center justify-between border-b border-slate-100 px-5 py-4">
                <h2 class="text-base font-semibold text-slate-900" x-text="'Modifier — ' + (edition?.titre ?? '')"></h2>
                <button type="button" @click="editionOuverte = false" class="rounded p-1 text-slate-400 hover:bg-slate-100">
                    <x-icone nom="x" class="h-5 w-5" />
                </button>
            </div>

            <form :action="edition?.update_url" method="POST" class="space-y-4 p-5">
                @csrf
                <input type="hidden" name="_method" value="PATCH">

                <div class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-slate-700">Prix par minute (Ar)</label>
                        <input type="number" name="montant_par_minute" x-model.number="edition.montant_par_minute"
                               required min="1" max="100000"
                               class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2.5 font-mono text-sm">
                    </div>
                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-slate-700">Minimum facturable (Ar)</label>
                        <input type="number" name="montant_minimum" x-model.number="edition.montant_minimum"
                               required min="0" max="1000000"
                               class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2.5 font-mono text-sm">
                        <p class="mt-1 text-xs text-slate-500">Montant plancher, ex. 300 Ar.</p>
                    </div>
                </div>

                <div class="rounded-lg border border-slate-200 bg-slate-50/60 p-3">
                    <label class="flex items-center gap-2 text-sm font-medium text-slate-700">
                        <input type="checkbox" name="arrondi_actif" value="1" x-model="edition.arrondi_actif"
                               class="rounded border-slate-300">
                        Appliquer un arrondi
                    </label>

                    <div class="mt-3 grid gap-4 sm:grid-cols-2" x-show="edition.arrondi_actif">
                        <div>
                            <label class="mb-1.5 block text-sm font-medium text-slate-700">Unité (Ar)</label>
                            <input type="number" name="unite_arrondi" x-model.number="edition.unite_arrondi"
                                   min="1" max="10000"
                                   class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2.5 font-mono text-sm">
                        </div>
                        <div>
                            <label class="mb-1.5 block text-sm font-medium text-slate-700">Seuil (Ar)</label>
                            <input type="number" name="seuil_arrondi" x-model.number="edition.seuil_arrondi"
                                   min="0" max="10000"
                                   class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2.5 font-mono text-sm">
                        </div>
                    </div>
                    <p class="mt-2 text-xs text-slate-500">
                        Ex. unité 100 / seuil 50 : 545 → 500, 550 → 600.
                    </p>
                </div>

                <div>
                    <label class="mb-1.5 block text-sm font-medium text-slate-700">Description (optionnel)</label>
                    <textarea name="description" x-model="edition.description" rows="2" maxlength="500"
                              class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm"></textarea>
                </div>

                <div class="flex items-center justify-end gap-3 border-t border-slate-100 pt-4">
                    <button type="button" @click="editionOuverte = false"
                            class="inline-flex h-10 items-center rounded-lg px-4 text-sm font-medium text-slate-600 hover:bg-slate-100">
                        Annuler
                    </button>
                    <button type="submit"
                            class="inline-flex h-10 items-center rounded-lg bg-slate-900 px-5 text-sm font-medium text-white hover:bg-slate-700">
                        Enregistrer
                    </button>
                </div>
            </form>
        </div>
    </div>

    {{-- ═══ Modale Confirmation de changement de mode ═══ --}}
    <div x-show="modeModal.ouvert" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4">
        <div class="absolute inset-0 bg-slate-900/50" @click="modeModal.ouvert = false" x-transition.opacity></div>
        <div x-show="modeModal.ouvert" x-transition
             class="relative w-full max-w-md overflow-hidden rounded-2xl bg-white shadow-xl">
            <div class="border-b border-slate-100 px-5 py-4">
                <h2 class="text-base font-semibold text-slate-900" x-text="modeModal.titre"></h2>
                <p class="mt-1 text-sm text-slate-500" x-text="modeModal.description"></p>
            </div>

            <form :action="modeModal.action" method="POST" class="space-y-4 p-5">
                @csrf

                <template x-if="modeModal.vers === 'unifie'">
                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-slate-700">Tarif à conserver comme base</label>
                        <select name="base" class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-sm">
                            <option value="ethernet">Tarif Ethernet actuel</option>
                            <option value="wifi">Tarif Wi-Fi actuel</option>
                        </select>
                    </div>
                </template>

                <div>
                    <label class="mb-1.5 block text-sm font-medium text-slate-700">
                        Pour confirmer, tapez
                        <code class="ml-1 rounded bg-slate-100 px-1.5 py-0.5 font-mono text-xs text-slate-700"
                              x-text="modeModal.mot"></code>
                    </label>
                    <input type="text" name="confirmation" x-model="modeModal.saisie" autocomplete="off"
                           class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 font-mono text-sm focus:border-slate-800 focus:outline-none">
                </div>

                <div class="flex items-center justify-end gap-3 border-t border-slate-100 pt-4">
                    <button type="button" @click="modeModal.ouvert = false"
                            class="inline-flex h-10 items-center rounded-lg px-4 text-sm font-medium text-slate-600 hover:bg-slate-100">
                        Annuler
                    </button>
                    <button type="submit" :disabled="modeModal.saisie !== modeModal.mot"
                            class="inline-flex h-10 items-center rounded-lg bg-amber-600 px-5 text-sm font-medium text-white hover:bg-amber-500 disabled:cursor-not-allowed disabled:opacity-40">
                        Confirmer
                    </button>
                </div>
            </form>
        </div>
    </div>

    {{-- ═══ Modale Réinitialisation ═══ --}}
    <div x-show="resetModal.ouvert" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4">
        <div class="absolute inset-0 bg-slate-900/50" @click="resetModal.ouvert = false" x-transition.opacity></div>
        <div x-show="resetModal.ouvert" x-transition
             class="relative w-full max-w-md overflow-hidden rounded-2xl bg-white shadow-xl">
            <div class="border-b border-slate-100 px-5 py-4">
                <h2 class="text-base font-semibold text-slate-900">Réinitialiser les tarifs ?</h2>
                <p class="mt-1 text-sm text-slate-500">
                    Toutes les règles et tous les raccourcis seront remplacés par les valeurs par défaut (20 Ar/min, minimum 300 Ar). Cette action ne touche pas aux sessions existantes.
                </p>
            </div>

            <form method="POST" action="{{ route('tarifs.reinitialiser') }}" class="space-y-4 p-5">
                @csrf

                <div>
                    <label class="mb-1.5 block text-sm font-medium text-slate-700">
                        Pour confirmer, tapez
                        <code class="ml-1 rounded bg-slate-100 px-1.5 py-0.5 font-mono text-xs text-slate-700">REINITIALISER</code>
                    </label>
                    <input type="text" name="confirmation" x-model="resetModal.saisie" autocomplete="off"
                           class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 font-mono text-sm focus:border-slate-800 focus:outline-none">
                </div>

                <div class="flex items-center justify-end gap-3 border-t border-slate-100 pt-4">
                    <button type="button" @click="resetModal.ouvert = false"
                            class="inline-flex h-10 items-center rounded-lg px-4 text-sm font-medium text-slate-600 hover:bg-slate-100">
                        Annuler
                    </button>
                    <button type="submit" :disabled="resetModal.saisie !== 'REINITIALISER'"
                            class="inline-flex h-10 items-center rounded-lg bg-rose-600 px-5 text-sm font-medium text-white hover:bg-rose-500 disabled:cursor-not-allowed disabled:opacity-40">
                        Réinitialiser
                    </button>
                </div>
            </form>
        </div>
    </div>

</div>

<script>
function tarifsPage(cfg) {
    return {
        editionOuverte: false,
        edition: {
            titre: '', update_url: '',
            montant_par_minute: 0, montant_minimum: 0,
            arrondi_actif: true, unite_arrondi: 100, seuil_arrondi: 50,
            description: '',
        },

        modeModal: { ouvert: false, vers: '', titre: '', description: '', action: '', mot: '', saisie: '' },
        resetModal: { ouvert: false, saisie: '' },

        ouvrirEdition(data) {
            this.edition = { ...data };
            this.editionOuverte = true;
        },

        demanderMode(vers) {
            if (vers === cfg.mode) return;
            if (vers === 'separe') {
                this.modeModal = {
                    ouvert: true, vers: 'separe',
                    titre: 'Passer en tarifs séparés',
                    description: 'Deux tarifs distincts (Poste client et Wi-Fi) vont être créés à partir du tarif unifié actuel. Vous pourrez ensuite les ajuster indépendamment.',
                    action: '{{ route('tarifs.passer-separe') }}',
                    mot: 'SEPARER', saisie: '',
                };
            } else {
                this.modeModal = {
                    ouvert: true, vers: 'unifie',
                    titre: 'Passer en tarif unique',
                    description: 'Un seul tarif sera appliqué aux accès Poste client et Wi-Fi. Les tarifs séparés actuels seront supprimés (les sessions en cours gardent leur ancien tarif).',
                    action: '{{ route('tarifs.passer-unifie') }}',
                    mot: 'UNIFIER', saisie: '',
                };
            }
        },

        ouvrirReinitialisation() {
            this.resetModal = { ouvert: true, saisie: '' };
        },
    };
}
</script>

@endsection