<script setup>
    import { nextTick, watch } from 'vue';
    import { useForm } from '@inertiajs/vue3';
    import InputError from "@/Components/InputError.vue";
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

    const added_contact_obj = defineModel('added_contact_obj');             // define the model for the object used on the parent form


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
                <div class="flex mt-6">
                    <div class="form-control w-36 max-w-xs mr-4">
                        <label for="theForm.title" class="label_sm-700 ml-2">
                            Title<span class="ml-2 red_star-700-2">*</span>
                        </label>
                        <select v-model="theForm.title" class="border border-gray-300 rounded p-2.5" id="theForm.title">
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
                        <div class="form-control w-56 max-w-xs mr-4">
                            <label for="theForm.first_name" class="label_sm-700 ml-2">
                                First Name<span class="red_star-700-2 ml-2">*</span>
                            </label>
                            <input v-model="theForm.first_name" type="text"
                                class="input input-bordered w-full max-w-xs" id="theForm.first_name" required autocomplete="off"/>
                            <InputError class="mt-2" :message="theForm.errors.first_name" />
                        </div>

                        <div class="form-control w-32 max-w-xs mr-4">
                            <label for="theForm.middle_name" class="label_sm-700 ml-2">
                                Middle
                            </label>
                            <input v-model="theForm.middle_name" type="text"
                                class="input input-bordered w-full max-w-xs" id="theForm.middle_name" />
                            <InputError class="mt-2" :message="theForm.errors.middle_name" />
                        </div>

                        <div class="form-control w-56 max-w-xs mr-4">
                            <label for="theForm.last_name" class="label_sm-700 ml-2">
                                Last Name<span class="red_star-700-2 ml-2">*</span>
                            </label>
                            <input v-model="theForm.last_name" type="text"
                                class="input input-bordered w-full max-w-xs" id="theForm.last_name" required />
                            <InputError class="mt-2" :message="theForm.errors.last_name" />

                        </div>

                        <div class="form-control w-32 max-w-xs mr-4">
                            <label for="theForm.srjr" class="label_sm-700 ml-2">
                                Sr/Jr
                            </label>
                            <input v-model="theForm.srjr" type="text" class="input input-bordered w-full max-w-xs" id="theForm.srjr" />
                            <InputError class="mt-2" :message="theForm.errors.srjr" />
                        </div>

                        <div class="form-control w-32 max-w-xs">
                            <label for="theForm.esqphd" class="label_sm-700 ml-2">
                                Esq/Ph.D
                            </label>
                            <input v-model="theForm.esqphd" type="text"
                                class="input input-bordered w-full max-w-xs" id="theForm.esqphd" />
                            <InputError class="mt-2" :message="theForm.errors.esqphd" />
                        </div>
                    </div>
                </div>
                <InputError class="mt-2" :message="theForm.errors.display_name" />

                <!-- Company Line starts-->
                <div class="flex mt-4">
                    <div class="form-control w-1/2 max-w-sm">
                        <label for="theForm.company" class="label_sm-700 ml-2">
                            Company
                        </label>
                        <input v-model="theForm.company" type="text" class="input input-bordered w-full" id="theForm.company" />
                        <InputError class="mt-2" :message="theForm.errors.company" />
                    </div>
                    <div v-if="theForm.title != 'Co.'" class="form-control w-1/2 max-w-sm ml-32">
                        <label for="theForm.business_title" class="label_sm-700 ml-2">
                            Business Title
                        </label>
                        <input v-model="theForm.business_title" type="text" class="input input-bordered w-full" id="theForm.business_title" />
                        <InputError class="mt-2" :message="theForm.errors.business_title" />
                    </div>
                </div>

                <!-- Address Line starts-->
                <div class="flex my-4">
                    <div class="form-control w-1/2 max-w-sm">
                        <label for="theForm.address" class="label_sm-700 ml-2">
                            Address
                        </label>
                        <textarea v-model="theForm.address" class="textarea textarea-bordered p-2 h-32 w-full" style="line-height: 1.3;" id="theForm.address"></textarea>
                        <InputError class="mt-2" :message="theForm.errors.address" />

                    </div>
                    <div class="form-control w-1/2 max-w-sm ml-32">
                        <label for="theForm.email" class="label_sm-700 ml-2">
                            Email
                        </label>
                        <input v-model="theForm.email" type="text" class="input input-bordered w-full" id="theForm.email" />
                        <InputError class="mt-2" :message="theForm.errors.email" />
                        <label for="theForm.email_alt" class="label_sm-700 ml-2 mt-2">
                            Alternate Email
                        </label>
                        <input v-model="theForm.email_alt" type="text" class="input input-bordered w-full" id="theForm.email_alt" />
                        <InputError class="mt-2" :message="theForm.errors.email_alt" />
                    </div>
                </div>

                <!-- Phones Line starts-->
                <span class="label_sm-700 ml-2">PHONES</span>
                <div class="flex space-x-4 border-2 rounded-md p-5">
                    <div class="form-control w-1/5 max-w-sm">
                        <label for="theForm.work_phone" class="label_sm-700 ml-2">
                            Work
                        </label>
                        <input v-model="theForm.work_phone" type="text" class="input input-bordered w-full" id="theForm.work_phone" />
                        <InputError class="mt-2" :message="theForm.errors.work_phone" />
                    </div>
                    <div class="form-control w-1/5 max-w-sm">
                        <label for="theForm.cell_phone" class="label_sm-700 ml-2">
                            Cell
                        </label>
                        <input v-model="theForm.cell_phone" type="text" class="input input-bordered w-full" id="theForm.cell_phone" />
                        <InputError class="mt-2" :message="theForm.errors.cell_phone" />
                    </div>
                    <div class="form-control w-1/5 max-w-sm">
                        <label for="theForm.home_phone" class="label_sm-700 ml-2">
                            Home
                        </label>
                        <input v-model="theForm.home_phone" type="text" class="input input-bordered w-full" id="theForm.home_phone" />
                        <InputError class="mt-2" :message="theForm.errors.home_phone" />
                    </div>
                    <div class="form-control w-1/5 max-w-sm">
                        <label for="theForm.fax_phone" class="label_sm-700 ml-2">
                            Fax
                        </label>
                        <input v-model="theForm.fax_phone" type="text" class="input input-bordered w-full" id="theForm.fax_phone" />
                        <InputError class="mt-2" :message="theForm.errors.fax_phone" />
                    </div>
                    <div class="form-control w-1/5 max-w-sm">
                        <label for="theForm.other_phone" class="label_sm-700 ml-2">
                            Other
                        </label>
                        <input v-model="theForm.other_phone" type="text" class="input input-bordered w-full" id="theForm.other_phone" />
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
