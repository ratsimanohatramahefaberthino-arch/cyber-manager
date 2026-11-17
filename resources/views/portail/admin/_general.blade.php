<div x-show="tab === 'general'" class="space-y-6" role="tabpanel">

    <x-portail.carte titre="Titre et message d'accueil" aide="Ce que le client lit en premier sur son téléphone." icone="pencil">
        <div class="space-y-5">

            <div>
                <label for="f-titre" class="{{ $label }}">Titre</label>
                <input id="f-titre" type="text" name="titre" x-model="form.titre" maxlength="120" class="{{ $champ }}">
            </div>

            <div>
                <div class="mb-1.5 flex items-center justify-between">
                    <label for="f-taille" class="text-[13px] font-medium text-slate-700">Taille du titre</label>
                    <span class="font-mono text-xs text-violet-700" x-text="form.titre_taille + ' px'"></span>
                </div>
                <div class="flex items-center gap-3">
                    <span class="text-xs text-slate-400">18</span>
                    <input id="f-taille" type="range" name="titre_taille" min="18" max="36" step="1"
                           x-model.number="form.titre_taille" class="w-full cursor-pointer accent-violet-600">
                    <span class="text-xs text-slate-400">36</span>
                </div>
            </div>

            <div>
                <label for="f-message" class="{{ $label }}">Message de bienvenue</label>
                <textarea id="f-message" name="message" x-model="form.message" rows="3" maxlength="300" class="{{ $champ }} resize-none"></textarea>
            </div>

            <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                <x-portail.couleur nom="titre_couleur" label="Couleur du titre" />
                <x-portail.couleur nom="message_couleur" label="Couleur du message" />
            </div>

            <div>
                <label for="f-footer" class="{{ $label }}">Texte sous le bouton</label>
                <input id="f-footer" type="text" name="footer_texte" x-model="form.footer_texte" maxlength="200" class="{{ $champ }}">
            </div>

            {{-- Logo --}}
            <div>
                <span class="{{ $label }}">Logo</span>
                <div class="flex flex-wrap items-center gap-4 rounded-xl border border-dashed border-slate-300 bg-slate-50/60 p-3">
                    <div class="flex h-16 w-16 shrink-0 items-center justify-center overflow-hidden rounded-xl border border-slate-200 bg-white p-1.5">
                        <template x-if="logoUrl"><img :src="logoUrl" alt="" class="max-h-full max-w-full object-contain"></template>
                        <template x-if="!logoUrl"><x-icone nom="image" class="h-6 w-6 text-slate-300" /></template>
                    </div>
                    <div class="min-w-0 flex-1">
                        <div class="flex flex-wrap items-center gap-2">
                            <label class="inline-flex cursor-pointer items-center rounded-lg border border-slate-300 bg-white px-3 py-1.5 text-xs font-semibold text-slate-700 shadow-sm transition hover:bg-slate-50">
                                <span x-text="logoUrl ? 'Remplacer' : 'Choisir une image'">Choisir une image</span>
                                <input type="file" name="logo" accept="image/png,image/jpeg,image/webp" x-ref="logoInput" @change="changerLogo($event)" class="sr-only">
                            </label>
                            <button type="button" x-show="logoUrl" x-cloak @click="retirerLogo()"
                                    class="inline-flex items-center gap-1 rounded-lg px-2 py-1.5 text-xs font-semibold text-rose-600 hover:bg-rose-50">
                                <x-icone nom="trash" class="h-4 w-4" /> Supprimer
                            </button>
                        </div>
                        <p class="mt-1.5 truncate text-xs text-slate-500" x-text="logoNom || 'PNG, JPG ou WebP, 2 Mo au plus.'"></p>
                        <p x-show="logoErreur" x-cloak class="mt-1 text-xs font-medium text-rose-600" x-text="logoErreur"></p>
                    </div>
                </div>
            </div>
        </div>
    </x-portail.carte>

    <x-portail.carte titre="Bannière d'annonce" aide="Un message mis en avant au-dessus du formulaire (promotion, consigne…)." icone="megaphone">
        <x-slot:action>
            <button type="button" role="switch" :aria-checked="form.annonce_active" aria-label="Afficher la bannière"
                    @click="form.annonce_active = !form.annonce_active"
                    :class="form.annonce_active ? 'bg-violet-600' : 'bg-slate-300'"
                    class="relative inline-flex h-6 w-11 shrink-0 cursor-pointer rounded-full transition-colors focus:outline-none focus-visible:ring-2 focus-visible:ring-violet-500 focus-visible:ring-offset-2">
                <span :class="form.annonce_active ? 'translate-x-5' : 'translate-x-0.5'"
                      class="mt-0.5 inline-block h-5 w-5 rounded-full bg-white shadow transition-transform"></span>
            </button>
        </x-slot:action>

        <p x-show="!form.annonce_active" class="text-sm text-slate-500">La bannière est cachée. Activez-la avec l'interrupteur.</p>

        <div x-show="form.annonce_active" x-cloak class="space-y-4">
            <div>
                <label for="f-annonce" class="{{ $label }}">Texte de l'annonce</label>
                <input id="f-annonce" type="text" name="annonce_texte" x-model="form.annonce_texte" maxlength="150" placeholder="Ex : Tarif étudiant le matin" class="{{ $champ }}">
            </div>
            <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                <x-portail.couleur nom="annonce_fond" label="Fond de la bannière" />
                <x-portail.couleur nom="annonce_texte_color" label="Texte de la bannière" />
            </div>
        </div>
    </x-portail.carte>
</div>