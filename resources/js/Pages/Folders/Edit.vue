<template>

    <Head title="Folders > Create" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="font-normal text-sm text-base-content leading-tight ml-2">
                <Link :href="('/folders?page=' + form.current_page + '&show=' + form.show)">Folders</Link> > Edit Folder
            </h2>
        </template>

        <div class="py-3 min-h-screen">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 min-h-screen">
                <div class="bg-base-100 overflow-hidden shadow-sm sm:rounded-lg">
                    <p class="mt-3 text-3xl font-bold text-center text-primary">
                        Edit Folder
                    </p>

                    <form class="max-w-5xl mx-auto mt-4">

                        <div class="flex w-64 max-w-xs mr-4">
                            <label class="label_sm-700 ml-2 mr-4">
                                Folder
                            </label>
                            <input v-model="form.name" type="text" class="input input-bordered w-full max-w-xs"
                                name="form.first_name" id="form.first_name" required autofocus />
                            <InputError class="mt-2" :message="form.errors.name" />
                        </div>

                        <div class="flex mt-4">
                            <label for="input_time">
                                <input type="checkbox" id="change_email" name="change_email" v-model="form.input_time"
                                    value=false class="rounded">
                                <span class="ml-4">Input time</span>
                            </label>
                        </div>

                        <div class="form-control w-96 max-w-xs mr-4 mt-4">
                            <label class="label_sm-700 ml-2">
                                Date 1 Prompt
                            </label>
                            <input v-model="form.date1_prompt" type="text" class="input input-bordered max-w-xs"
                                name="date1_prompt" id="date1_prompt" required />
                            <InputError class="mt-2" :message="form.errors.date1_prompt" />
                        </div>

                        <div class="form-control w-64 max-w-xs mr-4 mt-4">
                            <label class="label_sm-700 ml-2">
                                Date 2 Prompt
                            </label>
                            <input v-model="form.date2_prompt" type="text" class="input input-bordered w-full max-w-xs"
                                name="date2_prompt" id="date2_prompt"/>
                            <InputError class="mt-2" :message="form.errors.date2_prompt" />
                        </div>

                        <div class="flex mt-4">
                            <label for="hide_date2_prompt">
                                <input type="checkbox" id="hide_date2_prompt" name="hide_date2_prompt" v-model="form.hide_date2_prompt"
                                    value=false class="rounded">
                                <span class="ml-4">Hide date2</span>
                            </label>
                        </div>

                        <div class="form-control w-64 max-w-xs mr-4 mt-4">
                            <label class="label_sm-700 ml-2">
                                From Prompt
                            </label>
                            <input v-model="form.from_prompt" type="text" class="input input-bordered w-full max-w-xs"
                                name="from_prompt" id="from_prompt"/>
                            <InputError class="mt-2" :message="form.errors.from_prompt" />
                        </div>

                        <div class="form-control w-64 max-w-xs mr-4 mt-4">
                            <label class="label_sm-700 ml-2">
                                To Prompt
                            </label>
                            <input v-model="form.to_prompt" type="text" class="input input-bordered w-full max-w-xs"
                                name="to_prompt" id="to_prompt"/>
                            <InputError class="mt-2" :message="form.errors.to_prompt" />
                        </div>

                        <div class="flex mt-4">
                            <label for="hide_to_prompt">
                                <input type="checkbox" id="hideto2_prompt" name="hide_to_prompt" v-model="form.hide_to_prompt"
                                    value=false class="rounded">
                                <span class="ml-4">Hide To</span>
                            </label>
                        </div>

                        <div class="form-control w-64 max-w-xs mr-4 mt-4">
                            <label class="label_sm-700 ml-2">
                                Entrytype Prompt
                            </label>
                            <input v-model="form.entrytype_prompt" type="text" class="input input-bordered w-full max-w-xs"
                                name="entrytype_prompt" id="entrytype_prompt"/>
                            <InputError class="mt-2" :message="form.errors.entrytype_prompt" />
                        </div>

                        <div class="form-control w-64 max-w-xs mr-4 mt-4">
                            <label class="label_sm-700 ml-2">
                                Note Prompt
                            </label>
                            <input v-model="form.note_prompt" type="text" class="input input-bordered w-full max-w-xs"
                                name="note_prompt" id="note_prompt"/>
                            <InputError class="mt-2" :message="form.errors.note_prompt" />
                        </div>

                        <div class="form-control w-64 max-w-xs mr-4 mt-4">
                            <label class="label_sm-700 ml-2">
                                Response expected Prompt
                            </label>
                            <input v-model="form.responseexpected_prompt" type="text" class="input input-bordered w-full max-w-xs"
                                name="responseexpected_prompt" id="responseexpected_prompt"/>
                            <InputError class="mt-2" :message="form.errors.responseexpected_prompt" />
                        </div>

                        <div class="form-control w-64 max-w-xs mr-4 mt-4">
                            <label class="label_sm-700 ml-2">
                                Short Name:
                            </label>
                            <input v-model="form.short_name" type="text" class="input input-bordered w-full max-w-xs"
                                name="short_name" id="short_name"/>
                            <InputError class="mt-2" :message="form.errors.short_name" />
                        </div>

                        <div class="form-control w-64 max-w-xs mr-4 mt-4">
                            <label class="label_sm-700 ml-2">
                                Amount Prompt:
                            </label>
                            <input v-model="form.amount_prompt" type="text" class="input input-bordered w-full max-w-xs"
                                name="amount_prompt" id="amount_prompt"/>
                            <InputError class="mt-2" :message="form.errors.amount_prompt" />
                        </div>

                        <div class="flex mt-4">
                            <label for="hide_to_prompt">
                                <input type="checkbox" id="hide_amount_prompt" name="hide_amount_prompt" v-model="form.hide_amount_prompt"
                                    value=false class="rounded">
                                <span class="ml-4">Hide Amount Prompt</span>
                            </label>
                        </div>

                        <div class="flex justify-center mt-4 mb-4">
                            <Link href="" class="btn btn-primary w-40 mr-10" @click="submitForm">OK</Link>
                            <Link :href="('/folders?page=' + form.current_page + '&show=' + form.show)"
                                class="btn btn-primary mr-16">Cancel</Link>

                        </div>

                    </form>

                    <div class="h-7">

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

import { reactive, computed, onMounted, onUnmounted } from "vue";
import { EMPTY_ARR } from "@vue/shared";
import { Head, Link, useForm, router } from "@inertiajs/vue3";


const props = defineProps({
    folder: Object,
});

const queryString = window.location.search;
const urlParams = new URLSearchParams(queryString);

let form = useForm({
    formtype: "folder",
    name: "",
    input_time: false,
    date1_prompt: "",
    date2_prompt: "",
    hide_date2_prompt: false,
    from_prompt: "",
    to_prompt: "",
    hide_to_prompt: false,
    entrytype_prompt: "",
    note_prompt: "",
    responseexpected_prompt: "",
    short_name: "",
    amount_prompt: "",
    hide_amount_prompt: false,
    current_page: urlParams.get('page'),
    show: urlParams.get('show'),
});

form.name = props.folder.name;
form.input_time = props.folder.input_time;
form.date1_prompt = props.folder.date1_prompt;
form.date2_prompt = props.folder.date2_prompt;
form.hide_date2_prompt = props.folder.hide_date2_prompt;
form.from_prompt = props.folder.from_prompt;
form.to_prompt = props.folder.to_prompt;
form.hide_to_prompt = props.folder.hide_to_prompt;
form.entrytype_prompt = props.folder.entrytype_prompt;
form.note_prompt = props.folder.note_prompt;
form.responseexpected_prompt = props.folder.responseexpected_prompt;
form.short_name = props.folder.short_name;
form.amount_prompt = props.folder.amount_prompt;
form.hide_amount_prompt = props.folder.hide_amount_prompt;



function submitForm() {
    form.put("/folders/" + props.folder.id, {
        // onSuccess: () => increment(),
    });
}


/*
function deleteClicked() {
    let r = confirm("Do you want to delete this contact?\n\nClick Ok to delete");
    if (r == true) {
       form.delete("/contacts/" + props.contact.id, {
        });
    }
}
*/

function handleEsc(e) {
    if (e.key === 'Escape') {
        router.get('/folders?page=' + form.current_page + '&show=' + form.show)
    }
}

onMounted(() => document.addEventListener('keydown', handleEsc));
onUnmounted(() => document.removeEventListener('keydown', handleEsc));

</script>
