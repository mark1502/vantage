<script setup>
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout.vue";
import InputError from "@/Components/InputError.vue";
import InputLabel from "@/Components/InputLabel.vue";
import TextInput from "@/Components/TextInput.vue";
import ContactLookup from "@/Components/ContactLookup.vue";
import AddContactForm from "@/Components/AddContactForm.vue";
import { ref, reactive, onMounted, onUnmounted, nextTick, watch, onBeforeUnmount } from "vue";
import { Head, Link, useForm, router } from "@inertiajs/vue3";

const props = defineProps({
    filetypes: Object,
    attorneys: Object,
    firm_members: Array,
});

const queryString = window.location.search;
const urlParams = new URLSearchParams(queryString);

let default_filetype = null;                            // var to hold the default filetype.id
const solEnabled = ref();                                   // reactive variable to determine if the SOL date is enabled
let ok_clicked = false;                                     // used to submit a dirty form on ok click (needed for RemoveListener)

props.filetypes.forEach( filetype => {              // go through filetypes
    if( filetype.set_as_default === 1 ) {                   // if default filetype is found, set the default variables with it
        default_filetype = filetype.id;                     // hold default filetype.id (used to initialize on the form)
        solEnabled.value = filetype.enable_file_SOL;            // set ref for enable_file_SOL
    }
});

const display_name = reactive({
    client: '',
});

let added_contact_obj = reactive({
    name: '',
    id: 0,
    display_modal: false,
    accept: false,
    field: '',
    new_contact_added: false,
});

let form = useForm({
    formtype: "file",
    name: "",
    summary: "",
    date_sol: "",
    date_opened: "",
    date_filed: "",
    date_closed: "",
    date_archived: "",
    court_filed: "",
    docket_number: "",
    file_number: "",
    referred_by: "",
    referral_amount: "",
    fee_arrangement: "",
    fee_amount: "",
    final_disposition: "",
    filetype_id: default_filetype,                      // start the form with the default filetype, if any
    attorney_id: null,
    client_contact_id: null,
    current_page: urlParams.get('page'),
    show: urlParams.get('show'),
});

let saved_file_form = { ...form };                              // clone a copy of the file form

const the_mode = ref('file_add');

watch(added_contact_obj, (newValue) => {
    if (newValue.accept === true && newValue.field === 'file_client') {
        form.client_contact_id = newValue.id;
        display_name.client = newValue.name;

        nextTick(() => {
            newValue.id = 0;
            newValue.name = '';
            newValue.field = '';
            newValue.accept = false;
        });
    }
});

const removeListener = router.on('before', (event) => {         // Inertia onBefore event, before rerouting
    if( !form.isDirty ) return;
    else {
        if( ok_clicked === true ) { }     // completing the form, so don't prevent the event
        else if( !confirm( 'You have unsaved changes. Do you want to leave anyway?\n\nClick "Ok" to leave without adding new file, or "Cancel" to continue adding a new file.' )) {
            event.preventDefault();
        }
    }
  });

watch( () => form.filetype_id, (newTypeId, oldTypeId) => {                         // Watch for changes in file type and clear SOL date if disabled
    const selectedType = props.filetypes.find(type => type.id === newTypeId);           // Find the selected file type based on the newTypeId

    solEnabled.value = selectedType && selectedType.enable_file_SOL == 1 ? true : false;    // Set solEnabled based on whether the filetype allows SOL date

    if ( solEnabled.value === false && form.date_sol !== '' ) {                        // If SOL date is disabled and a date is set, clear it
        form.date_sol = '';
    }
});

function fileform_click(what) {
    if( what === 'ok' ) {
        if( solEnabled.value == true && form.date_sol === '' || form.date_sol === null) {    // if SOL is enabled and SOL is not set, display the modal to ask if they want to set it
            show_modal('sol', true);
        } else {                                                                                        // else, clicked ok so submit
            ok_clicked = true;      
            fileform_actions('submit');
        }
    } else if( what === 'cancel' ) {                                                                    // clicked cancel, show modal
        show_modal('cancelcreate', true);
    }
}


function fileform_actions( action ) {
    if( action === 'submit' ) {             // submitting the form
        show_modal('sol', false);               // close the modals, in case called from a modal
        show_modal('cancelcreate', false);

        form.post( route('files.store') );
    } else if( action === 'revert' ) {      // reverting the form
        show_modal('cancelcreate', false);        
        router.get( route('files.index'), {
            page: form.current_page,
            show: form.show
        });
    }
}

function SOL_setFocus() {       // note: this field is on the FileForm component now, so should this also be moved there?
    show_modal('sol', false);
    nextTick( () => { document.getElementById("date_sol").focus(); });
}


function show_modal( a_modal, OnOff = null) {
    let modal_name = '';
    let display_modal = false;

    switch( OnOff ) {
        case true:
        case 'show':
        case 'on':
            display_modal = true;
            break;
        case false:
        case 'hide':
        case 'off':
            display_modal = false;
            break;
    }

    if( a_modal === 'cancelcreate' || a_modal === 'sol' ) modal_name = a_modal + '_modal';
    let the_modal = document.getElementById( modal_name );  // just a shortcut to the modal for cleaner code

    if( modal_name !== '' && OnOff === 'status' ) {     // modal specified, return the status
        return the_modal.open;
    } else if( modal_name !== '' && display_modal ) {   // modal specfied, display it
        the_modal.showModal();
    } else if( modal_name !== '' && !display_modal ) {  // modal specfied, hide it
        the_modal.close();
    } else if( modal_name !== '' && OnOff == null ) {   // modal specfied, toggle it
        the_modal.open ? the_modal.close() : the_modal.showModal();
    }
}

function handle_modal_buttons( modal_in, button_in ) {
    if( modal_in === 'sol_modal' ) { 
        if( button_in === 'yes' ) {
            SOL_setFocus();
        } else {
            fileform_actions('submit');
        }
    }

    if( modal_in === 'cancelcreate_modal') {
        if( button_in === 'yes' ) {
            form.isDirty = false;       // already confirmed cancellation, so clear isDirty to avoid listener
            fileform_actions('revert')
        } else {
            show_modal('cancelcreate','off');
        }
    }
}

function handleEsc(e) {         // event listener to handle esc press and also y and n for modals
    if (e.key === 'Escape') {                                                               // if the Escape key is pressed
        e.preventDefault();                                                                 // prevent the default action of the Escape key
        show_modal('cancelcreate', true);

        if( show_modal( 'sol', 'status') === true) {                                        // if the SOL modal is showing, hide it
            document.getElementById('sol_modal').close();
        }
    } else if( e.key === 'n' || e.key === 'N' ) {                                           // if the N key is pressed
        if( document.getElementById('cancelcreate_modal').open === true ) {              // if cancel modal is showing, close it
            show_modal('cancelcreate', false );
        } 
    } else if( e.key === 'y' || e.key === 'Y' ) {                                           // else if Y key is pressed
        if (document.getElementById('cancelcreate_modal').open === true) {               // if cancel modal is showing, cancel/revert
            fileform_actions('revert');
        }
    }
        
}

onMounted( () => {
    document.addEventListener('keydown', handleEsc);
    document.getElementById("name").focus();    
});

onBeforeUnmount( () => removeListener() );

onUnmounted( () => document.removeEventListener('keydown', handleEsc) );

</script>

<template>

    <Head title="Create File" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="font-semibold text-gray-800 ml-6">
                <!-- <Link :href="('/files?page=' + file_form.current_page + '&show=' + file_form.show)">Files</Link> > Add -->
                <Link :href="route('files.index', { page: form.current_page, show: form.show })"
                    class="hover:underline text-blue-600">File List</Link> > Add
                
            </h2>
        </template>

        <div class="py-3 min-h-screen">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 min-h-dvh">
                <div class="bg-base-300 overflow-hidden min-h-dvh sm:rounded-lg">
                    <p class="mt-4 text-3xl font-bold text-center text-blue-700">
                        Add New File
                    </p>

                    <form @submit.prevent="" class="max-w-5xl mx-auto mt-4 p-6 bg-base-200 rounded border" >

                            <!-- File name line -->
                        <div class="flex items-center">
                            <div class="flex w-32">
                                <InputLabel for="name" value="File Name:" /><span class="red_star-700-2 ml-2">*</span>
                            </div>
                            <div>
                                <TextInput v-model="form.name" id="name" class="w-115" required autocomplete="off" />
                                <InputError class="mt-2" :message="form.errors.name" />
                            </div>
                            <div class="text-sm font-semibold ml-36">
                                Fields marked with * are required.
                            </div>
                        </div>

                            <!-- File type line -->
                        <div class="mt-3 flex items-center">
                            <div class="flex w-32">
                                <InputLabel for="filetype_id" value="File Type:" /><span class="red_star-700-2 ml-2">*</span>
                            </div>
                            <select v-model="form.filetype_id" id="filetype_id" class="select select-bordered select-sm w-64 disabled:text-base-content" >
                                <option v-if="!saved_file_form.filetype_id" :value="null">Select file type . . .</option>
                                <option v-for="filetype in filetypes" :key="filetype.id" :value="filetype.id">{{ filetype.name }}</option>
                            </select>
                        </div>

                            <!-- Attorney and Client Line -->
                        <div class="mt-4 flex items-center">
                            <div class="flex items-baseline w-1/2 p-0">
                                <div class="flex w-32">
                                     <InputLabel for="attorney_id" value="Attorney:" /><span class="red_star-700-2 ml-2">*</span>
                                </div>
                                <select v-model="form.attorney_id" id="attorney_id" class="select select-bordered select-sm w-64" placeholder="Select Attorney...">
                                    <option v-for="attorney in attorneys" :key="attorney.id" :value="attorney.id">
                                        {{ attorney.display_last_first }}
                                    </option>
                                </select>
                            </div>
                            <div class="flex items-baseline w-1/2">
                                <div class="flex w-24">
                                    <InputLabel for="file_client" value="Client:" /><span class="red_star-700-2 ml-2">*</span>
                                </div>
                                <ContactLookup
                                    v-model:contact_id="form.client_contact_id"
                                    v-model:contact_name="display_name.client"
                                    v-model:the_mode="the_mode"
                                    v-model:added_contact_obj="added_contact_obj"
                                    :id="'file_client'"
                                    :folder_id="0"
                                    :next_field="'date_opened'"
                                    :state="{ mode: 'file_add' }"
                                    :firm_members="firm_members"
                                    :file_contacts="[]" />
                            </div>
                        </div>
                        <div class="flex">
                            <div class="w-1/2">
                                <InputError class="mt-2 ml-32" :message="form.errors.attorney_id" />
                            </div>
                            <div class="w-1/2">
                                <InputError class="mt-2 ml-24" :message="form.errors.client_contact_id" />
                            </div>
                        </div>

                            <!-- Date opened and SOL Date line -->
                        <div class="mt-4 flex items-center">
                            <div class="flex items-baseline w-1/2 p-0">
                                <InputLabel value="Opened:" class="w-32" />
                                <div>
                                    <input type="date" id="date_opened" v-model="form.date_opened" class="input input-sm input-bordered w-44" />
                                    <InputError class="mt-2" :message="form.errors.date_opened" />
                                </div>
                            </div>
                            <div class="flex items-baseline w-1/2">
                                <InputLabel value="SOL Date:" class="w-24" :class="{ 'text-gray-400': !solEnabled }"/>
                                <div>
                                    <input v-if="solEnabled && form.filetype_id" type="date" id="date_sol" v-model="form.date_sol" class="input input-sm input-bordered w-44"
                                    :class="{ 'input-disabled text-gray-400 bg-gray-100': !solEnabled }" :disabled="!solEnabled" />
                                    <InputError v-if="solEnabled && form.filetype_id" class="mt-2" :message="form.errors.date_sol" />
                                    <div v-if="!solEnabled && form.filetype_id" class="text-xs text-gray-500 mt-1">
                                        This file type does not use a Statute of Limitations date.
                                    </div>
                                </div>
                            </div>
                        </div>

                            <!-- Date filed and Court line -->
                        <div class="mt-4 flex items-center">
                            <div class="flex items-baseline w-1/2 p-0">
                                <InputLabel value="Filed:" class="w-32" />
                                <div>
                                    <input type="date" id="date_filed" v-model="form.date_filed" class="input input-sm input-bordered w-44" />
                                    <InputError class="mt-2" :message="form.errors.date_filed" />
                                </div>
                            </div>
                            <div class="flex items-baseline w-1/2 p-0">
                                <InputLabel value="Court:" class="w-24"/>
                                <div>
                                    <TextInput id="court_filed" class="w-64" v-model="form.court_filed" />
                                    <InputError class="mt-2" :message="form.errors.court_filed" />
                                </div>
                            </div>
                        </div>

                            <!-- Docket # and Our # line -->
                        <div class="mt-4 flex items-center">
                            <div class="flex items-baseline w-1/2 p-0">
                                <InputLabel value="Docket #:" class="w-32" />
                                <div class="mr-2">
                                    <TextInput id="docket_number" v-model="form.docket_number" />
                                    <InputError class="mt-2" :message="form.errors.docket_number" />
                                </div>
                            </div>
                            <div class="flex items-baseline w-1/2 p-0">
                                <InputLabel value="Our File #:" class="" />
                                <div class="ml-7 mr-3">
                                    <TextInput id="file_number" class="w-64" v-model="form.file_number" />
                                    <InputError class="mt-2" :message="form.errors.file_number" />
                                </div>
                            </div>
                        </div>

                            <!-- Summary line -->
                        <div class="mt-4 flex items-top">
                            <div class="w-32">
                                <InputLabel for="summary" value="Summary:" />
                            </div>
                            <div>
                                <textarea v-model="form.summary" id="summary" class="textarea textarea-bordered w-178 py-1 px-2" />
                                <InputError class="mt-2" :message="form.errors.summary" />
                            </div>
                        </div>

                            <!-- Date closed and Disposition line -->
                        <div class="mt-3 flex items-center">
                            <div class="flex items-baseline w-1/2 p-0">
                                <InputLabel value="Closed:" class="w-32"/>
                                <div>
                                    <input type="date" id="date_closed" v-model="form.date_closed" class="input input-sm input-bordered w-44" />
                                    <InputError class="mt-2" :message="form.errors.date_closed" />
                                </div>
                            </div>
                            <div class="flex items-baseline w-1/2 p-0">
                                <InputLabel value="Disposition:"  class="w-24"/>
                                <div>
                                    <TextInput id="final_disposition" class="w-64" v-model="form.final_disposition" />
                                    <InputError class="mt-2" :message="form.errors.final_disposition" />
                                </div>
                            </div>
                        </div>

                            <!-- Collapsed Referral and Fee Information lines -->
                        <div class="mt-8 collapse collapse-arrow border border-base-300 bg-base-300 rounded-box max-w-4xl">
                            <input type="checkbox" />
                            <div class="collapse-title font-semibold text-md">
                                Referral and Fee Information
                            </div>
                            <div class="collapse-content">
                                <div class="mt-4 flex items-center">
                                    <InputLabel value="Fee Arrangement:" class="w-40" />
                                    <select v-model="form.fee_arrangement" id="fee_arrangement" class="select select-bordered select-sm w-64">
                                        <option value="null" />
                                        <option value="Contingencey">Contingency</option>
                                        <option value="Hourly">Hourly</option>
                                        <option value="Flat">Flat</option>
                                    </select>

                                    <InputLabel value="Fee:" class="ml-16 w-28" />
                                    <div>
                                        <TextInput id="fee_amount" class="w-40" v-model="form.fee_amount" />
                                        <InputError class="mt-2" :message="form.errors.fee_amount" />
                                    </div>
                                </div>

                                <div class="mt-3 flex items-center">
                                    <InputLabel for="referred_by" value="Referred by:" class="w-40" />
                                    <div>
                                        <TextInput id="referred_by" class="w-48" v-model="form.referred_by" />
                                        <InputError class="mt-2" :message="form.errors.referred_by" />
                                    </div>
                                    <InputLabel for="referral_amount" value="Referral Amt:" class="ml-16 w-28" />
                                    <div>
                                        <TextInput id="referral_amount" class="w-40" v-model="form.referral_amount" />
                                        <InputError class="mt-2" :message="form.errors.referral_amount" />
                                    </div>
                                </div>
                            </div>
                        </div>

                            <!-- Save and Cancel Buttons line (only shown when form is not disabled) -->
                        <div v-show="true" class="flex mt-6 mb-2 justify-center">
                            <button type="button" href="" class="btn btn-primary w-36 mr-14" @click="fileform_click('ok')">Ok</button>
                            <button type="button" href="" class="btn btn-primary" @click="fileform_click('cancel')">Cancel</button>
                        </div>

                        <!-- END of File Info Form -->
                    </form>


                    <div class="h-5">

                    </div>
                </div>
            </div>
        </div>


        <!-- Put this part before </body> tag - SOL Modal -->
        <dialog id="sol_modal" class="modal">
            <div class="modal-box w-11/12 max-w-3xl">
                <h3 class="font-bold text-2xl text-center">Confirm: SOL Date</h3>
                <p class="text-xl mt-4">You have not entered a Statute of Limitations date for this file.</p>
                <p class="text-xl mt-4">Do you want to enter an SOL date for this file?</p>
                <div class="modal-action justify-center mt-12">
                    <button type="button" class="btn mr-10 w-28 gap-0" @click="handle_modal_buttons('sol_modal','yes')"><u>Y</u>es</button>
                    <button type="button" class="btn gap-0" @click="handle_modal_buttons('sol_modal','no')"><u>N</u>o</button>
                </div>
            </div>
        </dialog>


        <!-- Put this part before </body> tag - Confirm File Changemodal -->
        <dialog id="cancelcreate_modal" class="modal">
            <div class="modal-box w-11/12 max-w-3xl">
                <h3 class="font-bold text-2xl text-center">Cancel New File?</h3>
                <p class="text-xl mt-5">Do you want to cancel adding a new file?</p>
                <!-- <p class="text-xl mt-4">Any data entered into this form will be lost.</p> -->
                <div class="modal-action justify-center mt-12">
                    <button type="button" class="btn mr-10 w-28 gap-0" @click="handle_modal_buttons('cancelcreate_modal','yes')"><u>Y</u>es</button>
                    <button type="button" class="btn gap-0" @click="handle_modal_buttons('cancelcreate_modal','no')"><u>N</u>o</button>
                </div>
            </div>
        </dialog>

        <!-- Add Contact Form Modal -->
        <AddContactForm v-model:added_contact_obj="added_contact_obj" :id="'contact_modal_form'" />

    </AuthenticatedLayout>
</template>
