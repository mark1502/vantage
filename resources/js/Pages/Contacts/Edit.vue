<script setup>
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout.vue";
import InputError from "@/Components/InputError.vue";
import InputLabel from "@/Components/InputLabel.vue";
// import PrimaryButton from "@/Components/PrimaryButton.vue";
import TextInput from "@/Components/TextInput.vue";

import { Head, Link, useForm, router } from "@inertiajs/vue3";
import { computed, onMounted, onUnmounted } from "vue";
// import { EMPTY_ARR } from "@vue/shared";


const props = defineProps({
    contact: Object,
});

const isFauxDeleted = computed(() => props.contact.faux_deleted ?? false);

const queryString = window.location.search;
const urlParams = new URLSearchParams(queryString);

let form = useForm({
    formtype: "contact",
    id: props.contact.id,
    title: props.contact.title,
    first_name: props.contact.first_name ?? '',
    middle_name: props.contact.middle_name ?? '',
    last_name: props.contact.last_name,
    srjr: props.contact.srjr ?? '',
    esqphd: props.contact.esqphd ?? '',
    company: props.contact.company ?? '',
    business_title: props.contact.business_title ?? '',
    address: props.contact.address ?? '',
    email: props.contact.email ?? '',
    email_alt: props.contact.email_alt ?? '',
    home_phone: props.contact.home_phone ?? '',
    work_phone: props.contact.work_phone ?? '',
    cell_phone: props.contact.cell_phone ?? '',
    fax_phone: props.contact.fax_phone ?? '',
    other_phone: props.contact.other_phone ?? '',
    note: props.contact.note ?? '',
    display_name: props.contact.display_name,
    display_last_first: props.contact.display_last_first,
    current_page: urlParams.get('page'),
    show: urlParams.get('show'),
});


function submitForm() {
    buildDisplayNames();
    form.put( route( 'contacts.update', { contact : props.contact.id } ) );
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


function deleteClicked() {
    let r = confirm("Do you want to delete this contact?\n\nClick Ok to delete");
    if (r == true) {
       form.delete( "/contacts/" + props.contact.id, {} );
    }
}


function handleEsc(e) {
    if(e.key === 'Escape') {
        router.get( route('contacts.index', { page: form.current_page, show: form.show }) );
    } else if( e.altKey && e.key==='o' ) { 
        e.preventDefault();
        submitForm();
    } 
}

onMounted(() => document.addEventListener('keydown', handleEsc));
onUnmounted(() => document.removeEventListener('keydown', handleEsc));

</script>

<template>

    <Head title="DashboardER" />

    <AuthenticatedLayout>
         <template #header>
            <h2 class="font-semibold text-xl text-base-content leading-tight ml-2">
                <Link class="font-normal hover:text-blue-500 hover:font-semibold" :href="route( 'contacts.index', { page: form.current_page, show: form.show })">Contacts</Link> > Edit Contact
            </h2>
        </template>

        <div class="py-3 min-h-screen">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                <div class="bg-base-300 overflow-hidden shadow-sm sm:rounded-lg min-h-dvh">
                    <form autocomplete="off" class="max-w-5xl mx-auto mt-4">

                        <div v-if="isFauxDeleted" class="alert alert-warning mb-4">
                            <span>This contact has been deleted. You can restore it from the contacts list.</span>
                        </div>

                        <!-- Form 3 - Name Line starts-->
                        <div class="flex mt-8">
                            <div class="flex-col w-36 max-w-xs mr-4">
                                <div class="flex ml-2"><InputLabel for="form.title" value="Title" /><span class="text-sm text-error ml-2">*</span></div>
                                <select v-model="form.title" class="select select-bordered select-sm text-base" id="form.title">
                                    <option value="" disabled selected>Pick one</option>
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

                        <!-- Form 3 - Company Line starts-->
                        <div class="flex mt-6">
                            <div class="flex-col w-1/2 max-w-sm">
                                <div class="flex ml-2"><InputLabel for="form.company" value="Company" />
                                    <span v-if="form.title === 'Co.'" class="text-sm text-error ml-2">*</span>
                                    <span v-else="form.title === 'Co.'" class="text-sm ml-2 invisible">*</span>
                                </div>
                                <TextInput v-model="form.company" id="form.company" class="input input-bordered w-full" />
                                <InputError class="mt-2" :message="form.errors.company" />
                            </div>
                            <div v-if="form.title != 'Co.'" class="flex-col w-1/2 max-w-sm ml-32">
                                <div class="flex ml-2"><InputLabel for="form.business_title" value="Business Title" class=""/><span class="text-sm ml-2 invisible">*</span></div>
                                <TextInput v-model="form.business_title" id="form.business_title" class="w-full" />
                                <InputError class="mt-2" :message="form.errors.business_title" />
                            </div>
                        </div>
                        <!-- Form 3 - Address Line starts-->
                        <div class="flex my-5">
                            <div class="flex-col w-1/2 max-w-sm">
                                <InputLabel for="form.address" value="Address" class="ml-2"/>
                                <textarea v-model="form.address" id="form.address" class="textarea textarea-bordered text-base p-2 h-32 w-full"></textarea>
                                <InputError class="mt-2" :message="form.errors.address" />

                            </div>
                            <div class="flex-col w-1/2 max-w-sm ml-32">
                                <InputLabel for="form.email" value="Email" class="ml-2"/>
                                <TextInput v-model="form.email" id="form.email" class="w-full mb-2" />
                                <InputError class="mt-2" :message="form.errors.email" />

                                <InputLabel for="form.email_alt" value="Alternate Email" class="ml-2"/>
                                <TextInput v-model="form.email_alt" id="form.email_alt" class="w-full" />
                                <InputError class="mt-2" :message="form.errors.email_alt" />
                            </div>
                        </div>
                        <!-- Form 3 - Phones Line starts-->
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
                        <!-- Form 3 - Notes Line and Buttons-->
                        <div class="flex mt-6 items-end justify-between">
                            <div class="flex-col w-1/2">
                                <InputLabel for="form.note" value="Notes" class="ml-2"/>
                                <textarea v-model="form.note" id="formnotes" class="textarea textarea-bordered text-base p-2 w-full" rows="4"></textarea>
                                <InputError class="mt-2" :message="form.errors.note" />
                            </div>
                            <div class="flex mb-2">
                                <Link href="" class="btn btn-primary w-40 mr-10 gap-0" @click="submitForm"><u>O</u>k</Link>
                                <Link :href="route( 'contacts.index', { page: form.current_page, show: form.show })" class="btn btn-primary mr-16">Cancel</Link>
                                <Link href="" class="btn btn-outline btn-error" @click="deleteClicked" >Delete</Link>
                            </div>
                        </div>

                    </form>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>

