<script setup>
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout.vue";
// import InputError from "@/Components/InputError.vue";
// import InputLabel from "@/Components/InputLabel.vue";
// import PrimaryButton from "@/Components/PrimaryButton.vue";
// import TextInput from "@/Components/TextInput.vue";
// import VueDatePicker from '@vuepic/vue-datepicker';
// import '@vuepic/vue-datepicker/dist/main.css'
import FileForm from "@/Pages/Files/FileForm.vue";


import { reactive, ref, computed, onMounted, onUnmounted } from "vue";
import { EMPTY_ARR } from "@vue/shared";
import { Head, Link, useForm, router } from "@inertiajs/vue3";
import { nextTick } from "vue";

const props = defineProps({
    file: Object,
    filetypes: Object,
    attorneys: Object,
    assigned_attorney_id: Number,
    client_name: String,
});

const queryString = window.location.search;
const urlParams = new URLSearchParams(queryString);

const state = reactive({
    mode: 'file_show',
});

// let default_filetype = null;

// props.filetypes.forEach( filetype => {                                                  // go through the filetypes
//     if( filetype['set_as_default'] === 1 ) default_filetype = filetype.id;          // if a default type is found, set the variable with it
// });

function handleEsc(e) {
    // Handle escape key if needed
}

onMounted(() => document.addEventListener('keydown', handleEsc));
onUnmounted(() => document.removeEventListener('keydown', handleEsc));

</script>

<template>

    <Head title="Edit File" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="font-semibold text-xl text-base-content ml-6">
                <Link :href="('/files?page=' + file_form.current_page + '&show=' + file_form.show)">Files</Link> > Edit
            </h2>
        </template>

        <div class="py-3 min-h-screen">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 min-h-screen">
                <div class="bg-base-100 overflow-hidden shadow-sm sm:rounded-lg">
                    <p class="mt-3 text-3xl font-bold text-center text-blue-800">
                        File Information
                    </p>

                    <FileForm
                        v-model:the_mode="state.mode"
                        :file="props.file"
                        :filetypes="props.filetypes"
                        :attorneys="props.attorneys"
                        :assigned_attorney_id="props.assigned_attorney_id"
                        :client_name="props.client_name"
                    />

                </div>
            </div>
        </div>

    </AuthenticatedLayout>
</template>