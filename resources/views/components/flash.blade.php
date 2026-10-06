@if(session('success'))
    <div x-data="{ show: true }" x-show="show" x-transition
         class="mb-4 flex items-start gap-3 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">
        <x-icone nom="check" class="mt-0.5 h-5 w-5 shrink-0" />
        <p class="flex-1">{{ session('success') }}</p>
        <button type="button" @click="show = false" class="text-emerald-600 hover:text-emerald-800">
            <x-icone nom="x" class="h-4 w-4" />
        </button>
    </div>
@endif

@foreach($errors->get('global') as $message)
    <div x-data="{ show: true }" x-show="show" x-transition
         class="mb-4 flex items-start gap-3 rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800">
        <x-icone nom="warning" class="mt-0.5 h-5 w-5 shrink-0" />
        <p class="flex-1">{{ $message }}</p>
        <button type="button" @click="show = false" class="text-rose-600 hover:text-rose-800">
            <x-icone nom="x" class="h-4 w-4" />
        </button>
    </div>
@endforeach