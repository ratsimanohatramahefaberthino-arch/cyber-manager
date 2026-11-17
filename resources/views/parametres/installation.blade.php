@extends('layouts.cyber-manager', ['title' => 'Installation — Cyber Manager'])

@section('content')
<div class="space-y-6">

    <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
        <div>
            <h1 class="text-2xl font-semibold tracking-tight text-slate-900">Installer un nouveau poste</h1>
            <p class="mt-1 text-sm text-slate-500">Quatre étapes simples, aucune compétence technique n'est nécessaire.</p>
        </div>

        @if ($nbManquants === 0)
            <span class="inline-flex items-center gap-2 self-start rounded-full border border-emerald-200 bg-emerald-50 px-3 py-1.5 text-sm font-semibold text-emerald-700">
                <x-icone nom="check" class="h-4 w-4" /> Prêt à installer un poste
            </span>
        @else
            <span class="inline-flex items-center gap-2 self-start rounded-full border border-amber-200 bg-amber-50 px-3 py-1.5 text-sm font-semibold text-amber-800">
                <x-icone nom="warning" class="h-4 w-4" />
                À compléter : {{ $nbManquants }} fichier{{ $nbManquants > 1 ? 's' : '' }} manquant{{ $nbManquants > 1 ? 's' : '' }}
            </span>
        @endif
    </div>

    <x-flash />

    @include('parametres.installation._adresse')
    @include('parametres.installation._fichiers')
    @include('parametres.installation._etapes')
    @include('parametres.installation._smartscreen')
    @include('parametres.installation._avance')
</div>
@endsection