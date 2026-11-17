@props(['nom', 'label'])

<label {{ $attributes->merge(['class' => 'flex cursor-pointer items-center gap-3 rounded-xl border border-slate-200 bg-white p-2 pr-3 transition hover:border-slate-300']) }}>
    <input type="color" name="{{ $nom }}" x-model="form.{{ $nom }}"
           class="h-9 w-9 shrink-0 cursor-pointer appearance-none rounded-md border-0 bg-transparent p-0 [&::-webkit-color-swatch-wrapper]:p-0 [&::-webkit-color-swatch]:rounded-md [&::-webkit-color-swatch]:border [&::-webkit-color-swatch]:border-slate-200 [&::-moz-color-swatch]:rounded-md [&::-moz-color-swatch]:border-slate-200">
    <span class="min-w-0 flex-1">
        <span class="block truncate text-[13px] font-medium text-slate-700">{{ $label }}</span>
        <span class="block font-mono text-[11px] uppercase text-slate-400" x-text="form.{{ $nom }}"></span>
    </span>
</label>