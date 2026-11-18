@extends('layouts.cyber-manager', ['title' => 'Installation — Cyber Manager'])

@section('content')
<div class="space-y-6">

    <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
        <div>
            <h1 class="text-2xl font-semibold tracking-tight text-slate-900">Installer un nouveau poste</h1>
            <p class="mt-1 text-sm text-slate-500">Un assistant vous guide étape par étape, aucune compétence technique n'est nécessaire.</p>
        </div>

        @if ($nbManquants === 0)
            <span class="inline-flex items-center gap-2 self-start rounded-full border border-emerald-200 bg-emerald-50 px-3 py-1.5 text-sm font-semibold text-emerald-700">
                <x-icone nom="check" classe="h-4 w-4" /> Prêt à installer un poste
            </span>
        @else
            <a href="#fichiers" class="inline-flex items-center gap-2 self-start rounded-full border border-amber-200 bg-amber-50 px-3 py-1.5 text-sm font-semibold text-amber-800">
                <x-icone nom="warning" classe="h-4 w-4" />
                À compléter : {{ $nbManquants }} fichier{{ $nbManquants > 1 ? 's' : '' }} manquant{{ $nbManquants > 1 ? 's' : '' }}
            </a>
        @endif
    </div>

    {{-- Compteur de postes (Étape 9) --}}
    <div class="grid gap-3 sm:grid-cols-2">
        <div class="rounded-2xl border border-violet-100 bg-gradient-to-br from-violet-50 to-white p-4 shadow-sm">
            <p class="text-xs font-bold uppercase tracking-wider text-violet-700">Postes déjà installés</p>
            <p class="mt-1 text-2xl font-extrabold text-slate-900">{{ $totalPostes }}</p>
        </div>
        <div class="rounded-2xl border border-emerald-100 bg-gradient-to-br from-emerald-50 to-white p-4 shadow-sm">
            <p class="text-xs font-bold uppercase tracking-wider text-emerald-700">Installés cette semaine</p>
            <p class="mt-1 text-2xl font-extrabold text-slate-900">+{{ $postesCetteSemaine }}</p>
        </div>
    </div>

    <x-flash />

    @include('parametres.installation._adresse')
    @include('parametres.installation._etapes')
    @include('parametres.installation._fichiers')
    @include('parametres.installation._smartscreen')
    @include('parametres.installation._avance')
</div>
@endsection