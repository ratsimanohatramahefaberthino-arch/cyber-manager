@extends('layouts.cyber-manager', ['title' => 'Enregistrement — Cyber Manager'])

@section('content')
<div class="space-y-6">

    <div>
        <h1 class="text-2xl font-semibold tracking-tight text-slate-900">Enregistrement des postes</h1>
        <p class="mt-1 text-sm text-slate-500">Le code pour ajouter un poste, le nom de chaque poste et les alertes.</p>
    </div>

    <x-flash />

    @include('enregistrement._conflits')
    @include('enregistrement._code')
    @include('enregistrement._postes')
</div>
@endsection