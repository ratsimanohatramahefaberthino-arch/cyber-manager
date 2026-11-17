@php
    $libelles = [
        'disponible' => 'Disponible', 'occupe' => 'Occupé', 'suspendu' => 'Suspendu',
        'verrouille' => 'Verrouillé', 'desactive' => 'Désactivé', 'hors_ligne' => 'Hors ligne',
    ];
    $styles = [
        'disponible' => 'bg-emerald-50 text-emerald-800 ring-emerald-200',
        'occupe'     => 'bg-blue-50 text-blue-800 ring-blue-200',
        'suspendu'   => 'bg-violet-50 text-violet-800 ring-violet-200',
        'verrouille' => 'bg-amber-50 text-amber-800 ring-amber-200',
        'desactive'  => 'bg-rose-50 text-rose-800 ring-rose-200',
        'hors_ligne' => 'bg-slate-100 text-slate-600 ring-slate-200',
    ];

    $lignes = $postes->map(function ($p) use ($libelles, $styles, $fuseau) {
        $etat  = $p->etatAffiche();
        $signe = $p->dernier_heartbeat;

        return (object) [
            'poste'       => $p,
            'etat'        => $libelles[$etat] ?? $etat,
            'style'       => $styles[$etat] ?? $styles['hors_ligne'],
            'ip'          => $p->adresse_ip ?: ($p->derniere_ip_vue ?: '—'),
            'version'     => $p->agent_version ?: '—',
            'signe'       => $signe ? $signe->locale('fr')->diffForHumans() : 'Jamais',
            'signe_exact' => $signe ? $signe->timezone($fuseau)->format('d/m/Y H:i:s') : null,
            'conflit'     => $p->aConflitIdentite(),
            'texte'       => mb_strtolower($p->nom_poste . ' ' . ($p->adresse_ip ?? '') . ' ' . ($p->derniere_ip_vue ?? '')),
        ];
    });

    $nb = $postes->count();
    $nbConflits = $conflits->count();
@endphp

<section class="rounded-2xl border border-slate-200 bg-white shadow-sm"
         x-data="{
            q: '',
            textes: @js($lignes->pluck('texte')->all()),
            vu(t) { const q = this.q.trim().toLowerCase(); return q === '' || t.includes(q); },
            get aucun() { return this.textes.length > 0 && this.textes.every(t => !this.vu(t)); }
         }">

    <div class="flex flex-col gap-3 border-b border-slate-100 p-5 sm:flex-row sm:items-center sm:justify-between sm:p-6">
        <div>
            <h2 class="text-base font-bold text-slate-900">Postes enregistrés</h2>
            <p class="text-sm text-slate-500">Le nouveau nom est envoyé au poste et affiché sur son écran de verrouillage.</p>
        </div>

        <div class="flex items-center gap-2">
            @if ($nb > 5)
                <div class="relative min-w-0 flex-1 sm:w-56 sm:flex-none">
                    <x-icone nom="search" class="pointer-events-none absolute left-2.5 top-2.5 h-4 w-4 text-slate-400" />
                    <input type="search" x-model="q" placeholder="Chercher un poste ou une adresse…" aria-label="Chercher un poste"
                           class="w-full rounded-xl border-slate-300 py-2 pl-9 pr-3 text-sm focus:border-violet-500 focus:ring-violet-500">
                </div>
            @endif
            <button type="button" @click="window.location.reload()" title="Actualiser la liste" aria-label="Actualiser la liste"
                    class="shrink-0 rounded-xl border border-slate-300 p-2 text-slate-600 transition hover:bg-slate-50 hover:text-slate-900 focus:outline-none focus-visible:ring-2 focus-visible:ring-violet-500">
                <x-icone nom="refresh" class="h-5 w-5" />
            </button>
        </div>
    </div>

    @if ($nb === 0)
        <div class="px-5 py-10 text-center sm:px-6">
            <p class="text-sm text-slate-600">Aucun poste enregistré pour le moment.</p>
            <a href="{{ route('parametres.installation.index') }}"
               class="mt-3 inline-flex items-center gap-2 rounded-xl bg-violet-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-violet-700">
                <x-icone nom="upload-cloud" class="h-4 w-4" /> Installer un premier poste
            </a>
        </div>
    @else
        {{-- Grand écran : tableau --}}
        <div class="hidden overflow-x-auto xl:block">
            <table class="w-full text-left text-sm">
                <thead>
                    <tr class="border-b border-slate-100 text-xs uppercase tracking-wide text-slate-500">
                        <th scope="col" class="px-6 py-3 font-semibold">Nom</th>
                        <th scope="col" class="px-4 py-3 font-semibold">État</th>
                        <th scope="col" class="px-4 py-3 font-semibold">Adresse</th>
                        <th scope="col" class="px-4 py-3 font-semibold">Version</th>
                        <th scope="col" class="px-4 py-3 font-semibold">Dernier signe de vie</th>
                        <th scope="col" class="px-4 py-3 font-semibold">Identité</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @foreach ($lignes as $l)
                        <tr x-show="vu(@js($l->texte))" @class(['bg-rose-50/50' => $l->conflit])>
                            <td class="max-w-[16rem] px-6 py-3"><x-parametres.nom-poste :poste="$l->poste" /></td>
                            <td class="px-4 py-3">
                                <span class="rounded-full px-2.5 py-0.5 text-xs font-semibold ring-1 {{ $l->style }}">{{ $l->etat }}</span>
                            </td>
                            <td class="px-4 py-3 font-mono text-xs text-slate-600">{{ $l->ip }}</td>
                            <td class="px-4 py-3 text-xs text-slate-600">{{ $l->version }}</td>
                            <td class="px-4 py-3 text-xs text-slate-600" @if ($l->signe_exact) title="{{ $l->signe_exact }}" @endif>{{ $l->signe }}</td>
                            <td class="px-4 py-3">
                                @if ($l->conflit)
                                    <span class="rounded-full bg-rose-100 px-2.5 py-0.5 text-xs font-semibold text-rose-800 ring-1 ring-rose-200">Conflit</span>
                                @else
                                    <span class="rounded-full bg-slate-100 px-2.5 py-0.5 text-xs font-semibold text-slate-600 ring-1 ring-slate-200">Normale</span>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        {{-- Mobile, tablette et portable : cartes --}}
        <ul class="grid gap-3 p-4 sm:grid-cols-2 sm:p-5 xl:hidden">
            @foreach ($lignes as $l)
                <li x-show="vu(@js($l->texte))"
                    @class(['rounded-xl border p-4', 'border-rose-200 bg-rose-50/50' => $l->conflit, 'border-slate-200 bg-white' => !$l->conflit])>
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0 flex-1"><x-parametres.nom-poste :poste="$l->poste" /></div>
                        <span class="shrink-0 rounded-full px-2.5 py-0.5 text-xs font-semibold ring-1 {{ $l->style }}">{{ $l->etat }}</span>
                    </div>

                    <dl class="mt-3 grid grid-cols-2 gap-x-3 gap-y-2 text-xs">
                        <div>
                            <dt class="text-slate-500">Adresse</dt>
                            <dd class="font-mono text-slate-800">{{ $l->ip }}</dd>
                        </div>
                        <div>
                            <dt class="text-slate-500">Version</dt>
                            <dd class="text-slate-800">{{ $l->version }}</dd>
                        </div>
                        <div>
                            <dt class="text-slate-500">Dernier signe de vie</dt>
                            <dd class="text-slate-800" @if ($l->signe_exact) title="{{ $l->signe_exact }}" @endif>{{ $l->signe }}</dd>
                        </div>
                        <div>
                            <dt class="text-slate-500">Identité</dt>
                            <dd class="{{ $l->conflit ? 'font-semibold text-rose-700' : 'text-slate-800' }}">{{ $l->conflit ? 'Conflit' : 'Normale' }}</dd>
                        </div>
                    </dl>
                </li>
            @endforeach
        </ul>

        <p x-show="aucun" x-cloak class="px-6 pb-6 text-sm text-slate-500">Aucun poste ne correspond à votre recherche.</p>

        <div class="rounded-b-2xl border-t border-slate-100 bg-slate-50 px-5 py-3 text-xs text-slate-600 sm:px-6">
            <strong class="font-semibold text-slate-800">{{ $nb }} poste{{ $nb > 1 ? 's' : '' }}</strong>
            · {{ $nbEnLigne }} en ligne
            · {{ $nbConflits === 0 ? 'aucun conflit' : $nbConflits . ' conflit' . ($nbConflits > 1 ? 's' : '') }}
        </div>
    @endif
</section>