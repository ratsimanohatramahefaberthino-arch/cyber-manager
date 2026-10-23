@extends('layouts.cyber-manager', ['title' => 'Réglages du Portail Captif'])

@section('content')
<div x-data="{
    titre: @js($titre),
    message: @js($message),
    logoUrl: @js($logo ? Storage::url($logo) : ''),
    logoNom: @js($logo ? basename($logo) : ''),
    
    changerLogo(event) {
        const file = event.target.files[0];
        if (file) {
            this.logoNom = file.name;
            this.logoUrl = URL.createObjectURL(file);
        }
    }
}" class="max-w-7xl mx-auto pb-10">

    <div class="mb-6">
        <h1 class="text-2xl font-bold text-slate-900">Réglages du Portail Captif</h1>
        <p class="text-slate-500">Personnalisez l'écran que verront vos clients lorsqu'ils se connecteront au Wi-Fi.</p>
    </div>

    @if(session('success'))
        <div class="bg-emerald-100 text-emerald-800 p-4 rounded-xl mb-6 shadow-sm border border-emerald-200 flex items-center gap-2">
            <x-icone nom="check" class="h-5 w-5" /> {{ session('success') }}
        </div>
    @endif

    <div class="grid grid-cols-1 lg:grid-cols-12 gap-8 items-start">
        
        {{-- COLONNE GAUCHE : LE FORMULAIRE --}}
        <div class="lg:col-span-5 bg-white p-6 rounded-2xl shadow-sm border border-slate-200">
            <form action="{{ route('portail.admin.save') }}" method="POST" enctype="multipart/form-data" class="space-y-5">
                @csrf
                
                {{-- Logo --}}
                <div>
                    <label class="block text-sm font-semibold text-slate-700 mb-1">Logo de l'établissement</label>
                    <div class="mt-1 flex items-center gap-4">
                        <div class="h-16 w-16 rounded-xl bg-slate-100 border border-slate-200 flex items-center justify-center overflow-hidden shrink-0">
                            <template x-if="logoUrl">
                                <img :src="logoUrl" class="h-full w-full object-contain">
                            </template>
                            <template x-if="!logoUrl">
                                <x-icone nom="link" class="h-6 w-6 text-slate-400" />
                            </template>
                        </div>
                        <div class="flex-1">
                            <input type="file" name="logo" accept="image/*" @change="changerLogo($event)" 
                                   class="block w-full text-sm text-slate-500 file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-sm file:font-semibold file:bg-slate-100 file:text-slate-700 hover:file:bg-slate-200">
                            <p class="text-xs text-slate-400 mt-1">PNG, JPG ou SVG. Max 2 Mo.</p>
                        </div>
                    </div>
                </div>

                {{-- Titre --}}
                <div>
                    <label class="block text-sm font-semibold text-slate-700 mb-1">Titre d'accueil</label>
                    <input type="text" name="titre" x-model="titre" required 
                           class="w-full rounded-lg border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                </div>

                {{-- Message --}}
                <div>
                    <label class="block text-sm font-semibold text-slate-700 mb-1">Message de bienvenue</label>
                    <textarea name="message" x-model="message" rows="3" required 
                              class="w-full rounded-lg border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"></textarea>
                </div>

                {{-- Info Tarif (Lecture seule) --}}
                <div class="rounded-xl bg-sky-50 p-4 border border-sky-100">
                    <div class="flex items-start gap-3">
                        <x-icone nom="warning" class="h-5 w-5 text-sky-600 mt-0.5 shrink-0" />
                        <div>
                            <h4 class="text-sm font-semibold text-sky-900">Tarification appliquée</h4>
                            <p class="text-sm text-sky-800 mt-1">
                                Le portail affichera automatiquement votre tarif Wi-Fi actuel : 
                                <strong>{{ $tarifWifi ? $tarifWifi->montant_par_minute . ' Ar/min' : 'Non configuré' }}</strong>
                                @if($tarifWifi && $tarifWifi->montant_minimum)
                                    et <strong>{{ $tarifWifi->montant_minimum }} Ar minimum</strong>.
                                @endif
                            </p>
                            <p class="text-xs text-sky-600 mt-2">Pour modifier ces prix, rendez-vous dans le menu "Tarifs".</p>
                        </div>
                    </div>
                </div>

                <div class="pt-2">
                    <button type="submit" class="w-full flex justify-center items-center gap-2 bg-indigo-600 text-white px-6 py-3 rounded-xl font-semibold hover:bg-indigo-700 transition shadow-sm">
                        <x-icone nom="check" class="h-5 w-5" />
                        Enregistrer les réglages
                    </button>
                </div>
            </form>

            {{-- Section Configuration Automatique du Routeur --}}
            <div class="mt-8 border-t border-slate-200 pt-6">
                <h3 class="text-sm font-bold text-slate-900 mb-2">Synchronisation avec MikroTik</h3>
                <p class="text-xs text-slate-500 mb-4">
                    Cliquez sur ce bouton pour que Laravel configure automatiquement le routeur. 
                    Il autorisera l'adresse IP actuelle du serveur (<strong>{{ request()->server('SERVER_ADDR') ?? 'locale' }}</strong>) et configurera la redirection.
                </p>

                @if(session('error'))
                    <div class="bg-rose-100 text-rose-800 p-3 rounded-lg mb-4 text-sm">
                        {{ session('error') }}
                    </div>
                @endif

                <form action="{{ route('portail.admin.configurer') }}" method="POST">
                    @csrf
                    <button type="submit" class="w-full flex justify-center items-center gap-2 bg-slate-900 text-white px-6 py-3 rounded-xl font-semibold hover:bg-slate-700 transition shadow-sm">
                        <x-icone nom="refresh" class="h-5 w-5" />
                        Configurer le routeur automatiquement
                    </button>
                </form>
            </div>
        </div>

        {{-- COLONNE DROITE : APERÇU EN DIRECT --}}
        <div class="lg:col-span-7 flex flex-col items-center">
            <div class="mb-3 text-sm font-semibold text-slate-400 uppercase tracking-widest flex items-center gap-2">
                <x-icone nom="eye" class="h-4 w-4" /> Aperçu en direct (Mobile)
            </div>
            
            {{-- Cadre style Téléphone --}}
            <div class="relative w-[340px] h-[680px] bg-slate-900 rounded-[3rem] border-[10px] border-slate-800 shadow-2xl overflow-hidden shrink-0 flex flex-col">
                <div class="absolute top-0 inset-x-0 h-6 flex justify-center z-20">
                    <div class="w-32 h-6 bg-slate-800 rounded-b-xl"></div>
                </div>

                <div class="flex-1 bg-slate-50 relative pt-12 flex flex-col overflow-y-auto">
                    <div class="absolute top-0 inset-x-0 h-48 bg-gradient-to-br from-indigo-500 to-purple-600 rounded-b-[2.5rem] z-0"></div>

                    <div class="relative z-10 px-6 flex flex-col items-center h-full">
                        <div class="h-20 w-20 bg-white rounded-2xl shadow-lg flex items-center justify-center p-2 mb-4 overflow-hidden border border-slate-100">
                            <template x-if="logoUrl">
                                <img :src="logoUrl" class="h-full w-full object-contain">
                            </template>
                            <template x-if="!logoUrl">
                                <x-icone nom="wifi" class="h-10 w-10 text-slate-300" />
                            </template>
                        </div>

                        <h2 class="text-xl font-bold text-white text-center mb-1" x-text="titre || 'Bienvenue'"></h2>
                        <p class="text-sm text-indigo-100 text-center mb-6 px-4" x-text="message || 'Veuillez entrer vos identifiants.'"></p>

                        <div class="w-full bg-white p-5 rounded-2xl shadow-xl border border-slate-100">
                            <div class="space-y-3">
                                <div>
                                    <div class="h-3 w-16 bg-slate-200 rounded mb-1"></div>
                                    <div class="h-10 w-full bg-slate-50 border border-slate-200 rounded-lg flex items-center px-3">
                                        <span class="text-slate-400 text-sm">Nom d'utilisateur</span>
                                    </div>
                                </div>
                                <div>
                                    <div class="h-3 w-16 bg-slate-200 rounded mb-1"></div>
                                    <div class="h-10 w-full bg-slate-50 border border-slate-200 rounded-lg flex items-center px-3">
                                        <span class="text-slate-400 text-sm">Mot de passe</span>
                                    </div>
                                </div>
                                <div class="pt-2">
                                    <div class="h-10 w-full bg-indigo-600 rounded-lg flex items-center justify-center text-white font-semibold text-sm">
                                        Se connecter
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="mt-6 text-center">
                            <p class="text-xs text-slate-400">Tarification :</p>
                            <p class="text-sm font-bold text-slate-700">
                                {{ $tarifWifi ? $tarifWifi->montant_par_minute : '20' }} Ar / minute
                            </p>
                            @if($tarifWifi && $tarifWifi->montant_minimum)
                                <p class="text-xs text-slate-500">(Minimum {{ $tarifWifi->montant_minimum }} Ar)</p>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
            <p class="text-xs text-slate-400 mt-4 text-center max-w-sm">Cet aperçu est généré en temps réel. Il correspond à ce que verra un client sur son téléphone.</p>
        </div>

    </div>
</div>
@endsection