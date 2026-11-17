@extends('layouts.cyber-manager', ['title' => 'Mode production — Cyber Manager'])

@section('content')
<div class="mx-auto max-w-3xl space-y-6">
    <div>
        <h1 class="text-2xl font-semibold tracking-tight text-slate-900">Mode production</h1>
        <p class="mt-1 text-sm text-slate-500">Protège le logiciel pour une utilisation réelle : les messages d'erreur techniques sont masqués et les journaux allégés.</p>
    </div>

    <x-flash />

    <section class="rounded-2xl border bg-white p-5 shadow-sm sm:p-6 {{ $etat['production'] ? 'border-emerald-200' : 'border-amber-200' }}">
        <p class="text-sm font-semibold {{ $etat['production'] ? 'text-emerald-700' : 'text-amber-800' }}">
            {{ $etat['production'] ? '✔ Le logiciel est en mode production.' : '⚠ Le logiciel est en mode test.' }}
        </p>
        <p class="mt-1 text-xs text-slate-500">Environnement : <code class="font-mono">{{ $etat['env'] }}</code> · Détails d'erreur : {{ $etat['debug'] ? 'visibles (à éviter)' : 'masqués' }}</p>

        @if (!$etat['modifiable'])
            <p class="mt-3 rounded-lg bg-rose-50 px-3 py-2 text-sm text-rose-800">Le fichier de configuration est introuvable ou protégé en écriture : le changement ne peut pas se faire d'ici. Voir docs/PRODUCTION.md.</p>
        @else
            <div class="mt-4 flex flex-col gap-2 sm:flex-row">
                @unless ($etat['production'])
                    <form method="POST" action="{{ route('parametres.production.appliquer') }}"
                          onsubmit="return confirm('Passer en mode production ? Une sauvegarde de la configuration est conservée.')">
                        @csrf <input type="hidden" name="mode" value="production">
                        <button type="submit" class="rounded-xl bg-emerald-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-emerald-700">Passer en mode production</button>
                    </form>
                @else
                    <form method="POST" action="{{ route('parametres.production.appliquer') }}"
                          onsubmit="return confirm('Revenir au mode test ?')">
                        @csrf <input type="hidden" name="mode" value="test">
                        <button type="submit" class="rounded-xl border border-slate-300 bg-white px-4 py-2.5 text-sm font-semibold text-slate-800 hover:bg-slate-50">Revenir au mode test</button>
                    </form>
                @endunless
            </div>
        @endif
    </section>

    <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
        <h2 class="text-base font-bold text-slate-900">Base de données</h2>
        <p class="mt-1 text-sm text-slate-600">
            Utilisateur : <code class="font-mono">{{ $etat['bd_utilisateur'] ?: '—' }}</code>
            @if ($etat['bd_ecoute']) · écoute : <code class="font-mono">{{ $etat['bd_ecoute'] }}</code> @endif
        </p>
        @if ($etat['bd_root_sans_mdp'])
            <p class="mt-2 rounded-lg bg-amber-50 px-3 py-2 text-sm text-amber-900">
                MySQL est utilisé avec « root » sans mot de passe (réglage par défaut de XAMPP). C'est acceptable tant que MySQL n'est accessible que depuis ce PC.
                Pour le renforcer, voir <code class="font-mono">docs/PRODUCTION.md</code> (à faire avec votre technicien : ce changement coupe aussi phpMyAdmin tant qu'il n'est pas reconfiguré).
            </p>
        @else
            <p class="mt-2 text-sm text-emerald-700">✔ La base de données est protégée par un mot de passe.</p>
        @endif
    </section>
</div>
@endsection