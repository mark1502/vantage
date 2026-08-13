<script setup>
import VantageMark from '@/Components/VantageMark.vue';
import PageBackdrop from '@/Components/PageBackdrop.vue';
import { Link } from '@inertiajs/vue3';
</script>

<template>
    <div class="relative min-h-screen overflow-hidden bg-slate-50 text-slate-900 antialiased">
        <PageBackdrop />

        <div class="relative flex min-h-screen flex-col items-center justify-center px-4 py-10 sm:px-6">
            <Link href="/" class="flex items-center gap-3">
                <VantageMark class="w-9 text-indigo-700" />
                <span class="text-2xl font-bold tracking-tight text-slate-900">Vantage</span>
            </Link>

            <div
                class="mt-7 w-full sm:max-w-md overflow-hidden rounded-2xl border border-slate-200 bg-white px-6 py-6 shadow-xl shadow-slate-900/10 ring-1 ring-slate-900/5 sm:px-8"
            >
                <slot />
            </div>

            <Link href="/" class="mt-6 text-sm font-medium text-slate-500 transition hover:text-slate-800">
                &larr; Back to home
            </Link>
        </div>
    </div>
</template>
