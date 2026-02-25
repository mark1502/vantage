<template>

    <Head title="AdminMenu" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="font-semibold text-xl text-base-content leading-tight">
                Admin Menu
            </h2>
        </template>

        <div class="py-3">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                <div class="bg-base-300 p-4 min-h-dvh sm:rounded-lg">
                    <p class="text-3xl text-base-content text-center mt-8">Administrators Menu</p>
                    <table class="mx-auto mt-12">
                        <tbody>
                            <tr>
                                <td class="py-3 pr-6">
                                    <Link class="btn btn-primary text-white px-8 w-40" :href="route('firm.edit')">Firm Settings</Link>
                                </td>
                                <td class="py-3 text-base-content">Manage firm profile and document storage settings</td>
                            </tr>
                            <tr>
                                <td class="py-3 pr-6">
                                    <Link class="btn btn-primary text-white px-8 w-40" href="/users">User List</Link>
                                </td>
                                <td class="py-3 text-base-content">Manage user accounts, roles, and permissions</td>
                            </tr>
                            <tr>
                                <td class="py-3 pr-6">
                                    <Link class="btn btn-primary text-white px-8 w-40" href="/filetypes">File Types</Link>
                                </td>
                                <td class="py-3 text-base-content">Configure file types and their available folders</td>
                            </tr>
                            <tr>
                                <td class="py-3 pr-6">
                                    <Link class="btn btn-primary text-white px-8 w-40" href="/Entrytypes">Entry Types</Link>
                                </td>
                                <td class="py-3 text-base-content">Manage entry types within each folder</td>
                            </tr>
                            <!-- <tr>
                                <td class="py-3 pr-6">
                                    <Link class="btn btn-primary text-white px-8 w-40" href="/folders">Folder Types</Link>
                                </td>
                                <td class="py-3 text-base-content">Manage folder categories for organizing entries</td>
                            </tr> -->

                            <!-- <tr>
                                <td class="py-3 pr-6">
                                    <Link class="btn btn-primary text-white px-8 w-40" href="/test_form">Test Form</Link>
                                </td>
                                <td class="py-3 text-base-content">Test form for development purposes</td>
                            </tr> -->
                        </tbody>
                    </table>
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
