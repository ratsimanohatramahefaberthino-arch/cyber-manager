@extends('layouts.cyber-manager', ['title' => 'Personnel — Cyber Manager'])

@section('content')
@php
    $champ = 'h-11 w-full rounded-xl border border-slate-300 bg-white px-3.5 text-sm text-slate-900 shadow-sm placeholder:text-slate-400 focus:border-violet-500 focus:ring-2 focus:ring-violet-500/20';
    $label = 'mb-1.5 block text-sm font-semibold text-slate-800';
    $btn   = 'inline-flex h-9 items-center justify-center gap-1.5 rounded-lg px-3 text-sm font-semibold shadow-sm transition focus:outline-none focus-visible:ring-2 focus-visible:ring-violet-500';

    // Données préparées pour Alpine (une seule fois, pas de recalcul)
    $donnees = $utilisateurs->map(fn ($u) => [
        'id'                    => $u->id,
        'name'                  => $u->name,
        'email'                 => $u->email,
        'role'                  => $u->role,
        'telephone'             => $u->telephone,
        'notes_internes'        => $u->notes_internes,
        'last_login_at'         => $u->last_login_at?->format('d/m/Y H:i'),
        'last_login_ts'         => $u->last_login_at?->timestamp,
        'last_login_ip'         => $u->last_login_ip, // <-- NOUVEAU
        'en_ligne'              => $u->estEnLigne(),
        'dernier_signe'         => $u->dernierSigneDeVie(),
        'avatar_url'            => $u->avatarUrl(),
        'initiales'             => $u->initiales(),
        'couleur'               => $u->couleurAvatar(),
        'est_admin'             => $u->estAdmin(),
        'libelle_role'          => $u->libelleRole(),
        'email_verified'        => $u->email_verified_at !== null,
        'verification_code'     => $u->verification_code,
        'locked_until'          => $u->locked_until ? true : false,
    ])->values();
@endphp

<script>
function pagePersonnel(init) {
    return {
        recherche: '',
        tri: 'role',
        utilisateurs: init.utilisateurs,
        moi: init.moi,
        toast: null,
        chargementId: null,

        init() {
            this.$watch('toast', (v) => { if (v) setTimeout(() => this.toast = null, 5000); });
        },

        get filtres() {
            const r = this.recherche.trim().toLowerCase();
            let liste = this.utilisateurs.filter(u =>
                !r || u.name.toLowerCase().includes(r) || u.email.toLowerCase().includes(r)
            );

            if (this.tri === 'nom') {
                liste.sort((a, b) => a.name.localeCompare(b.name, 'fr'));
            } else if (this.tri === 'recent') {
                liste.sort((a, b) => (b.last_login_ts || 0) - (a.last_login_ts || 0));
            } else {
                liste.sort((a, b) => (b.role === 'admin' ? 1 : 0) - (a.role === 'admin' ? 1 : 0) || a.name.localeCompare(b.name, 'fr'));
            }
            return liste;
        },

        async copierEmail(email) {
            try { await navigator.clipboard.writeText(email); } catch (e) {}
            this.toast = { ok: true, message: 'Email copié : ' + email };
        },

        async copierCode(code) {
            try { await navigator.clipboard.writeText(code); } catch (e) {}
            this.toast = { ok: true, message: 'Code copié : ' + code };
        },

        async reinitialiser(id) {
            if (!confirm('Générer un nouveau mot de passe temporaire pour ce compte ?')) return;
            try {
                const r = await cmFetch('/parametres/personnel/' + id + '/reinitialiser', { method: 'POST' });
                const d = await r.json();
                this.toast = { ok: d.ok, message: d.message };
            } catch (e) { this.toast = { ok: false, message: 'Serveur injoignable.' }; }
        },

        async deconnecterPartout(id) {
            if (!confirm('Fermer toutes les sessions de ce compte ? L\'utilisateur devra se reconnecter.')) return;
            try {
                const r = await cmFetch('/parametres/personnel/' + id + '/deconnecter-partout', { method: 'POST' });
                const d = await r.json();
                this.toast = { ok: d.ok, message: d.message };
            } catch (e) { this.toast = { ok: false, message: 'Serveur injoignable.' }; }
        },

        async debloquer(id) {
            if (!confirm('Débloquer ce compte ?')) return;
            try {
                const r = await cmFetch('/parametres/personnel/' + id + '/debloquer', { method: 'POST' });
                const d = await r.json();
                this.toast = { ok: d.ok, message: d.message };
                if (d.ok) { setTimeout(() => window.location.reload(), 1000); }
            } catch (e) { this.toast = { ok: false, message: 'Serveur injoignable.' }; }
        },
    };
}
</script>

<div x-data="pagePersonnel(@js(['utilisateurs' => $donnees, 'moi' => auth()->id()]))" class="space-y-6">

    {{-- En-tête --}}
    <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
        <div>
            <h1 class="text-2xl font-semibold tracking-tight text-slate-900">Personnel</h1>
            <p class="text-sm text-slate-500">Créez les comptes de vos employés et choisissez ce qu'ils ont le droit de faire.</p>
        </div>
        <button type="button" x-on:click="$dispatch('open-modal', 'creation-personnel')"
                class="inline-flex h-11 shrink-0 items-center gap-2 rounded-xl bg-violet-600 px-5 text-sm font-semibold text-white shadow-sm hover:bg-violet-700">
            <x-icone nom="plus" classe="h-4 w-4" /> Ajouter une personne
        </button>
    </div>

    <x-flash />

    {{-- Toast local --}}
    <div x-show="toast" x-cloak x-transition
         class="fixed bottom-6 right-6 z-[110] max-w-md rounded-xl px-4 py-3 text-sm font-medium text-white shadow-lg"
         :class="toast?.ok ? 'bg-emerald-600' : 'bg-rose-600'">
        <span x-text="toast?.message"></span>
    </div>

    @if ($errors->any() && !$errors->has('global'))
        <div class="rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800" role="alert">
            <p class="mb-1 font-semibold">Les changements n'ont pas été enregistrés :</p>
            <ul class="list-disc space-y-0.5 pl-5">@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
        </div>
    @endif

    {{-- Statistiques --}}
    <div class="grid gap-3 sm:grid-cols-3">
        <div class="rounded-2xl border border-violet-100 bg-gradient-to-br from-violet-50 to-white p-4 shadow-sm">
            <p class="text-xs font-bold uppercase tracking-wider text-violet-700">Administrateurs</p>
            <p class="mt-1 text-2xl font-extrabold text-slate-900">{{ $nbAdmins }}</p>
        </div>
        <div class="rounded-2xl border border-sky-100 bg-gradient-to-br from-sky-50 to-white p-4 shadow-sm">
            <p class="text-xs font-bold uppercase tracking-wider text-sky-700">Personnels</p>
            <p class="mt-1 text-2xl font-extrabold text-slate-900">{{ $nbPersonnels }}</p>
        </div>
        <div class="rounded-2xl border border-emerald-100 bg-gradient-to-br from-emerald-50 to-white p-4 shadow-sm">
            <p class="text-xs font-bold uppercase tracking-wider text-emerald-700">En ligne</p>
            <p class="mt-1 text-2xl font-extrabold text-slate-900">
                {{ $utilisateurs->filter(fn ($u) => $u->estEnLigne())->count() }}
                <span class="text-sm font-medium text-slate-400">/ {{ $utilisateurs->count() }}</span>
            </p>
        </div>
    </div>

    {{-- Barre recherche + tri --}}
    <div class="flex flex-col gap-3 sm:flex-row sm:items-center">
        <div class="relative flex-1">
            <span class="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-slate-400">
                <x-icone nom="search" classe="h-4 w-4" />
            </span>
            <input type="text" x-model="recherche" placeholder="Rechercher par nom ou email…"
                   class="h-11 w-full rounded-xl border border-slate-300 bg-white pl-9 pr-3.5 text-sm text-slate-900 shadow-sm placeholder:text-slate-400 focus:border-violet-500 focus:ring-2 focus:ring-violet-500/20">
        </div>
        <div class="flex items-center gap-2">
            <label for="tri" class="text-sm text-slate-600">Trier :</label>
            <select id="tri" x-model="tri"
                    class="h-11 rounded-xl border border-slate-300 bg-white px-3.5 text-sm text-slate-900 shadow-sm focus:border-violet-500 focus:ring-2 focus:ring-violet-500/20">
                <option value="role">Administrateurs d'abord</option>
                <option value="nom">Nom (A → Z)</option>
                <option value="recent">Dernière connexion</option>
            </select>
        </div>
    </div>

    {{-- Droits --}}
    <div class="grid gap-4 md:grid-cols-2">
        <div class="rounded-2xl border border-violet-100 bg-violet-50/60 p-5">
            <div class="flex items-center gap-2"><x-parametres.badge couleur="violet">Administrateur</x-parametres.badge></div>
            <p class="mt-2 text-sm text-slate-700">Tous les droits : tarifs, paramètres, installation des postes, versions, comptes du personnel.</p>
        </div>
        <div class="rounded-2xl border border-slate-200 bg-white p-5">
            <div class="flex items-center gap-2"><x-parametres.badge couleur="bleu">Personnel</x-parametres.badge></div>
            <p class="mt-2 text-sm text-slate-700">
                Peut voir les sessions et les postes, créer des tickets, encaisser et consulter l'historique.
                <strong>Ne peut pas</strong> modifier les tarifs ni les paramètres, ni gérer les comptes.
            </p>
        </div>
    </div>

    {{-- Liste --}}
    <x-parametres.section titre="Comptes existants" icone="lock">
        <template x-if="filtres.length === 0">
            <p class="py-8 text-center text-sm text-slate-400">Aucun compte ne correspond à votre recherche.</p>
        </template>

        <div class="space-y-3">
            <template x-for="u in filtres" :key="u.id">
                <article class="rounded-xl border border-slate-200 bg-slate-50/50 p-4">
                    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                        <div class="flex min-w-0 items-center gap-3">
                            <div class="relative">
                                <template x-if="u.avatar_url">
                                    <img :src="u.avatar_url" :alt="u.name" class="h-10 w-10 shrink-0 rounded-full object-cover ring-1 ring-slate-200">
                                </template>
                                <template x-if="!u.avatar_url">
                                    <span class="inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-full text-sm font-bold text-white ring-1 ring-black/5"
                                          :style="'background-color:' + u.couleur" x-text="u.initiales"></span>
                                </template>
                                <span x-show="u.en_ligne" class="absolute -bottom-0.5 -right-0.5 h-3 w-3 rounded-full bg-emerald-500 ring-2 ring-white" title="En ligne"></span>
                            </div>
                            <div class="min-w-0">
                                <div class="flex flex-wrap items-center gap-2">
                                    <span class="font-bold text-slate-900" x-text="u.name"></span>
                                    <span class="inline-flex items-center gap-1 whitespace-nowrap rounded-full px-2.5 py-0.5 text-xs font-semibold ring-1"
                                          :class="u.est_admin ? 'bg-violet-50 text-violet-700 ring-violet-200' : 'bg-sky-50 text-sky-700 ring-sky-200'"
                                          x-text="u.libelle_role"></span>
                                    <span x-show="u.id === moi" class="inline-flex items-center rounded-full bg-emerald-50 px-2.5 py-0.5 text-xs font-semibold text-emerald-700 ring-1 ring-emerald-200">Vous</span>
                                    {{-- Badge Email non vérifié --}}
                                    <template x-if="!u.email_verified">
                                        <span class="inline-flex items-center gap-1 whitespace-nowrap rounded-full bg-amber-50 px-2.5 py-0.5 text-xs font-semibold text-amber-700 ring-1 ring-amber-200"
                                              title="L'utilisateur doit saisir un code pour activer son compte">
                                            ✉ Email non vérifié
                                        </span>
                                    </template>
                                    {{-- Badge Compte verrouillé --}}
                                    <template x-if="u.locked_until">
                                        <span class="inline-flex items-center gap-1 whitespace-nowrap rounded-full bg-rose-50 px-2.5 py-0.5 text-xs font-semibold text-rose-700 ring-1 ring-rose-200">
                                            🔒 Compte verrouillé
                                        </span>
                                    </template>
                                </div>
                                <div class="flex flex-wrap items-center gap-2 text-sm text-slate-500">
                                    <span x-text="u.email" class="truncate"></span>
                                    <button type="button" x-on:click="copierEmail(u.email)" title="Copier l'email"
                                            class="rounded p-1 text-slate-400 hover:bg-white hover:text-slate-700">
                                        <x-icone nom="copy" classe="h-3.5 w-3.5" />
                                    </button>
                                    <template x-if="u.telephone">
                                        <span class="text-slate-400" x-text="'· ' + u.telephone"></span>
                                    </template>
                                </div>
                                <div class="flex flex-wrap items-center gap-2 text-xs text-slate-400 mt-0.5">
                                    <span x-text="u.dernier_signe"></span>
                                    <template x-if="u.last_login_ip">
                                        <span x-text="'· IP: ' + u.last_login_ip"></span>
                                    </template>
                                </div>
                            </div>
                        </div>

                        <div class="flex flex-wrap items-center gap-2">
                            <template x-if="u.locked_until">
                                <button type="button" x-on:click="debloquer(u.id)" title="Débloquer le compte"
                                        class="inline-flex h-9 items-center gap-1.5 rounded-lg border border-amber-300 bg-amber-50 px-3 text-sm font-semibold text-amber-700 hover:bg-amber-100">
                                    <x-icone nom="lock-open" classe="h-4 w-4" /> Débloquer
                                </button>
                            </template>
                            <button type="button" x-on:click="reinitialiser(u.id)" title="Générer un mot de passe temporaire"
                                    class="inline-flex h-9 items-center gap-1.5 rounded-lg border border-slate-300 bg-white px-3 text-sm font-semibold text-slate-700 hover:bg-slate-50">
                                <x-icone nom="key" classe="h-4 w-4" /> Mot de passe
                            </button>
                            <button type="button" x-on:click="deconnecterPartout(u.id)" title="Fermer toutes les sessions"
                                    class="inline-flex h-9 items-center gap-1.5 rounded-lg border border-slate-300 bg-white px-3 text-sm font-semibold text-slate-700 hover:bg-slate-50">
                                <x-icone nom="lock" classe="h-4 w-4" /> Déconnecter
                            </button>
                            <template x-if="u.id !== moi && !(u.est_admin && {{ $nbAdmins }} <= 1)">
                                <form :id="'form-suppression-' + u.id" method="POST" :action="'/parametres/personnel/' + u.id"
                                      x-on:submit.prevent="if (confirm('Supprimer définitivement le compte de ' + u.name + ' ?')) $el.submit()">
                                    @csrf @method('DELETE')
                                    <button type="submit" class="inline-flex h-9 items-center gap-1.5 rounded-lg border border-rose-200 bg-white px-3 text-sm font-semibold text-rose-600 hover:bg-rose-50">
                                        <x-icone nom="trash" classe="h-4 w-4" /> Supprimer
                                    </button>
                                </form>
                            </template>
                            <template x-if="u.id === moi || (u.est_admin && {{ $nbAdmins }} <= 1)">
                                <span class="inline-flex h-9 cursor-not-allowed items-center gap-1.5 rounded-lg border border-slate-200 bg-slate-100 px-3 text-sm font-semibold text-slate-400"
                                      :title="u.id === moi ? 'Vous ne pouvez pas supprimer votre propre compte' : 'Il doit rester au moins un administrateur'">
                                    <x-icone nom="trash" classe="h-4 w-4" /> Supprimer
                                </span>
                            </template>
                        </div>
                    </div>

                    {{-- Bandeau code de vérification (si non vérifié et code présent) --}}
                    <template x-if="!u.email_verified && u.verification_code">
                        <div class="mt-3 rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-sm">
                            <div class="flex flex-wrap items-center gap-2">
                                <span class="font-semibold text-amber-900">Code de vérification à transmettre :</span>
                                <code class="rounded bg-white px-2 py-0.5 font-mono text-base font-bold tracking-widest text-amber-900 ring-1 ring-amber-200"
                                      x-text="u.verification_code"></code>
                                <button type="button" x-on:click="copierCode(u.verification_code)" title="Copier le code"
                                        class="rounded p-1 text-amber-700 hover:bg-white">
                                    <x-icone nom="copy" classe="h-4 w-4" />
                                </button>
                            </div>
                            <p class="mt-1 text-xs text-amber-700">
                                L'utilisateur doit saisir ce code sur la page « Vérifiez votre email » après s'être connecté.
                            </p>
                        </div>
                    </template>

                    {{-- Modifier --}}
                    <details class="mt-3">
                        <summary class="{{ $btn }} inline-flex cursor-pointer list-none border border-slate-300 bg-white text-slate-700 hover:bg-slate-50">
                            <x-icone nom="pencil" classe="h-4 w-4" /> Modifier
                        </summary>
                        <form method="POST" :action="'/parametres/personnel/' + u.id" enctype="multipart/form-data"
                              x-data="{ chargement: false }" x-on:submit="chargement = true"
                              class="mt-3 grid gap-3 md:grid-cols-2">
                            @csrf @method('PATCH')

                            <div class="md:col-span-2 flex items-center gap-3">
                                <template x-if="u.avatar_url">
                                    <img :src="u.avatar_url" class="h-14 w-14 rounded-full object-cover ring-1 ring-slate-200">
                                </template>
                                <template x-if="!u.avatar_url">
                                    <span class="inline-flex h-14 w-14 items-center justify-center rounded-full text-lg font-bold text-white ring-1 ring-black/5"
                                          :style="'background-color:' + u.couleur" x-text="u.initiales"></span>
                                </template>
                                <div class="flex-1">
                                    <label class="{{ $label }}">Photo (PNG, JPG, WebP — 2 Mo max)</label>
                                    <input type="file" name="avatar" accept="image/png,image/jpeg,image/webp" class="text-sm">
                                </div>
                            </div>

                            <div>
                                <label class="{{ $label }}">Nom</label>
                                <input type="text" name="name" :value="u.name" maxlength="60" required class="{{ $champ }}">
                            </div>
                            <div>
                                <label class="{{ $label }}">Adresse e-mail</label>
                                <input type="email" name="email" :value="u.email" maxlength="120" required class="{{ $champ }}">
                            </div>
                            <div>
                                <label class="{{ $label }}">Téléphone <span class="font-normal text-slate-400">(facultatif)</span></label>
                                <input type="text" name="telephone" :value="u.telephone || ''" maxlength="30" class="{{ $champ }}">
                            </div>
                            <div>
                                <label class="{{ $label }}">Rôle</label>
                                <select name="role" :disabled="u.id === moi" class="{{ $champ }} disabled:bg-slate-100 disabled:text-slate-500">
                                    <option value="personnel" :selected="u.role === 'personnel'">Personnel</option>
                                    <option value="admin" :selected="u.role === 'admin'">Administrateur</option>
                                </select>
                                <template x-if="u.id === moi">
                                    <p class="mt-1 text-xs text-slate-500">Vous ne pouvez pas changer votre propre rôle.</p>
                                </template>
                            </div>
                            <div>
                                <label class="{{ $label }}">Nouveau mot de passe <span class="font-normal text-slate-400">(vide = inchangé)</span></label>
                                <input type="password" name="password" autocomplete="new-password" class="{{ $champ }}">
                            </div>
                            <div>
                                <label class="{{ $label }}">Répétez le nouveau mot de passe</label>
                                <input type="password" name="password_confirmation" autocomplete="new-password" class="{{ $champ }}">
                            </div>
                            <div class="md:col-span-2">
                                <label class="{{ $label }}">Notes internes <span class="font-normal text-slate-400">(facultatif)</span></label>
                                <textarea name="notes_internes" rows="2" maxlength="1000" class="{{ $champ }} resize-none" x-text="u.notes_internes || ''"></textarea>
                            </div>
                            <div class="md:col-span-2">
                                <button type="submit" class="inline-flex h-10 items-center justify-center gap-2 rounded-xl bg-violet-600 px-5 text-sm font-semibold text-white shadow-sm hover:bg-violet-700 disabled:opacity-70 disabled:cursor-not-allowed" x-bind:disabled="chargement">
                                    <svg x-show="chargement" x-cloak class="animate-spin h-4 w-4 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                                    <span x-text="chargement ? 'Enregistrement...' : 'Enregistrer'"></span>
                                </button>
                            </div>
                        </form>
                    </details>
                </article>
            </template>
        </div>
    </x-parametres.section>
</div>

{{-- ═══ Modale de création ═══ --}}
<x-modal name="creation-personnel" :show="false" max-width="xl" focusable>
    <form method="POST" action="{{ route('parametres.personnel.store') }}" enctype="multipart/form-data"
          x-data="{ voir: false, chargement: false }" x-on:submit="chargement = true" class="p-6 space-y-4">
        @csrf

        <h2 class="text-lg font-bold text-slate-900">Ajouter une personne</h2>
        <p class="text-sm text-slate-500">Elle se connectera avec son adresse e-mail et ce mot de passe.</p>

        <div class="grid gap-4 md:grid-cols-2">
            <div>
                <label for="p_name" class="{{ $label }}">Nom</label>
                <input type="text" id="p_name" name="name" value="{{ old('name') }}" maxlength="60" required class="{{ $champ }}">
            </div>
            <div>
                <label for="p_email" class="{{ $label }}">Adresse e-mail</label>
                <input type="email" id="p_email" name="email" value="{{ old('email') }}" maxlength="120" required class="{{ $champ }}">
            </div>
            <div>
                <label for="p_pwd" class="{{ $label }}">Mot de passe <span class="font-normal text-slate-400">(8 car. min, 1 maj, 1 chiffre)</span></label>
                <input :type="voir ? 'text' : 'password'" id="p_pwd" name="password" autocomplete="new-password" required class="{{ $champ }}">
            </div>
            <div>
                <label for="p_pwd2" class="{{ $label }}">Répétez le mot de passe</label>
                <input :type="voir ? 'text' : 'password'" id="p_pwd2" name="password_confirmation" autocomplete="new-password" required class="{{ $champ }}">
            </div>
            <div>
                <label for="p_role" class="{{ $label }}">Rôle</label>
                <select id="p_role" name="role" class="{{ $champ }}">
                    <option value="personnel" @selected(old('role', 'personnel') === 'personnel')>Personnel (droits limités)</option>
                    <option value="admin" @selected(old('role') === 'admin')>Administrateur (tous les droits)</option>
                </select>
            </div>
            <div>
                <label for="p_tel" class="{{ $label }}">Téléphone <span class="font-normal text-slate-400">(facultatif)</span></label>
                <input type="text" id="p_tel" name="telephone" value="{{ old('telephone') }}" maxlength="30" class="{{ $champ }}">
            </div>
            <div class="md:col-span-2">
                <label for="p_avatar" class="{{ $label }}">Photo de profil <span class="font-normal text-slate-400">(facultatif, 2 Mo max)</span></label>
                <input type="file" id="p_avatar" name="avatar" accept="image/png,image/jpeg,image/webp" class="text-sm">
            </div>
            <div class="md:col-span-2">
                <label for="p_notes" class="{{ $label }}">Notes internes <span class="font-normal text-slate-400">(facultatif)</span></label>
                <textarea id="p_notes" name="notes_internes" rows="2" maxlength="1000" class="{{ $champ }} resize-none">{{ old('notes_internes') }}</textarea>
            </div>
        </div>

        <label class="flex items-center gap-2 text-sm text-slate-600">
            <input type="checkbox" x-model="voir" class="h-4 w-4 rounded border-slate-300 text-violet-600 focus:ring-violet-500">
            Afficher les mots de passe
        </label>

        <div class="flex items-center justify-end gap-2 border-t border-slate-100 pt-4">
            <button type="button" x-on:click="$dispatch('close-modal', 'creation-personnel')"
                    class="inline-flex h-10 items-center rounded-xl border border-slate-300 bg-white px-4 text-sm font-semibold text-slate-700 hover:bg-slate-50">
                Annuler
            </button>
            <button type="submit" class="inline-flex h-10 items-center gap-2 rounded-xl bg-violet-600 px-5 text-sm font-semibold text-white shadow-sm hover:bg-violet-700 disabled:opacity-70 disabled:cursor-not-allowed" x-bind:disabled="chargement">
                <svg x-show="chargement" x-cloak class="animate-spin h-4 w-4 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                <x-icone nom="plus" classe="h-4 w-4" x-show="!chargement" />
                <span x-text="chargement ? 'Création...' : 'Créer le compte'"></span>
            </button>
        </div>
    </form>
</x-modal>
@endsection