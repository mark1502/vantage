<template>

    <Head title="AdminMenu" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                Admin Menu
            </h2>
        </template>

        <div class="py-3 min-h-screen">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 min-h-screen">
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <p class="text-3xl text-center mt-8">Administrators Menu</p>
                    <div class="mx-auto text-center">
                        <Link class="btn btn-primary text-white mb-5 mt-12 px-8" href="/users">User List</Link>
                    </div>
                    <div class="mx-auto text-center">
                        <Link class="btn btn-primary text-white mb-5 mt-12 px-8" href="/casefiletypes">File Types</Link>
                    </div>
                    <div>
                        <Link class="btn btn-primary mt-10" href="/Entrytypes" >Entrytypes</Link>
                    </div>

                    <div class="mx-auto text-center">
                        <Link class="btn btn-primary text-white mb-5 mt-12 px-8" href="/folders">Folder List</Link>
                    </div>
                    <div class="mx-auto text-center">
                        <Link class="btn btn-primary text-white mb-5 mt-12 px-8" href="/test_form">Test Form</Link>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>

<script setup>
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout.vue";
import InputError from "@/Components/InputError.vue";
import InputLabel from "@/Components/InputLabel.vue";
import PrimaryButton from "@/Components/PrimaryButton.vue";
import TextInput from "@/Components/TextInput.vue";
import NavLink from "@/Components/NavLink.vue";

import { Head, Link, useForm } from "@inertiajs/vue3";
import { reactive, computed } from "vue";
import { EMPTY_ARR } from "@vue/shared";


// function handleKey(e) {
//     if (e.key === 'Escape') {
//         Inertia.get('/users?page=' + form3.current_page + '&show=' + form3.show)
//     }
// }

// onMounted(() => document.addEventListener('keydown', handleKey));
// onUnmounted(() => document.removeEventListener('keydown', handleKey));


</script>
