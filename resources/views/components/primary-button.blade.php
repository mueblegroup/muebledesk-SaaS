<button {{ $attributes->merge([
    'type' => 'submit',
    'class' => 'inline-flex min-h-12 items-center justify-center rounded-2xl border border-transparent bg-gradient-to-r from-indigo-600 to-violet-600 px-5 py-3 text-sm font-extrabold text-white shadow-lg shadow-indigo-500/20 transition duration-200 hover:-translate-y-0.5 hover:from-indigo-500 hover:to-violet-500 hover:shadow-xl focus:outline-none focus:ring-4 focus:ring-indigo-500/20 disabled:cursor-not-allowed disabled:opacity-60 disabled:hover:translate-y-0'
]) }}>
    {{ $slot }}
</button>
