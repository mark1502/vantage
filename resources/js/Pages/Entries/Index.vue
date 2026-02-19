<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';

import FileForm from "@/Pages/Files/FileForm.vue";
import FileLookup from "@/Pages/Files/FileLookup.vue";
import EntryForm from "@/Pages/Entries/EntryForm.vue";


import { Head, Link, useForm, router } from '@inertiajs/vue3';
import { reactive, ref, onMounted, onUnmounted, onUpdated, nextTick, inject, computed } from "vue";

import { useTheme } from '@/Composables/useTheme';
import Pagination from '@/Components/Pagination.vue'

// Get access to theme management
const { theme, setTheme } = useTheme();

const urlParams = new URLSearchParams(window.location.search);

const props = defineProps({
    file: Object,
    entries: Object,
    view_folder_id: Number,
    expecting_response: Object,
    firm_members: Object,
    attorneys: Object,
    filetypes: Object,
    folders: Object,
    file_contacts: Object,
    assigned_attorney_id: Number,
    client_name: String,
    contact_role_ids: Array,
    role_options: Object,
    file_contact_roles: Array,
});

const state = reactive({
    row: 0,                             // current list row
    folder_name: '',                    // used for filepart - should I rename it to filepart?
    folder_row: 0,
    show: props.entries.per_page,       // how many rows to show in list - if not set, then show is 15 set in the controller index
    tab: 2,                             // current tab (file info sheet or entry list)
    mode: 'browse',                     // All Modes: browse, entry_add, entry_edit, entry_delete, file_show, file_edit
    view: 'file',                       // viewing a file - used to distinguish between "File" and "View" (memos, etc.) for the entry form
    add_folder_id: 0,
    switch_file: false,
});

state.folder_name = urlParams.get('filepart');

const index_form = reactive({
    formtype: "entry",
    current_page: urlParams.get('page'),
    show: urlParams.get('show'),
    filepart: state.folder_name,
});

const hotkey_pressed = ref();
const keep_row = ref();
const EntryForm_ref = ref(null);        // ref used to call update_disp method in EntryForm child component
const FileForm_ref = ref(null);         // ref used to call fileform_click or fileform_actions methods in FileForm child component

const table_heading = reactive({
    date1: "Date",
    date2: "Sent/Received",
    from: "From",
    to: "To",
    entrytype: "Type",
});

const search = ref('');                 // this doesn't seem to be used here

const disp = reactive({
    listFormat: 1,                      // the format of the main listbox
});

const folder_singular = [  // this array is used to display the singular name of a folder
    'Correspondence',
    'Pleading',
    'Discovery',
    'Document',
    'Memo',
    'Event',
    'To-Do',
    'Phone Message',
    'Medical Record',
    'Medical Bill',
    'Case Cost',
];

function entryList_click(what, index = null) {                            // there was a click on the list or list buttons
    switch (what) {
        case 'list':                                                            // on single click on list
        case 'list_double':                                                     // on double clickon list
            if( state.mode === 'browse' && index !== null ) {                // if browse mode && index is not null
                state.row = index;                                              // set state.row
                state.folder_row = getFolderRow();                              // set the folder row

                if( what === 'list_double' ) list_actions('edit');              // if double_click, start the edit mode
            }
            break;
        case 'right':
            alert('right!');
            break;
        case 'add_button':                                                      // clicked the add button
            if( props.view_folder_id === 0 ) {                                   // if viewing the file timeleine
                document.getElementById('timeline_add_modal').checked = true;   // display modal to select folder for the new entry
            } else {                                                            // else
                state.add_folder_id = props.view_folder_id;                     // set the add folder
                list_actions('add');                                          // start add mode
            }
            break;
        case 'timeline_add_close':                                                       // timeline add modal has selected the folder, so close the modal and start add mode
            document.getElementById('timeline_add_modal').checked = false;
            list_actions('add');
            break;
    }
}


function list_actions(action) {
    state.mode = 'entry_' + action;                                       // set the mode based on the action
}


function display_modal(which_modal, OnOff = null) {                                       // opens or closes a modal
    let modal_name = '';
    if( which_modal === 'filechanged' ) modal_name = 'filechanged_modal';
    else if( which_modal === 'sol' ) modal_name = 'sol_modal';
    else if( which_modal === 'entrychanged' ) modal_name = 'entrychanged_modal';
    else if( which_modal === 'timeline_add' ) modal_name = 'timeline_add_modal';
    else if( which_modal === 'confirm_delete' ) modal_name = 'confirm_delete_modal';

    if( modal_name != '' && OnOff === 'status' ) {
        return document.getElementById(modal_name).checked;
    }
    else if( modal_name != '' && (OnOff == true || OnOff === 'show' || OnOff === 'on') ) {
        document.getElementById(modal_name).checked = true;
    }
    else if( modal_name != '' && (OnOff == false || OnOff === 'hide' || OnOff === 'off') ) {
        document.getElementById(modal_name).checked = false;
    }
    else if( modal_name != '' && OnOff == null ) {
        document.getElementById(modal_name).checked = !document.getElementById(modal_name).checked;
    }
}

function refreshEntryList() {                                                       // function is called from filepart droplist - reloads the entrylist or shows the file info tab
    let isInfoTab = state.folder_name === 'info' ? true : false;                    // is the file info tab to be displayed? (T/F)
    let isContactsTab = state.folder_name === 'file_contacts' ? true : false;       // is the file contacts tab to be displayed? (T/F)

    state.tab = isInfoTab ? 1 : (isContactsTab ? 3 : 2);                           // state tab - file info (1), entries (2), or file contacts (3)
    state.mode = isInfoTab ? 'file_show' : 'browse';                                // state mode is 'file_show', otherwise 'browse'

    if( isInfoTab ) {                                                              // if infotab, then blur active element
        document.activeElement.blur();
    } else if( isContactsTab ) {                                                   // if contacts tab, reload contact roles
        router.reload({
            only: ['file_contact_roles'],
            replace: true,
            headers: { 'X-Custom-Refresh': 'ContactRoles' },
        });
        document.activeElement.blur();
    } else {                                                                        // else, if not infotab, then reload the entries
        router.reload({
            only: ['entries', 'view_folder_id'],
            replace: true,
            data: {
                filepart: state.folder_name,
                page: 1,
                show: state.show,
            },
            headers: { 'X-Custom-Refresh': 'Entries' },                             // use custom header to set the refresh on the server
        });                                                                         // this avoids adding parameters to the url which caused other problems

        update_listFormat();
        document.activeElement.blur();
    }
}

function update_listFormat() {                                                          // sets the format of the listox
    switch (state.folder_name ) {
        case 'correspondence':
        case 'pleadings':
        case 'discovery':
        case 'memos':
        case 'phone':
        case 'medrecs':
        case 'medbills':
            disp.listFormat = 1;
            break;
        case 'events':
        case 'todo':
            //disp.listFormat = 2;  Don't use this format for events or todo
            disp.listFormat = 1;
            break;
        case 'documents':
        case 'costs':
            disp.listFormat = 3;
            break;
        default:
            // disp.listFormat = 4;  Don't use this 'All' format for now
            disp.listFormat = 1;
            break;
    }

    let f = getFolderRow( state.folder_name );
    
    table_heading.date1 = props.folders[f].date1_prompt;
    table_heading.date2 = props.folders[f].date2_prompt;
    table_heading.from = props.folders[f].from_prompt;
    table_heading.to = props.folders[f].to_prompt;
    table_heading.entrytype = props.folders[f].entrytype_prompt;
}


function folder_shortcut(what) {                                                      // keyboard shortcuts to folders
    let folder_name = {
        't': 'all',
        'c': 'correspondence',
        'p': 'pleadings',
        'e': 'events',
        'f': 'info'
    };

    state.folder_name = folder_name[what];                                       // set the folder name based on the keyboard shortcut
    refreshEntryList();                                                   // refresh the entry list
}


function keypress_handler(e) {
    if( state.mode === 'browse' ) {
        if( (e.altKey && e.key === 'a' && !e.ctrlKey) ) {                        //alt A - Add button
            e.preventDefault();
            list_actions('add');
        } else if( (e.altKey && e.key === 'e') && !e.ctrlKey ) {                 // alt E - Edit button
            e.preventDefault();
            list_actions('edit');
        } else if( e.altKey && e.ctrlKey && e.key >= 'a' && e.key <= 'z' ) {     // CtrlAlt a - z  Folder shortcuts
            e.preventDefault();
            folder_shortcut(e.key);
        }

        const theseKeys = ['Escape', 'ArrowDown', 'ArrowUp', 'Enter', 'PageUp', 'PageDown'];       // preventDefault on these keypresses
        if( theseKeys.includes(e.key) ) e.preventDefault();

        if( e.key === 'Escape' ) {                                                                  // Escape key pressed
            if( state.switch_file === true ) state.switch_file = false;                                  // if while switching file, close switch file
            else if( display_modal('timeline_add', 'status') === true ) close_timeline_add_modal();      // else if timeline add modal, then close it
            else router.get(route('files.index'));                                                      // else, go back to the files index
        }
        else if( e.key === 'Enter' ) {                                                            // Enter key pressed, so edit
            list_actions('edit');
        }
        else if( e.key === 'ArrowDown' && state.row < (props.entries.data.length - 1) ) {         // ArrowDown and not at list bottom
            state.row++;                                                                                // increase the state.row (list row)
            state.folder_row = getFolderRow();                                                          // get the folder row of the entry
        }         
        else if( e.key === 'ArrowUp' && state.row > 0 ) {                                         // ArrowUp and not at list top
            state.row--;                                                                                // decrease state.row
            state.folder_row = getFolderRow();                                                          // get the folder row of the entry
        }
        else if( e.key === 'PageUp' && props.entries.current_page > 1 ) {                         // PageUp and current page > 1
            router.get(props.entries.prev_page_url);                                                // router get the url of the preceeding page
        }
        else if( e.key === 'PageDown' && props.entries.current_page < props.entries.last_page ) { // PageDown and not on last page
            router.get(props.entries.next_page_url);                                                // router get the url of next page
        }
    } 
    else if( state.mode === 'entry_add' || state.mode === 'entry_edit' ) {                        // Else, if we're adding or editing
        if( e.key === 'Escape' ) {                                                                  // if escape is pressed
            e.preventDefault();                                                                         // preventDefault
            hotkey_pressed.value = e;                                                                   // set the hotkey being watched in the form
        }

        if( (document.getElementById('addcancelled_modal' ).checked === true                       // if addcancelled_modal is active
            || document.getElementById('entrychanged_modal' ).checked === true)                     // or if entrychanged_modal is active
            && (e.key === 'y' || e.key === 'Y' || e.key === 'n' || e.key === 'N') ) {             // and the 'y' or 'n' key is pressed
            e.preventDefault();                                                                         // preventDefault
            hotkey_pressed.value = e;                                                                   // trigger the hotkey
        }
    } 
    else if( state.mode === 'file_show' ) {                                                     // if file_show mode (viewing file info)
        if( e.key === 'Escape' ) {                                                                  // if escape is pressed
            e.preventDefault();                                                                         // preventDefault
            router.get(route('files.index'));                                                           // go back to the files index
        } else if( e.altKey && e.ctrlKey && e.key >= 'a' && e.key <= 'z' ) {                        // CtrlAlt a - z  Folder shortcuts
            e.preventDefault();
            folder_shortcut(e.key);
        }

    } 
    else if( state.mode === 'file_edit' ) {                                                      // if file_edit mode ( so we know there are changes to the file info form )
        if( e.key === 'Escape' ) {                                                                  // if escape is pressed
            e.preventDefault();                                                                         // preventDefault
            if( display_modal('filechanged', 'status' ) === false ) {                                   // if filechanged modal is not active
                display_modal('filechanged', true);                                                         // display the filechanged modal
            }
        }
        if( document.getElementById('filechanged_modal').checked === true ) {                   // if filechanged modal is active
            if( e.key === 'y' || e.key === 'Y' ) {                                                   // if 'y' or 'Y' is pressed
                e.preventDefault();                                                                     // preventDefault
                FileForm_ref.value.fileform_click( 'ok' );                                              // complete form - save the changes
            } else if( e.key === 'n' || e.key === 'N' ) {                                            // if 'n' or 'N' is pressed
                e.preventDefault();                                                                     // preventDefault
                FileForm_ref.value.fileform_actions( 'revert' );                                        // revert the changes
            }
        }
    }
}; // end keypress_handler function


function getFolderRow( folder_name_in = null  ) {                                    // function determines which row in the folders array to use
    let entries = props.entries.data;                                                   // shortcut entries for cleaner code
    let folder_id = 0
    let folder_row = 0;

    if( folder_name_in !== null ) {                                                 // if a folder name is passed in
        props.folders.forEach( (folder, index) => {                                     // foreach folder in the props.folders array
            if( folder.name.toLowerCase() === folder_name_in ) {                            // if folder name matches the passed in name, set the folder_id
                folder_id = folder.id;                                                      
            }
        });
        if( folder_id < 1 ) folder_id = 0;                                              // if not found, set to 0
    } else if( state.mode === 'entry_add' ) {                                       // else if adding an entry, set the folder id to that of added entry
        folder_id = state.add_folder_id;
    } else if( entries.length > 0 && state.row < entries.length ) {                 // else if list has entries && the state.row is <= the number of entries (0 based)
        folder_id = entries[state.row].folder_id;                                       // use folder id of current entry
    } else {                                                                        // else, use the view_folder_id prop
        folder_id = props.view_folder_id;
    }

    folder_row = folder_id - 1;                                                         // folder_row is 1 less than the folder_id
    if( folder_row < 0 ) folder_row = 0;                                                // folder_row cannot be less than 0

    return folder_row;
}


function getFolderData( whichData, singular = null ) {                                  // returns data about a folder, and if name is requested and singular is true, it returns the singular name 
    let dataBack = null;
    let row = getFolderRow();                                                           // get the row number to access in the props.folders object
    let folder = props.folders[row];                                                    // set a shortcut for cleaner code

    if( singular != null && whichData === 'name' ) {                                   // if singular and name is requested, return the singular name
        dataBack = folder_singular[row];
    } else if( whichData === 'input_time' || whichData.substring(0, 4) === 'hide' ) {  // if input_time or whether hiding a prompt field
        dataBack = folder[whichData] ? folder[whichData] : false;                       // boolean - return false if not set
    } else {
        dataBack = folder[whichData] ? folder[whichData] : '';                          // everything else returns a string
    }

    return dataBack;
}


const emptyRows = computed(() => {
    return Math.max(0, state.show - props.entries.data.length);
});

function setEntryClass( index ) {                                                       // sets the color of the listbox entries
    let entry = props.entries.data[index];                                                   // shortcut for cleaner code
    let textcolor = 'text-base-content';
    let bgcolor = 'bg-base-100';
    let border = '';

    if( state.mode === 'entry_edit' || state.mode === 'entry_add' ) {                  // if adding or entering, then gray out the listbox entries
        textcolor = 'text-base-300';
        bgcolor = 'bg-base-100';
     } else if( entry.expecting_response ) {                                            // else if entry expects a response, set the color
        textcolor = determine_response_expectation( entry.date_response_expected ) === 'Awaiting response' ? 'text-green-600' : 'text-red-500';
    }

    if( index === state.row ) {                                                        // if this row is the highlighted row
        textcolor = 'text-gray-900 dark:text-gray-900';
        if( entry.expecting_response ) {
            textcolor = determine_response_expectation( entry.date_response_expected ) === 'Awaiting response' ? 'text-green-600' : 'text-red-500';
        }
        bgcolor = 'bg-blue-200 dark:bg-blue-200';
        border = 'border-l-4 border-l-blue-600';
    }

    return textcolor + ' ' + bgcolor + ' ' + border;
}


function reformat_date(dt, input_time = false, all_day = false) {                       // returns a formatted date or date & time
    if( dt === null || dt === undefined ) {
        return '';                                                                          // if date is null or undefined, return empty string
    } else {
        dt = dt.toString();                                                                 // convert to string if not already
        if( input_time == true ) {
            let the_date = dt.slice(5, 7) + '/' + dt.slice(8, 10) + '/' + dt.slice(2, 4);
            let the_hour = dt.slice(11, 13);
            let the_minutes = dt.slice(14, 16);
            let hour_12 = the_hour;
            let a_p = "am";
            if( the_hour > 12 ) {
                hour_12 = the_hour - 12;
                a_p = "pm";
            }
            if( all_day == false ) {
                return the_date + ', ' + hour_12 + ':' + the_minutes + '' + a_p;
            } else {
                return the_date + ' (all day)';
            }

        } else {
            return dt.slice(5, 7) + '/' + dt.slice(8, 10) + '/' + dt.slice(2, 4);
        } // end if input_time or all
    }
}


function determine_response_expectation( date_expected ) {                              // determines if an expected response is overdue
    let date1 = new Date(date_expected);
    let date2 = new Date();
    let sendback = date2 > date1 ? "Awaiting response (overdue)" : "Awaiting response";

    return sendback;
}


function SwitchFile() {                                                       // if switchfile is activated, show the lookup control and set focus to it
    if( state.mode === 'browse' || state.mode === 'info' ) {
        state.switch_file = true;
        nextTick(() => { document.getElementById('file_lookup').focus(); });
    }
}


function close_timeline_add_modal() {                                                   // closes the timeline_add_modal
    document.getElementById('timeline_add_modal').checked = false;
}


onMounted(() => document.addEventListener('keydown', keypress_handler));              // onMounded, set the keypress_handler event listener


onUpdated(() => {                                                                      // On update to the dom, may call update_disp in EntryForm
    if( state.tab === 2 ) {                                                           // if the tab is the entries tab
        state.row = keep_row.value > 0 ? keep_row.value : 0                            // if keep_row > 0, use that, otherwise state.row = 0

        if( state.mode === 'browse' ) {
            EntryForm_ref.value.update_disp();                                          // call update_disp from the EntryForm
        }
    }
});

onUnmounted(() => document.removeEventListener('keydown', keypress_handler));         // onUMounded, remove the keypress_handler event listener

state.folder_row = getFolderRow();

update_listFormat();                                                                    // sets the format of the listbox

if( props.view_folder_id == -1 || state.folder_name === 'info' ) {                       // may start the index component showing the file info tab, if passed in -1 or folder is 'info'
    state.tab = 1;
    state.mode = 'file_show';
    document.activeElement.blur();
} else if( props.view_folder_id == -2 || state.folder_name === 'file_contacts' ) {      // may start showing the file contacts tab
    state.tab = 3;
    state.mode = 'browse';
    document.activeElement.blur();
}

</script>

<template>

    <Head title="File Entries" />

    <AuthenticatedLayout>

        <template #header>
            <div class=" flex items-baseline bg-base-100">
                <div class="text-base w-2/5 justify-items-start text-left ml-3">
                    <div v-if="state.switch_file == false" class="flex items-baseline">
                        <!-- <Link :href="('/files')" class="hover:underline font-semibold text-blue-800 ml-2"> -->
                        <Link :href="route('files.index')" class="hover:underline hover:text-blue-500 font-semibold text-base-content ml-2">                        
                            File
                        </Link>
                        <span class="font-semibold text-base-content mx-2">></span>
                        <div class="border border-gray-400 rounded ml-1 pl-2 py-1 text-base-content bg-base-100 w-80" @click="SwitchFile()">
                            {{ props.file.name }}
                            <!-- Show Button if mode is Browse or file_show -->
                            <button v-if="state.mode === 'browse' || state.mode === 'file_show'" type="button" class="btn btn-xs border-0 bg-neutral-200 float-end">
                                <img src="/images/search-50B.png" width="16px" height="16px">
                            </button>
                        </div>
                    </div>
                    <div v-else-if="state.switch_file == true">
                        <FileLookup v-model:switch_file="state.switch_file" :state="state" />
                    </div>
                </div>

                <div class="w-2/5 justify-items-start text-left">
                    <div v-if="state.mode === 'browse' || state.mode === 'file_show'">
                        <label for="folderlist" class="font-semibold text-base text-base-content ml-6 mr-1">
                            Folder:
                        </label>
                        <select v-model="state.folder_name" id="folderlist"
                            class="border border-gray-400 rounded p-1 font-normal text-base text-base-content bg-base-100 w-56"
                            @change="refreshEntryList()">
                            <optgroup label="File Folders:" class="my-2">
                                <option v-if="file.filetype.has_correspondence === 1" value="correspondence">
                                    Correspondence
                                </option>
                                <option v-if="file.filetype.has_pleadings === 1" value="pleadings">
                                    Pleadings and Motions
                                </option>
                                <option v-if="file.filetype.has_discovery === 1" value="discovery">
                                    Discovery
                                </option>
                                <option v-if="file.filetype.has_documents === 1" value="documents">
                                    Documents
                                </option>
                                <option v-if="file.filetype.has_memos === 1" value="memos">
                                    Memos
                                </option>
                                <option v-if="file.filetype.has_events === 1" value="events">
                                    Events and Deadlines
                                </option>
                                <option v-if="file.filetype.has_todo === 1" value="todo">
                                    To-Do (for this file)
                                </option>
                                <option v-if="file.filetype.has_phone === 1" value="phone">
                                    Phone Messages
                                </option>
                                <option v-if="file.filetype.has_medrecs === 1" value="medrecs">
                                    Medical Records
                                </option>
                                <option v-if="file.filetype.has_medbills === 1" value="medbills">
                                    Medical Bills
                                </option>
                                <option v-if="file.filetype.has_costs === 1" value="costs">
                                    Case Costs
                                </option>
                            </optgroup>
                            <optgroup label="Timeline:">
                                <option value="all">File Timeline</option>
                            </optgroup>
                            <optgroup label="Contacts:">
                                <option value="file_contacts">File Contacts</option>
                            </optgroup>
                            <optgroup label="File Details:" class="my-2">
                                <option value="info">File Details</option>
                            </optgroup>
                        </select>
                    </div>
                    <div v-if="state.mode === 'file_edit'">
                        <p class="font-bold text-xl text-blue-700 pl-20">
                            File Details
                        </p>
                    </div>
                </div>

                <div class="w-1/5 pr-16">
                    <div v-if="state.mode === 'entry_edit' || state.mode === 'file_edit'" class="text-blue-800 dark:text-blue-400 text-lg font-semibold text-right pr-8">
                        Edit Mode: On
                    </div>
                    <div v-if="state.mode === 'entry_add'" class="text-blue-800 dark:text-blue-400 text-lg font-semibold text-right pr-8">
                        Add Mode
                    </div>
                </div>
            </div>
        </template>

        <div class="pt-3">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                <div class="bg-base-300 p-4 min-h-dvh sm:rounded-lg" id="EntriesScreen" name="EntriesScreen">

                    <!-- Tab 1 - File Information Tab - Starts Here-->
                    <div v-if="state.tab === 1">
                        <!-- File Info Form -->
                        <FileForm
                            ref="FileForm_ref"
                            v-model:the_mode="state.mode"
                            :file="props.file"
                            :attorneys="props.attorneys"
                            :filetypes="props.filetypes"
                            :assigned_attorney_id="props.assigned_attorney_id"
                            :client_name="props.client_name"
                            :index_form="index_form"
                        />
                    </div>
                    <!-- end of tab 1 content panel -->

                    <!-- Tab 2 - File Entries Tab - Starts Here-->
                    <div v-if="state.tab === 2">
                        <div class="flex">
                            <!-- Start of Left Side -->
                            <div name="left-side" class="w-1/2 ml-1 mt-1">
                                <div v-if="state.mode === 'entry_edit'" class="w-full font-bold text-xl text-base-content ml-2 mb-2">
                                    Edit Entry <span class="font-normal text-base">(in {{ getFolderData('name') }})</span>
                                </div>
                                <div v-else-if="state.mode === 'entry_add'"
                                    class="w-full font-bold text-xl text-base-content ml-2 mb-2">
                                    Add New {{ getFolderData('name') }} Entry
                                </div>
                                <!--Start of entries table -->
                                <!-- show table if there are entries, but not if adding a new entry -->
                                <div v-if="entries.data.length && state.mode != 'entry_add'">
                                    <div>
                                        <table id="folderlist" tabindex="0" class="w-full border border-base-content text-sm font-sans font-normal">
                                            <thead class="text-left bg-gray-300 text-gray-800">
                                                <tr>
                                                    <th class="border-b border-r border-gray-700 pl-1">
                                                        {{ table_heading.date1 }}
                                                    </th>
                                                    <th v-if="disp.listFormat === 4" class="border-b border-r border-gray-700 pl-1">
                                                        Folder
                                                    </th>
                                                    <th class="border-b border-r border-gray-700 pl-1">
                                                        {{ table_heading.entrytype }}
                                                    </th>
                                                    <th class="border-b border-r border-gray-700 pl-1">
                                                        {{ table_heading.from }}
                                                    </th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <tr v-for="entry, index in entries.data" :key="entry.id"
                                                    class="border-b border-gray-400"
                                                    :class="setEntryClass(index)"
                                                    @click.left="entryList_click('list', index)"
                                                    @click.right.prevent="entryList_click('right', index)"
                                                    @dblclick="entryList_click('list_double', index)"
                                                >
                                                    <td class="pl-1 pr-2 py-1.5 border-base-content w-28">
                                                        {{ reformat_date(entry.date1, props.folders[entry.folder_id-1].input_time, entry.all_day) }}
                                                    </td>
                                                    <!-- find the entrytype in the folders -->
                                                    <td class="border-x border-base-content w-40 pl-1">
                                                        {{ props.folders[entry.folder_id - 1].entrytypes.find((entrytype) =>
                                                        entrytype.id === entry.entrytype_id).name }}
                                                    </td>
                                                    <!-- find the contact in file_contacts -->
                                                    <td class="border-base-content w-40 pl-1">
                                                        {{ props.file_contacts.find((contact) => contact.id ===
                                                        entry.from_contact_id ).display_last_first }}
                                                    </td>
                                                    <!-- next is only shown for listFormat 4, which doesn't exist right now -->
                                                    <td v-show="disp.listFormat === 4" class="border-gray-900 pl-1">
                                                        {{ props.folders[entry.folder_id - 1].name }}
                                                    </td>
                                                </tr>
                                                <tr v-for="n in emptyRows" :key="'empty-' + n" class="border-b border-gray-400 bg-base-100">
                                                    <td class="pl-1 pr-2 py-1.5">&nbsp;</td>
                                                    <td class="border-x border-base-content">&nbsp;</td>
                                                    <td>&nbsp;</td>
                                                    <td v-show="disp.listFormat === 4">&nbsp;</td>
                                                </tr>
                                            </tbody>
                                        </table>
                                    </div>
                                    <!-- if we're browsing the data (not edit or add), then display show droplist and link buttons -->
                                    <div v-if="state.mode === 'browse'"
                                        class="btn-group flex justify-between mt-2 items-center">
                                        <div>
                                            <label for="state_show" class="font-semibold text-sm text-blue-600 ml-2 mr-1">
                                                Show:
                                            </label>
                                            <select v-model="state.show" id="state_show"
                                                @change="refreshEntryList()"
                                                class="font-normal text-sm p-1 border border-gray-500 bg-base-300 text-base-content rounded">
                                                <option>6</option>
                                                <option>8</option>
                                                <option selected>10</option>
                                                <option>12</option>
                                                <option>15</option>
                                                <option>20</option>
                                                <option>25</option>
                                            </select>
                                        </div>
                                        <div class="pr-2">
                                            <Pagination :links="entries.links" :only="['entries', 'view_folder_id']"/>
                                        </div>
                                    </div>
                                </div>
                                <!-- else, if there are no entries and we're not adding a new entry, display message that there are no entries -->
                                <div v-else-if="state.mode != 'entry_add'" class="border p-4 text-xl text-center">
                                    No Entries In This Folder
                                </div>
                            </div>
                            <!-- End of Left Side -->

                            <!-- Start of Right Side -->
                            <div name="right-side" class="w-1/2 justify-items-center ml-4 mt-1">
                                <div id="disp_card" class="rounded-sm w-145 bg-base-200"
                                    :class="{
                                        'border border-base-content h-120': state.mode === 'browse',
                                        'border-4 border-blue-700 h-140': state.mode === 'entry_edit' || state.mode === 'entry_add'
                                    }">

                                    <!-- HERE is the EntryForm -->
                                    <EntryForm                                         
                                        ref="EntryForm_ref" 
                                        v-model:the_mode="state.mode"
                                        v-model:hotkey_pressed="hotkey_pressed" 
                                        v-model:keep_row="keep_row"
                                        :index_form="index_form" 
                                        :state="state" 
                                        :p1="props" 
                                        :file_view="'file'"
                                        :getFolderRow="getFolderRow" 
                                        :getFolderData="getFolderData"
                                    />
                                </div>

                                <!-- Entry List Button Row -->
                                <div v-if="state.mode === 'browse'" name="control_buttons" class="mt-6 w-full flex justify-evenly" >
                                    <button type="button" id="addbutton" class="btn btn-primary btn-sm h-10 gap-0" @click="entryList_click('add_button')" >
                                        + &nbsp;<u>A</u>dd New Entry
                                    </button>
                                    <button type="button" id="changebutton" class="btn btn-primary btn-sm h-10 gap-0" @click="list_actions('edit')" :disabled="entries.total === 0">
                                        △ &nbsp;<u>E</u>dit
                                    </button>
                                    <button type="button" id="deletebutton" class="btn btn-outline btn-error btn-sm h-10 " @click="display_modal('confirm_delete', true)" :disabled="entries.total === 0" >
                                        - &nbsp;Delete
                                    </button>
                                </div>
                            </div>
                            <!-- End of Right  Side -->
                        </div>
                    </div>
                    <!-- end of tab 2 content panel -->

                    <!-- Tab 3 - File Contacts Tab - Starts Here-->
                    <div v-if="state.tab === 3">
                        <h2 class="text-xl font-bold text-base-content mb-4 ml-2">File Contacts</h2>
                        <div v-if="props.file_contact_roles && props.file_contact_roles.length > 0">
                            <table class="w-full max-w-3xl border border-base-content text-sm font-sans font-normal">
                                <thead class="text-left bg-gray-300 text-gray-800">
                                    <tr>
                                        <th class="border-b border-r border-gray-700 pl-2 py-1.5">Contact Name</th>
                                        <th class="border-b border-r border-gray-700 pl-2 py-1.5">Role</th>
                                        <th class="border-b border-gray-700 pl-2 py-1.5">Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr v-for="cr in props.file_contact_roles" :key="cr.id"
                                        class="border-b border-gray-400 bg-base-100">
                                        <td class="pl-2 py-1.5 border-r border-base-content">{{ cr.contact_name }}</td>
                                        <td class="pl-2 py-1.5 border-r border-base-content">{{ cr.role_name }}</td>
                                        <td class="pl-2 py-1.5">
                                            <span v-if="cr.is_file_attorney" class="badge badge-sm badge-primary mr-1">File Attorney</span>
                                            <span v-if="cr.is_file_client" class="badge badge-sm badge-secondary mr-1">File Client</span>
                                            <span v-if="cr.is_protected && !cr.is_file_attorney && !cr.is_file_client" class="badge badge-sm badge-info mr-1">Protected</span>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                        <div v-else class="border p-4 text-xl text-center">
                            No Contacts Assigned To This File
                        </div>
                    </div>
                    <!-- end of tab 3 content panel -->
                </div>
            </div>
        </div>


        <!-- Put this part before </body> tag - Timeline Add modal-->
        <input hidden type="checkbox" id="timeline_add_modal" name="timeline_add_modal" class="modal-toggle" />
        <div class="modal">
            <div class="modal-box w-11/12 max-w-xl">
                <h3 class="font-bold text-xl text-center">Select The Folder For New Entry</h3>
                <div class="border border-gray-800 mt-4 w-64 mx-auto p-4">
                    <label v-if="file.filetype.has_correspondence === 1">
                        <input type="radio" v-model="state.add_folder_id" id="corr" value="1" autocomplete="off">
                        Correspondence<br>
                    </label>
                    <label v-if="file.filetype.has_pleadings === 1">
                        <input type="radio" v-model="state.add_folder_id" id="plea" value="2" autocomplete="off">
                        Pleadings and Motions<br>
                    </label>
                    <label v-if="file.filetype.has_discovery === 1">
                        <input type="radio" v-model="state.add_folder_id" id="disc" value="3" autocomplete="off">
                        Discovery Requests<br>
                    </label>
                    <label v-if="file.filetype.has_documents === 1">
                        <input type="radio" v-model="state.add_folder_id" id="docs" value="4" autocomplete="off">
                        Documents<br>
                    </label>
                    <label v-if="file.filetype.has_memos === 1">
                        <input type="radio" v-model="state.add_folder_id" id="memo" value="5" autocomplete="off">
                        Memos and Notes<br>
                    </label>
                    <label v-if="file.filetype.has_events === 1">
                        <input type="radio" v-model="state.add_folder_id" id="events" value="6" autocomplete="off">
                        Events and Deadlines<br>
                    </label>
                    <label v-if="file.filetype.has_todo === 1">
                        <input type="radio" v-model="state.add_folder_id" id="todo" value="7" autocomplete="off">
                        To-Do (for this file)<br>
                    </label>
                    <label v-if="file.filetype.has_phone === 1">
                        <input type="radio" v-model="state.add_folder_id" id="phone" value="8" autocomplete="off">
                        Phone Messages<br>
                    </label>
                    <label v-if="file.filetype.has_medrecs === 1">
                        <input type="radio" v-model="state.add_folder_id" id="medrecs" value="9" autocomplete="off">
                        Medical Records<br>
                    </label>
                    <label v-if="file.filetype.has_medbills === 1">
                        <input type="radio" v-model="state.add_folder_id" id="medbills" value="10" autocomplete="off">
                        Medical Bills<br>
                    </label>
                    <label v-if="file.filetype.has_costs === 1">
                        <input type="radio" v-model="state.add_folder_id" id="costs" value="11" autocomplete="off">
                        Case Costs<br>
                    </label>
                </div>
                <div class="modal-action justify-center mt-12">
                    <button type="button" class="btn btn-primary mr-10 w-28"
                        @click="entryList_click('timeline_add_close')">Ok</button>
                    <!-- <button type="button" class="btn btn-primary" @click="display_modal('timeline_add', false)">Cancel</button> -->
                    <button type="button" class="btn btn-primary" @click="close_timeline_add_modal()">Cancel</button>
                </div>
            </div>
        </div>

        <!-- Put this part before </body> tag - Confirm_Delete modal -->
        <input hidden type="checkbox" id="confirm_delete_modal" name="confirm_delete_modal" class="modal-toggle" />
        <div class="modal">
            <div class="modal-box w-11/12 max-w-3xl">
                <h3 class="font-bold text-2xl text-center">Confirm: Delete Entry</h3>
                <p class="text-xl mt-4">Do you want to delete this entry?</p>
                <div class="modal-action justify-center mt-12">
                    <button type="button" class="btn mr-10 w-28 gap-0"
                        @click="list_actions('delete')"><u>Y</u>es</button>
                    <button type="button" class="btn gap-0"
                        @click="display_modal('confirm_delete', false)"><u>N</u>o</button>
                </div>
            </div>
        </div>


    </AuthenticatedLayout>
</template>

<style>
.vdtp .dp__input {
    height: 48px;
    width: 252px;
    border-radius: 4px;
    border-color: red;
    border-width: 8px;
}

.vdtp-disabled .dp__input {
    height: 48px;
    width: 252px;
    border-radius: 4px;
    border: 0;
}

.vdtp_time .dp__input {
    height: 32px;
    width: 180px;
    border-radius: 6px;
    /* border-color: lightgray; */
        /* Set text color to match DaisyUI's base content color */
    color: oklch(var(--bc)); /* This is DaisyUI's base content color variable */
    
    /* Set background color to match DaisyUI's base background */
    background-color: oklch(var(--b1)); /* This is DaisyUI's base-100 color variable */

       /* Add other input styles to match DaisyUI's aesthetic */
    /* border-color: oklch(var(--bc)); This is DaisyUI's base-200 color variable */

    font-size: 14px;
}

.vdtp_time-disabled .dp__input {
    height: 32px;
    width: 180px;
    border-radius: 6px;
    border: 0;
    font-size: 14px;
}

.vdtp_date .dp__input {
    height: 32px;
    width: 120px;
    border-radius: 6px;
    /* Set text color to match DaisyUI's base content color */
    color: oklch(var(--bc));
    /* Set background color to match DaisyUI's base background */
    background-color: oklch(var(--b1));

       /* Add other input styles to match DaisyUI's aesthetic */
    /*border-color: hsl(var(--b3)); /* This is DaisyUI's base-200 color variable */
    /* border-color: rgba(220,220,220, .5);  */
    /* border-color: oklch(var(--n3)); */
    font-size: 14px;
}

.vdtp_date-disabled .dp__input {
    height: 32px;
    width: 120px;
    border-radius: 6px;
    border: 0;
    font-size: 14px;
}

.vdtp_main_date .dp__main {
    width: 120px;
}

.vdtp_main_time .dp__main {
    width: 200px;
}

.vdtp_sm .dp__input {
    height: 32px;
    width: 180px;
    border-radius: 6px;
    border-color: lightgray;
    font-size: 14px;
}

.vdtp-disabled_sm .dp__input {
    height: 32px;
    width: 180px;
    border-radius: 6px;
    border: 0;
    font-size: 14px;
}

/* .dp__main  {
  width: 200px;
}
 */

input[type="text"]:disabled {
    background: #FAFAFA;
}

select:disabled {
    background: #FAFAFA;
}

textarea:disabled {
    background: #FAFAFA;
}

#name:disabled {
    background: #FAFAFA;
}

#contact_id:disabled {
    background: #FAFAFA;
}

#summary:disabled {
    background: #FAFAFA;
}

.my-datepicker .dp__input {
    height: 48px;
    width: 252px;
    border-radius: 8px;
    border-color: lightgray;
}

.autocomplete-suggestions {
    position: absolute;
    z-index: 100;
    background-color: #fff;
    border: 1px solid #ccc;
    list-style: none;
    left: 50px;
    right: 0;
    padding: 2;
    margin: 0;
}

.suggestion-table {
    position: absolute;
    z-index: 100;
    left: 105px;
}
</style>
