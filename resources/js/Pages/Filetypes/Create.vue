<script setup>
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout.vue";
import InputError from "@/Components/InputError.vue";
// import InputLabel from "@/Components/InputLabel.vue";
// import PrimaryButton from "@/Components/PrimaryButton.vue";
// import TextInput from "@/Components/TextInput.vue";

import { Head, Link, useForm, router } from "@inertiajs/vue3";
import { reactive, computed, watch, onMounted, onUnmounted } from "vue";
// import { EMPTY_ARR } from "@vue/shared";


// const props = defineProps({
//     contact: Object,
// });

const queryString = window.location.search;
const urlParams = new URLSearchParams(queryString);

// alert(contact);


let form = useForm({
    formtype: "filetype",
    name: "",
    has_correspondence: true,
    has_pleadings: true,
    has_discovery: false,
    has_documents: false,
    has_memos: true,
    has_events: true,
    has_todo: true,
    has_phone: true,
    has_medrecs: false,
    has_medbills: false,
    has_costs: false,
    enable_file_SOL: true,
    current_page: urlParams.get('page'),
    show: urlParams.get('show'),
});


watch(() => form.enable_file_SOL, (checked) => {
    if (checked) {
        form.has_pleadings = true;
    }
});

function submitForm() {
    // buildDisplayNames();
    form.post("/filetypes", {
        // onSuccess: () => increment(),
    });
}

function buildDisplayNames() {
    if (form.middle_name === null || form.middle_name === undefined) { form.middle_name = ''; }
    if (form.srjr === null || form.srjr === undefined) { form.srjr = ''; }

    form.display_name = form.first_name.trim();
    form.display_name += form.middle_name.trim().length != 0 ? ' ' + form.middle_name.trim() : "";
    form.display_name += ' ' + form.last_name.trim();
    form.display_name += form.srjr.trim().length != 0 ? ', ' + form.srjr.trim() : "";
    form.display_name += form.esqphd.trim().length != 0 ? ', ' + form.esqphd.trim() : "";

    form.display_last_first = form.last_name.trim() + ', ' + form.first_name.trim();
    form.display_last_first += form.middle_name.trim().length != 0 ? ' ' + form.middle_name.trim() : "";
    form.display_last_first += form.srjr.trim().length != 0 ? ', ' + form.srjr.trim() : "";
    form.display_last_first += form.esqphd.trim().length != 0 ? ', ' + form.esqphd.trim() : "";
}

function handleEsc(e) {
    if(e.key === 'Escape') {
        router.get('/contacts?page=' + form.current_page + '&show=' + form.show)
    }
}

onMounted(() => document.addEventListener('keydown', handleEsc));
onUnmounted(() => document.removeEventListener('keydown', handleEsc));


</script>

<template>

    <Head title="Create File Type" />

    <AuthenticatedLayout>
         <template #header>
            <h2 class="font-bold text-xl text-base-content ml-3">
                <Link :href="'/adminmenu'">Admin</Link> > 
                <Link :href="'/filetypes'" class="hover:text-blue-600">File Types</Link> > 
                Create New File Type
            </h2>
        </template>

        <div class="py-3">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                <div class="bg-base-300 overflow-hidden sm:rounded-lg min-h-dvh">
                    <!-- <p class="mt-3 text-3xl font-bold text-center text-blue-600">
                        Create New File Type
                    </p> -->

                    <form autocomplete="off" class="max-w-3xl mx-auto mt-4 text-base-content">
                        <table class="ml-10">
                            <tbody>
                            <tr class="flex items-baseline my-4">
                                <td class="flex items-baseline">
                                    <label for="name" class="mr-3 font-semibold">File Type:</label>
                                    <input v-model="form.name" type="text" id="name" class="input input-sm w-72"/>
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
                                  &nbsp;This option should be enabled for any file type which may involve litigation.
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
