@extends('layouts.cyber-manager', ['title' => 'Mon profil — Cyber Manager'])

@section('content')
<div class="mx-auto max-w-3xl space-y-6">
    <div>
        <h1 class="text-2xl font-semibold tracking-tight text-slate-900">Mon profil</h1>
        <p class="text-sm text-slate-500">Modifiez vos informations personnelles, votre photo et votre mot de passe.</p>
    </div>

    <x-flash />

    {{-- Informations --}}
    <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
        <div class="mb-4 flex items-center gap-3">
            <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-violet-50 text-violet-700">
                <x-icone nom="pencil" classe="h-5 w-5" />
            </span>
            <div>
                <h2 class="text-base font-bold text-slate-900">Informations personnelles</h2>
                <p class="text-sm text-slate-500">Votre nom et votre photo sont visibles dans la barre latérale.</p>
            </div>
        </div>

        <form method="POST" action="{{ route('profile.update') }}" enctype="multipart/form-data" class="space-y-4">
            @csrf @method('PATCH')

            <div class="flex items-center gap-4">
                <div class="shrink-0">
                    <x-avatar :user="$user" taille="xl" />
                </div>
                <div class="flex-1 space-y-2">
                    <label class="mb-1.5 block text-sm font-semibold text-slate-800">Photo de profil <span class="font-normal text-slate-400">(PNG, JPG, WebP — 2 Mo max)</span></label>
                    <input type="file" name="avatar" accept="image/png,image/jpeg,image/webp" class="text-sm">
                    @if ($user->avatarUrl())
                        <button type="submit" form="form-suppr-avatar" class="text-xs font-semibold text-rose-600 hover:text-rose-800 underline">
                            Supprimer la photo actuelle
                        </button>
                    @endif
                </div>
            </div>

            <div class="grid gap-4 md:grid-cols-2">
                <div>
                    <label for="name" class="mb-1.5 block text-sm font-semibold text-slate-800">Nom complet</label>
                    <input type="text" id="name" name="name" value="{{ old('name', $user->name) }}" maxlength="60" required class="h-11 w-full rounded-xl border border-slate-300 bg-white px-3.5 text-sm text-slate-900 shadow-sm placeholder:text-slate-400 focus:border-violet-500 focus:ring-2 focus:ring-violet-500/20">
                </div>
                <div>
                    <label for="email" class="mb-1.5 block text-sm font-semibold text-slate-800">Adresse e-mail</label>
                    <input type="email" id="email" name="email" value="{{ old('email', $user->email) }}" maxlength="120" required class="h-11 w-full rounded-xl border border-slate-300 bg-white px-3.5 text-sm text-slate-900 shadow-sm placeholder:text-slate-400 focus:border-violet-500 focus:ring-2 focus:ring-violet-500/20">
                </div>
            </div>

            <div class="flex justify-end">
                <button type="submit" class="inline-flex h-10 items-center gap-2 rounded-xl bg-violet-600 px-5 text-sm font-semibold text-white shadow-sm hover:bg-violet-700">Enregistrer les modifications</button>
            </div>
        </form>
        
        @if ($user->avatarUrl())
            <form id="form-suppr-avatar" method="POST" action="{{ route('profile.avatar.supprimer') }}" onsubmit="return confirm('Supprimer votre photo de profil ?')">
                @csrf @method('DELETE')
            </form>
        @endif
    </section>

    {{-- Mot de passe --}}
    <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
        <div class="mb-4 flex items-center gap-3">
            <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-violet-50 text-violet-700">
                <x-icone nom="lock" classe="h-5 w-5" />
            </span>
            <div>
                <h2 class="text-base font-bold text-slate-900">Mot de passe</h2>
                <p class="text-sm text-slate-500">Par sécurité, vous devez saisir votre mot de passe actuel pour le modifier.</p>
            </div>
        </div>

        <form method="POST" action="{{ route('profile.password') }}" class="space-y-4" x-data="{ voir: false }">
            @csrf @method('PATCH')

            <div>
                <label for="current_password" class="mb-1.5 block text-sm font-semibold text-slate-800">Mot de passe actuel</label>
                <input :type="voir ? 'text' : 'password'" id="current_password" name="current_password" autocomplete="current-password" required class="h-11 w-full rounded-xl border border-slate-300 bg-white px-3.5 text-sm text-slate-900 shadow-sm focus:border-violet-500 focus:ring-2 focus:ring-violet-500/20">
                @if ($errors->updatePassword->has('current_password'))
                    <p class="mt-1 text-xs text-rose-600">{{ $errors->updatePassword->first('current_password') }}</p>
                @endif
            </div>

            <div class="grid gap-4 md:grid-cols-2">
                <div>
                    <label for="password" class="mb-1.5 block text-sm font-semibold text-slate-800">Nouveau mot de passe</label>
                    <input :type="voir ? 'text' : 'password'" id="password" name="password" autocomplete="new-password" required class="h-11 w-full rounded-xl border border-slate-300 bg-white px-3.5 text-sm text-slate-900 shadow-sm focus:border-violet-500 focus:ring-2 focus:ring-violet-500/20">
                    @if ($errors->updatePassword->has('password'))
                        <p class="mt-1 text-xs text-rose-600">{{ $errors->updatePassword->first('password') }}</p>
                    @endif
                </div>
                <div>
                    <label for="password_confirmation" class="mb-1.5 block text-sm font-semibold text-slate-800">Répétez le nouveau mot de passe</label>
                    <input :type="voir ? 'text' : 'password'" id="password_confirmation" name="password_confirmation" autocomplete="new-password" required class="h-11 w-full rounded-xl border border-slate-300 bg-white px-3.5 text-sm text-slate-900 shadow-sm focus:border-violet-500 focus:ring-2 focus:ring-violet-500/20">
                </div>
            </div>

            <label class="flex items-center gap-2 text-sm text-slate-600">
                <input type="checkbox" x-model="voir" class="h-4 w-4 rounded border-slate-300 text-violet-600 focus:ring-violet-500">
                Afficher les mots de passe
            </label>

            <div class="flex justify-end">
                <button type="submit" class="inline-flex h-10 items-center gap-2 rounded-xl bg-violet-600 px-5 text-sm font-semibold text-white shadow-sm hover:bg-violet-700">Changer le mot de passe</button>
            </div>
        </form>
    </section>
</div>
@endsection