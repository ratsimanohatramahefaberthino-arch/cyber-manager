<div x-show="tab === 'design'" x-cloak class="space-y-6" role="tabpanel">

    <x-portail.carte titre="Couleurs et style" aide="L'ambiance de la page : fond, carte de connexion, bouton." icone="palette">
        <div class="space-y-6">

            <div>
                <label for="f-police" class="{{ $label }}">Police</label>
                <select id="f-police" name="police" x-model="form.police" class="{{ $champ }}">
                    @foreach ($polices as $pile => $nom)
                        <option value="{{ $pile }}">{{ $nom }}</option>
                    @endforeach
                </select>
                <p class="mt-1 text-xs text-slate-500">Seules des polices déjà présentes sur les téléphones sont proposées : avant la connexion, ils n'ont pas Internet.</p>
            </div>

            <div class="space-y-3">
                <span class="{{ $label }} !mb-0">Arrière-plan</span>
                <input type="hidden" name="fond_type" :value="form.fond_type">
                <div class="grid grid-cols-2 gap-1 rounded-xl bg-slate-100 p-1" role="radiogroup" aria-label="Type d'arrière-plan">
                    <button type="button" role="radio" :aria-checked="form.fond_type === 'gradient'" @click="form.fond_type = 'gradient'"
                            :class="form.fond_type === 'gradient' ? 'bg-white text-violet-700 shadow-sm' : 'text-slate-500'"
                            class="rounded-lg py-2 text-sm font-semibold transition">Dégradé</button>
                    <button type="button" role="radio" :aria-checked="form.fond_type === 'solid'" @click="form.fond_type = 'solid'"
                            :class="form.fond_type === 'solid' ? 'bg-white text-violet-700 shadow-sm' : 'text-slate-500'"
                            class="rounded-lg py-2 text-sm font-semibold transition">Couleur unie</button>
                </div>
                <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                    <x-portail.couleur nom="fond_couleur_1" label="Fond principal" />
                    <x-portail.couleur nom="fond_couleur_2" label="Halo lumineux" x-show="form.fond_type === 'gradient'" />
                </div>
            </div>

            <div class="space-y-3">
                <span class="{{ $label }} !mb-0">Carte de connexion</span>
                <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                    <x-portail.couleur nom="carte_couleur" label="Fond de la carte" />
                    <x-portail.couleur nom="carte_texte" label="Texte de la carte" />
                    <x-portail.couleur nom="accent_couleur" label="Contour du champ actif" />
                </div>
            </div>

            <div class="space-y-3">
                <span class="{{ $label }} !mb-0">Bouton « Se connecter »</span>
                <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                    <x-portail.couleur nom="bouton_fond" label="Fond du bouton" />
                    <x-portail.couleur nom="bouton_texte" label="Texte du bouton" />
                </div>

                <input type="hidden" name="bouton_rayon" :value="form.bouton_rayon">
                <div class="grid grid-cols-3 gap-2" role="radiogroup" aria-label="Arrondi du bouton">
                    @foreach ([['carre', 'Carré', 4, '4px'], ['doux', 'Doux', 16, '10px'], ['pilule', 'Pilule', 99, '9999px']] as [$id, $nom, $valeur, $forme])
                        <button type="button" role="radio" :aria-checked="rayonPreset === '{{ $id }}'" @click="form.bouton_rayon = {{ $valeur }}"
                                :class="rayonPreset === '{{ $id }}' ? 'border-violet-600 bg-violet-50 text-violet-700 ring-1 ring-violet-600' : 'border-slate-300 text-slate-600 hover:border-slate-400'"
                                class="border px-2 py-2.5 text-sm font-semibold transition" style="border-radius: {{ $forme }}">
                            {{ $nom }}
                        </button>
                    @endforeach
                </div>
            </div>

            <div class="border-t border-slate-100 pt-4">
                <button type="submit" form="form-reset"
                        onclick="return confirm('Remettre tous les textes et couleurs comme à l\'origine ? Le logo est conservé.')"
                        class="text-xs font-semibold text-rose-600 underline hover:no-underline">
                    Revenir aux réglages d'origine
                </button>
            </div>
        </div>
    </x-portail.carte>
</div>