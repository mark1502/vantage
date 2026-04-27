<script setup>
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout.vue";
import InputError from "@/Components/InputError.vue";
import InputLabel from "@/Components/InputLabel.vue";
import TextInput from "@/Components/TextInput.vue";

import { Head, Link, useForm, router } from "@inertiajs/vue3";
import { onMounted, onUnmounted } from "vue";

const props = defineProps({
    firm: Object,
});

const form = useForm({
    name: props.firm.name ?? '',
    address: props.firm.address ?? '',
    phone: props.firm.phone ?? '',
    email: props.firm.email ?? '',
    document_base_path: props.firm.document_base_path ?? '',
});

function submitForm() {
    form.put(route('firm.update'));
}

function handleEsc(e) {
    if (e.key === 'Escape') {
        router.get(route('adminmenu'));
    }
}

onMounted(() => document.addEventListener('keydown', handleEsc));
onUnmounted(() => document.removeEventListener('keydown', handleEsc));
</script>

<template>
    <Head title="Firm Settings" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="font-normal text-sm text-base-content leading-tight ml-2">
                <Link :href="route('adminmenu')">Admin</Link> > Firm Settings
            </h2>
        </template>

        <div class="py-3">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                <div class="bg-base-300 overflow-hidden sm:rounded-lg min-h-dvh">
                    <p class="mt-3 text-3xl font-bold text-center text-blue-600">
                        Firm Settings
                    </p>

                    <form autocomplete="off" class="max-w-2xl mx-auto mt-8 text-base-content">

                        <div class="flex-col mb-5">
                            <div class="flex ml-2">
                                <InputLabel for="name" value="Firm Name" />
                                <span class="text-sm text-error ml-2">*</span>
                            </div>
                            <TextInput v-model="form.name" id="name" class="w-full mt-1" />
                            <InputError class="mt-1" :message="form.errors.name" />
                        </div>

                        <div class="flex-col mb-5">
                            <InputLabel for="address" value="Address" class="ml-2" />
                            <textarea
                                v-model="form.address"
                                id="address"
                                rows="4"
                                class="textarea textarea-bordered text-base w-full mt-1 resize-none overflow-y-auto"
                            ></textarea>
                            <InputError class="mt-1" :message="form.errors.address" />
                        </div>

                        <div class="flex gap-6 mb-5">
                            <div class="flex-col w-1/2">
                                <InputLabel for="phone" value="Phone" class="ml-2" />
                                <TextInput v-model="form.phone" id="phone" class="w-full mt-1" />
                                <InputError class="mt-1" :message="form.errors.phone" />
                            </div>
                            <div class="flex-col w-1/2">
                                <div class="flex ml-2">
                                    <InputLabel for="email" value="Email" />
                                    <span class="text-sm text-error ml-2">*</span>
                                </div>
                                <TextInput v-model="form.email" id="email" type="email" class="w-full mt-1" />
                                <InputError class="mt-1" :message="form.errors.email" />
                            </div>
                        </div>

                        <div class="flex-col mb-2 border border-base-content/30 rounded p-4">
                            <InputLabel for="document_base_path" value="Document Storage Path" class="ml-2 font-semibold" />
                            <TextInput
                                v-model="form.document_base_path"
                                id="document_base_path"
                                class="w-full mt-1"
                                placeholder="e.g., S:\Cases or /Volumes/shared/cases"
                            />
                            <p class="text-sm text-base-content/60 mt-1 ml-2">
                                The root folder where your firm's documents are stored. Leave blank to disable document linking.
                            </p>
                            <InputError class="mt-1" :message="form.errors.document_base_path" />
                            <div class="mt-3 ml-2">
                                <Link :href="route('firm.protocol-setup')" class="text-sm text-blue-500 hover:text-blue-700 underline">
                                    Protocol Handler Setup Instructions
                                </Link>
                                <p class="text-xs text-base-content/50 mt-1">
                                    Each user's PC needs a one-time setup to open linked documents directly from the app.
                                </p>
                            </div>
                        </div>

                    </form>

                    <div class="text-center mt-12 mb-8">
                        <Link href="" class="btn btn-primary mr-12 w-32" @click.prevent="submitForm">Save</Link>
                        <Link :href="route('adminmenu')" class="btn btn-primary w-32">Cancel</Link>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
