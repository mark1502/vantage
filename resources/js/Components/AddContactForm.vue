<script setup>
    import { nextTick, watch } from 'vue';
    import { useForm } from '@inertiajs/vue3';
    import InputError from "@/Components/InputError.vue";
    import InputLabel from "@/Components/InputLabel.vue";
    import TextInput from "@/Components/TextInput.vue";
    import axios from 'axios';

    const props = defineProps(['id']);
    let dialog_id = props.id + '_dialog';

    const theForm = useForm({
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
            work_phone: "",
            cell_phone: "",
            home_phone: "",
            fax_phone: "",
            other_phone: "",
            display_name: "",
            display_last_first: ""
            });

    let added_contact_obj = defineModel('added_contact_obj');             // define the model for the object used on the parent form


    watch( added_contact_obj.value, (setting) => {                          // watch added_contact_obj, and display or close modal based on value of display_modal
        if( setting.display_modal === true ) {
            document.getElementById(dialog_id).showModal();
        } else {
            document.getElementById(dialog_id).close();
        }
    });

    function setModalErrors( errors ) {
                                                                            // First, clear all errors on the form
        Object.keys(theForm).forEach( function (key) {                          // for each key in theForm
            if( key in theForm.errors ) theForm.errors[key] = '';               // if the key is in theForm.errors, clear the error
        });
                                                                            // Next, set errors on the theForm.errors object
        Object.keys(errors).forEach( function (key) {                           // for each key in theForm.errors
            theForm.errors[key] = errors[key] ? errors[key][0] : '';            // if the key is in errors, set the theForm.error, otherwise set it to empty string
        });
    }

    function buildDisplayNames() {
        if( theForm.title === 'Co.') {                                      // if the contact is a company
            theForm.display_name = theForm.company;                         // set the display name to the company name
            theForm.display_last_first = theForm.company;                   // set the display last first to the company name
        }
        else {                                                              // else, format the person's name as needed
            if( theForm.middle_name === null || theForm.middle_name === undefined) { theForm.middle_name = ''; }
            if( theForm.srjr === null || theForm.srjr === undefined) { theForm.srjr = ''; }
            if( theForm.esqphd === null || theForm.esqphd === undefined) { theForm.esqphd = ''; }

            theForm.display_name = theForm.first_name.trim();
            theForm.display_name += theForm.middle_name.trim().length != 0 ? ' ' + theForm.middle_name.trim() : "";
            theForm.display_name += ' ' + theForm.last_name.trim();
            theForm.display_name += theForm.srjr.trim().length != 0 ? ', ' + theForm.srjr.trim() : "";
            theForm.display_name += theForm.esqphd.trim().length != 0 ? ', ' + theForm.esqphd.trim() : "";

            theForm.display_last_first = theForm.last_name.trim() + ', ' + theForm.first_name.trim();
            theForm.display_last_first += theForm.middle_name.trim().length != 0 ? ' ' + theForm.middle_name.trim() : "";
            theForm.display_last_first += theForm.srjr.trim().length != 0 ? ', ' + theForm.srjr.trim() : "";
            theForm.display_last_first += theForm.esqphd.trim().length != 0 ? ', ' + theForm.esqphd.trim() : "";
        }
    }

    function clicked_contactModal_button( button ) {
        if( button === 'ok' ) {
            buildDisplayNames();

            axios.post('/new_contact_modal', theForm )                          // post the form to add the new contact to the db
                .then( function (response) {
                    if (response.data.added_contact_name != '' && response.data.added_contact_id != 0) {    // on success, if added name and id are returned

                        added_contact_obj.value.display_modal = false;          // close the modal
                        theForm.reset();                                        // clear the form

                        nextTick(() => {                                        // on nextTick, copy contact_id and name to the parent form, mark accept and added as true
                            added_contact_obj.value.id = response.data.added_contact_id;
                            added_contact_obj.value.name = response.data.added_contact_name;
                            added_contact_obj.value.accept = true;
                            added_contact_obj.value.new_contact_added = true;   // also record that a new contact was added
                        });
                    } else console.log('error - contact name or id was not returned from server - clicked_contactModal_button'); // NOTE: should add error handling/logging here
                })
                .catch(function (error) {
                    setModalErrors(error.response.data.errors)
                });

        } else if( button === 'cancel' ) {                  // if cancel button clicked, clear form and close modal
            theForm.reset();
            added_contact_obj.value.display_modal = false;
        }
    }

</script>

<template>
    <dialog :id="dialog_id" class="modal">
        <div class="modal-box w-11/12 max-w-6xl z-300">
            <h3 class="font-bold text-2xl text-center">Add New Contact</h3>
            <form :id="props.id" name="form4contact" class="max-w-5xl mx-auto mt-4" autocomplete="off">
                <!-- Name Line starts-->
                <div class="flex mt-8">
                    <div class="flex-col w-36 max-w-xs mr-4">
                        <div class="flex ml-2"><InputLabel for="theForm.title" value="Title" /><span class="text-sm text-red-600 ml-2">*</span></div>
                        <select v-model="theForm.title" class="select select-bordered select-sm text-base" id="theForm.title">
                            <option value="" disabled selected>Pick one</option>
                            <option>Mr.</option>
                            <option>Ms.</option>
                            <option>Mrs.</option>
                            <option>Miss</option>
                            <option>Dr.</option>
                            <option>Hon.</option>
                            <option>Co.</option>
                        </select>
                        <InputError class="mt-2" :message="theForm.errors.title" />
                    </div>
                    <div v-if="theForm.title != 'Co.'" class="flex">
                        <div class="flex-col w-56 max-w-xs mr-4">
                            <div class="flex ml-2"><InputLabel for="theForm.first_name" value="First Name" /><span class="text-sm text-red-600 ml-2">*</span></div>
                            <TextInput v-model="theForm.first_name" id="theForm.first_name" class="w-full max-w-xs" required />
                            <InputError class="mt-2" :message="theForm.errors.first_name" />
                        </div>

                        <div class="flex-col w-32 max-w-xs mr-4">
                            <div class="flex ml-2"><InputLabel for="theForm.middle_name" value="Middle" class="ml-2"/><span class="text-sm ml-2 invisible">*</span></div>
                            <TextInput v-model="theForm.middle_name" id="theForm.middle_name" class="w-full max-w-xs" />
                            <InputError class="mt-2" :message="theForm.errors.middle_name" />
                        </div>

                        <div class="flex-col w-56 max-w-xs mr-4">
                            <div class="flex ml-2"><InputLabel for="theForm.last_name" value="Last Name" /><span class="text-sm text-red-600 ml-2">*</span></div>
                            <TextInput v-model="theForm.last_name" id="theForm.last_name" class="w-full max-w-xs" required />
                            <InputError class="mt-2" :message="theForm.errors.last_name" />
                        </div>

                        <div class="flex-col w-32 max-w-xs mr-4">
                            <div class="flex ml-2"><InputLabel for="theForm.srjr" value="Sr/Jr" class="ml-2"/><span class="text-sm ml-2 invisible">*</span></div>
                            <TextInput v-model="theForm.srjr" id="theForm.srjr" class="w-full max-w-xs" />
                            <InputError class="mt-2" :message="theForm.errors.srjr" />
                        </div>

                        <div class="flex-col w-32 max-w-xs">
                            <div class="flex ml-2"><InputLabel for="theForm.esqphd" value="Esq/Ph.D" class="ml-2"/><span class="text-sm ml-2 invisible">*</span></div>
                            <TextInput v-model="theForm.esqphd" id="theForm.esqphd" class="w-full max-w-xs" />
                            <InputError class="mt-2" :message="theForm.errors.esqphd" />
                        </div>
                    </div>
                </div>
                <InputError class="mt-2" :message="theForm.errors.display_name" />

                <!-- Company Line starts-->
                <div class="flex mt-6">
                    <div class="flex-col w-1/2 max-w-sm">
                        <div class="flex ml-2"><InputLabel for="theForm.company" value="Company" />
                            <span v-if="theForm.title === 'Co.'" class="text-sm text-red-600 ml-2">*</span>
                            <span v-else class="text-sm ml-2 invisible">*</span>
                        </div>
                        <TextInput v-model="theForm.company" id="theForm.company" class="w-full" />
                        <InputError class="mt-2" :message="theForm.errors.company" />
                    </div>
                    <div v-if="theForm.title != 'Co.'" class="flex-col w-1/2 max-w-sm ml-32">
                        <div class="flex ml-2"><InputLabel for="theForm.business_title" value="Business Title" /><span class="text-sm ml-2 invisible">*</span></div>
                        <TextInput v-model="theForm.business_title" id="theForm.business_title" class="w-full" />
                        <InputError class="mt-2" :message="theForm.errors.business_title" />
                    </div>
                </div>

                <!-- Address Line starts-->
                <div class="flex my-5">
                    <div class="flex-col w-1/2 max-w-sm">
                        <InputLabel for="theForm.address" value="Address" class="ml-2"/>
                        <textarea v-model="theForm.address" id="theForm.address" class="textarea textarea-bordered text-base p-2 h-32 w-full"></textarea>
                        <InputError class="mt-2" :message="theForm.errors.address" />
                    </div>
                    <div class="flex-col w-1/2 max-w-sm ml-32">
                        <InputLabel for="theForm.email" value="Email" class="ml-2"/>
                        <TextInput v-model="theForm.email" id="theForm.email" class="w-full mb-3" />
                        <InputError class="mt-2" :message="theForm.errors.email" />

                        <InputLabel for="theForm.email_alt" value="Alternate Email" class="ml-2"/>
                        <TextInput v-model="theForm.email_alt" id="theForm.email_alt" class="w-full" />
                        <InputError class="mt-2" :message="theForm.errors.email_alt" />
                    </div>
                </div>

                <!-- Phones Line starts-->
                <span class="text-sm font-semibold ml-2">PHONES:</span>
                <div class="flex space-x-4 border border-base-content rounded p-4">
                    <div class="flex-col w-1/5 max-w-sm">
                        <InputLabel for="theForm.work_phone" value="Work" class="ml-2"/>
                        <TextInput v-model="theForm.work_phone" id="theForm.work_phone" class="w-full" />
                        <InputError class="mt-2" :message="theForm.errors.work_phone" />
                    </div>
                    <div class="flex-col w-1/5 max-w-sm">
                        <InputLabel for="theForm.cell_phone" value="Cell" class="ml-2"/>
                        <TextInput v-model="theForm.cell_phone" id="theForm.cell_phone" class="w-full" />
                        <InputError class="mt-2" :message="theForm.errors.cell_phone" />
                    </div>
                    <div class="flex-col w-1/5 max-w-sm">
                        <InputLabel for="theForm.home_phone" value="Home" class="ml-2"/>
                        <TextInput v-model="theForm.home_phone" id="theForm.home_phone" class="w-full" />
                        <InputError class="mt-2" :message="theForm.errors.home_phone" />
                    </div>
                    <div class="flex-col w-1/5 max-w-sm">
                        <InputLabel for="theForm.fax_phone" value="Fax" class="ml-2"/>
                        <TextInput v-model="theForm.fax_phone" id="theForm.fax_phone" class="w-full" />
                        <InputError class="mt-2" :message="theForm.errors.fax_phone" />
                    </div>
                    <div class="flex-col w-1/5 max-w-sm">
                        <InputLabel for="theForm.other_phone" value="Other" class="ml-2"/>
                        <TextInput v-model="theForm.other_phone" id="theForm.other_phone" class="w-full" />
                        <InputError class="mt-2" :message="theForm.errors.other_phone" />
                    </div>
                </div>

                <div class="modal-action">
                    <button type="button" class="btn mr-8 w-24" @click="clicked_contactModal_button( 'ok' )">OK</button>
                    <button type="button" class="btn" @click="clicked_contactModal_button( 'cancel' )">Cancel</button>
                </div>

            </form>
        </div>
    </dialog>
</template>
