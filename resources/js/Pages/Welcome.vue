<script setup>
import { Head, Link } from '@inertiajs/vue3';
import GuestLayout from '@/Layouts/GuestLayout.vue';

defineProps({
    canLogin: {
        type: Boolean,
    },
    canRegister: {
        type: Boolean,
    },
});
</script>

<template>
    <Head title="Welcome" />

    <div class="min-h-screen bg-gray-50">
        <!-- Header with white background -->
        <header class="bg-gray-200 shadow-sm">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-3">
                <nav class="flex place-content-end">
                    <div class="flex gap-8">
                        <Link
                            v-if="canLogin"
                            :href="route('login')"
                            class="font-semibold text-gray-600 hover:text-gray-900 focus:outline focus:outline-2 focus:rounded-sm focus:outline-red-500"
                        >
                            Log in
                        </Link>
                        <Link
                            v-if="canRegister"
                            :href="route('register')"
                            class="ml-4 font-semibold text-gray-600 hover:text-gray-900 focus:outline focus:outline-2 focus:rounded-sm focus:outline-red-500"
                        >
                            Register
                        </Link>
                    </div>
                </nav>
            </div>
        </header>

        <!-- Main content area with vista background -->
        <div class="relative flex-1">
            <!-- Vista background -->
            <div 
                class="absolute inset-0 bg-cover bg-center bg-no-repeat"
                style="background-image: url('/images/vista_1-min.png')"
            ></div>

            <!-- Content -->
            <div class="relative z-10 flex items-center justify-center min-h-[calc(100vh-73px)]">
                <div class="bg-gray-300 rounded-lg px-6 py-4 max-w-4xl mx-4">
                    <h1 class="text-6xl font-black text-indigo-800 text-center mb-2">
                        Vantage
                    </h1>
                    <h1 class="text-5xl font-normal text-indigo-800 text-center mb-2">
                        Case Management
                    </h1>
                    <h1 class="text-6xl font-bold text-amber-500 text-center mb-2">
                        
                    </h1>
                </div>
            </div>
        </div>
    </div>
</template>