<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';

import FileForm from "@/Pages/Files/FileForm.vue";
import FileLookup  from  "@/Pages/Files/FileLookup.vue";
import EntryForm from "@/Pages/Entries/EntryForm.vue";

import Pagination from '@/Components/Pagination.vue'

import { Head, Link, useForm, router } from '@inertiajs/vue3';
import { reactive, ref, computed, onMounted, onUnmounted, onUpdated, nextTick } from "vue";
import { PencilSquareIcon, PhoneIcon, ExclamationTriangleIcon, ClipboardDocumentCheckIcon, CalendarIcon, QueueListIcon } from '@heroicons/vue/24/outline';

// import { preventDefault } from '@fullcalendar/core/internal';

import { VueDatePicker } from '@vuepic/vue-datepicker';

const queryString = window.location.search;
const urlParams = new URLSearchParams(queryString);

const props = defineProps({
    view: String,
    entries: Object,
    view_folder_id: Number,
    initials: String,
    from_to: String,
    read: String,
    folders: Object,
    firm_members: Object,
    attorneys: Object,
    file_contacts: Object,
    expecting_response: Object,
    contact_role_ids: Array,
    role_options: Object,
});

const state = reactive({
    row: 0,                                         // current list row
    folder_name: '',                                // used for filepart - should I rename it to filepart?
    folder_row: 0,
    show: props.entries.per_page ?? 15,             // how many rows to show in list - if not set, then show is 15 set in the controller index
    mode: props.mode ?? 'browse',
    view: props.view ?? 'memos',
    from_to: props.from_to ?? 'to',
    initials: props.initials ?? '****',
    read: props.read ?? 'unread',
    date_from: '',
    date_to: '',
    add_folder_id: 0,
});

const disp = reactive({
    date_range: 'All',
    events_filter: 'all_events',
    check_from: false,
    check_to: true,
    check_read: false,
    check_unread: true,
});

// if( props.view === 'todo' ) {
//     if( props.read === 'unread') props.read = 'pending'
// }

const index_form = reactive({
    formtype: "entry",
    current_page: urlParams.get('page'),
    show: urlParams.get('show'),
    filepart: state.folder_name,
});

const folder_singular = [                                                       // this array is used to display the singular name of a folder
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

const hotkey_pressed = ref();
const keep_row = ref();
const EntryForm_ref = ref(null);                                                // ref used to call update_disp method in EntryForm child component

const table_heading = reactive({
    date1: "Date",
    date2: "Sent/Received",
    from: "From",
    to: "To",
    entrytype: "Type",
});


const emptyRows = computed(() => {
    return Math.max(0, state.show - props.entries.data.length);
});

// ---------------------------------------------------------------------------
// View table column definitions (data-driven approach)
//
// Each view (memos, todo, events, etc.) defines its own set of columns.
// A column is an object with:
//   label  - the header text shown in <th>
//   width  - CSS width for consistent column sizing
//   value  - a function that receives an entry and returns the display string
//
// The template renders a single <table> that iterates over whichever
// column set is active, instead of duplicating the entire table per view.
// ---------------------------------------------------------------------------
const viewColumns = computed(() => {
    switch (state.view) {

        // Memos and Phone share the same 3-column layout: Date / From / To
        case 'memos':
        case 'phone':
            return [
                {
                    label: table_heading.date1,
                    width: 'w-28',
                    value: (entry) => reformat_date(entry.date1, getFolderData('input_time')),
                },
                {
                    label: table_heading.from,
                    width: 'w-40',
                    value: (entry) => display_entry_contact(entry, 'from'),
                },
                {
                    label: table_heading.to,
                    width: 'w-40',
                    value: (entry) => display_entry_contact(entry, 'to'),
                },
            ];

        // To-Do shows Date / For (initials) / the note text
        case 'todo':
            return [
                {
                    label: 'Date:',
                    width: 'w-16',
                    value: (entry) => reformat_date(entry.date1, getFolderData('input_time')),
                },
                {
                    label: 'For:',
                    width: 'w-12',
                    value: (entry) => entry.contact_from?.member_initials ?? '',
                },
                {
                    label: 'To-Do:',
                    width: 'w-80',
                    value: (entry) => entry.note ?? '',
                },
            ];

        // Events shows Date / For (initials) / the entrytype name
        case 'events':
            return [
                {
                    label: 'Date:',
                    width: 'w-32',
                    value: (entry) => reformat_date(entry.date1, getFolderData('input_time')),
                },
                {
                    label: 'For:',
                    width: 'w-12',
                    value: (entry) => entry.contact_from?.member_initials ?? '',
                },
                {
                    label: 'Event Type:',
                    width: 'w-80',
                    // Look up the entrytype name from the folder's entrytypes array
                    value: (entry) => {
                        const folder = props.folders[entry.folder_id - 1];
                        const et = folder?.entrytypes?.find(t => t.id === entry.entrytype_id);
                        return et ? et.name : '';
                    },
                },
            ];

        // Timeline shows Date / Folder name / entrytype name (spans all folders)
        case 'timeline':
            return [
                {
                    label: 'Date:',
                    width: 'w-36',
                    // Timeline entries can come from any folder, so pass that folder's input_time
                    value: (entry) => reformat_date(entry.date1, props.folders[entry.folder_id - 1].input_time, entry.all_day),
                },
                {
                    label: 'Folder:',
                    width: 'w-12',
                    value: (entry) => props.folders[entry.folder_id - 1].name,
                },
                {
                    label: 'Type:',
                    width: 'w-80',
                    value: (entry) => {
                        const folder = props.folders[entry.folder_id - 1];
                        const et = folder?.entrytypes?.find(t => t.id === entry.entrytype_id);
                        return et ? et.name : '';
                    },
                },
            ];

        // Due shows Response From / Expected By / Date Due (column order differs from others)
        case 'due':
            return [
                {
                    label: 'Response From',
                    width: 'w-40',
                    value: (entry) => display_entry_contact(entry, 'to'),
                },
                {
                    label: 'Expected By',
                    width: 'w-40',
                    value: (entry) => display_entry_contact(entry, 'from'),
                },
                {
                    label: 'Date Due',
                    width: 'w-28',
                    value: (entry) => reformat_date(entry.date_response_expected),
                },
            ];

        default:
            return [];
    }
});

function refreshView() {
    router.reload( {
        only: ['entries','view_folder_id','view','initials','from_to','read'],
        replace: true,
        data: {
            view: state.view,
            view_for: state.initials,
            page: 1,                                                            // refresh, so start at page 1
            show: state.show,
            read: state.read,
            from_to: state.from_to,
            starting: state.date_from,
            ending:  state.date_to,
        },
        headers: { 'X-Custom-Refresh': 'Entries' },                             // use custom header to set the refresh on the server
        onSuccess: () => {                                                      // after refresh, set mode as browse and call update_disp
            state.mode = 'browse';
            document.activeElement.blur();
            EntryForm_ref.value.update_disp();
        }
    });
}

    // this sets the color of the listbox entries
function setViewClass(index) {
    let entry = props.entries.data[index];
    let textcolor = 'text-base-content';
    let bgcolor = 'bg-base-100';
    let border = '';

    if (state.mode === 'entry_edit' || state.mode === 'entry_add') {          // if adding or entering, then gray out the listbox entries
        textcolor = 'text-base-300';
        bgcolor = 'bg-base-100';
    } else if (entry.expecting_response == true) {                            // else if entry expects a response, set the color
        let thestat = determine_response_expectation(entry.date_response_expected);
        textcolor = thestat === 'Awaiting response' ? 'text-success' : 'text-error';
    }

    if (index === state.row) {                                                // if it is the highlighted row
        textcolor = 'text-base-content';
        if (entry.expecting_response) {
            textcolor = determine_response_expectation(entry.date_response_expected) === 'Awaiting response' ? 'text-success' : 'text-error';
        }
        bgcolor = 'bg-primary/20';
        border = 'border-l-4 border-l-blue-600';
    }

    return textcolor + ' ' + bgcolor + ' ' + border;
}

function viewList_click( what, index = null ) {                                 // there was a click on the list, buttons or entry form buttons
    switch( what ) {
    case 'list':                                                                // single click on list, set state.row and state.folder_row to display the record
        if (state.mode == 'browse') {
            state.row = index;
            state.folder_row = getFolderRow();
        }
        break;
    case 'list_double':                                                         // double clickon list, set state.row and state.folder_row and the set the edit mode
        if (state.mode == 'browse') {
            state.row = index;
            state.folder_row = getFolderRow();
            list_actions('edit')
        }
        break;
    case 'right':
        alert('right!');
        break;
    case 'add_button':
        if( props.view_folder_id == 0 ) {
            document.getElementById('timeline_add_modal').showModal();       // if clicked while in timeline, then display modal to select folder
            return;                                                          // don't proceed to add mode yet - wait for folder selection
        } else {                                                                                          // else, set the add folder and put in add mode
            state.add_folder_id = props.view_folder_id;
        }
        list_actions( 'add' );
        break;
    case 'add_close':                                                           // timeline add modal has selected the folder, so close the modal and start add mode
        document.getElementById('timeline_add_modal').close();
        state.add_folder_id = Number(state.add_folder_id);                      // ensure folder id is a number (radio values are strings)
        list_actions('add')
    }
}

function list_actions( action ) {
    if( action === 'add' ) {
        if( state.view === 'memos' ) state.add_folder_id = 5;
        else if( state.view === 'phone' ) state.add_folder_id = 8;
        else if( state.view === 'todo' ) state.add_folder_id = 7;
        else if( state.view === 'events' ) state.add_folder_id = 6;

        state.mode = 'entry_add';
    }
    else if( action === 'edit' ) state.mode = 'entry_edit';
    else if( action === 'delete' ) state.mode = 'entry_delete';
}


// const keypress_handler = (e) => {
function keypress_handler(e) {
    if( state.mode === 'browse' ) {
        if( (e.altKey && e.key === 'a' && !e.ctrlKey ) ) {        //alt A - Add button
            e.preventDefault();
            list_actions('add');
        }
        else if ( (e.altKey && e.key === 'e') && !e.ctrlKey ) {   // alt E - Edit button
            e.preventDefault();
            list_actions('edit');
        }
        else if (e.altKey && e.ctrlKey && e.key >= 'a' && e.key <= 'z' ) {  // CtrlAlt a - z  Folder shortcuts
            e.preventDefault();
            folder_shortcut(e.key);
        }

        let theseKeys = [ 'Escape', 'ArrowDown', 'ArrowUp', 'Enter', 'PageUp', 'PageDown' ];    // preventDefault on these keypresses
        if( theseKeys.includes(e.key) ) e.preventDefault();

        if( e.key === 'Escape' ) {} // router.get('/files?page=' + index_form.current_page + '&show=' + index_form.show); // if escape while browsing, go to files index
        else if( e.key === 'Enter' ) list_actions('edit');                                      // else if enter, then edit
        else if( e.key === 'ArrowDown' && state.row < (props.entries.data.length - 1) ) {       // if ArrowDown and not at list bottom
            state.row++;
            state.folder_row = getFolderRow();
        } else if( e.key === 'ArrowUp' && state.row > 0 ) {                                     // if ArrowUp and not at list top
            state.row--;
            state.folder_row = getFolderRow();
        }
        else if( e.key === 'PageUp' && props.entries.current_page > 1 ) router.get( props.entries.links[props.entries.current_page - 1].url );
        else if( e.key === 'PageDown' && props.entries.current_page < props.entries.last_page ) router.get( props.entries.links[props.entries.current_page + 1].url );
    }
    else if( state.mode === 'entry_add' || state.mode === 'entry_edit' ) {                      // Else, if we're adding or editing
        if( e.key === 'Escape' ) {
            e.preventDefault();
            hotkey_pressed.value = e;
        }

        if( (  document.getElementById('addcancelled_modal').open === true                   // if addcancelled_modal is active
            || document.getElementById('entrychanged_modal').open === true )                 // or if entrychanged_modal is active
            && ( e.key === 'y' || e.key === 'Y' || e.key === 'n' || e.key === 'N' ) ) {          // and the 'y' or 'n' key is pressed
            e.preventDefault();                                                                 // preventDefault
            hotkey_pressed.value = e;                                                           // trigger the hotkey
        }
    }
};

function clicked_set_date_range() {
    // date_range_filters.showModal();
    document.getElementById('events_filters_modal').showModal();
}

function clicked_from_to( clicked ) {
    if( clicked === 'from' && disp.check_from === false && disp.check_to === false ) disp.check_to = true;      // if from is now false and to was false, make to true
    if( clicked === 'to' && disp.check_to === false && disp.check_from === false ) disp.check_from = true;      // if to is now false and from was false, make from true

    if( disp.check_from && disp.check_to ) state.from_to = 'both';                                              // if both are true, set state.from_to to 'both'
    else if( disp.check_from ) state.from_to = 'from';
    else if( disp.check_to ) state.from_to = 'to';

    refreshView();
}

function clicked_read_unread( clicked ) {
    if( clicked === 'unread' && disp.check_unread === false && disp.check_read === false ) disp.check_read = true;  // if unread is now false and read was false, make read true
    if( clicked === 'read' && disp.check_read === false && disp.check_unread === false ) disp.check_unread = true;  // if read is now false and unread was false, make unread true

    if( disp.check_read && disp.check_unread ) state.read = 'all';                                                  // if both are true, set state.read to 'all'
    else if( disp.check_unread ) state.read = 'unread';
    else if( disp.check_read ) state.read = 'read';

    refreshView();
}

function set_events_filter() {
    if( disp.events_filter === 'all_events') {
        state.date_from = null;
        state.date_to = null;
    }

    if( state.date_from === '' ) state.date_from = null;
    if( state.date_to === '' ) state.date_to = null;

    if( state.date_from === null && state.date_to === null ) disp.events_filter = 'all_events';

    document.getElementById('events_filters_modal').close();

    if( state.date_from !== null && state.date_to !== null ) {
        disp.date_range = reformat_date( state.date_from ) + ' - ' + reformat_date( state.date_to );
    } else if( state.date_from !== null && state.date_to === null ) {
        disp.date_range = 'Starting ' + reformat_date( state.date_from );
    } else if( state.date_from === null && state.date_to !== null ) {
        disp.date_range = 'Ending '  + reformat_date( state.date_to );
    } else if( (state.date_from === null && state.date_to === null) || disp.events_filter === 'all_events' ) {
        disp.date_range = 'All';
    }

    refreshView();
}

function folder_shortcut( what ) {
    switch( what ) {
        case 'm':
            state.view = 'memos';
            break;
        case 'P':
            state.view = 'phone';
            break;
        case 'd':
            state.view = 'due';
            break;
        case 't':
            state.view = 'todo';
            break;
        case 'e':
            state.view = 'events';
            break;
        case 'l':
            state.view = 'timeline';
            break;

    }

    refreshView();
}

function getFolderRow() {                                                               // determines which array row to use in the folders array
    let entries = props.entries.data;                                                   // shortcut for cleaner code
    let folder_id = 0
    let folder_row = 0;

    if( state.mode === 'entry_add' && state.add_folder_id > 0 ) {                      // if adding an entry, use the selected add folder
        folder_id = state.add_folder_id;
    } else if( props.view === 'due' || props.view === 'timeline' ) {                    // if view is due or timeline
        if( entries.length > 0 && state.row < entries.length ) {                        // - if there are entries and the selected row is < # enries (for 0 based array)
            folder_id = entries[state.row].folder_id;                                   // use id of current entry
        }
    } else {                                                                            // else - view is for a folder (memos, phone, events)
        folder_id = props.view_folder_id;                                               // use the props.view.folder_id
    }

    if( folder_id < 1 ) folder_row = 0;                                                 // if the folder_id < 1, just make the folder_row 0
    else folder_row = folder_id - 1;                                                    // else, folder row is 1 less than the id (0 based array)

    // folder_row = folder_id - 1;                                                         // folder row is 1 less than the id (0 based array)
    // if( folder_row < 0 ) folder_row = 0;                                                // folder_row cannot be less than 0

    return folder_row;
}

function getFolderData( whichData, singular = null ) {                                  // returns data about a folder, and if name is requested and singular is true, it returns the singular name
    let dataBack = null;
    let row = getFolderRow();                                                           // get the row number to access in the props.p1.folders object
    let folder = props.folders[row];                                                    // set a shortcut for cleaner code

    if( singular != null && whichData === 'name' ) {
        dataBack = folder_singular[row];                                                // singular of name
    } else if( whichData === 'input_time' || whichData.substring(0, 4) === 'hide' ) {
        dataBack = folder[whichData] ? folder[whichData] : false;                       // boolean - input_time and hide prompt fields
    } else {
        dataBack = folder[whichData] ? folder[whichData] : '';                          // everything else returns a string
    }

    return dataBack;
}

function determine_response_expectation(date_expected) {
    let date1 = new Date(date_expected);
    let date2 = new Date();
    let sendback = date2 > date1 ? "Awaiting response (overdue)" : "Awaiting response";

    return sendback;
}


function isFromToDisabled() {                                                           // determines whether to disable to from,to, from/to filter select
    let disable_it = false;
    if( state.initials === '****' ) disable_it = true;
    // else if( state.view === 'phone' || state.view === 'todo' || state.view === 'events') disable_it = true;

    return disable_it;
}

function showFromTo() {                                                                 // determines whether to show or hide the From, To, From/Too filter select
    let show_it = false;
    if( state.view === 'memos' || state.view === 'due' || state.view === 'timeline' || state.view === 'due') show_it = true;
    return show_it;
}

function showStatusPrompt() {
    let show_it = false;
    if( state.view === 'todo' ) show_it = true;
    return show_it;
}

function showReadUnread() {                                                                 // determines whether to show or hide the From, To, From/Too filter select
    let show_it = false;
    if( state.view === 'memos' || state.view === 'phone' ) show_it = true;
    return show_it;
}

function showCompletedPending() {                                                                 // determines whether to show or hide the From, To, From/Too filter select
    let show_it = false;
    if( state.view === 'todo' ) show_it = true;
    return show_it;
}

function reformat_date(dt, input_time = false, all_day = false) {
    if (dt === null || dt === undefined) {
        return '';                                                                          // if date is null or undefined, return empty string
    } else {
        dt = dt.toString();                                                                 // convert to string if not already
        if (input_time == true) {
            let the_date = dt.slice(5, 7) + '/' + dt.slice(8, 10) + '/' + dt.slice(2, 4);
            let the_hour = dt.slice(11, 13);
            let the_minutes = dt.slice(14, 16);
            let hour_12 = the_hour;
            let a_p = "am";
            if (the_hour > 12) {
                hour_12 = the_hour - 12;
                a_p = "pm";
            }
            if (all_day == false) {
                return the_date + ', ' + hour_12 + ':' + the_minutes + '' + a_p;
            } else {
                return the_date + ' (all day)';
            }

        } else {
            return dt.slice(5, 7) + '/' + dt.slice(8, 10) + '/' + dt.slice(2, 4);
        } // end if input_time or all
    }
}

function display_entry_contact( entry, fromto ) {
    let $sendback = '';
    if( fromto === 'from' && entry.contact_from !== null ) $sendback = entry.contact_from.display_last_first;
    else if( fromto === 'to' && entry.contact_to !== null ) $sendback = entry.contact_to.display_last_first;
    return $sendback;
}

function display_modal( which_modal, OnOff = null ) {
    let modal_name = '';
    if( which_modal == 'confirm_delete' ) modal_name = 'confirm_delete_modal';
    else if( which_modal == 'timeline_add' ) modal_name = 'timeline_add_modal';

    if( modal_name != '' && OnOff === 'status' ) {
        return document.getElementById(modal_name).open;
    }
    else if( modal_name != '' && (OnOff == true || OnOff === 'show' || OnOff === 'on') ) {
        document.getElementById(modal_name).showModal();
    }
    else if( modal_name != '' && (OnOff == false || OnOff === 'hide' || OnOff === 'off') ) {
        document.getElementById(modal_name).close();
    }
    else if( modal_name != '' && OnOff == null ) {
        document.getElementById(modal_name).open ? document.getElementById(modal_name).close() : document.getElementById(modal_name).showModal();
    }
}

function close_timeline_add_modal() {
    document.getElementById('timeline_add_modal').close();
}



onMounted( () => document.addEventListener('keydown', keypress_handler) );

onUpdated( () => {                                                      // On update to the dom, call update_disp in EntryForm child component
    state.row = keep_row.value > 0 ? keep_row.value : 0                 // keep_row.value is set in the form, when the read checkbox is clicked and passed back by defineModel
    EntryForm_ref.value.update_disp();                                  // set state.row and call update_disp
});

onUnmounted( () => document.removeEventListener('keydown', keypress_handler) );


</script>

<template>
    <Head title="Office Views" />

    <AuthenticatedLayout>

        <template #header>
            <div class=" flex items-baseline bg-base-100">

                <div class="font-normal text-base text-base-content bg-base-100 w-4/5 justify-items-start text-left">
                    <label for="viewlist" class="font-semibold text-base text-base-content mx-2">Viewing: </label>
                    <select v-model="state.view" id="viewlist" @change="refreshView()" class="border border-base-300 rounded bg-base-100 text-base-content p-1 w-44" >
                        <option value="memos">Memos</option>
                        <option value="phone">Phone Messages</option>
                        <option value="due">Response Due</option>
                        <option value="todo">To-Do Entries</option>
                        <option value="events">Events & Deadlines</option>
                        <option value="timeline">Office Timeline</option>
                    </select>
                    <!-- Memos: pencil-square -->
                    <!-- <svg v-if="state.view === 'memos'" class="inline-block align-middle w-6 h-6 ml-5 mr-1 text-base-content" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L10.582 16.07a4.5 4.5 0 0 1-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 0 1 1.13-1.897l8.932-8.931Zm0 0L19.5 7.125M18 14v4.75A2.25 2.25 0 0 1 15.75 21H5.25A2.25 2.25 0 0 1 3 18.75V8.25A2.25 2.25 0 0 1 5.25 6H10" />
                    </svg> -->
                    <PencilSquareIcon v-if="state.view === 'memos'" class="inline-block align-middle w-6 h-6 ml-5 mr-2 text-primary/90" />
                    <!-- Phone -->
                    <!-- <svg v-else-if="state.view === 'phone'" class="inline-block align-middle w-6 h-6 ml-5 mr-1 text-base-content" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 6.75c0 8.284 6.716 15 15 15h2.25a2.25 2.25 0 0 0 2.25-2.25v-1.372c0-.516-.351-.966-.852-1.091l-4.423-1.106c-.44-.11-.902.055-1.173.417l-.97 1.293c-.282.376-.769.542-1.21.38a12.035 12.035 0 0 1-7.143-7.143c-.162-.441.004-.928.38-1.21l1.293-.97c.363-.271.527-.734.417-1.173L6.963 3.102a1.125 1.125 0 0 0-1.091-.852H4.5A2.25 2.25 0 0 0 2.25 4.5v2.25Z" />
                    </svg> -->
                    <PhoneIcon v-else-if="state.view === 'phone'" class="inline-block align-middle w-6 h-6 ml-5 mr-2 text-primary/90" />
                    <!-- Due: exclamation-triangle -->
                    <!-- <svg v-else-if="state.view === 'due'" class="inline-block align-middle w-6 h-6 ml-5 mr-1 text-base-content" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126ZM12 15.75h.007v.008H12v-.008Z" />
                    </svg> -->
                    <ExclamationTriangleIcon v-else-if="state.view === 'due'" class="inline-block align-middle w-6 h-6 ml-5 mr-2 text-primary/90" />
                    <!-- Todo: clipboard-document-check -->
                    <!-- <svg v-else-if="state.view === 'todo'" class="inline-block align-middle w-6 h-6 ml-5 mr-1 text-base-content" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M11.35 3.836c-.065.21-.1.433-.1.664 0 .414.336.75.75.75h4.5a.75.75 0 0 0 .75-.75 2.25 2.25 0 0 0-.1-.664m-5.8 0A2.251 2.251 0 0 1 13.5 2.25H15c1.012 0 1.867.668 2.15 1.586m-5.8 0c-.376.023-.75.05-1.124.08C9.095 4.01 8.25 4.973 8.25 6.108V8.25m8.9-4.414c.376.023.75.05 1.124.08 1.131.094 1.976 1.057 1.976 2.192V16.5A2.25 2.25 0 0 1 18 18.75h-2.25m-7.5-10.5H4.875c-.621 0-1.125.504-1.125 1.125v11.25c0 .621.504 1.125 1.125 1.125h9.75c.621 0 1.125-.504 1.125-1.125V18.75m-7.5-10.5h6.375c.621 0 1.125.504 1.125 1.125v9.375m-8.25-3 1.5 1.5 3-3.75" />
                    </svg> -->
                    <ClipboardDocumentCheckIcon v-else-if="state.view === 'todo'" class="inline-block align-middle w-6 h-6 ml-5 mr-2 text-primary/90" />
                    <!-- Events: calendar -->
                    <!-- <svg v-else-if="state.view === 'events'" class="inline-block align-middle w-6 h-6 ml-5 mr-1 text-base-content" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 0 1 2.25-2.25h13.5A2.25 2.25 0 0 1 21 7.5v11.25m-18 0A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75m-18 0v-7.5A2.25 2.25 0 0 1 5.25 9h13.5A2.25 2.25 0 0 1 21 11.25v7.5" />
                    </svg> -->
                    <CalendarIcon v-else-if="state.view === 'events'" class="inline-block align-middle w-6 h-6 ml-5 mr-2 text-primary/90" />
                    <!-- Timeline: queue-list -->
                    <!-- <svg v-else-if="state.view === 'timeline'" class="inline-block align-middle w-6 h-6 ml-5 mr-1 text-base-content" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 12h16.5m-16.5 3.75h16.5M3.75 19.5h16.5M5.625 4.5h12.75a1.875 1.875 0 0 1 0 3.75H5.625a1.875 1.875 0 0 1 0-3.75Z" />
                    </svg> -->
                    <QueueListIcon v-else-if="state.view === 'timeline'" class="inline-block align-middle w-6 h-6 ml-5 mr-2 text-primary/90" />
                    <!--
                    <select v-if="showFromTo() === true" v-model="state.from_to" id="from_to" @change="refreshView()" :disabled="isFromToDisabled()"
                        class="border border-base-300 rounded bg-base-100 text-base-content p-1 ml-8 mr-1 w-28 disabled:bg-base-200 disabled:text-base-content/60" >
                        <option value="to">To</option>
                        <option value="from">From</option>
                        <option value="both">To / From</option>
                    </select>
                    -->
                    <span v-if="showFromTo() === false" class="font-medium text-base text-base-content ml-2">
                        For:
                    </span>
                    <select v-model="state.initials" id="initials" @change="refreshView()" class="border border-base-300 rounded bg-base-100 text-base-content p-1 ml-3 w-20" >
                        <option value="****">****</option>
                        <option v-for="member, index in firm_members" :key="member.id">
                            {{ member.member_initials }}
                        </option>
                    </select>
                    <div v-if="showFromTo() === true" class="inline-flex flex-col gap-1 ml-6 align-bottom">
                        <label class="flex items-center gap-1 cursor-pointer">
                            <input
                                type="checkbox"
                                v-model="disp.check_to"
                                @change="clicked_from_to('to')"
                                :disabled="isFromToDisabled()"
                                class=""
                            >
                            <span class="text-xs" :class="{ 'text-base-content/50': isFromToDisabled() }">To</span>
                        </label>
                        <label class="flex items-center gap-1 cursor-pointer">
                            <input
                                type="checkbox"
                                v-model="disp.check_from"
                                @change="clicked_from_to('from')"
                                :disabled="isFromToDisabled()"
                                class=""
                            >
                            <span class="text-xs" :class="{ 'text-base-content/50': isFromToDisabled() }">From</span>
                        </label>
                    </div>
                    <span v-if="showStatusPrompt()" class="font-medium text-base text-base-content ml-4">Status:</span>
                    <!--
                    <select v-if="showReadUnread()" v-model="state.read" id="read_unread" @change="refreshView()" class="border border-base-300 rounded bg-base-100 text-base-content p-1 ml-3 w-28" >
                        <option value="unread">Unread</option>
                        <option value="read">Read</option>
                        <option value="all">All</option>
                    </select>
                    -->
                    <div v-if="showReadUnread()" class="inline-flex flex-col gap-1 ml-6 align-bottom">
                        <label class="flex items-center gap-1 cursor-pointer">
                            <input
                                type="checkbox"
                                v-model="disp.check_unread"
                                @change="clicked_read_unread('unread')"
                                class=""
                            >
                            <span class="text-xs">Unread</span>
                        </label>
                        <label class="flex items-center gap-1 cursor-pointer">
                            <input
                                type="checkbox"
                                v-model="disp.check_read"
                                @change="clicked_read_unread('read')"
                                class=""
                            >
                            <span class="text-xs">Read</span>
                        </label>
                    </div>
                    <select v-if="showCompletedPending()" v-model="state.read" id="read_unread" @change="refreshView()" class="border border-base-300 rounded bg-base-100 p-1 ml-3 w-32" >
                        <option value="unread">Pending</option>
                        <option value="read">Completed</option>
                        <option value="both">All</option>
                    </select>

                    <span v-if="state.view === 'events'" class="font-medium text-base text-base-content ml-7 w-24">Date Range: </span>
                    <span v-if="state.view === 'events'" class="font-normal text-xs ml-1"> {{ disp.date_range }}</span>
                    <button v-if="state.view === 'events'" type="button" id="date_range_button" class="btn btn-outline btn-primary btn-xs ml-3" @click="clicked_set_date_range()">Change</button>


                </div>

                <div class="w-1/5 flex items-baseline justify-end pr-8">
                    <div v-if="state.mode === 'entry_edit' || state.mode === 'file_edit'" class="text-info text-lg font-semibold text-right pr-8">
                        Edit Mode
                    </div>
                    <div v-if="state.mode == 'entry_add'" class="text-info text-lg font-semibold text-right pr-8">
                        Add Mode
                    </div>
                </div>


            </div>
        </template>

        <div class="py-3">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                <div class="bg-base-300 p-4 min-h-dvh sm:rounded-lg" id="ViewsScreen" name="ViewsScreen">
                        <div class="flex">
                            <div name="left-side" class="w-1/2 ml-1 mt-1">

                                <!-- If editing or adding an entry, show this div to indicate the action -->
                                <div v-show="state.mode == 'entry_edit'" class="w-full font-bold text-xl text-info ml-2 mb-2">
                                    Edit Entry <span class="font-normal text-base ">(in {{ getFolderData('name') }})</span>
                                </div>
                                <div v-show="state.mode == 'entry_add'" class="w-full font-bold text-xl text-info ml-2 mb-2">
                                    Add New {{ getFolderData('name', true) }} Entry
                                </div>

                                <div v-if="entries.data.length && state.mode != 'entry_add'">
                                    <div>
                                        <!-- Single data-driven table for all views.
                                             viewColumns computed returns the column definitions for the active view.
                                             Headers and cells both iterate over the same column array,
                                             so adding/changing a view only requires updating the computed, not the template. -->
                                        <table id="view_table" tabindex="0"
                                            class="w-full border border-base-content text-sm font-sans font-normal">
                                            <!-- Column headers from viewColumns -->
                                            <thead class="text-left bg-base-200 text-base-content">
                                                <tr>
                                                    <th v-for="col in viewColumns" :key="col.label"
                                                        class="border-b border-r border-base-content pl-1">
                                                        {{ col.label }}
                                                    </th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <!-- Data rows — click handlers are identical across all views -->
                                                <tr v-for="(entry, index) in entries.data" :key="entry.id"
                                                    class="border-b"
                                                    :class="[setViewClass(index), index === entries.data.length - 1 && emptyRows === 0 ? 'border-base-content' : 'border-primary/20']"
                                                    @click.left="viewList_click( 'list', index)"
                                                    @click.right.prevent="viewList_click( 'right', index)"
                                                    @dblclick="viewList_click( 'list_double', index)">
                                                    <!-- Each cell calls col.value(entry) to get its display text -->
                                                    <td v-for="col in viewColumns" :key="col.label"
                                                        class="pl-1 py-1.5 border-r border-base-content"
                                                        :class="col.width">
                                                        {{ col.value(entry) }}
                                                    </td>
                                                </tr>
                                                <!-- Empty padding rows to fill out the table to state.show height -->
                                                <tr v-for="n in emptyRows" :key="'empty-' + n"
                                                    :class="['border-b bg-base-100', n === emptyRows ? 'border-base-content' : 'border-primary/20']">
                                                    <td v-for="col in viewColumns" :key="'empty-' + col.label"
                                                        class="pl-1 py-1.5 border-r border-base-content">&nbsp;</td>
                                                </tr>
                                            </tbody>
                                        </table>
                                    </div>
                                    <!-- if we're browsing the data (not edit or add), then display show droplist and link buttons -->
                                    <div v-if="state.mode == 'browse'" class="flex justify-between mt-2 items-center">
                                        <div class="flex items-center w-28">
                                            <label for="state_show" class="font-semibold text-sm text-base-content ml-1 mr-1">
                                                Rows:
                                            </label>
                                            <select v-model="state.show" id="state_show" @change="refreshView()"
                                                class="bg-base-100 font-normal text-sm p-1 border border-base-300 rounded mr-8" >
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
                                            <!-- <Link v-for="link in entries.links" :href="link.url ?? ''" :only="['entries','view_folder_id']" method="get" v-html="link.label"
                                                class="btn btn-sm" :class="{ 'text-base-content/50': !link.url, 'btn-active': link.active }" /> -->
                                                  <!-- Pagination -->
                                            <Pagination :links="entries.links" :only="['entries','view_folder_id']"/>
                                        </div>
                                    </div>
                                </div>
                                <!-- else, if there are no entries and we're not adding a new entry, display message that there are no entries -->
                                <div v-else-if="state.mode != 'entry_add'" class="border p-4 text-xl text-center">No Matching Entries Found
                                </div>
                                <div v-else></div>


                            </div>
                            <div name="right-side" class="w-1/2 ml-4 mt-1">
                                <div id="disp_card" class="rounded-lg w-[580px] h-[480px] bg-base-200" :class="{ 'border border-base-content': state.mode === 'browse',
                                        'border-solid border-4 border-blue-800 h-[560px]': state.mode === 'entry_edit' || state.mode === 'entry_add' }">
                                <!-- HERE is the EntryForm -->
                                    <EntryForm
                                        ref = "EntryForm_ref"
                                        v-model:the_mode="state.mode"
                                        v-model:hotkey_pressed="hotkey_pressed"
                                        v-model:keep_row="keep_row"
                                        :index_form="index_form"
                                        :state="state"
                                        :p1="props"
                                        :file_view="'view'"
                                        :getFolderRow="getFolderRow"
                                        :getFolderData="getFolderData"
                                        />
                                </div>

                                    <!-- Entry List Button Row -->
                                    <div v-if="state.mode == 'browse'" name="control_buttons" class="mt-6 flex justify-around">
                                        <!-- <button type="button" id="addbutton" class="btn btn-primary btn-sm h-10 gap-0"
                                             @click="list_actions('add')" :disabled="state.view === 'due'">
                                            + &nbsp;<u>A</u>dd New Entry
                                        </button> -->
                                        <button type="button" id="addbutton" class="btn btn-primary btn-sm h-10 gap-0"
                                             @click="viewList_click('add_button')" :disabled="state.view === 'due'">
                                            + &nbsp;<u>A</u>dd New Entry
                                        </button>
                                        <button type="button" id="changebutton" class="btn btn-primary btn-sm h-10 gap-0"
                                            @click="list_actions('edit')" :disabled="entries.total === 0">
                                            △ &nbsp;<u>E</u>dit
                                        </button>
                                        <button type="button" id="deletebutton" name="deletebutton" href='' @click="display_modal('confirm_delete', true)"
                                            class="btn btn-outline btn-error btn-sm h-10 "  :disabled="entries.total === 0" >- &nbsp;Delete
                                        </button>
                                    </div>


                            </div>
                        </div>
                </div>
            </div>
        </div>


        <!-- Put this part before </body> tag - timeline add modal-->
        <dialog id="timeline_add_modal" class="modal">
            <div class="modal-box w-11/12 max-w-xl">
                <h3 class="font-bold text-xl text-center">Please Select a Folder For New Entry</h3>
                <div class="border border-base-content mt-4 w-64 mx-auto p-4">
                    <label>
                        <input type="radio" v-model="state.add_folder_id" id="corr" :value="1" autocomplete="off"> Correspondence
                    </label><br>
                    <label>
                        <input type="radio" v-model="state.add_folder_id" id="plea" :value="2" autocomplete="off"> Pleadings and Motions
                    </label><br>
                    <label>
                        <input type="radio" v-model="state.add_folder_id" id="disc" :value="3" autocomplete="off"> Discovery Requests
                    </label><br>
                    <label>
                        <input type="radio" v-model="state.add_folder_id" id="docs" :value="4" autocomplete="off"> Documents
                    </label><br>
                    <label>
                        <input type="radio" v-model="state.add_folder_id" id="memo" :value="5" autocomplete="off"> Memos and Notes
                    </label><br>
                    <label>
                        <input type="radio" v-model="state.add_folder_id" id="events" :value="6" autocomplete="off"> Events and Deadlines
                    </label><br>
                    <label>
                        <input type="radio" v-model="state.add_folder_id" id="todo" :value="7" autocomplete="off"> To-Do (for this file)
                    </label><br>
                    <label>
                        <input type="radio" v-model="state.add_folder_id" id="phone" :value="8" autocomplete="off"> Phone Messages
                    </label><br>
                    <label>
                        <input type="radio" v-model="state.add_folder_id" id="medrecs" :value="9" autocomplete="off"> Medical Records
                    </label><br>
                    <label>
                        <input type="radio" v-model="state.add_folder_id" id="medbills" :value="10" autocomplete="off"> Medical Bills
                    </label><br>
                    <label>
                        <input type="radio" v-model="state.add_folder_id" id="costs" :value="11" autocomplete="off"> Case Costs
                    </label><br>
                </div>
                <div class="modal-action justify-center mt-12">
                    <button type="button" class="btn btn-primary mr-10 w-28" @click="viewList_click('add_close')">Ok</button>
                    <!-- <button type="button" class="btn btn-primary" @click="display_modal('timeline_add', false)">Cancel</button> -->
                    <button type="button" class="btn btn-primary" @click="close_timeline_add_modal()">Cancel</button>
                </div>
            </div>
        </dialog>

        <!-- Put this part before </body> tag - Confirm_Delete modal -->
        <dialog id="confirm_delete_modal" class="modal">
            <div class="modal-box w-11/12 max-w-3xl">
                <h3 class="font-bold text-2xl text-center">Confirm: Delete Entry</h3>
                <p class="text-xl mt-4">Do you want to delete this entry?</p>
                <div class="modal-action justify-center mt-12">
                    <button type="button" class="btn mr-10 w-28 gap-0" @click="list_actions( 'delete' )"><u>Y</u>es</button>
                    <button type="button" class="btn gap-0" @click="display_modal( 'confirm_delete', false )"><u>N</u>o</button>
                </div>
            </div>
        </dialog>

        <!-- Put this part before </body> tag - Events Filters modal -->
        <dialog id="events_filters_modal" class="modal">
            <div class="modal-box w-[500px] h-[270px] overflow-visible">
                <h3 class="font-bold text-2xl text-center mb-5">Events & Deadlines Date Filter</h3>
            <!-- <div class="font-bold text-lg">
                Date Filter:
            </div> -->
                <div class="mt-1 ml-3">
                    <label for="all_events">
                    <input type="radio" v-model="disp.events_filter" id="all_events" value="all_events" checked /> - Show All Dates
                    </label>
                </div>
                <div class="mt-2 ml-3">
                    <label for="date_range">
                    <input type="radio" v-model="disp.events_filter" id="date_range" value="date_range"  /> - Specify a Date Range
                    </label>
                </div>
                <div v-if="disp.events_filter === 'date_range'" class="flex items-baseline mt-3 ">
                    <span class="">Starting: </span>
                    <VueDatePicker v-model="state.date_from" :enable-time-picker="false"
                        week-start="0" :model-type="'yyyy-MM-dd'" :format="'MM/dd/yyyy'"
                        class="pl-1 text-sm font-normal vdtp_main_date vdtp_date" auto-apply
                        :input-attrs="{ hideInputIcon: true, clearable: true }" @closed="" />

                    <span class="ml-4">Ending: </span>
                    <VueDatePicker v-model="state.date_to" :enable-time-picker="false"
                        week-start="0" :model-type="'yyyy-MM-dd'" :format="'MM/dd/yyyy'"
                        class="pl-1 text-sm font-normal vdtp_main_date vdtp_date" auto-apply
                        :input-attrs="{ hideInputIcon: true, clearable: true }" @closed="" />

                </div>
                <div class="modal-action justify-center mt-12 absolute inset-x-0 bottom-5">
                    <button type="button" class="btn btn-primary btn-outline w-32 gap-0" @click="set_events_filter()">Set Filter</button>
                </div>
            </div>
        </dialog>


    </AuthenticatedLayout>
</template>

<style>
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
