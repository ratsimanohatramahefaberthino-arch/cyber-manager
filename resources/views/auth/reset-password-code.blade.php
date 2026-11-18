<x-guest-layout>
    <div class="w-full max-w-md">
        <div class="text-center mb-6">
            <h1 class="text-2xl font-bold text-slate-900">Nouveau mot de passe</h1>
            <p class="text-sm text-slate-500 mt-2">
                Saisissez le code reçu par email et choisissez un nouveau mot de passe.
            </p>
        </div>

        <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-xl sm:p-8">
            <form method="POST" action="{{ route('password.update.custom') }}" class="space-y-4" x-data="{ voir: false }">
                @csrf
                <div>
                    <label for="code" class="mb-1.5 block text-sm font-semibold text-slate-800">Code reçu par email</label>
                    <input id="code" type="text" name="code" maxlength="6" required autofocus
                           class="h-14 w-full rounded-xl border border-slate-300 bg-white px-4 text-center text-2xl tracking-[0.5em] font-mono text-slate-900 shadow-sm focus:border-violet-500 focus:ring-2 focus:ring-violet-500/20"
                           placeholder="000000">
                    @if ($errors->has('code'))
                        <p class="mt-1 text-xs text-rose-600">{{ $errors->first('code') }}</p>
                    @endif
                </div>

                <div>
                    <label for="password" class="mb-1.5 block text-sm font-semibold text-slate-800">Nouveau mot de passe</label>
                    <input :type="voir ? 'text' : 'password'" id="password" name="password" required
                           class="h-11 w-full rounded-xl border border-slate-300 bg-white px-3.5 text-sm text-slate-900 shadow-sm focus:border-violet-500 focus:ring-2 focus:ring-violet-500/20">
                </div>

                <div>
                    <label for="password_confirmation" class="mb-1.5 block text-sm font-semibold text-slate-800">Répétez le mot de passe</label>
                    <input :type="voir ? 'text' : 'password'" id="password_confirmation" name="password_confirmation" required
                           class="h-11 w-full rounded-xl border border-slate-300 bg-white px-3.5 text-sm text-slate-900 shadow-sm focus:border-violet-500 focus:ring-2 focus:ring-violet-500/20">
                </div>

                <label class="flex items-center gap-2 text-sm text-slate-600">
                    <input type="checkbox" x-model="voir" class="h-4 w-4 rounded border-slate-300 text-violet-600 focus:ring-violet-500">
                    Afficher les mots de passe
                </label>

                <button type="submit" class="w-full h-11 rounded-xl bg-violet-600 text-sm font-semibold text-white shadow-sm hover:bg-violet-700">
                    Réinitialiser le mot de passe
                </button>
            </form>
        </div>
    </div>
</x-guest-layout>