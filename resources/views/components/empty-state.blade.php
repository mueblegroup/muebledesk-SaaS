@props([
    'title' => 'No records found',
    'message' => 'Try adjusting your filters or create a new record.',
    'actionUrl' => null,
    'actionLabel' => null,
])

<div class="rounded-3xl border border-dashed border-slate-300 bg-white/70 px-6 py-12 text-center dark:border-slate-700 dark:bg-slate-900/60">
    <div class="mx-auto flex h-14 w-14 items-center justify-center rounded-2xl bg-indigo-50 text-xl font-black text-indigo-600 dark:bg-indigo-950/60 dark:text-indigo-300">+</div>
    <h3 class="mt-4 text-lg font-extrabold text-slate-950 dark:text-white">{{ $title }}</h3>
    <p class="mx-auto mt-2 max-w-xl text-sm text-slate-500 dark:text-slate-400">{{ $message }}</p>
    @if ($actionUrl && $actionLabel)
        <div class="mt-6">
            <a href="{{ $actionUrl }}" class="btn-primary">{{ $actionLabel }}</a>
        </div>
    @endif
</div>
