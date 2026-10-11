{{--
    Centered card for the small auth pages (login, forgot/reset password, verify email, confirm password, 2FA),
    rebuilt from Anmelden.dc.html: sand background with a dune half-sun, paper card with the logo sign.

    Props:
      title     string       the page's h1
      subtitle  string|null  one line below the title
    Slot: the form or content of the card. Session `status` messages (e.g. "reset link sent") are shown on top.
--}}
@props(['title', 'subtitle' => null])

<div class="relative overflow-hidden px-[clamp(20px,4vw,48px)] py-[clamp(56px,7vw,104px)]">
    <svg viewBox="0 0 600 300" aria-hidden="true" focusable="false" class="absolute -right-20 -bottom-10 h-auto w-[620px] max-w-[90%]">
        <path d="M60 300 A240 240 0 0 1 540 300 Z" fill="#EADFCC"/>
    </svg>

    <div class="relative mx-auto flex max-w-[480px] flex-col gap-[18px] rounded-[32px] border border-paper-line bg-paper p-[clamp(28px,4vw,48px)] shadow-[0_30px_60px_-36px_rgba(14,43,47,0.45)]">
        <x-oasis.logo :size="48" :wordmark="false" />
        <div class="flex flex-col gap-1.5">
            <h1 class="o-display m-0 text-[40px] leading-[1.1] tracking-[-0.015em] text-night">{{ $title }}</h1>
            @if ($subtitle)
                <p class="m-0 text-[17px] text-ink-2">{{ $subtitle }}</p>
            @endif
        </div>

        @if (session('status'))
            <p role="status" class="m-0 rounded-2xl bg-dune px-[18px] py-3.5 text-base leading-normal text-ink">{{ session('status') }}</p>
        @endif

        {{ $slot }}
    </div>
</div>
