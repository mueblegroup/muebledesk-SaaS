@props([
    'url',
    'title',
    'message',
    'phone' => null,
    'email' => null,
    'countryCode' => null,
])

@php
    $digits = preg_replace('/\D+/', '', (string) $phone);
    $countryCode = strtoupper((string) $countryCode);
    $dialCodes = ['MY' => '60', 'SG' => '65'];
    if ($digits !== '' && str_starts_with($digits, '0') && isset($dialCodes[$countryCode])) {
        $digits = $dialCodes[$countryCode].ltrim($digits, '0');
    }

    $shareText = trim($message."\n\n".$url);
    $encodedText = rawurlencode($shareText);
    $encodedTitle = rawurlencode($title);
    $encodedUrl = rawurlencode($url);
@endphp

<div
    x-data="{
        open: false,
        async nativeShare() {
            if (navigator.share) {
                try {
                    await navigator.share({ title: @js($title), text: @js($message), url: @js($url) });
                    this.open = false;
                    return;
                } catch (error) {
                    if (error && error.name === 'AbortError') return;
                }
            }
            await this.copyLink();
        },
        async copyLink() {
            try {
                await navigator.clipboard.writeText(@js($url));
                window.dispatchEvent(new CustomEvent('notify', { detail: 'Share link copied.' }));
            } catch (error) {
                window.prompt('Copy this share link:', @js($url));
            }
            this.open = false;
        }
    }"
    class="relative inline-flex"
    @click.outside="open = false"
    @keydown.escape.window="open = false"
>
    <button type="button" @click="open = !open" class="btn-secondary" aria-haspopup="true" :aria-expanded="open.toString()">
        Share
    </button>

    <div
        x-cloak
        x-show="open"
        x-transition.origin.top.right
        class="absolute right-0 top-full z-40 mt-2 w-56 overflow-hidden rounded-2xl border border-slate-200 bg-white p-2 shadow-xl dark:border-slate-700 dark:bg-slate-900"
    >
        @if($digits !== '')
            <a href="https://wa.me/{{ $digits }}?text={{ $encodedText }}" target="_blank" rel="noopener noreferrer" class="block rounded-xl px-3 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-100 dark:text-slate-200 dark:hover:bg-slate-800">
                WhatsApp client
            </a>
        @else
            <span class="block rounded-xl px-3 py-2 text-sm text-slate-400">WhatsApp · no client phone</span>
        @endif

        @if($email)
            <a href="mailto:{{ $email }}?subject={{ $encodedTitle }}&body={{ $encodedText }}" class="block rounded-xl px-3 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-100 dark:text-slate-200 dark:hover:bg-slate-800">
                Email client
            </a>
        @else
            <a href="mailto:?subject={{ $encodedTitle }}&body={{ $encodedText }}" class="block rounded-xl px-3 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-100 dark:text-slate-200 dark:hover:bg-slate-800">
                Email
            </a>
        @endif

        <a href="https://t.me/share/url?url={{ $encodedUrl }}&text={{ rawurlencode($message) }}" target="_blank" rel="noopener noreferrer" class="block rounded-xl px-3 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-100 dark:text-slate-200 dark:hover:bg-slate-800">
            Telegram
        </a>
        <a href="https://www.facebook.com/sharer/sharer.php?u={{ $encodedUrl }}" target="_blank" rel="noopener noreferrer" class="block rounded-xl px-3 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-100 dark:text-slate-200 dark:hover:bg-slate-800">
            Facebook
        </a>
        <button type="button" @click="copyLink()" class="block w-full rounded-xl px-3 py-2 text-left text-sm font-semibold text-slate-700 hover:bg-slate-100 dark:text-slate-200 dark:hover:bg-slate-800">
            Copy secure link
        </button>
        <button type="button" @click="nativeShare()" class="block w-full rounded-xl px-3 py-2 text-left text-sm font-semibold text-slate-700 hover:bg-slate-100 dark:text-slate-200 dark:hover:bg-slate-800">
            More sharing options
        </button>

        <p class="px-3 pb-1 pt-2 text-[11px] leading-4 text-slate-400">Secure link expires in 30 days.</p>
    </div>
</div>
