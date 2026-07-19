<template>

    <Head title="Contacts > Create" />

    <AuthenticatedLayout>
         <template #header>
            <h2 class="font-semibold text-xl text-base-content leading-tight ml-2">
                <Link class="font-normal hover:text-blue-500" :href="('/contacts?page=' + form.current_page + '&show=' + form.show)">Contacts</Link> > Create New Contact
            </h2>
        </template>

        <div class="py-3">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                <div class="overflow-hidden">
                    <form autocomplete="off" class="max-w-7xl mx-auto mt-4">
                    <div class="bg-base-200 shadow-sm sm:rounded-lg p-6">
                    <!-- Name Line starts-->
                        <div class="flex mt-2">
                            <div class="flex-col w-24 mr-4">
                                <div class="flex ml-2"><InputLabel for="form.title" value="Title" /><span class="text-sm text-error ml-2">*</span></div>
                                <select v-model="form.title" class="select select-bordered select-sm text-base w-full" id="form.title">
                                    <option value="" disabled selected>Select</option>
                                    <option>Mr.</option>
                                    <option>Ms.</option>
                                    <option>Mrs.</option>
                                    <option>Miss</option>
                                    <option>Dr.</option>
                                    <option>Hon.</option>
                                    <option>Co.</option>
                                </select>
                                <InputError class="mt-2" :message="form.errors.title" />
                            </div>
                            <div v-if="form.title != 'Co.'" class="flex">
                                <div class="flex-col w-56 max-w-xs mr-4">
                                    <div class="flex ml-2"><InputLabel for="form.first_name" value="First Name" /><span class="text-sm text-error ml-2">*</span></div>
                                    <TextInput v-model="form.first_name" id="form.first_name" class="w-full max-w-xs" required />
                                    <InputError class="mt-2" :message="form.errors.first_name" />
                                </div>

                                <div class="flex-col w-32 max-w-xs mr-4">
                                    <div class="flex ml-2"><InputLabel for="form.middle_name" value="Middle" class="ml-2"/><span class="text-sm ml-2 invisible">*</span></div>
                                    <TextInput v-model="form.middle_name" id="form.middle_name" class="w-full max-w-xs" />
                                    <InputError class="mt-2" :message="form.errors.middle_name" />
                                </div>

                                <div class="flex-col w-56 max-w-xs mr-4">
                                    <div class="flex ml-2"><InputLabel for="form.last_name" value="Last Name" /><span class="text-sm text-error ml-2">*</span></div>
                                    <TextInput v-model="form.last_name" id="form.last_name" class="w-full max-w-xs" required />
                                    <InputError class="mt-2" :message="form.errors.last_name" />
                                </div>

                                <div class="flex-col w-32 max-w-xs mr-4">
                                    <div class="flex ml-2"><InputLabel for="form.srjr" value="Sr/Jr" class="ml-2"/><span class="text-sm ml-2 invisible">*</span></div>
                                    <TextInput v-model="form.srjr" id="form.srjr" class="w-full max-w-xs" />
                                    <InputError class="mt-2" :message="form.errors.srjr" />
                                </div>

                                <div class="flex-col w-32 max-w-xs">
                                    <div class="flex ml-2"><InputLabel for="form.esqphd" value="Esq/Ph.D" class="ml-2"/><span class="text-sm ml-2 invisible">*</span></div>
                                    <TextInput v-model="form.esqphd" id="form.esqphd" class="w-full max-w-xs" />
                                    <InputError class="mt-2" :message="form.errors.esqphd" />
                                </div>
                            </div>
                        </div>
                        <InputError class="mt-2" :message="form.errors.display_name" />

                    <!-- Company Line starts-->
                        <div class="flex mt-6">
                            <div class="flex-col w-1/2 max-w-sm">
                                <div class="flex ml-2"><InputLabel for="form.company" value="Company" />
                                    <span v-if="form.title === 'Co.'" class="text-sm text-error ml-2">*</span>
                                    <span v-else="form.title === 'Co.'" class="text-sm ml-2 invisible">*</span>
                                </div>
                                <TextInput v-model="form.company" id="form.company" class="input w-full" />
                                <InputError class="mt-2" :message="form.errors.company" />
                            </div>
                            <div v-if="form.title != 'Co.'" class="flex-col w-1/2 max-w-sm ml-32">
                                <div class="flex ml-2"><InputLabel for="form.business_title" value="Business Title" class=""/><span class="text-sm ml-2 invisible">*</span></div>
                                <TextInput v-model="form.business_title" id="form.business_title" class="w-full" />
                                <InputError class="mt-2" :message="form.errors.business_title" />
                            </div>
                        </div>

                    <!-- Address Line starts-->
                        <div class="flex my-5">
                            <div class="flex-col w-1/2 max-w-sm">
                                <InputLabel for="form.address" value="Address" class="ml-2"/>
                                <textarea v-model="form.address" id="form.address" rows="4" class="textarea textarea-bordered text-sm p-2 w-full"></textarea>
                                <InputError class="mt-2" :message="form.errors.address" />

                            </div>
                            <div class="flex-col w-1/2 max-w-sm ml-32">
                                <InputLabel for="form.email" value="Email" class="ml-2"/>
                                <TextInput v-model="form.email" id="form.email" class="w-full mb-3" />
                                <InputError class="mt-2" :message="form.errors.email" />

                                <InputLabel for="form.email_alt" value="Alternate Email" class="ml-2"/>
                                <TextInput v-model="form.email_alt" id="form.email_alt" class="w-full" />
                                <InputError class="mt-2" :message="form.errors.email_alt" />
                            </div>
                        </div>

                    <!-- Phones Line starts-->
                        <span class="text-sm font-semibold ml-2">PHONES:</span>
                        <div class="flex space-x-4 border border-base-content/40 rounded p-4">
                            <div class="flex-col w-1/5 max-w-sm">
                                <InputLabel for="form.work_phone" value="Work" class="ml-2"/>
                                <TextInput v-model="form.work_phone" id="form.work_phone" class="w-full" />
                                <InputError class="mt-2" :message="form.errors.work_phone" />
                            </div>
                            <div class="flex-col w-1/5 max-w-sm">
                                <InputLabel for="form.cell_phone" value="Cell" class="ml-2"/>
                                <TextInput v-model="form.cell_phone" id="form.cell_phone" class="w-full" />
                                <InputError class="mt-2" :message="form.errors.cell_phone" />
                            </div>
                            <div class="flex-col w-1/5 max-w-sm">
                                <InputLabel for="form.home_phone" value="Home" class="ml-2"/>
                                <TextInput v-model="form.home_phone" id="form.home_phone" class="w-full" />
                                <InputError class="mt-2" :message="form.errors.home_phone" />
                            </div>
                            <div class="flex-col w-1/5 max-w-sm">
                                <InputLabel for="form.fax_phone" value="Fax" class="ml-2"/>
                                <TextInput v-model="form.fax_phone" id="form.fax_phone" class="w-full" />
                                <InputError class="mt-2" :message="form.errors.fax_phone" />
                            </div>
                            <div class="flex-col w-1/5 max-w-sm">
                                <InputLabel for="form.other_phone" value="Other" class="ml-2"/>
                                <TextInput v-model="form.other_phone" id="form.other_phone" class="w-full" />
                                <InputError class="mt-2" :message="form.errors.other_phone" />
                            </div>
                        </div>

                    <!--Notes Line-->
                        <div class="flex mt-6">
                            <div class="flex-col w-full">
                                <InputLabel for="form.note" value="Notes" class="ml-2"/>
                                <textarea v-model="form.note" id="formnotes" class="textarea textarea-bordered text-base p-2 w-full" rows="3"></textarea>
                                <InputError class="mt-2" :message="form.errors.note" />
                            </div>
                        </div>

                    </div>

                    <!-- Buttons outside the card -->
                        <div class="flex mt-6 justify-center">
                                <button type="button" class="btn btn-primary w-40 mr-10 gap-0" @click="submitForm"><u>O</u>k</button>
                                <Link :href="route( 'contacts.index', { page: form.current_page, show: form.show })" class="btn btn-primary mr-16">Cancel</Link>
                        </div>

                    </form>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>

<script setup>
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout.vue";
import InputError from "@/Components/InputError.vue";
import InputLabel from "@/Components/InputLabel.vue";
// import PrimaryButton from "@/Components/PrimaryButton.vue";
import TextInput from "@/Components/TextInput.vue";

import { Head, Link, useForm, router } from "@inertiajs/vue3";
import { reactive, computed, onMounted, onUnmounted } from "vue";
// import { EMPTY_ARR } from "@vue/shared";


const props = defineProps({
    contact: Object,
});

const queryString = window.location.search;
const urlParams = new URLSearchParams(queryString);

// alert(contact);


let form = useForm({
    formtype: "contact",
    title: "",
    first_name: "",
    middle_name: "",
    last_name: "",
    srjr: "",
    esqphd: "",
    company: "",
    business_title: "",
    address: "",
    email: "",
    email_alt: "",
    home_phone: "",
    work_phone: "",
    cell_phone: "",
    fax_phone: "",
    other_phone: "",
    note: "",
    display_name: "",
    display_last_first: "",
    current_page: urlParams.get('page'),
    show: urlParams.get('show'),
});


function submitForm() {
    buildDisplayNames();
    form.post("/contacts", {
        // onSuccess: () => increment(),
    });
}

function buildDisplayNames() {
    if( form.title === null || form.title === undefined ) {
        form.title = '';
    } else if( form.title === 'Co.' ) {
        form.last_name = form.company;
        form.display_last_first = form.company;
        form.display_name = form.company;
    } else {
            // Convert null or undefined vars to empty string
        if( form.first_name === null || form.first_name === undefined ) { form.first_name = ''; }
        if( form.middle_name === null || form.middle_name === undefined ) { form.middle_name = ''; }
        if( form.last_name === null || form.last_name === undefined ) { form.last_name = ''; }
        if( form.srjr === null || form.srjr === undefined ) { form.srjr = ''; }
        if( form.esqphd === null || form.esqphd === undefined ) { form.esqphd = ''; }

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

}

function handleEsc(e) {
    if(e.key === 'Escape') {
        router.get('/contacts?page=' + form.current_page + '&show=' + form.show)
    }
}

onMounted(() => document.addEventListener('keydown', handleEsc));
onUnmounted(() => document.removeEventListener('keydown', handleEsc));


</script>
