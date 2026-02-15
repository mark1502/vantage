<script setup>
    import { ref, reactive, computed, nextTick, onUnmounted, onBeforeUnmount } from 'vue';
    import { useForm, router } from '@inertiajs/vue3';
    import InputError from "@/Components/InputError.vue";
    import axios from 'axios';

    import TextInput from "@/Components/TextInput.vue";
    import VueDatePicker from '@vuepic/vue-datepicker';
    import '@vuepic/vue-datepicker/dist/main.css'
    import InputLabel from "@/Components/InputLabel.vue";

    const the_mode = defineModel('the_mode');           // the_mode is passed in from the parent component, it can be set to 'file_edit' or 'file_show'

    defineExpose({ fileform_click, fileform_actions });

    const props = defineProps({
        file: Object,
        filetypes: Object,
        attorneys: Object,
        assigned_attorney_id: Number,
        client_name: String,
        // index_form: Object,
    });

    const file_form = useForm({
        formtype: "file",
        name: props.file.name,
        summary: props.file.summary,
        date_sol: props.file.date_sol,
        date_opened: props.file.date_opened,
        date_filed: props.file.date_filed,
        date_closed: props.file.date_closed,
        date_archived: props.file.date_archived,
        court_filed: props.file.court_filed,
        docket_number: props.file.docket_number,
        file_number: props.file.file_number,
        referred_by: props.file.referred_by,
        referral_amount: props.file.referral_amount,
        fee_arrangement: props.file.fee_arrangement,
        fee_amount: props.file.fee_amount,
        final_disposition: props.file.final_disposition,
        filetype_id: props.file.filetype_id,
        attorney_id: props.assigned_attorney_id,
        // current_page: props.index_form.current_page,
        // show: props.index_form.show,
        // comeback: true,                          // ???
    });

    let saved_file_form = { ...file_form }; // clone a copy of the file form

    let solEnabled = ref(false);                                                            // reactive variable to determine if the SOL date is enabled

    solEnabled.value = props.file.filetype.enable_file_SOL === 1 ? true : false;    // set solEnabled based on the file type's enable_file_SOL property
    
    if( solEnabled.value === false ) {                                                      // if the SOL date is not enabled, set it to null
        file_form.date_sol = null;
    }

    const fileform_isDirty = ref(false);

    let file_save_clicked = false;  // used for test when clicked to go outside index (like calendar, views or contacts)

        // function to determine if the file form has changed, if so, set the fileform_isDirty to true, but if the mode is 'file_add', then it is always dirty
    function form_change() {
        var key;
        var changeFound = false;
        
        for ( key in file_form ) {
            if( file_form[key] !== saved_file_form[key] ) {
                changeFound = true;
                break;
            }
        }

        if( changeFound === true ) {
            fileform_isDirty.value = true;
            the_mode.value = 'file_edit'
        } else {
            fileform_isDirty.value = false;
            the_mode.value = 'file_show'
        }
        // fileform_isDirty.value = JSON.stringify( file_form ) !== JSON.stringify( saved_file_form );
    }

    function fileform_click( what ) {
        switch( what ) {
        case 'ok':
            if( solEnabled.value === true && (file_form.date_sol == '' || file_form.date_sol == null) ) {           // if the SOL date is not set, display sol modal
                display_modal( 'sol', true );
            } else {                                                                // else, save the file form
                file_save_clicked = true;
                fileform_actions('submit');
            }
            break;
        case 'cancel':
            if( fileform_isDirty.value === true) {                                  // if the form is dirty, display the file changed modal
                display_modal( 'filechanged', true );
            } else {                                                                // else, just revert the form
                fileform_actions('revert');
            }
            break;
        }
    }

    function fileform_actions( action, comeback = true ) {
        switch( action ) {
            case 'submit':
                file_form.comeback = comeback;
                    // if called from a modal, close the modal
                display_modal( 'sol', false);
                display_modal( 'filechanged', false);
                fileform_isDirty.value = false;
                the_mode.value = 'file_show';

                file_form.put(route( 'files.update', { file: props.file.id } ),
                    { onSuccess: () =>  { 
                        // alert('File information saved successfully.');
                        // console.log('File information saved successfully.');
                    } });
                break;
            case 'revert':
                if( fileform_isDirty.value === true ) {     // if the form was dirty, copy back the original and set to false
                    Object.keys( file_form ).forEach( function ( key ) { file_form[key] = saved_file_form[key]; } );
                    fileform_isDirty.value = false;
                }
                display_modal('filechanged', false);
                the_mode.value = 'file_show';
                break;
        }
    }

    function SOL_setFocus() {               // set focus to SOL date field after modal is shown and answered yes
        display_modal( 'sol', false );
        nextTick(() => { document.getElementById("date_sol").focus(); });
    }


    function display_modal( which_modal, OnOff = null ) {
        let modal_name = '';
        if( which_modal == 'filechanged' ) modal_name = 'filechanged_modal';
        else if( which_modal == 'sol' ) modal_name = 'sol_modal';
        else if( which_modal == 'entrychanged' ) modal_name = 'entrychanged_modal';
        else if( which_modal == 'contact' ) modal_name = 'contact_modal';
        else if( which_modal == 'entrytype' ) modal_name = 'entrytype_modal';

        if( modal_name != '' && OnOff == 'status' ) {
            return document.getElementById(modal_name).checked;
        }
        else if( modal_name != '' && (OnOff == true || OnOff == 'show' || OnOff == 'on') ) {
            document.getElementById(modal_name).checked = true;
        }
        else if( modal_name != '' && (OnOff == false || OnOff == 'hide' || OnOff == 'off') ) {
            document.getElementById(modal_name).checked = false;
        }
        else if( modal_name != '' && OnOff == null ) {
            document.getElementById(modal_name).checked = !document.getElementById(modal_name).checked;
        }    
    }

    // onUnmounted(() => alert('unmounting file form'));
    onBeforeUnmount(() => {
        if( fileform_isDirty.value === true && file_save_clicked === false ) {
            let r = confirm("There unsaved changes in the file information. Do you want to save these changes?\n\nClick Ok to save the changes.");
            if (r == true) fileform_actions( 'submit', false );        
        }
    });

</script>
<template>
    <form @submit.prevent="" class="max-w-5xl mx-auto mt-2 px-8 py-4 bg-base-200 rounded" 
                            :class="{ 'border border-gray-400' : !fileform_isDirty, 'border-2 border-blue-800 rounded-lg' : fileform_isDirty }"
    >

        <!-- File name & Edit button line -->
        <div class="flex items-center">
            <div class="flex w-32">
                <InputLabel for="name" value="File Name:" /><span class="red_star-700-2 ml-2">*</span>
            </div>
            <div>
                <TextInput v-model="file_form.name" id="name" class="w-[460px]" required autocomplete="off" @input="form_change()" />
                <InputError class="mt-2" :message="file_form.errors.name" />
            </div>
            <div class="text-sm font-semibold ml-36">
                Fields marked with * are required.
            </div>
        </div>

        <!-- File type line (not currently used) -->
    <div class="mt-3 flex items-center">
        <div class="flex w-32">
            <InputLabel for="filetype_id" value="File Type:" disabled /><span class="red_star-700-2 ml-2">*</span>
        </div>
        <select v-model="file_form.filetype_id" @change="form_change()" class="select select-bordered select-sm w-64 disabled:text-base-content" disabled >
            <option v-if="!saved_file_form.filetype_id" :value="null">Select file type . . .</option>
            <option v-for="filetype in filetypes" :key="filetype.id" :value="filetype.id">{{ filetype.name }}</option>
        </select>
        <!-- <button @click="click_AddType" class="ml-6 btn btn-xs btn-outline btn-primary">Add New Type</button> -->
    </div>

        <!-- Attorney and Client Line -->
        <div class="mt-4 flex items-center">
            <div class="flex items-baseline w-1/2 p-0">
                <div class="flex w-32">
                    <InputLabel for="attorney_id" value="Attorney:" /><span class="red_star-700-2 ml-2">*</span>
                </div>
                <select v-model="file_form.attorney_id" id="attorney_id" @change="form_change()" class="select select-bordered select-sm w-64">
                    <!-- <option :value="null" /> -->
                    <option v-for="attorney in attorneys" :key="attorney.id" :value="attorney.id">
                        {{ attorney.display_last_first }}
                    </option>
                </select>
            </div>
            <div class="flex items-baseline w-1/2">
                <InputLabel value="Client:" class="w-24" />
                <!-- <input
                    :value="client_name"
                    type="text"
                    disabled
                    class="input input-sm input-bordered w-64 disabled:input-bordered disabled:text-base-content disabled:bg-base-200"
                /> -->
                <span class="text-sm text-base-content">{{ client_name }}</span>
            </div>
        </div>
        <InputError class="mt-2 ml-32" :message="file_form.errors.attorney_id" />

        <!-- Date opened and SOL Date line -->
        <div class="mt-4 flex items-center">
            <div class="flex items-baseline w-1/2 p-0">
                <InputLabel value="Opened:" class="w-32" />
                <div>
                    <!-- <VueDatePicker v-model="file_form.date_opened" uid="date_opened" model-type="yyyy-MM-dd" class="vdtp_main_date vdtp_date"
                        text-input hide-input-icon :enable-time-picker="false" auto-apply :clearable="false"
                        @closed="file_datepicker_closed('date_opened', 'f')" @blur="file_form_changed()" /> -->
                    <input type="date" id="date_opened" v-model="file_form.date_opened" class="input input-sm input-bordered w-44" @change="form_change()" @blur="form_change()"/>
                    <InputError class="mt-2" :message="file_form.errors.date_opened" />
                </div>
            </div>
            <div class="flex items-baseline w-1/2">
                <InputLabel value="SOL Date:" class="w-24" />
                <div>
                    <!-- <VueDatePicker v-model="file_form.date_sol" uid="date_sol" model-type="yyyy-MM-dd" class="vdtp_main_date vdtp_date"
                        text-input hide-input-icon :enable-time-picker="false" :auto-apply="true" :clearable="false"
                        @closed="file_datepicker_closed('date_sol', 'f')" @blur="file_form_changed()"/> -->
                    <input v-if="solEnabled" type="date" id="date_sol" v-model="file_form.date_sol" class="input input-sm input-bordered w-44"
                        @change="form_change()" @blur="form_change()"/>
                    <div v-if="!solEnabled" class="text-xs text-gray-500 mt-1">
                        N/A - '{{ props.file.filetype.name }}' file
                    </div>
                    <InputError class="mt-2" :message="file_form.errors.date_sol" />
                </div>
            </div>
        </div>

        <!-- Date filed and Court line -->
        <div class="mt-4 flex items-center">
            <div class="flex items-baseline w-1/2 p-0">
                <InputLabel value="Filed:" class="w-32" />
                <div>
                    <!-- <VueDatePicker v-model="file_form.date_filed" uid="date_filed" model-type="yyyy-MM-dd" class="vdtp_main_date vdtp_date"
                        text-input hide-input-icon :enable-time-picker="false" auto-apply :clearable="false"
                        @closed="file_datepicker_closed('date_filed', 'f')" @blur="file_form_changed()" /> -->
                    <input type="date" id="date_filed" v-model="file_form.date_filed" class="input input-sm input-bordered w-44"
                        @change="form_change()" @blur="form_change()"/>
                    <InputError class="mt-2" :message="file_form.errors.date_filed" />
                </div>
            </div>
            <div class="flex items-baseline w-1/2 p-0">
                <InputLabel value="Court:" class="w-24"/>
                <div>
                    <TextInput id="court_filed" class="w-64" v-model="file_form.court_filed" @input="form_change()" />
                    <InputError class="mt-2" :message="file_form.errors.court_filed" />
                </div>

            </div>

        </div>

        <!-- Docket # and Our # line -->
        <div class="mt-4 flex items-center">
            <div class="flex items-baseline w-1/2 p-0">
            <InputLabel value="Docket #:" class="w-32" />
            <div class="mr-2">
                <TextInput id="docket_number" v-model="file_form.docket_number" @input="form_change()" @blur="form_change()" />
                <InputError class="mt-2" :message="file_form.errors.docket_number" />
            </div>
            </div>
            <div class="flex items-baseline w-1/2 p-0">

            <InputLabel value="Our File #:" class="" />
            <div class="ml-7 mr-3">
                <TextInput id="file_number" class="w-64" v-model="file_form.file_number" @input="form_change()" @blur="form_change()" />
                <InputError class="mt-2" :message="file_form.errors.file_number" />
            </div>
            </div>
        </div>

        <!-- Summary line -->
        <div class="mt-4 flex items-top">
            <div class="w-32">
                <InputLabel for="summary" value="Summary:" />
            </div>
            <div>
                <textarea v-model="file_form.summary" id="summary" class="textarea textarea-bordered w-[730px] py-1 px-2" @input="form_change()" />
                <InputError class="mt-2" :message="file_form.errors.summary" />
            </div>
        </div>

        <!-- Date closed and Disposition line -->
        <div class="mt-3 flex items-center">
            <div class="flex items-baseline w-1/2 p-0">
                <InputLabel value="Closed:" class="w-32"/>
                <div>
                    <!-- <TextInput id="date_closed" type="date" class="w-44" v-model="file_form.date_closed" /> -->
                    <!-- <VueDatePicker v-model="file_form.date_closed" uid="date_closed" model-type="yyyy-MM-dd" class="vdtp_main_date vdtp_date"
                        text-input hide-input-icon :enable-time-picker="false" auto-apply :clearable="false"
                        @closed="file_datepicker_closed('date_closed', 'f')" @blur="file_form_changed()" /> -->
                    <input type="date" id="date_closed" v-model="file_form.date_closed" class="input input-sm input-bordered w-44"
                        @change="form_change()" @blur="form_change()"/>
                    <InputError class="mt-2" :message="file_form.errors.date_closed" />
                </div>
            </div>
            <div class="flex items-baseline w-1/2 p-0">
                <InputLabel value="Disposition:"  class="w-24"/>
                <div>
                    <TextInput id="final_disposition" class="w-64" v-model="file_form.final_disposition"
                        @input="form_change()" />
                    <InputError class="mt-2" :message="file_form.errors.final_disposition" />
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
                    <select v-model="file_form.fee_arrangement" id="fee_arrangement"
                        @change="form_change()"
                        class="select select-bordered select-sm w-64">
                        <option value="null" />
                        <option value="Contingencey">Contingency</option>
                        <option value="Hourly">Hourly</option>
                        <option value="Flat">Flat</option>
                    </select>

                    <InputLabel value="Fee:" class="ml-16 w-28" />
                    <div>
                        <TextInput id="fee_amount" class="w-40" v-model="file_form.fee_amount"
                            @input="form_change()" />
                        <InputError class="mt-2" :message="file_form.errors.fee_amount" />
                    </div>
                </div>

                <div class="mt-3 flex items-center">
                    <InputLabel for="referred_by" value="Referred by:" class="w-40" />
                    <div>
                        <TextInput id="referred_by" class="w-48" v-model="file_form.referred_by"
                            @input="form_change()" />
                        <InputError class="mt-2" :message="file_form.errors.referred_by" />
                    </div>
                    <InputLabel for="referral_amount" value="Referral Amt:" class="ml-16 w-28" />
                    <div>
                        <TextInput id="referral_amount" class="w-40" v-model="file_form.referral_amount"
                            @input="form_change()" />
                        <InputError class="mt-2" :message="file_form.errors.referral_amount" />
                    </div>
                </div>
            </div>
        </div>

        <!-- Save and Cancel Buttons line (only shown when form is not disabled) -->
        <div v-show="true" class="flex mt-6 mb-2 justify-center">
            <button type="button" href="" class="btn btn-primary w-36 mr-14" :disabled="fileform_isDirty == false" @click="fileform_click('ok')">Save changes</button>
            <button type="button" href="" class="btn btn-primary" :disabled="fileform_isDirty == false" @click="fileform_click('cancel')">Cancel</button>
        </div>

        <!-- END of File Info Form -->
    </form>

    
        <!-- Put this part before </body> tag - SOL Modal -->
        <input hidden type="checkbox" id="sol_modal" class="modal-toggle" />
        <div class="modal">
            <div class="modal-box w-11/12 max-w-3xl">
                <h3 class="font-bold text-2xl text-center">Confirm: SOL Date</h3>
                <p class="text-xl mt-4">You have not entered a Statute of Limitations date for this file.</p>
                <p class="text-xl mt-4">Do you want to enter an SOL date for this file?</p>
                <div class="modal-action justify-center mt-12">
                    <button type="button" class="btn mr-10 w-28 gap-0" @click="SOL_setFocus"><u>Y</u>es</button>
                    <button type="button" class="btn gap-0" @click="fileform_actions('submit')"><u>N</u>o</button>
                </div>
            </div>
        </div>


        <!-- Put this part before </body> tag - Confirm File Changemodal -->
        <input hidden type="checkbox" id="filechanged_modal" class="modal-toggle" />
        <div class="modal">
            <div class="modal-box w-11/12 max-w-3xl">
                <h3 class="font-bold text-2xl text-center">Confirm: File Changes</h3>
                <p class="text-xl mt-4">Some file information has been changed.</p>
                <p class="text-xl mt-4">Do you want to save these changes?</p>
                <div class="modal-action justify-center mt-12">
                    <button type="button" class="btn mr-10 w-28 gap-0" @click="fileform_actions('submit')"><u>Y</u>es</button>
                    <button type="button" class="btn gap-0" @click="fileform_actions('revert')"><u>N</u>o</button>
                </div>
            </div>
        </div>

</template>
