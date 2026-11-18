@props([
    'user'  => null,
    'taille' => 'md', // sm | md | lg | xl
    'rond'   => true,
])

@php
    $tailleClasses = match ($taille) {
        'sm'  => 'h-8 w-8 text-xs',
        'lg'  => 'h-14 w-14 text-lg',
        'xl'  => 'h-20 w-20 text-2xl',
        default => 'h-10 w-10 text-sm',
    };
    $forme = $rond ? 'rounded-full' : 'rounded-lg';

    $url        = $user?->avatarUrl();
    $initiales  = $user?->initiales() ?? '?';
    $couleur    = $user?->couleurAvatar() ?? '#7c3aed';
    $nomComplet = $user?->name ?? 'Utilisateur inconnu';
@endphp

@if ($url)
    <img src="{{ $url }}"
         alt="{{ $nomComplet }}"
         class="shrink-0 object-cover ring-1 ring-slate-200 {{ $tailleClasses }} {{ $forme }}"
         loading="lazy">
@else
    <span class="inline-flex shrink-0 items-center justify-center font-bold text-white ring-1 ring-black/5 {{ $tailleClasses }} {{ $forme }}"
          style="background-color: {{ $couleur }};"
          aria-label="{{ $nomComplet }}"
          title="{{ $nomComplet }}">
        {{ $initiales }}
    </span>
@endif