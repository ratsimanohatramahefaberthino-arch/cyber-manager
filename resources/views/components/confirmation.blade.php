@props([
    'id',                    // identifiant unique Alpine
    'titre' => 'Confirmer',
    'message' => '',
    'bouton' => 'Confirmer',
    'danger' => false,
])

<div x-show="{{ $id }}.ouvert" x-cloak class="fixed inset-0 z-[80] flex items-center justify-center p-4">
    <div class="absolute inset-0 bg-slate-900/60" @click="{{ $id }}.ouvert = false" x-transition.opacity></div>
    <div x-show="{{ $id }}.ouvert" x-transition
         class="relative w-full max-w-lg overflow-hidden rounded-2xl bg-white shadow-2xl">
        <div class="flex items-start gap-3 border-b border-slate-100 px-6 pt-5 pb-4">
            <span class="flex h-12 w-12 shrink-0 items-center justify-center rounded-full {{ $danger ? 'bg-rose-100' : 'bg-amber-100' }}">
                <x-icone nom="warning" class="h-6 w-6 {{ $danger ? 'text-rose-600' : 'text-amber-600' }}" />
            </span>
            <div class="min-w-0">
                <h2 class="text-lg font-semibold text-slate-900">{{ $titre }}</h2>
                <div class="mt-1 text-base text-slate-600">{!! $message !!}</div>
            </div>
        </div>

        <div class="flex items-center justify-end gap-3 border-t border-slate-100 bg-slate-50 px-6 py-4">
            <button type="button" @click="{{ $id }}.ouvert = false"
                    class="inline-flex h-11 items-center rounded-lg border border-slate-200 bg-white px-5 text-base font-medium text-slate-700 hover:bg-slate-50">
                Annuler
            </button>
            <button type="button" @click="if ({{ $id }}.onConfirm) { {{ $id }}.onConfirm(); } {{ $id }}.ouvert = false;"
                    class="inline-flex h-11 items-center gap-2 rounded-lg px-5 text-base font-medium text-white shadow-sm {{ $danger ? 'bg-rose-600 hover:bg-rose-500' : 'bg-slate-900 hover:bg-slate-700' }}">
                <x-icone nom="{{ $danger ? 'trash' : 'check' }}" class="h-4 w-4" />
                {{ $bouton }}
            </button>
        </div>
    </div>
</div>