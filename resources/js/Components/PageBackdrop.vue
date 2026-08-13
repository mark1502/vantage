<template>
    <div class="pointer-events-none absolute inset-0 overflow-hidden" aria-hidden="true">
        <div
            class="absolute inset-0 opacity-[0.55]"
            style="
                background-image:
                    linear-gradient(to right, rgb(226 232 240) 1px, transparent 1px),
                    linear-gradient(to bottom, rgb(226 232 240) 1px, transparent 1px);
                background-size: 56px 56px;
                mask-image: radial-gradient(ellipse 80% 60% at 50% 0%, black 40%, transparent 100%);
                -webkit-mask-image: radial-gradient(ellipse 80% 60% at 50% 0%, black 40%, transparent 100%);
            "
        ></div>
        <div
            class="absolute -top-40 left-1/2 h-[38rem] w-[70rem] -translate-x-1/2 rounded-full opacity-40 blur-3xl"
            style="background: radial-gradient(closest-side, rgb(199 210 254), transparent)"
        ></div>
    </div>
</template>
