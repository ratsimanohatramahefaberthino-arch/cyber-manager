@extends('layouts.cyber-manager', ['title' => 'Personnel — Cyber Manager'])

@section('content')
@php
    $champ = 'h-11 w-full rounded-xl border border-slate-300 bg-white px-3.5 text-sm text-slate-900 shadow-sm placeholder:text-slate-400 focus:border-violet-500 focus:ring-2 focus:ring-violet-500/20';
    $label = 'mb-1.5 block text-sm font-semibold text-slate-800';
    $btn   = 'inline-flex h-9 items-center justify-center gap-1.5 rounded-lg px-3 text-sm font-semibold shadow-sm transition focus:outline-none focus-visible:ring-2 focus-visible:ring-violet-500';
@endphp

<div class="space-y-6">
    <div>
        <h1 class="text-2xl font-semibold tracking-tight text-slate-900">Personnel</h1>
        <p class="text-sm text-slate-500">Créez les comptes de vos employés et choisissez ce qu'ils ont le droit de faire.</p>
    </div>

    <x-flash />

    @if ($errors->any() && !$errors->has('global'))
        <div class="rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800" role="alert">
            <p class="mb-1 font-semibold">Les changements n'ont pas été enregistrés :</p>
            <ul class="list-disc space-y-0.5 pl-5">@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
        </div>
    @endif

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

    {{-- Ajouter --}}
    <x-parametres.section titre="Ajouter une personne" icone="key" aide="Elle se connectera avec son adresse e-mail et ce mot de passe.">
        <form method="POST" action="{{ route('parametres.personnel.store') }}" x-data="{ voir: false }" class="grid gap-4 md:grid-cols-2">
            @csrf
            <div>
                <label for="p_name" class="{{ $label }}">Nom</label>
                <input type="text" id="p_name" name="name" value="{{ old('name') }}" maxlength="60" required class="{{ $champ }}">
            </div>
            <div>
                <label for="p_email" class="{{ $label }}">Adresse e-mail <span class="font-normal text-slate-400">(sert à se connecter)</span></label>
                <input type="email" id="p_email" name="email" value="{{ old('email') }}" maxlength="120" required class="{{ $champ }}">
            </div>
            <div>
                <label for="p_pwd" class="{{ $label }}">Mot de passe <span class="font-normal text-slate-400">(8 caractères minimum)</span></label>
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
            <div class="flex items-end justify-between gap-3">
                <label class="flex items-center gap-2 text-sm text-slate-600">
                    <input type="checkbox" x-model="voir" class="h-4 w-4 rounded border-slate-300 text-violet-600 focus:ring-violet-500"> Afficher les mots de passe
                </label>
                <button type="submit" class="inline-flex h-11 items-center gap-2 rounded-xl bg-violet-600 px-5 text-sm font-semibold text-white shadow-sm hover:bg-violet-700">
                    <x-icone nom="plus" classe="h-4 w-4" /> Créer le compte
                </button>
            </div>
        </form>
    </x-parametres.section>

    {{-- Liste --}}
    <x-parametres.section titre="Comptes existants" icone="lock">
        <div class="space-y-3">
            @foreach ($utilisateurs as $u)
                @php
                    $estMoi = $u->id === $moi;
                    $dernierAdmin = $u->estAdmin() && $nbAdmins <= 1;
                @endphp
                <article class="rounded-xl border border-slate-200 bg-slate-50/50 p-4">
                    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                        <div class="min-w-0">
                            <div class="flex flex-wrap items-center gap-2">
                                <span class="font-bold text-slate-900">{{ $u->name }}</span>
                                <x-parametres.badge :couleur="$u->estAdmin() ? 'violet' : 'bleu'">{{ $u->libelleRole() }}</x-parametres.badge>
                                @if ($estMoi) <x-parametres.badge couleur="vert">Vous</x-parametres.badge> @endif
                            </div>
                            <p class="truncate text-sm text-slate-500">{{ $u->email }}</p>
                        </div>

                        @unless ($estMoi && $dernierAdmin && false)
                            <div class="flex items-center gap-2">
                                @unless ($estMoi || $dernierAdmin)
                                    <form method="POST" action="{{ route('parametres.personnel.destroy', $u) }}"
                                          onsubmit="return confirm('Supprimer définitivement le compte de {{ addslashes($u->name) }} ?')">
                                        @csrf @method('DELETE')
                                        <button type="submit" class="{{ $btn }} border border-rose-200 bg-white text-rose-600 hover:bg-rose-50">
                                            <x-icone nom="trash" classe="h-4 w-4" /> Supprimer
                                        </button>
                                    </form>
                                @endunless
                            </div>
                        @endunless
                    </div>

                    <details class="mt-3">
                        <summary class="{{ $btn }} inline-flex cursor-pointer list-none border border-slate-300 bg-white text-slate-700 hover:bg-slate-50">
                            <x-icone nom="pencil" classe="h-4 w-4" /> Modifier
                        </summary>
                        <form method="POST" action="{{ route('parametres.personnel.update', $u) }}" class="mt-3 grid gap-3 md:grid-cols-2">
                            @csrf @method('PATCH')
                            <div>
                                <label class="{{ $label }}" for="n{{ $u->id }}">Nom</label>
                                <input id="n{{ $u->id }}" type="text" name="name" value="{{ $u->name }}" maxlength="60" required class="{{ $champ }}">
                            </div>
                            <div>
                                <label class="{{ $label }}" for="e{{ $u->id }}">Adresse e-mail</label>
                                <input id="e{{ $u->id }}" type="email" name="email" value="{{ $u->email }}" maxlength="120" required class="{{ $champ }}">
                            </div>
                            <div>
                                <label class="{{ $label }}" for="r{{ $u->id }}">Rôle</label>
                                <select id="r{{ $u->id }}" name="role" @disabled($estMoi) class="{{ $champ }} disabled:bg-slate-100 disabled:text-slate-500">
                                    <option value="personnel" @selected($u->role === 'personnel')>Personnel</option>
                                    <option value="admin" @selected($u->role === 'admin')>Administrateur</option>
                                </select>
                                @if ($estMoi) <p class="mt-1 text-xs text-slate-500">Vous ne pouvez pas changer votre propre rôle.</p> @endif
                            </div>
                            <div>
                                <label class="{{ $label }}" for="m{{ $u->id }}">Nouveau mot de passe <span class="font-normal text-slate-400">(laisser vide = inchangé)</span></label>
                                <input id="m{{ $u->id }}" type="password" name="password" autocomplete="new-password" class="{{ $champ }}">
                            </div>
                            <div class="md:col-start-2">
                                <label class="{{ $label }}" for="c{{ $u->id }}">Répétez le nouveau mot de passe</label>
                                <input id="c{{ $u->id }}" type="password" name="password_confirmation" autocomplete="new-password" class="{{ $champ }}">
                            </div>
                            <div class="md:col-span-2">
                                <button type="submit" class="inline-flex h-10 items-center rounded-xl bg-violet-600 px-5 text-sm font-semibold text-white shadow-sm hover:bg-violet-700">Enregistrer</button>
                            </div>
                        </form>
                    </details>
                </article>
            @endforeach
        </div>
    </x-parametres.section>
</div>
@endsection