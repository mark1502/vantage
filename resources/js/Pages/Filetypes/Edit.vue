<script setup>
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout.vue";
import InputError from "@/Components/InputError.vue";
// import InputLabel from "@/Components/InputLabel.vue";
// import PrimaryButton from "@/Components/PrimaryButton.vue";
// import TextInput from "@/Components/TextInput.vue";

import { Head, Link, useForm, router, usePage } from "@inertiajs/vue3";
import { reactive, computed, watch, onMounted, onUnmounted } from "vue";
// import { EMPTY_ARR } from "@vue/shared";

const page = usePage();

const props = defineProps({
    filetype: Object,
});

const queryString = window.location.search;
const urlParams = new URLSearchParams(queryString);

// alert(contact);


let form = useForm({
    formtype: "filetype",
    name: props.filetype.name,
    has_correspondence: setTF('has_correspondence'),
    has_pleadings: setTF('has_pleadings'),
    has_discovery: setTF('has_discovery'),
    has_documents: setTF('has_documents'),
    has_memos: setTF('has_memos'),
    has_events: setTF('has_events'),
    has_todo: setTF('has_todo'),
    has_phone: setTF('has_phone'),
    has_medrecs: setTF('has_medrecs'),
    has_medbills: setTF('has_medbills'),
    has_costs: setTF('has_costs'),
    enable_file_SOL: setTF('enable_file_SOL'),
    current_page: urlParams.get('page'),
    show: urlParams.get('show'),
});

let saved_form = { ...form };

function setTF( var_in ) {
    return props.filetype[var_in] === 1 ? true : false;
}

watch(() => form.enable_file_SOL, (checked) => {
    if (checked) {
        form.has_pleadings = true;
    }
});

function submitForm() {
    form.put("/filetypes/" + props.filetype.id, {
        onError: () => {
            if( page.props.errors.existing_entries ) {
                alert('Error:' + page.props.errors.existing_entries);
            }
        }
    });
}

function handleEsc(e) {
    if(e.key === 'Escape') {
        router.get('/filetypes?page=' + form.current_page + '&show=' + form.show)
    }
}

onMounted(() => document.addEventListener('keydown', handleEsc));
onUnmounted(() => document.removeEventListener('keydown', handleEsc));


</script>

<template>

    <Head title="Create File Type" />

    <AuthenticatedLayout>
         <template #header>
            <h2 class="font-normal text-sm text-base-content leading-tight ml-2">
                <Link :href="'/adminmenu'">Admin</Link> > File Types > Edit File Type
            </h2>
        </template>

        <div class="py-3">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                <div class="bg-base-300 overflow-hidden sm:rounded-lg min-h-dvh">
                    <p class="mt-3 text-3xl font-bold text-center text-blue-600">
                        Edit File Type
                    </p>

                    <form autocomplete="off" class="max-w-3xl mx-auto mt-4 text-base-content">
                        <table class="ml-10">
                            <tbody>
                            <tr class="flex items-baseline my-4">
                                <td class="flex items-baseline">
                                    <label for="name" class="mr-3 font-semibold">File Type:</label>
                                    <input v-model="form.name" type="text" id="name" class="input input-bordered input-sm w-72"/>
                                </td>
                            </tr>
                            <tr class="h-10">
                                <td>
                                    <span class="font-semibold mb-10">Select Folders For This File Type: &nbsp;</span>
                                    <span class="text-xs"> (required folders are disabled)</span>
                                </td>
                            </tr>
                            <tr class="flex">
                                <td class="w-[200px] ml-8 flex">
                                    <input v-model="form.has_correspondence" type="checkbox" id="has_correspondence" value="1" disabled />
                                    <label for="has_correspondence" class="ml-2">Correspondence</label>
                                </td>
                                <td class="flex">
                                    <input v-model="form.has_pleadings" type="checkbox" id="has_pleadings" value="1" :disabled="form.enable_file_SOL" />
                                    <label for="has_pleadings" class="ml-2">Pleadings</label>
                                </td>
                            </tr>
                            <tr class="flex">
                                <td class="w-[200px] ml-8 flex">
                                    <input v-model="form.has_discovery" type="checkbox" id="has_discovery" value="1" />
                                    <label for="has_discovery" class="ml-2">Discovery</label>
                                </td>
                                <td class="flex">
                                    <input v-model="form.has_documents" type="checkbox" id="has_documents" value="1" />
                                    <label for="has_documents" class="ml-2">Documents</label>
                                </td>
                            </tr>
                            <tr class="flex">
                                <td class="w-[200px] ml-8 flex">
                                    <input v-model="form.has_memos" type="checkbox" id="has_memos" value="1" disabled />
                                    <label for="has_memos" class="ml-2">Memos</label>
                                </td>
                                <td class="flex">
                                    <input v-model="form.has_events" type="checkbox" id="has_events" value="1" disabled />
                                    <label for="has_events" class="ml-2">Events</label>
                                </td>
                            </tr>
                            <tr class="flex">
                                <td class="w-[200px] ml-8 flex">
                                    <input v-model="form.has_todo" type="checkbox" id="has_todo" value="1" disabled />
                                    <label for="has_todo" class="ml-2">To-Do</label>
                                </td>
                                <td class="flex">
                                    <input v-model="form.has_phone" type="checkbox" id="has_phone" value="1" disabled />
                                    <label for="has_phone" class="ml-2">Phone Messages</label>
                                </td>
                            </tr>
                            <tr class="flex">
                                <td class="w-[200px] ml-8 flex">
                                    <input v-model="form.has_medrecs" type="checkbox" id="has_medrecs" value="1" />
                                    <label for="has_medrecs" class="ml-2">Medical Records</label>
                                </td>
                                <td class="flex">
                                    <input v-model="form.has_medbills" type="checkbox" id="has_medbills" value="1" />
                                    <label for="has_medbills" class="ml-2">Medical Bills</label>
                                </td>
                            </tr>
                            <tr class="flex">
                                <td class="w-[200px] ml-8 flex">
                                    <input v-model="form.has_costs" type="checkbox" id="has_costs" value="1" />
                                    <label for="has_costs" class="ml-2">Case Costs</label>
                                </td>
                                <td class="flex">
                                </td>
                            </tr>
                            </tbody>
                        </table>
                        <div class="ml-10 mt-6 border border-base-content/30 p-4 rounded">
                            <input v-model="form.enable_file_SOL" type="checkbox" id="enable_file_SOL" value="1" />
                            <label for="enable_file_SOL" class="ml-2">
                                This file type may have a Statue of Limitations.
                            </label>
                            <p class="text-sm mt-1">Note: &nbsp;When enabled (default), a file of this type can record and track a statute of limitations.
                                  &nbsp;Enable for any file type which may involve litigation.
                                &nbsp;However, it can be disabled for file types which do not involve litigation, such as some transactional types of matters.</p>
                        </div>
                    </form>

                    <div class="text-center mt-16">
                        <Link href="" class="btn btn-primary text-center mr-12" @click="submitForm">OK</Link>
                        <Link :href="('/filetypes?page=' + form.current_page + '&show=' + form.show)" class="btn btn-primary">Cancel</Link>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
