<x-guest-layout>
    <div class="w-full max-w-md">
        <div class="text-center mb-6">
            <h1 class="text-2xl font-bold text-slate-900">Mot de passe oublié</h1>
            <p class="text-sm text-slate-500 mt-2">
                Saisissez votre adresse email pour recevoir un code de réinitialisation.
            </p>
        </div>

        <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-xl sm:p-8">
            @if (session('status'))
                <div class="mb-4 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">
                    {{ session('status') }}
                </div>
            @endif

            <form method="POST" action="{{ route('password.email') }}" class="space-y-4">
                @csrf
                <div>
                    <label for="email" class="mb-1.5 block text-sm font-semibold text-slate-800">Adresse e-mail</label>
                    <input id="email" type="email" name="email" value="{{ old('email') }}" required autofocus
                           class="h-11 w-full rounded-xl border border-slate-300 bg-white px-3.5 text-sm text-slate-900 shadow-sm focus:border-violet-500 focus:ring-2 focus:ring-violet-500/20">
                    @if ($errors->has('email'))
                        <p class="mt-1 text-xs text-rose-600">{{ $errors->first('email') }}</p>
                    @endif
                </div>

                <button type="submit" class="w-full h-11 rounded-xl bg-violet-600 text-sm font-semibold text-white shadow-sm hover:bg-violet-700">
                    Envoyer le code
                </button>
            </form>

            <div class="mt-6 text-center">
                <a href="{{ route('login') }}" class="text-sm text-slate-500 hover:text-slate-700 underline">
                    &larr; Retour à la connexion
                </a>
            </div>
        </div>
    </div>
</x-guest-layout>