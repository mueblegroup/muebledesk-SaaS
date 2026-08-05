@props(['value'])

<label {{ $attributes->merge(['class' => 'mb-2 block text-sm font-extrabold tracking-tight text-slate-700 dark:text-slate-200']) }}>
    {{ $value ?? $slot }}
</label>
