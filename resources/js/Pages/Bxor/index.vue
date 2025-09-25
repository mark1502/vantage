<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head } from '@inertiajs/vue3';

</script>
<template>
    <Head title="BXORer" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-gray-800" >
                BXORer
            </h2>
        </template>

        <div class="py-12">
            <div class="mx-auto max-w-7xl sm:px-6 lg:px-8">
                <div class="overflow-hidden bg-white shadow-xs sm:rounded-lg" >
                    <div class="p-6 text-gray-900">
                        <button type = "button" class="btn btn-sm btn-primary mr-4" @click="bxit()">BXOR</button>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
