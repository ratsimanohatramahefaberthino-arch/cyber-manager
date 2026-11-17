<div x-show="tab === 'reseau'" x-cloak class="space-y-6" role="tabpanel">

    <x-portail.carte titre="Liaison avec le routeur" aide="Comment les téléphones trouvent cette page avant d'être connectés." icone="wifi">
        <div class="space-y-5">

            <p class="rounded-lg bg-slate-50 px-3 py-2 text-sm text-slate-600">
                Avant la connexion, un téléphone ne peut joindre que ce PC (port {{ $port }}) : c'est le « Walled Garden ».
                Le routeur y redirige automatiquement chaque nouveau client.
            </p>

            {{-- Nom de la page --}}
            <div>
                <label for="f-domaine" class="{{ $label }}">Nom de la page (facultatif)</label>
                <div class="flex">
                    <span class="inline-flex items-center rounded-l-lg border border-r-0 border-slate-300 bg-slate-50 px-3 text-sm text-slate-500">http://</span>
                    <input id="f-domaine" type="text" name="portail_domaine" x-model="form.portail_domaine" maxlength="100"
                           autocomplete="off" autocapitalize="off" spellcheck="false" class="{{ $champ }} rounded-l-none font-mono">
                </div>
                <p class="mt-1 text-xs text-slate-500">Un nom facile à retenir, enregistré dans le routeur. N'utilisez pas le nom d'un vrai site (google.com, facebook.com…).</p>
            </div>

            {{-- Adresse de ce PC --}}
            <div>
                <span class="{{ $label }}">Adresse de ce PC sur le réseau</span>
                <input type="text" readonly value="{{ $ipServeur ?? 'Non détectée' }}"
                       class="w-full rounded-lg border-slate-200 bg-slate-50 font-mono text-sm {{ $ipServeur ? 'text-slate-700' : 'text-rose-600' }}">
                <details class="mt-2 text-xs text-slate-600" @if ($errors->has('portail_ip_serveur') || $ipManuelle) open @endif>
                    <summary class="cursor-pointer font-semibold">Forcer une autre adresse</summary>
                    <div class="mt-2">
                        <input type="text" name="portail_ip_serveur" value="{{ old('portail_ip_serveur', $ipManuelle) }}" inputmode="decimal"
                               placeholder="Laisser vide pour la détection automatique" autocomplete="off" class="{{ $champ }} font-mono">
                        <p class="mt-1">À remplir seulement si ce PC a plusieurs cartes réseau et que l'adresse détectée n'est pas celle des clients.</p>
                    </div>
                </details>
            </div>

            {{-- Adresses du portail --}}
            <div class="space-y-2">
                @if ($urlIp)
                    <div>
                        <span class="{{ $label }}">Adresse utilisée par la redirection</span>
                        <div class="flex gap-2">
                            <input type="text" readonly value="{{ $urlIp }}" class="w-full rounded-lg border-slate-200 bg-slate-50 font-mono text-xs text-slate-700">
                            <button type="button" @click="cmCopy(@js($urlIp))" class="{{ $bouton2 }} shrink-0 !px-3"><x-icone nom="copy" class="h-4 w-4" /> Copier</button>
                        </div>
                    </div>
                @endif
                @if ($urlNom)
                    <p class="text-xs text-slate-500">
                        Adresse par nom : <code class="font-mono text-slate-700">{{ $urlNom }}</code>
                        (fonctionne si le téléphone utilise le DNS du routeur).
                    </p>
                @endif
            </div>
        </div>
    </x-portail.carte>

    <x-portail.carte titre="Accès au routeur" aide="Pour que Cyber Manager puisse configurer le MikroTik." icone="key">
        <div class="space-y-4">
            <div class="grid grid-cols-1 gap-3 sm:grid-cols-3">
                <div class="sm:col-span-2">
                    <label for="mt-host" class="{{ $label }}">Adresse du routeur</label>
                    <input id="mt-host" type="text" name="mikrotik_host" value="{{ old('mikrotik_host', $routeurConfig['host']) }}" inputmode="decimal" autocomplete="off" class="{{ $champ }} font-mono">
                </div>
                <div>
                    <label for="mt-port" class="{{ $label }}">Port</label>
                    <input id="mt-port" type="number" name="mikrotik_port" value="{{ old('mikrotik_port', $routeurConfig['port']) }}" min="1" max="65535" class="{{ $champ }} font-mono">
                </div>
            </div>
            <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                <div>
                    <label for="mt-user" class="{{ $label }}">Utilisateur</label>
                    <input id="mt-user" type="text" name="mikrotik_user" value="{{ old('mikrotik_user', $routeurConfig['user']) }}" autocomplete="off" class="{{ $champ }}">
                </div>
                <div>
                    <label for="mt-pass" class="{{ $label }}">Mot de passe</label>
                    <input id="mt-pass" type="password" name="mikrotik_password" autocomplete="new-password"
                           placeholder="{{ $routeurConfig['mot_de_passe_defini'] ? 'Défini — laisser vide pour ne pas le changer' : 'Pas encore défini' }}"
                           class="{{ $champ }}">
                </div>
            </div>
        </div>
    </x-portail.carte>

    <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
        <h2 class="text-base font-bold text-slate-900">Le portail ne s'affiche pas sur les téléphones ?</h2>
        <p class="mt-1 text-sm text-slate-600">
            Ce bouton enregistre vos réglages, nettoie les anciennes règles du routeur, autorise ce PC avant connexion,
            réécrit la redirection, puis <strong>redémarre le HotSpot</strong>.
            Les clients déjà connectés seront coupés quelques secondes.
        </p>
        <button type="submit" formaction="{{ route('portail.admin.configurer') }}" :disabled="envoi"
                @click="if (! confirm('Redémarrer le HotSpot ? Les clients connectés seront coupés quelques secondes.')) $event.preventDefault()"
                class="mt-4 inline-flex items-center justify-center gap-2 rounded-xl bg-slate-900 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-slate-800 focus:outline-none focus-visible:ring-2 focus-visible:ring-slate-900 focus-visible:ring-offset-2 disabled:cursor-wait disabled:opacity-60">
            <x-icone nom="refresh" class="h-4 w-4" /> Synchroniser et redémarrer le HotSpot
        </button>
    </section>
</div>