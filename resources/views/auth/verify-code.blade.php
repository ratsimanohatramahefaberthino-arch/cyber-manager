<x-guest-layout>
    <div class="w-full max-w-md">
        <div class="text-center mb-6">
            <div class="mx-auto mb-4 flex h-16 w-16 items-center justify-center rounded-2xl bg-violet-100 text-violet-700 shadow-sm">
                <x-icone nom="key" classe="h-8 w-8" />
            </div>
            <h1 class="text-2xl font-bold text-slate-900">Vérifiez votre email</h1>
            <p class="text-sm text-slate-500 mt-2">
                Nous avons envoyé un code à 6 chiffres à <strong>{{ auth()->user()->email }}</strong>.
                Saisissez-le ci-dessous pour activer votre compte.
            </p>
        </div>

        <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-xl sm:p-8">
            @if (session('success'))
                <div class="mb-4 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">
                    {{ session('success') }}
                </div>
            @endif

            <form method="POST" action="{{ route('verification.verify.custom') }}" class="space-y-4">
                @csrf
                <div>
                    <label for="code" class="mb-1.5 block text-sm font-semibold text-slate-800">Code de vérification</label>
                    <input id="code" type="text" name="code" maxlength="6" required autofocus
                           class="h-14 w-full rounded-xl border border-slate-300 bg-white px-4 text-center text-2xl tracking-[0.5em] font-mono text-slate-900 shadow-sm focus:border-violet-500 focus:ring-2 focus:ring-violet-500/20"
                           placeholder="000000">
                    @if ($errors->has('code'))
                        <p class="mt-1 text-xs text-rose-600">{{ $errors->first('code') }}</p>
                    @endif
                </div>

                <button type="submit" class="w-full h-11 rounded-xl bg-violet-600 text-sm font-semibold text-white shadow-sm hover:bg-violet-700">
                    Vérifier mon compte
                </button>
            </form>

            <div class="mt-6 flex items-center justify-between text-sm">
                <form method="POST" action="{{ route('verification.resend.custom') }}">
                    @csrf
                    <button type="submit" class="text-violet-600 hover:text-violet-800 underline">Renvoyer le code</button>
                </form>

                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" class="text-slate-500 hover:text-slate-700 underline">Se déconnecter</button>
                </form>
            </div>
        </div>
    </div>
</x-guest-layout>