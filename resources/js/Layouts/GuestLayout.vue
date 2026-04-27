<script setup>
import ApplicationLogo from '@/Components/ApplicationLogo.vue';
import { Link } from '@inertiajs/vue3';
</script>

<template>
    <!-- 
    <div class="min-h-screen flex flex-col sm:justify-center items-center pt-6 sm:pt-0 bg-gray-100">
      -->
        
    <div  class="absolute inset-0 bg-cover bg-center bg-no-repeat min-h-screen flex flex-col sm:justify-center items-center pt-6 sm:pt-0"
                style="background-image: url('/images/vista_1-min.png')">

<!-- 
        <div>
            <Link href="/">
                <p class="text-3xl font-bold">Vantage</p>
            </Link>
        </div>
-->

        <div
            class="w-full sm:max-w-md mt-6 px-6 py-4 bg-base-100 shadow-md overflow-hidden sm:rounded-lg"
        >
            <slot />
        </div>
    </div>
</template>
