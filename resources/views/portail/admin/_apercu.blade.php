<div class="overflow-hidden rounded-2xl border border-slate-800 bg-slate-900 shadow-xl">

    <div class="flex items-center justify-between gap-3 border-b border-white/10 bg-slate-950/70 px-4 py-3">
        <div class="flex items-center gap-2 text-sm font-medium text-slate-300">
            <span class="relative flex h-2 w-2">
                <span class="absolute inline-flex h-full w-full animate-ping rounded-full bg-emerald-400 opacity-60"></span>
                <span class="relative inline-flex h-2 w-2 rounded-full bg-emerald-500"></span>
            </span>
            Aperçu en direct
        </div>

        <div class="inline-flex rounded-lg border border-white/10 bg-slate-900 p-0.5" role="group" aria-label="Type d'écran">
            <button type="button" @click="mode = 'mobile'" :class="mode === 'mobile' ? 'bg-violet-600 text-white' : 'text-slate-400 hover:text-white'"
                    class="inline-flex items-center gap-1.5 rounded-md px-3 py-1.5 text-xs font-semibold transition">
                <x-icone nom="phone" class="h-4 w-4" /> Mobile
            </button>
            <button type="button" @click="mode = 'desktop'" :class="mode === 'desktop' ? 'bg-violet-600 text-white' : 'text-slate-400 hover:text-white'"
                    class="inline-flex items-center gap-1.5 rounded-md px-3 py-1.5 text-xs font-semibold transition">
                <x-icone nom="desktop" class="h-4 w-4" /> PC
            </button>
        </div>
    </div>

    <div class="p-4" style="background-color:#0f172a;background-image:radial-gradient(#27364d 1px,transparent 1px);background-size:20px 20px;">
        <div x-ref="scene" class="w-full">
            <div class="relative mx-auto" :style="{ width: (cadre.w * echelle) + 'px', height: (cadre.h * echelle) + 'px' }">
                <div class="absolute left-0 top-0 origin-top-left"
                     :style="{ width: cadre.w + 'px', height: cadre.h + 'px', transform: 'scale(' + echelle + ')' }">

                    {{-- Appareil --}}
                    <div class="relative h-full w-full bg-slate-950 shadow-2xl ring-1 ring-white/10"
                         :class="mode === 'mobile' ? 'rounded-[48px] p-3' : 'flex flex-col rounded-xl'">

                        <div x-show="mode === 'mobile'" class="pointer-events-none absolute left-1/2 top-5 z-20 h-6 w-24 -translate-x-1/2 rounded-full bg-black"></div>
                        <span x-show="mode === 'mobile'" class="absolute -left-[3px] top-28 h-8 w-[3px] rounded-l bg-slate-700"></span>
                        <span x-show="mode === 'mobile'" class="absolute -left-[3px] top-40 h-12 w-[3px] rounded-l bg-slate-700"></span>
                        <span x-show="mode === 'mobile'" class="absolute -right-[3px] top-32 h-14 w-[3px] rounded-r bg-slate-700"></span>

                        <div x-show="mode === 'desktop'" class="flex h-10 shrink-0 items-center gap-3 px-4">
                            <div class="flex gap-1.5">
                                <span class="h-3 w-3 rounded-full bg-slate-700"></span>
                                <span class="h-3 w-3 rounded-full bg-slate-700"></span>
                                <span class="h-3 w-3 rounded-full bg-slate-700"></span>
                            </div>
                            <div class="mx-auto flex h-6 w-[460px] items-center justify-center truncate rounded-md bg-slate-800 px-3 font-mono text-[11px] text-slate-400" x-text="urlAffichee"></div>
                        </div>

                        {{-- Écran : reproduit portail/login.blade.php --}}
                        <div class="relative overflow-hidden" :class="mode === 'mobile' ? 'h-full rounded-[36px]' : 'min-h-0 flex-1 rounded-b-xl'">
                            <div class="h-full overflow-y-auto" :style="fondEcran">
                                <div class="flex min-h-full w-full" :class="mode === 'desktop' ? 'items-center justify-center p-10' : ''">
                                    <div class="flex w-full flex-col" :class="mode === 'desktop' ? 'max-w-[440px]' : 'min-h-full'">

                                        <header class="px-6 pb-20 pt-8 text-center">
                                            <div class="mx-auto mb-4 grid h-[76px] w-[76px] place-items-center overflow-hidden rounded-[22px] bg-white p-2.5 shadow-xl">
                                                <template x-if="logoUrl"><img :src="logoUrl" alt="" class="max-h-full max-w-full object-contain"></template>
                                                <template x-if="!logoUrl"><x-icone nom="wifi" class="h-9 w-9 text-slate-700" /></template>
                                            </div>
                                            <h2 class="font-bold leading-tight" :style="{ color: form.titre_couleur, fontSize: form.titre_taille + 'px' }" x-text="form.titre || 'Titre'"></h2>
                                            <p class="mx-auto mt-2 max-w-[32ch] text-[15px] leading-snug" :style="{ color: form.message_couleur }" x-text="form.message"></p>
                                        </header>

                                        <div class="-mt-12 p-5 pt-6" :class="mode === 'desktop' ? '' : 'flex-1'"
                                             :style="{ background: form.carte_couleur, color: form.carte_texte, borderRadius: mode === 'desktop' ? '28px' : '28px 28px 0 0' }">

                                            <div x-show="form.annonce_active && form.annonce_texte" x-cloak
                                                 class="mb-4 rounded-[14px] px-3.5 py-2.5 text-center text-[13px] font-bold"
                                                 :style="{ background: form.annonce_fond, color: form.annonce_texte_color }" x-text="form.annonce_texte"></div>

                                            <div class="mb-4">
                                                <div class="mb-1.5 ml-0.5 text-[13px] font-semibold opacity-65">Identifiant</div>
                                                <div class="flex h-[54px] items-center rounded-[14px] bg-white px-4 text-base text-slate-400"
                                                     :style="{ border: '1.5px solid ' + form.accent_couleur, boxShadow: '0 0 0 2px ' + form.accent_couleur }">Ex : cm-4821</div>
                                            </div>

                                            <div class="mb-4">
                                                <div class="mb-1.5 ml-0.5 text-[13px] font-semibold opacity-65">Mot de passe</div>
                                                <div class="flex h-[54px] items-center rounded-[14px] border-[1.5px] border-[#cfdcdc] bg-white px-4 text-base text-slate-400">Votre mot de passe</div>
                                            </div>

                                            <div class="mt-2 flex h-14 w-full items-center justify-center text-[17px] font-bold shadow-lg"
                                                 :style="{ background: form.bouton_fond, color: form.bouton_texte, borderRadius: form.bouton_rayon + 'px' }">Se connecter</div>

                                            <div class="mt-5 text-center text-sm leading-relaxed opacity-80">
                                                <p x-show="form.footer_texte" x-text="form.footer_texte"></p>
                                                <span x-show="tarif" x-cloak class="mt-2 inline-block rounded-full bg-black/10 px-3.5 py-1.5 text-[13px] font-semibold" x-text="tarif"></span>
                                            </div>
                                        </div>

                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="flex items-center justify-between gap-3 border-t border-white/10 bg-slate-950/70 px-4 py-2.5 text-xs text-slate-400">
        <span>Même rendu que la vraie page, à l'échelle <span class="font-mono" x-text="Math.round(echelle * 100) + ' %'"></span></span>
        <button type="button" @click="$refs.haut.scrollIntoView({ behavior: 'smooth', block: 'start' })" class="font-semibold text-slate-300 hover:text-white 2xl:hidden">↑ Retour aux réglages</button>
    </div>
</div>