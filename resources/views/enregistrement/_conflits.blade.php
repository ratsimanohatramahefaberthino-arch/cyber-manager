@foreach ($conflits as $p)
    <section class="rounded-2xl border border-rose-200 bg-rose-50 p-5 shadow-sm sm:p-6">
        <div class="flex items-start gap-4">
            <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-rose-600 text-white">
                <x-icone nom="warning" class="h-5 w-5" />
            </span>

            <div class="min-w-0 flex-1 space-y-3">
                <div>
                    <h2 class="text-base font-bold text-rose-900">Deux ordinateurs semblent utiliser le poste « {{ $p->nom_poste }} »</h2>
                    <p class="mt-1 text-sm leading-relaxed text-rose-900/90">
                        Alerte du {{ $p->conflit_identite_at->timezone($fuseau)->format('d/m/Y à H:i') }}.
                        C'est typique d'un disque copié (cloné) : le programme a été copié sur un autre PC avec sa configuration.
                    </p>
                    @if ($p->derniere_ip_vue)
                        <p class="mt-1 text-xs text-rose-800">
                            Dernière adresse réseau vue pour ce poste :
                            <code class="rounded bg-white/70 px-1 font-mono">{{ $p->derniere_ip_vue }}</code>
                        </p>
                    @endif
                </div>

                <div class="flex flex-col gap-2 sm:flex-row">
                    <form method="POST" action="{{ route('enregistrement.separer', $p) }}"
                          @submit="if (! confirm(@js('Séparer les deux ordinateurs ? L\'ordinateur en trop recevra une nouvelle identité et apparaîtra comme un nouveau poste à renommer.'))) $event.preventDefault()">
                        @csrf
                        <button type="submit"
                                class="inline-flex w-full items-center justify-center rounded-xl bg-rose-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-rose-700 focus:outline-none focus-visible:ring-2 focus-visible:ring-rose-500 focus-visible:ring-offset-2 sm:w-auto">
                            Séparer les deux ordinateurs
                        </button>
                    </form>

                    <form method="POST" action="{{ route('enregistrement.ignorer', $p) }}"
                          @submit="if (! confirm(@js('Effacer l\'alerte sans rien changer ? À faire seulement si vous savez que c\'est une fausse alerte.'))) $event.preventDefault()">
                        @csrf
                        <button type="submit"
                                class="inline-flex w-full items-center justify-center rounded-xl border border-rose-200 bg-white px-4 py-2.5 text-sm font-semibold text-rose-800 transition hover:bg-rose-100 focus:outline-none focus-visible:ring-2 focus-visible:ring-rose-500 sm:w-auto">
                            C'est une fausse alerte
                        </button>
                    </form>
                </div>

                <details class="text-xs text-rose-900/90">
                    <summary class="cursor-pointer font-semibold">La séparation ne marche pas ?</summary>
                    <p class="mt-1 leading-relaxed">
                        Sur l'ordinateur en trop, supprimez le dossier
                        <code class="rounded bg-white/70 px-1 font-mono">%LocalAppData%\CyberManagerAgent</code>
                        puis relancez l'installateur avec le code d'installation.
                    </p>
                </details>
            </div>
        </div>
    </section>
@endforeach