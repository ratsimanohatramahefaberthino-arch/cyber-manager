@extends('layouts.cyber-manager', ['title' => 'Versions — Cyber Manager'])

@section('content')
@php
    use App\Models\AgentVersion;
    $etiquettes = ['brouillon' => ['Brouillon', 'gris'], 'pilote' => ['Test (pilote)', 'orange'], 'general' => ['Publiée (tous)', 'vert'], 'archivee' => ['Archivée', 'bleu']];
    $generale = AgentVersion::generale();
    $pilote   = AgentVersion::pilote();
    $champ = 'h-11 w-full rounded-xl border border-slate-300 bg-white px-3.5 text-sm text-slate-900 shadow-sm placeholder:text-slate-400 focus:border-violet-500 focus:ring-2 focus:ring-violet-500/20';
    $label = 'mb-1.5 block text-sm font-semibold text-slate-800';
    $btn   = 'inline-flex h-9 items-center justify-center gap-1.5 rounded-lg px-3 text-sm font-semibold shadow-sm transition focus:outline-none focus-visible:ring-2 focus-visible:ring-violet-500';
@endphp

<div class="space-y-6" x-data="{ confirmerMAJ: false, chargement: false }">
    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h1 class="text-2xl font-semibold tracking-tight text-slate-900">Versions de l'écran</h1>
            <p class="text-sm text-slate-500">Envoi, test sur un poste pilote, publication et retour arrière.</p>
        </div>
        {{-- Bouton Mise à jour immédiate --}}
        @if($generale)
            <div>
                <template x-if="!confirmerMAJ">
                    <button type="button" x-on:click="confirmerMAJ = true"
                            class="inline-flex h-10 items-center gap-2 rounded-xl bg-violet-600 px-5 text-sm font-semibold text-white shadow-sm hover:bg-violet-700">
                        <x-icone nom="refresh" classe="h-4 w-4" /> Mettre à jour maintenant
                    </button>
                </template>
                <template x-if="confirmerMAJ">
                    <form method="POST" action="{{ route('parametres.versions.forcer') }}" x-on:submit="chargement = true" class="flex items-center gap-2">
                        @csrf
                        <span class="text-sm text-slate-600">Forcer la MAJ sur tous les postes ?</span>
                        <button type="button" x-on:click="confirmerMAJ = false" class="rounded-lg border border-slate-300 bg-white px-3 py-1.5 text-sm font-semibold text-slate-700 hover:bg-slate-50">Annuler</button>
                        <button type="submit" class="inline-flex items-center gap-2 rounded-lg bg-emerald-600 px-3 py-1.5 text-sm font-semibold text-white hover:bg-emerald-700 disabled:opacity-50" x-bind:disabled="chargement">
                            <svg x-show="chargement" x-cloak class="animate-spin h-4 w-4 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                            <span x-text="chargement ? 'Envoi...' : 'Confirmer'"></span>
                        </button>
                    </form>
                </template>
            </div>
        @endif
    </div>

    <x-flash />

    @if ($errors->any() && !$errors->has('global'))
        <div class="rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800" role="alert">
            <ul class="list-disc space-y-0.5 pl-5">@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
        </div>
    @endif

    {{-- Version publiée / version en test --}}
    <div class="grid gap-4 md:grid-cols-2">
        <div class="rounded-2xl border border-emerald-100 bg-gradient-to-br from-emerald-50 to-white p-5 shadow-sm">
            <p class="text-xs font-bold uppercase tracking-wider text-emerald-700">Publiée pour tous les postes</p>
            <p class="mt-2 text-3xl font-extrabold text-slate-900">{{ $generale ? $generale->version : 'Aucune' }}</p>
            @if ($generale)
                <p class="mt-1 text-xs text-slate-500">Publiée le {{ AgentVersion::heure($generale->publiee_generale_at) }}</p>
            @endif
        </div>
        <div class="rounded-2xl border border-amber-100 bg-gradient-to-br from-amber-50 to-white p-5 shadow-sm">
            <p class="text-xs font-bold uppercase tracking-wider text-amber-700">En test sur des postes pilotes</p>
            <p class="mt-2 text-3xl font-extrabold text-slate-900">{{ $pilote ? $pilote->version : 'Aucune' }}</p>
            <p class="mt-1 text-xs text-slate-500">
                @if ($pilote) {{ $postes->whereIn('id', $pilote->pilotes ?? [])->pluck('nom_poste')->join(', ') ?: '—' }}
                @else Aucun test en cours. @endif
            </p>
        </div>
    </div>

    {{-- Envoi (Compacté) --}}
    <x-parametres.section titre="Envoyer un nouveau paquet" icone="upload-cloud" aide="Fichier .zip fabriqué sur le PC du développeur. Le serveur calcule son empreinte SHA-256. Il reste en brouillon tant qu'il n'est pas publié.">
        <form method="POST" action="{{ route('parametres.versions.televerser') }}" enctype="multipart/form-data" x-data="{ fichier: '' }" class="grid gap-4 md:grid-cols-[10rem_minmax(0,1fr)_minmax(0,1fr)_auto] md:items-end">
            @csrf
            <div>
                <label for="version" class="{{ $label }}">Version</label>
                <input type="text" id="version" name="version" maxlength="32" placeholder="2.2.0" value="{{ old('version') }}" required class="{{ $champ }}">
            </div>
            <div>
                <label class="{{ $label }}">Paquet (.zip)</label>
                <label class="flex h-11 cursor-pointer items-center gap-2 rounded-xl border-2 border-dashed border-slate-300 bg-slate-50 px-3.5 text-sm text-slate-600 hover:border-violet-400">
                    <x-icone nom="upload-cloud" classe="h-5 w-5 shrink-0 text-slate-400" />
                    <span class="truncate" x-text="fichier || 'Choisir le fichier…'"></span>
                    <input type="file" name="paquet" accept=".zip" required class="hidden" @change="fichier = $event.target.files[0] ? $event.target.files[0].name : ''">
                </label>
            </div>
            <div>
                <label for="notes" class="{{ $label }}">Notes <span class="font-normal text-slate-400">(facultatif)</span></label>
                <input type="text" id="notes" name="notes" maxlength="500" value="{{ old('notes') }}" class="{{ $champ }}">
            </div>
            <button type="submit" class="inline-flex h-11 items-center justify-center rounded-xl bg-violet-600 px-5 text-sm font-semibold text-white shadow-sm hover:bg-violet-700">Envoyer</button>
        </form>
    </x-parametres.section>

    {{-- Barre de filtres (Ergonomique, juste au-dessus de la liste) --}}
    <div id="liste-versions" class="space-y-3">
        <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
            <h2 class="text-lg font-bold text-slate-900">Historique des versions</h2>
            <p class="text-sm text-slate-500">Sélectionnez un filtre pour voir les versions correspondantes.</p>
        </div>
        
        <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-5">
            {{-- Toutes --}}
            <a href="{{ route('parametres.versions.index') }}#liste-versions" 
               class="group flex flex-col rounded-2xl border p-3 shadow-sm transition hover:-translate-y-0.5 hover:shadow-md {{ !$filtre ? 'border-violet-400 bg-violet-50 ring-2 ring-violet-200' : 'border-slate-200 bg-white' }}">
                <div class="flex items-center justify-between">
                    <p class="text-sm font-bold text-slate-900 group-hover:text-violet-700">Toutes</p>
                    <span class="text-lg font-extrabold text-slate-900">{{ array_sum($stats) }}</span>
                </div>
                <p class="mt-1 text-xs text-slate-500">Afficher toutes les versions</p>
            </a>

            {{-- Brouillons --}}
            <a href="{{ route('parametres.versions.index', ['statut' => 'brouillon']) }}#liste-versions" 
               class="group flex flex-col rounded-2xl border p-3 shadow-sm transition hover:-translate-y-0.5 hover:shadow-md {{ $filtre === 'brouillon' ? 'border-slate-400 bg-slate-50 ring-2 ring-slate-200' : 'border-slate-200 bg-white' }}">
                <div class="flex items-center justify-between">
                    <p class="text-sm font-bold text-slate-900 group-hover:text-slate-700">Brouillons</p>
                    <span class="text-lg font-extrabold text-slate-900">{{ $stats['brouillon'] }}</span>
                </div>
                <p class="mt-1 text-xs text-slate-500">Envoyées, pas encore testées</p>
            </a>

            {{-- Pilotes --}}
            <a href="{{ route('parametres.versions.index', ['statut' => 'pilote']) }}#liste-versions" 
               class="group flex flex-col rounded-2xl border p-3 shadow-sm transition hover:-translate-y-0.5 hover:shadow-md {{ $filtre === 'pilote' ? 'border-amber-400 bg-amber-50 ring-2 ring-amber-200' : 'border-slate-200 bg-white' }}">
                <div class="flex items-center justify-between">
                    <p class="text-sm font-bold text-amber-700 group-hover:text-amber-800">Pilotes</p>
                    <span class="text-lg font-extrabold text-amber-700">{{ $stats['pilote'] }}</span>
                </div>
                <p class="mt-1 text-xs text-slate-500">En test sur 1 ou 2 postes</p>
            </a>

            {{-- Publiées --}}
            <a href="{{ route('parametres.versions.index', ['statut' => 'general']) }}#liste-versions" 
               class="group flex flex-col rounded-2xl border p-3 shadow-sm transition hover:-translate-y-0.5 hover:shadow-md {{ $filtre === 'general' ? 'border-emerald-400 bg-emerald-50 ring-2 ring-emerald-200' : 'border-slate-200 bg-white' }}">
                <div class="flex items-center justify-between">
                    <p class="text-sm font-bold text-emerald-700 group-hover:text-emerald-800">Publiées</p>
                    <span class="text-lg font-extrabold text-emerald-700">{{ $stats['general'] }}</span>
                </div>
                <p class="mt-1 text-xs text-slate-500">Utilisées par tous les postes</p>
            </a>

            {{-- Archivées --}}
            <a href="{{ route('parametres.versions.index', ['statut' => 'archivee']) }}#liste-versions" 
               class="group flex flex-col rounded-2xl border p-3 shadow-sm transition hover:-translate-y-0.5 hover:shadow-md {{ $filtre === 'archivee' ? 'border-sky-400 bg-sky-50 ring-2 ring-sky-200' : 'border-slate-200 bg-white' }}">
                <div class="flex items-center justify-between">
                    <p class="text-sm font-bold text-sky-700 group-hover:text-sky-800">Archivées</p>
                    <span class="text-lg font-extrabold text-sky-700">{{ $stats['archivee'] }}</span>
                </div>
                <p class="mt-1 text-xs text-slate-500">Anciennes versions (retour arrière)</p>
            </a>
        </div>
    </div>

    {{-- Liste des versions --}}
    <div class="space-y-3">
        @forelse ($versions as $v)
            @php [$lib, $couleur] = $etiquettes[$v->statut] ?? [$v->statut, 'gris']; @endphp
            <article class="rounded-xl border border-slate-200 bg-slate-50/50 p-4">
                <div class="flex flex-col gap-3 lg:flex-row lg:items-start lg:justify-between">
                    <div class="min-w-0 space-y-1.5">
                        <div class="flex flex-wrap items-center gap-2">
                            <span class="text-lg font-extrabold text-slate-900">{{ $v->version }}</span>
                            <x-parametres.badge :couleur="$couleur">{{ $lib }}</x-parametres.badge>
                            <span class="text-xs text-slate-500">{{ AgentVersion::tailleLisible((int) $v->taille) }} · envoyée le {{ AgentVersion::heure($v->created_at) }}</span>
                        </div>
                        @if ($v->notes) <p class="text-sm text-slate-600">{{ $v->notes }}</p> @endif
                        @if ($v->statut === 'pilote')
                            <p class="text-xs text-slate-500">Postes pilotes : {{ $postes->whereIn('id', $v->pilotes ?? [])->pluck('nom_poste')->join(', ') ?: '—' }}</p>
                        @endif
                        <button type="button" @click="cmCopy(@js($v->sha256))" title="Copier l'empreinte SHA-256" class="flex max-w-full items-center gap-1.5 text-left font-mono text-[11px] text-slate-400 hover:text-slate-600">
                            <x-icone nom="copy" classe="h-3.5 w-3.5 shrink-0" /><span class="truncate">{{ $v->sha256 }}</span>
                        </button>
                        @if (!$v->fichierExiste())
                            <p class="text-xs font-semibold text-rose-600">Fichier introuvable sur le serveur.</p>
                        @endif
                    </div>

                    <div class="flex flex-wrap items-start gap-2 lg:justify-end">
                        {{-- Télécharger --}}
                        <a href="{{ route('parametres.versions.telecharger', $v) }}" class="{{ $btn }} border border-slate-300 bg-white text-slate-700 hover:bg-slate-50">
                            <x-icone nom="download" classe="h-4 w-4" /> Télécharger
                        </a>

                        {{-- Notes --}}
                        @if($v->notes)
                            <button type="button" onclick="alert('Notes de la version {{ $v->version }} :\n\n{{ addslashes($v->notes) }}')" class="{{ $btn }} border border-slate-300 bg-white text-slate-700 hover:bg-slate-50">
                                <x-icone nom="eye" classe="h-4 w-4" /> Notes
                            </button>
                        @endif

                        {{-- Tester sur des pilotes --}}
                        <details class="relative">
                            <summary class="{{ $btn }} cursor-pointer list-none border border-slate-300 bg-white text-slate-700 hover:bg-slate-50">Tester sur des pilotes…</summary>
                            <form method="POST" action="{{ route('parametres.versions.pilote', $v) }}" class="mt-2 space-y-1.5 rounded-xl border border-slate-200 bg-white p-3 shadow-lg lg:absolute lg:right-0 lg:z-10 lg:w-60">
                                @csrf
                                @foreach ($postes as $p)
                                    <label class="flex items-center gap-2 text-sm text-slate-700">
                                        <input type="checkbox" name="postes[]" value="{{ $p->id }}" class="h-4 w-4 rounded border-slate-300 text-violet-600 focus:ring-violet-500" {{ in_array($p->id, $v->pilotes ?? [], true) ? 'checked' : '' }}> {{ $p->nom_poste }}
                                    </label>
                                @endforeach
                                <button type="submit" class="{{ $btn }} mt-1 w-full bg-violet-600 text-white hover:bg-violet-700">Publier sur ces postes</button>
                            </form>
                        </details>

                        {{-- Publier / Rétablir --}}
                        @if ($v->statut !== 'general')
                            <form method="POST" action="{{ route('parametres.versions.general', $v) }}" onsubmit="return confirm('Publier la version {{ $v->version }} pour TOUS les postes ?')">
                                @csrf
                                <button type="submit" class="{{ $btn }} bg-emerald-600 text-white hover:bg-emerald-700">{{ $v->statut === 'archivee' ? 'Rétablir' : 'Publier pour tous' }}</button>
                            </form>
                        @endif

                        {{-- Retirer / Supprimer --}}
                        @if (in_array($v->statut, ['pilote', 'general'], true))
                            <form method="POST" action="{{ route('parametres.versions.depublier', $v) }}" onsubmit="return confirm('Retirer cette version ?')">
                                @csrf
                                <button type="submit" class="{{ $btn }} border border-slate-300 bg-white text-slate-700 hover:bg-slate-50">Retirer</button>
                            </form>
                        @else
                            <form method="POST" action="{{ route('parametres.versions.supprimer', $v) }}" onsubmit="return confirm('Supprimer définitivement la version {{ $v->version }} ?')">
                                @csrf @method('DELETE')
                                <button type="submit" class="{{ $btn }} border border-rose-200 bg-white text-rose-600 hover:bg-rose-50">
                                    <x-icone nom="trash" classe="h-4 w-4" /> Supprimer
                                </button>
                            </form>
                        @endif
                    </div>
                </div>
            </article>
        @empty
            <p class="rounded-xl border border-dashed border-slate-300 p-6 text-center text-sm text-slate-500">Aucune version ne correspond à ce filtre.</p>
        @endforelse
    </div>

    {{-- Derniers résultats (Reste en bas, c'est un journal technique) --}}
    <x-parametres.section titre="Derniers résultats de mise à jour" icone="clock" aide="Les 30 derniers retours envoyés par les postes.">
        <div class="overflow-x-auto rounded-xl border border-slate-200">
            <table class="min-w-full divide-y divide-slate-200 text-sm">
                <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">
                    <tr><th class="px-4 py-2.5">Heure</th><th class="px-4 py-2.5">Poste</th><th class="px-4 py-2.5">Version</th><th class="px-4 py-2.5">Résultat</th><th class="px-4 py-2.5">Message</th></tr>
                </thead>
                <tbody class="divide-y divide-slate-100 bg-white">
                @forelse ($historique as $h)
                    <tr>
                        <td class="whitespace-nowrap px-4 py-2.5 text-slate-600">{{ AgentVersion::heure($h->created_at, 'd/m/Y H:i:s') }}</td>
                        <td class="px-4 py-2.5 font-medium text-slate-900">{{ $h->poste->nom_poste ?? '—' }}</td>
                        <td class="px-4 py-2.5">{{ $h->version }}</td>
                        <td class="px-4 py-2.5"><x-parametres.badge :couleur="$h->succes ? 'vert' : 'rouge'">{{ $h->succes ? 'Réussie' : 'Échouée' }}</x-parametres.badge></td>
                        <td class="px-4 py-2.5 text-slate-500">{{ $h->message }}</td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="px-4 py-6 text-center text-slate-400">Aucune mise à jour effectuée pour l'instant.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </x-parametres.section>
</div>
@endsection