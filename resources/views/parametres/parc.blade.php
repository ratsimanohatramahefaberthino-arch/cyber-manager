@extends('layouts.cyber-manager', ['title' => 'Parc — Cyber Manager'])

@section('content')
@php
    use App\Models\AgentVersion;
    $etats = [
        'disponible' => ['Disponible', 'vert'],
        'occupe'     => ['Occupé', 'bleu'],
        'suspendu'   => ['Suspendu', 'orange'],
        'verrouille' => ['Verrouillé', 'gris'],
        'desactive'  => ['Désactivé', 'gris'],
        'hors_ligne' => ['Hors ligne', 'rouge']
    ];

    $total     = $postes->count();
    $enLigne   = $postes->filter(fn ($p) => $p->estEnLigne())->count();
    $aJour     = $postes->filter(fn ($p) => ($visees[$p->id] ?? null) && $visees[$p->id] === $p->agent_version)->count();
    $enAttente = $postes->filter(fn ($p) => ($visees[$p->id] ?? null) && $visees[$p->id] !== $p->agent_version)->count();
    $btn = 'inline-flex h-9 items-center justify-center gap-1.5 rounded-lg px-3 text-sm font-semibold shadow-sm transition focus:outline-none focus-visible:ring-2 focus-visible:ring-violet-500';

    // Données préparées pour Alpine
    $donnees = $postes->map(fn ($p) => [
        'id'                    => $p->id,
        'nom'                   => $p->nom_poste,
        'nom_windows'           => $p->nom_windows,
        'ip'                    => $p->adresse_ip,
        'mac'                   => $p->adresse_mac,
        'etat'                  => $p->etatAffiche(),
        'etat_libelle'          => $etats[$p->etatAffiche()][0] ?? $p->etatAffiche(),
        'etat_couleur'          => $etats[$p->etatAffiche()][1] ?? 'gris',
        'version'               => $p->agent_version,
        'visee'                 => $visees[$p->id] ?? null,
        'en_ligne'              => $p->estEnLigne(),
        'mode_maintenance'      => (bool)$p->mode_maintenance,
        'derniere_maj_at'       => $p->derniere_maj_at ? AgentVersion::heure($p->derniere_maj_at) : null,
        'derniere_maj_resultat' => $p->derniere_maj_resultat,
        'derniere_maj_message'  => $p->derniere_maj_message,
        'url_logs'              => route('parametres.parc.logs', $p),
        'url_redemarrer'        => route('parametres.parc.redemarrer', $p),
        'url_renommer'          => route('parametres.parc.renommer', $p),
        'url_maintenance'       => route('parametres.parc.maintenance', $p),
    ])->values();
@endphp

<script>
function pageParc(init) {
    return {
        recherche: '',
        filtre: 'tous',
        tri: 'nom',
        postes: init.postes,
        toast: null,
        modalOuverte: false,
        posteSelectionne: null,
        logsCharges: false,
        logs: [],
        chargementLogs: false,
        chargementRedemarrage: false,

        init() {
            this.$watch('toast', (v) => { if (v) setTimeout(() => this.toast = null, 5000); });
        },

        get filtres() {
            const r = this.recherche.trim().toLowerCase();
            let liste = this.postes.filter(u => {
                const matchTexte = !r || u.nom.toLowerCase().includes(r) || (u.ip && u.ip.includes(r));
                let matchEtat = true;
                if (this.filtre === 'en_ligne') matchEtat = u.en_ligne;
                else if (this.filtre === 'hors_ligne') matchEtat = !u.en_ligne;
                else if (this.filtre === 'occupe') matchEtat = u.etat === 'occupe';
                else if (this.filtre === 'verrouille') matchEtat = u.etat === 'verrouille';
                return matchTexte && matchEtat;
            });

            if (this.tri === 'nom') {
                liste.sort((a, b) => a.nom.localeCompare(b.nom, 'fr'));
            } else if (this.tri === 'etat') {
                liste.sort((a, b) => a.etat.localeCompare(b.etat));
            } else if (this.tri === 'version') {
                liste.sort((a, b) => (a.version || '').localeCompare(b.version || ''));
            } else if (this.tri === 'maj') {
                liste.sort((a, b) => (b.derniere_maj_at || '').localeCompare(a.derniere_maj_at || ''));
            }
            return liste;
        },

        ouvrirDetail(poste) {
            this.posteSelectionne = poste;
            this.modalOuverte = true;
            this.logsCharges = false;
            this.logs = [];
        },

        async chargerLogs() {
            if (!this.posteSelectionne || this.chargementLogs) return;
            this.chargementLogs = true;
            try {
                const r = await cmFetch(this.posteSelectionne.url_logs);
                const d = await r.json();
                this.logs = d.logs;
                this.logsCharges = true;
            } catch (e) {
                this.toast = { ok: false, message: 'Impossible de charger les logs.' };
            }
            this.chargementLogs = false;
        },

        async redemarrer() {
            if (!this.posteSelectionne) return;
            if (!confirm('Redémarrer le poste « ' + this.posteSelectionne.nom + ' » ? Les clients seront déconnectés.')) return;
            
            this.chargementRedemarrage = true;
            try {
                const r = await cmFetch(this.posteSelectionne.url_redemarrer, { method: 'POST' });
                const d = await r.json();
                this.toast = { ok: d.ok, message: d.message };
                if (d.ok) {
                    this.modalOuverte = false;
                }
            } catch (e) {
                this.toast = { ok: false, message: 'Serveur injoignable.' };
            }
            this.chargementRedemarrage = false;
        }
    };
}
</script>

<div x-data="pageParc(@js(['postes' => $donnees]))" class="space-y-6">
    <div>
        <h1 class="text-2xl font-semibold tracking-tight text-slate-900">Parc et restrictions</h1>
        <p class="text-sm text-slate-500">État de chaque poste : version installée, version visée, dernière mise à jour.</p>
    </div>

    <x-flash />

    {{-- Toast --}}
    <div x-show="toast" x-cloak x-transition class="fixed bottom-6 right-6 z-[110] max-w-md rounded-xl px-4 py-3 text-sm font-medium text-white shadow-lg" :class="toast?.ok ? 'bg-emerald-600' : 'bg-rose-600'">
        <span x-text="toast?.message"></span>
    </div>

    {{-- Compteurs --}}
    <div class="grid grid-cols-2 gap-4 lg:grid-cols-4">
        @foreach ([['Postes', $total, 'desktop', 'violet'], ['En ligne', $enLigne, 'wifi', 'vert'], ['À jour', $aJour, 'check', 'vert'], ['Mise à jour en attente', $enAttente, 'refresh', 'orange']] as [$titre, $nb, $ic, $col])
            <div class="flex items-center gap-3 rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
                <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl {{ $col === 'vert' ? 'bg-emerald-50 text-emerald-700' : ($col === 'orange' ? 'bg-amber-50 text-amber-700' : 'bg-violet-50 text-violet-700') }}">
                    <x-icone :nom="$ic" classe="h-5 w-5" />
                </span>
                <div><p class="text-2xl font-extrabold leading-none text-slate-900">{{ $nb }}</p><p class="mt-1 text-xs text-slate-500">{{ $titre }}</p></div>
            </div>
        @endforeach
    </div>

    {{-- Barre de recherche + Filtres --}}
    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div class="relative flex-1 max-w-md">
            <span class="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-slate-400">
                <x-icone nom="search" classe="h-4 w-4" />
            </span>
            <input type="text" x-model="recherche" placeholder="Rechercher un poste ou une IP…"
                   class="h-11 w-full rounded-xl border border-slate-300 bg-white pl-9 pr-3.5 text-sm text-slate-900 shadow-sm focus:border-violet-500 focus:ring-2 focus:ring-violet-500/20">
        </div>
        <div class="flex items-center gap-2">
            <select x-model="filtre" class="h-11 rounded-xl border border-slate-300 bg-white px-3.5 text-sm text-slate-900 shadow-sm focus:border-violet-500 focus:ring-2 focus:ring-violet-500/20">
                <option value="tous">Tous les états</option>
                <option value="en_ligne">En ligne</option>
                <option value="hors_ligne">Hors ligne</option>
                <option value="occupe">Occupé</option>
                <option value="verrouille">Verrouillé</option>
            </select>
            <select x-model="tri" class="h-11 rounded-xl border border-slate-300 bg-white px-3.5 text-sm text-slate-900 shadow-sm focus:border-violet-500 focus:ring-2 focus:ring-violet-500/20">
                <option value="nom">Trier par nom</option>
                <option value="etat">Trier par état</option>
                <option value="version">Trier par version</option>
                <option value="maj">Dernière mise à jour</option>
            </select>
        </div>
    </div>

    {{-- Liste des postes --}}
    <div class="overflow-x-auto rounded-xl border border-slate-200">
        <table class="min-w-full divide-y divide-slate-200 text-sm">
            <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">
                <tr>
                    <th class="px-4 py-2.5">Poste</th>
                    <th class="px-4 py-2.5">État</th>
                    <th class="px-4 py-2.5">Version</th>
                    <th class="px-4 py-2.5">Dernière MAJ</th>
                    <th class="px-4 py-2.5">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 bg-white">
                <template x-for="u in filtres" :key="u.id">
                    <tr class="align-top">
                        <td class="px-4 py-3">
                            <form :action="u.url_renommer" method="POST" class="flex gap-1.5">
                                <input type="hidden" name="_token" value="{{ csrf_token() }}">
                                <input type="text" name="nom" :value="u.nom" maxlength="100"
                                       class="h-9 w-32 rounded-lg border border-slate-300 px-2.5 text-sm focus:border-violet-500 focus:ring-2 focus:ring-violet-500/20">
                                <button type="submit" class="{{ $btn }} border border-slate-300 bg-white text-slate-700 hover:bg-slate-50">
                                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z" /></svg>
                                </button>
                            </form>
                            <template x-if="u.nom_windows">
                                <p class="mt-1 text-[11px] text-slate-400" x-text="'Windows : ' + u.nom_windows"></p>
                            </template>
                        </td>
                        <td class="px-4 py-3">
                            <span class="inline-flex items-center gap-1 rounded-full px-2.5 py-0.5 text-xs font-semibold ring-1"
                                  :class="u.etat_couleur === 'vert' ? 'bg-emerald-50 text-emerald-700 ring-emerald-200' : (u.etat_couleur === 'orange' ? 'bg-amber-50 text-amber-700 ring-amber-200' : 'bg-slate-50 text-slate-700 ring-slate-200')"
                                  x-text="u.etat_libelle"></span>
                            <template x-if="u.mode_maintenance">
                                <span class="ml-1 inline-flex items-center gap-1 rounded-full bg-sky-50 px-2 py-0.5 text-xs font-semibold text-sky-700 ring-1 ring-sky-200">Maintenance</span>
                            </template>
                        </td>
                        <td class="px-4 py-3">
                            <div class="font-medium text-slate-800" x-text="u.version || '—'"></div>
                            <template x-if="u.visee && u.visee !== u.version">
                                <p class="text-xs text-amber-600" x-text="'En attente de ' + u.visee"></p>
                            </template>
                        </td>
                        <td class="px-4 py-3 text-slate-600">
                            <template x-if="u.derniere_maj_at">
                                <div>
                                    <span x-text="u.derniere_maj_at"></span>
                                    <span class="ml-1 inline-flex items-center rounded-full px-2 py-0.5 text-xs font-semibold" :class="u.derniere_maj_resultat === 'succes' ? 'bg-emerald-50 text-emerald-700' : 'bg-rose-50 text-rose-700'" x-text="u.derniere_maj_resultat === 'succes' ? 'Réussie' : 'Échouée'"></span>
                                </div>
                            </template>
                            <template x-if="!u.derniere_maj_at">
                                <span>—</span>
                            </template>
                        </td>
                        <td class="px-4 py-3">
                            <div class="flex flex-wrap items-center gap-2">
                                <button type="button" x-on:click="ouvrirDetail(u)" class="{{ $btn }} border border-slate-300 bg-white text-slate-700 hover:bg-slate-50">
                                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" /><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" /></svg>
                                    Détail
                                </button>
                                {{-- Correction appliquée ici avec :class au lieu de {{ ... }} --}}
                                <form :action="u.url_maintenance" method="POST">
                                    <input type="hidden" name="_token" value="{{ csrf_token() }}">
                                    <button type="submit"
                                            class="{{ $btn }}"
                                            :class="u.mode_maintenance ? 'bg-emerald-600 text-white hover:bg-emerald-700' : 'border border-slate-300 bg-white text-slate-600 hover:bg-slate-50'">
                                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" /></svg>
                                        Maintenance
                                    </button>
                                </form>
                            </div>
                        </td>
                    </tr>
                </template>
                <template x-if="filtres.length === 0">
                    <tr><td colspan="5" class="px-4 py-8 text-center text-slate-400">Aucun poste ne correspond à votre recherche.</td></tr>
                </template>
            </tbody>
        </table>
    </div>

    {{-- Profil de restrictions Windows --}}
    <x-parametres.section titre="Profil de restrictions Windows" icone="shield" aide="Cochez ce qui doit être bloqué. Appliqué par le Gardien sur le compte « Client » uniquement.">
        <form method="POST" action="{{ route('parametres.parc.restrictions') }}">
            @csrf
            <div class="grid gap-3 md:grid-cols-2">
                @foreach ($cles as $cle => [$libelle, $aide])
                    <label class="flex cursor-pointer items-start gap-3 rounded-xl border border-slate-200 bg-slate-50/50 p-3 transition hover:border-violet-300 hover:bg-violet-50/40">
                        <input type="checkbox" name="r[{{ $cle }}]" value="1" {{ ($profil[$cle] ?? true) ? 'checked' : '' }}
                               class="mt-0.5 h-5 w-5 rounded border-slate-300 text-violet-600 focus:ring-violet-500">
                        <span><span class="block text-sm font-semibold text-slate-900">{{ $libelle }}</span><span class="block text-xs text-slate-500">{{ $aide }}</span></span>
                    </label>
                @endforeach
            </div>
            <button type="submit" class="mt-4 inline-flex h-10 items-center rounded-xl bg-violet-600 px-5 text-sm font-semibold text-white shadow-sm hover:bg-violet-700">Enregistrer le profil</button>
        </form>
    </x-parametres.section>

    {{-- Modal Détail du poste --}}
    <div x-show="modalOuverte" x-cloak class="fixed inset-0 z-[100] flex items-center justify-center p-4">
        <div class="absolute inset-0 bg-slate-900/50" x-on:click="modalOuverte = false"></div>
        <div class="relative w-full max-w-2xl max-h-[90vh] overflow-y-auto rounded-2xl bg-white p-6 shadow-xl" x-show="modalOuverte" x-transition>
            <template x-if="posteSelectionne">
                <div>
                    <div class="flex items-center justify-between border-b border-slate-100 pb-4">
                        <h3 class="text-xl font-bold text-slate-900" x-text="posteSelectionne.nom"></h3>
                        <button type="button" x-on:click="modalOuverte = false" class="rounded-lg p-1 text-slate-400 hover:bg-slate-100 hover:text-slate-600">
                            <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
                        </button>
                    </div>

                    <div class="mt-4 grid grid-cols-2 gap-4 text-sm">
                        <div><p class="text-slate-500">IP</p><p class="font-medium" x-text="posteSelectionne.ip || 'Inconnue'"></p></div>
                        <div><p class="text-slate-500">Adresse MAC</p><p class="font-medium" x-text="posteSelectionne.mac || 'Inconnue'"></p></div>
                        <div><p class="text-slate-500">Version installée</p><p class="font-medium" x-text="posteSelectionne.version || '—'"></p></div>
                        <div><p class="text-slate-500">Version visée</p><p class="font-medium" x-text="posteSelectionne.visee || 'Aucune'"></p></div>
                        <div><p class="text-slate-500">Dernière MAJ</p><p class="font-medium" x-text="posteSelectionne.derniere_maj_at || 'Jamais'"></p></div>
                        <div><p class="text-slate-500">État</p><span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-semibold" :class="posteSelectionne.etat_couleur === 'vert' ? 'bg-emerald-50 text-emerald-700' : 'bg-slate-50 text-slate-700'" x-text="posteSelectionne.etat_libelle"></span></div>
                    </div>

                    <div class="mt-6 flex items-center justify-between border-t border-slate-100 pt-4">
                        <button type="button" x-on:click="chargerLogs()" class="inline-flex h-9 items-center gap-2 rounded-lg border border-slate-300 bg-white px-4 text-sm font-semibold text-slate-700 hover:bg-slate-50">
                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 8h14M5 8a2 2 0 110-4h14a2 2 0 110 4M5 8v10a2 2 0 002 2h10a2 2 0 002-2V8m-9 4h4" /></svg>
                            Voir les logs
                        </button>
                        <button type="button" x-on:click="redemarrer()" class="inline-flex h-9 items-center gap-2 rounded-lg bg-rose-600 px-4 text-sm font-semibold text-white hover:bg-rose-700 disabled:opacity-50" :disabled="!posteSelectionne.en_ligne || chargementRedemarrage">
                            <svg x-show="chargementRedemarrage" x-cloak class="animate-spin h-4 w-4 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                            <svg x-show="!chargementRedemarrage" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" /></svg>
                            Redémarrer le poste
                        </button>
                    </div>

                    {{-- Logs --}}
                    <div x-show="logsCharges" x-cloak class="mt-4 rounded-xl border border-slate-200 bg-slate-50 p-3">
                        <h4 class="mb-2 font-semibold text-slate-800">Derniers logs</h4>
                        <div class="max-h-60 overflow-y-auto space-y-2 text-xs">
                            <template x-for="log in logs" :key="log.date + log.commande">
                                <div class="flex items-start gap-2 border-b border-slate-100 pb-2">
                                    <span class="text-slate-400 whitespace-nowrap" x-text="log.date"></span>
                                    <span class="font-medium text-slate-800" x-text="log.commande"></span>
                                    <span class="ml-auto rounded px-1.5 py-0.5" :class="log.statut === 'succes' ? 'bg-emerald-100 text-emerald-700' : 'bg-amber-100 text-amber-700'" x-text="log.statut"></span>
                                </div>
                            </template>
                            <template x-if="logs.length === 0">
                                <p class="text-slate-400">Aucun log.</p>
                            </template>
                        </div>
                    </div>
                </div>
            </template>
        </div>
    </div>
</div>
@endsection