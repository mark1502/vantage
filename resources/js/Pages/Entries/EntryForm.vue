<script setup>
import { ref, reactive, nextTick, watch, onBeforeUnmount, computed, inject } from 'vue';
import { useForm, router } from '@inertiajs/vue3';
import InputError from "@/Components/InputError.vue";
import ContactLookup from "@/Components/ContactLookup.vue";
import AddContactForm from "@/Components/AddContactForm.vue";
import FileLookup_form from '@/Pages/Files/FileLookup_form.vue';
import DocumentPicker from "@/Components/DocumentPicker.vue";
import { VueDatePicker } from '@vuepic/vue-datepicker';
import axios from 'axios';

// Inject the theme ref and the setTheme function
const currentTheme = inject('currentTheme');
const setThemeFunction = inject('setThemeFunction');

const isDark = computed(() => {
    return currentTheme.value === 'dark' ? true : false; // Check the injected theme
});

const the_mode = defineModel('the_mode');               // references state.mode in parent
const hotkey_pressed = defineModel('hotkey_pressed');   // references hotkey_pressed in parent
const keep_row = defineModel('keep_row');               // references keep_row in parent

defineExpose({ update_disp });                          // expose the update_disp function to the parent component

const props = defineProps({
    state: Object,                                      // state object passed in from the index 
    index_form: Object,                                 // index form holds things like current row, mode, etc.
    p1: Object,                                         // all the props from the parent    
    file_view: String,                                  // does this form appear on the file or view index
    getFolderRow: Function,                             // passed function to get the folder row and data    
    getFolderData: Function,
});

const fileSpecific = ref("true");

const display_name = reactive({                         // used to display the from, to and file names on the form
    from: '',
    to: '',
    file: '',
});

const matching = reactive({                             // object holds matching entries from entrytype lookup
    entrytypes: Object,
});

const entry_form = useForm({                            // the entry_form - passed to the server
    formtype: "entry",
    file_id: null,
    folder_id: null,
    entry_id: null,
    input_time: null,
    hide_date2_prompt: null,
    entrytype_id: null,
    from_contact_id: null,
    to_contact_id: null,
    date1: null,
    date2: null,
    all_day: false,
    note: "",
    linked_document_path: null,
    date_response_expected: "",
    was_a_response: "",
    was_response_to: null,
    is_a_response: "",
    is_response_to: null,
    amount: null,
    current_page: props.index_form.current_page,
    show: props.index_form.show,
    filepart: props.index_form.filepart,
    comeback: true,
    view: props.state.view,
    view_for: props.state.initials, 
    viewpage: props.p1.entries.current_page,           // is this a problem?  should it be page 1?
    viewshow: props.state.show,
    read: props.state.read,
    from_to: props.state.from_to,
    new_entrytype_added: false,
    new_contact_added: false,
    pending_contact_roles: [],
});

let saved_entry_form = {};

const entrytype_form = useForm({                        // form for entrytype lookup and addition
    name: '',
    id: null,
    folder_id: null,
    isChosen: false,
    chosenName: '',
    lookup: false,
});

const disp = reactive({
    listFormat: 1,                                      // the format of the main listbox
    response_status: "",                                // displays the status of a response (i.e., late, ontime)
    response_color: 'text-error',
    entry_responses_received: Object,                   // display object of responses received to this entry
    show_entry_form: false,                             // shows or hides the entry form
    show_file_contacts: "off",                          // show file contacts modal - 'from', 'to' or 'off'
    show_contact_id: null,                              // used for file contacts modal
    show_contact_name: '',                              // used for file contacts modal
});

const related_entry = reactive({                        // used for displaying info about the related entry
    date: '',
    from: '',
    type: '',
    // The next var is used to track if an entry, which has a response, still expects a response.  It's needed to properly display responsive entries.
    expecting_response: false,
});

const responseModalRef = ref(null);

function openResponseModal() {
    if (responseModalRef.value) responseModalRef.value.showModal();
}

function closeResponseModal() {
    if (responseModalRef.value) responseModalRef.value.close();
}

// Entries in the current file that are expecting a response, formatted for the response picker
const responseCandidates = computed(() => {
    if (!props.p1.expecting_response || !props.p1.entries.data.length) return [];
    return props.p1.expecting_response
        .filter((e) => e.file_id === entry_form.file_id)
        .map((e) => ({
            id: e.id,
            date: e.date1,
            from: e.contact_from.display_last_first,
            type: findEntryType(e.folder_id, e.entrytype_id),
        }));
});

// Preserves the previously-saved response link when it's no longer in the expecting_response list
const fallbackResponseEntry = computed(() => {
    if (entry_form.was_response_to && !related_entry.expecting_response) {
        return {
            id: entry_form.was_response_to,
            date: related_entry.date,
            from: related_entry.from,
            type: related_entry.type,
        };
    }
    return null;
});

// Merges the fallback entry with candidates into a deduplicated list for the response picker
const responseRows = computed(() => {
    const rows = [];
    if (fallbackResponseEntry.value) rows.push(fallbackResponseEntry.value);
    responseCandidates.value.forEach((c) => {
        if (!rows.some((r) => r.id === c.id)) rows.push(c);
    });
    return rows;
});

// Formats the currently selected response entry as a display string (type - date - from)
const currentResponseDisplay = computed(() => {
    if (!entry_form.is_response_to) return null;
    const match = responseRows.value.find((r) => r.id === entry_form.is_response_to);
    if (!match) return null;
    return match.type + ' - ' + reformat_date(match.date) + ' - ' + match.from;
});

function pickResponseEntry(id) {
    entry_form.is_response_to = id;
    checkEditMode();
    closeResponseModal();
}

function onIsAResponseChange() {
    checkEditMode();
    if ((entry_form.is_a_response === 'P' || entry_form.is_a_response === 'F')
        && !entry_form.is_response_to) {
        openResponseModal();
    }
}

const folder_singular = [                               // this array is used to display the singular name of a folder
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

let added_contact_obj = reactive({                    // used for contact lookup
    name: '',
    id: 0,
    display_modal: false,
    accept: false,
    field: '',
    new_contact_added: false,
});

const mark_read = reactive({                            // used for the "Read" checkbox on memos and phone messages
    status_msg: '',
    button_msg: '',
    checked: false,
});

let save_clicked = false;                               // use this to avoid repeat of warning before unmounting
let refresh_entrytype_pending = ref(false);             // use this to stop update_disp (in onUpdate) upon return from posting to add a new entrytype
const showDocumentPicker = ref(false);                  // controls the DocumentPicker modal

// Contact role assignment state
const role_modal = reactive({
    show: false,
    contact_id: null,
    contact_name: '',
    selected_role: '',
    custom_role_label: '',
});
const known_role_contact_ids = ref([...(props.p1.contact_role_ids || [])]);
let suppress_role_check = false;

watch(() => props.state.row, (row) => {                 // watch the row - calls update_disp on change
    update_disp()
});

watch(() => disp.show_file_contacts, (newVal, oldVal) => {  // watch the show_file_contacts - if changed, update the display
    if (newVal === 'from' || newVal === 'to') {          // if the show_file_contacts is set to 'from' or 'to'
        nextTick(() => {
            document.getElementById('file_contacts_modal').showModal(); // open the file contacts modal
        });
    } else if (newVal === 'off') {
        document.getElementById('file_contacts_modal').close(); // close the file contacts modal
    }
});

watch(the_mode, (newMode, oldMode) => {                 // watch for mode change, including those set by a click on list button of parent
    if (newMode === 'entry_add') {
        nextTick(() => {
            setup_add();
        });
    }
    else if (newMode === 'entry_delete') entry_actions('submit');
    else if (newMode === 'set_edit') the_mode.value = 'entry_edit';     // it seems set_edit is used when user types in contact field in ContactLookup.vue
});

watch(hotkey_pressed, (hotkey) => {
    handle_hotkey_press(hotkey);
});

watch(fileSpecific, (newVal) => {
    if (newVal === "false") {
        entry_form.file_id = 1;
    } else {
        entry_form.file_id = null;
    }
});

// Watch file_id changes in view mode to fetch contact role IDs
watch(() => entry_form.file_id, (newVal, oldVal) => {
    if (suppress_role_check || !newVal) return;
    if (newVal === 1) return;
    if (props.file_view === 'view' && (props.state.mode === 'entry_add' || props.state.mode === 'entry_edit')) {
        axios.get('/contact-role-ids/' + newVal)
            .then(response => {
                known_role_contact_ids.value = response.data || [];
            })
            .catch(() => {
                known_role_contact_ids.value = [];
            });
    }
});

// Watch from_contact_id for role assignment
watch(() => entry_form.from_contact_id, (newVal, oldVal) => {
    if (suppress_role_check || !newVal) return;
    if (props.state.mode === 'entry_add' || props.state.mode === 'entry_edit') {
        nextTick(() => checkContactRole(newVal, display_name.from));
    }
});

// Watch to_contact_id for role assignment
watch(() => entry_form.to_contact_id, (newVal, oldVal) => {
    if (suppress_role_check || !newVal) return;
    if (props.state.mode === 'entry_add' || props.state.mode === 'entry_edit') {
        nextTick(() => checkContactRole(newVal, display_name.to));
    }
});



function isTimePicker() {                                                                               // this function enables or disables the timepicker on a datetimepicker as needed
    if (entry_form.all_day === true) return false;                                                      // if all_day is checked, no time picker
    let sendback = null;
    if(props.state.mode === 'entry_add') {                                                              // if adding a new entry
        sendback = props.p1.folders[props.getFolderRow()].input_time === 0 ? false : true;              // determine if the entry inputs time
    } 
    else if( props.p1.entries.data.length > 0 ) {                                                       // else, determine based on the folder of the entry
        sendback = props.p1.folders[entry_form.folder_id - 1].input_time === 0 ? false : true;
    }
    else sendback = false;                                                                              // else, no time entry

    return sendback;
}

function entry_datepicker_closed(theDP) {                                                               // determin what's next after a datepicker closes
    let go_next = '';
    checkEditMode();                                                                                    // first, check if the date changed to enable edit mode

    switch (theDP) {
        case 'date1':
            go_next = props.p1.folders[props.getFolderRow()].hide_date2_prompt == true ? "entry_entrytype_select" : "dp-input-entry_date2";
            break;
        case 'date2': go_next = 'entry_entrytype_select';   // go_next = 'entry_from';
            break;
        case 'date_response_expected': go_next = 'entry_from';
            break;
    }

    if (go_next) {
        nextTick(() => { document.getElementById(go_next).focus(); });
    }
}


function checkEditMode() {                                              // change to edit mode if there's a change on the form
    if (objectChanged(entry_form, saved_entry_form)) {
        if (props.state.mode == 'browse') the_mode.value = 'entry_edit';   // only change to edit mode if browse mode (but not entry_add)
    } else if (props.state.mode == 'entry_edit') {                     // else, if in entry_edit mode and no change, set to browse mode
        the_mode.value = 'browse';
    }
}


function clicked_AddTypeButton() {                                      // the user clicked the Add Type button
    if (props.state.mode == 'browse') {                                 // if in browse mode, then set to 'entry_edit'
        () => the_mode.value = 'entry_edit';
    }
    entrytype_form.reset();                                             // clear entrytype form
    document.getElementById('entrytype_modal').showModal();          // open the entytype form modal
    nextTick(() => {
        document.getElementById('type_name').focus();                   // set the focus to the entrytype name on the form
    });
}


function clicked_matchingEntrytype(index) {                             // the user clicked a matching existing entrytype while adding new entrytype
    entrytype_form.name = matching.entrytypes[index].name;              // so, copy those values and close that lookup as chosen
    entrytype_form.id = matching.entrytypes[index].id;
    entrytype_form.isChosen = true;
    entrytype_form.chosenName = entrytype_form.name;
    entry_form.entrytype_id = entrytype_form.id;                        // copy value to entry form
    display_modal('entrytype', false);                                  // close the modal
}


function display_modal(which_modal, OnOff = null) {
    // console.log('called:' + which_modal);
    let modal_name = '';
    if (which_modal === 'filechanged') modal_name = 'filechanged_modal';
    else if (which_modal === 'sol') modal_name = 'sol_modal';
    else if (which_modal === 'entrychanged') modal_name = 'entrychanged_modal';
    //         else if( which_modal === 'contact' ) modal_name = 'contact_modal';
    else if (which_modal === 'entrytype') modal_name = 'entrytype_modal';
    else if (which_modal === 'addcancelled') modal_name = 'addcancelled_modal';

    nextTick(() => {
        if (modal_name !== '') {
            const el = document.getElementById(modal_name);
            if ((OnOff === true || OnOff === 'show' || OnOff === 'on')) el.showModal();
            else if ((OnOff === false || OnOff === 'hide' || OnOff === 'off')) el.close();
            else if (OnOff === null) el.open ? el.close() : el.showModal();
            else if (OnOff === 'status') return el.open;
        }
    });
}


function lookup_entrytype() {                                           // this function returns up to 6 matching entrytypes from those available for this folder
    let row2get = props.getFolderRow();                                 // set the folder 
    matching.entrytypes = props.p1.folders[row2get].entrytypes.filter(function (e) {
        if (e.name.toLowerCase().includes(entrytype_form.name.toLowerCase()) && this.count < 6) {  // set the test and limit the filtered list to 6 entries
            this.count++;
            return true;
        }
        else return false;
    }, { count: 0 }); // end of filter
};


function clicked_entrytypeModal_button(clicked_button) {
    if (clicked_button == 'ok') {
        if (entrytype_form.isChosen == true && entrytype_form.name == entrytype_form.chosenName) {
            entry_form.entrytype_id = entrytype_form.id;
            display_modal('entrytype', false);
        } else {
            // Check if an entrytype with this name already exists (case-insensitive) before posting
            let row2get = props.getFolderRow();
            const existingMatch = props.p1.folders[row2get].entrytypes.find(
                e => e.name.toLowerCase() === entrytype_form.name.trim().toLowerCase()
            );
            if (existingMatch) {
                entry_form.entrytype_id = existingMatch.id;
                display_modal('entrytype', false);
                return;
            }
            entrytype_form.folder_id = props.getFolderData('id');
            entrytype_form.post('/add_new_entrytype',
            {   only: ['folders', 'new_entrytype'], 
                preserveState: true,
                onSuccess: () => {
                    nextTick( () => {
                        entry_form.entrytype_id = props.p1.new_entrytype.id;
                        entry_form.new_entrytype_added = true;
                        // document.getElementById('entrytype_modal').close();          // close the entrytype form modal
                        display_modal('entrytype', false);
                        console.log('back from new entrytype');
                    });
                } // onSuccess
            }); // post
        } // if isChosen
    }
    else if (clicked_button == 'cancel') {
        entrytype_form.reset();
    }

    document.getElementById('entrytype_modal').showModal();
}

function clicked_mark_read() {                                                                      // user clicked the mark read checkbox, so handle toggle and update the record
    axios.put('/toggle_read/' + entry_form.entry_id )
    .then(response => {
        keep_row.value = props.state.row;
        document.activeElement.blur();

        if( props.file_view === 'file' ) {
            router.reload( { 
                only: ['entries','view_folder_id'], 
                replace: true, 
                data: { 
                    filepart: props.state.folder_name, 
                    page: props.p1.entries.current_page,
                    show: props.state.show,
                },
                headers: { 'X-Custom-Refresh': 'KeepRow' },                
            });
        } else if( props.file_view === 'view' ) {
            router.reload( { 
                only: ['entries','view_folder_id'], 
                replace: true, 
                data: {
                    view: props.state.view,
                    view_for: props.state.initials, 
                    page: props.p1.entries.current_page, 
                    show: props.state.show,
                    read: props.state.read,
                    from_to: props.state.from_to,
                },
                headers: { 'X-Custom-Refresh': 'KeepRow' },
            });
        }
    })
    .catch(error => {
        console.log('There was an error', error);
    });    
}

function clicked_entryform_button(what, index = null) {                                             // there was a click on the entry form buttons
    switch (what) {
        case 'ok':
            save_clicked = true;                                                                    // set to avoid reaction when component is unmounted
            entry_actions('submit');
            break;
        case 'cancel':
            if (props.state.mode === 'entry_add') {                                                 // if cancelling entry_add, display modal confirming they want to cancel
                display_modal('addcancelled', true);
            } else if (props.state.mode === 'entry_edit') {                                         // else if entry_edit' cancelled
                if (objectChanged(saved_entry_form, entry_form) == true) {                          // if there's a change in the form
                    display_modal('entrychanged', true);                                            // disnplay modal confirming if they want to cancel
                } else {
                    entry_actions('revert');                                                        // else, no change, so revert/cancel
                }
            }

            break;
    }
}

function entry_actions(action, comeback = true) {
    if( action === 'submit' ) {                                                                      // if submitting the form
        entry_form.comeback = comeback;                                                             // comeback tells controller to come back after update (if not, go to the other clicked component)
        let theEntry = props.p1.entries.data[props.state.row];

        if( props.state.mode === 'entry_edit' ) {                                                    // if submitting from 'edit_entry'
            if( props.file_view === 'file' ) submit_file_edit();
            else if( props.file_view === 'view' ) submit_view_edit();


        } else if (props.state.mode === 'entry_add') {                                              // else if submitting 'entry_add'
            if( props.file_view === 'file' ) submit_file_add();
            else if( props.file_view === 'view' ) submit_view_add();

        } else if (props.state.mode === 'entry_delete') {
            // entry_form.delete(route('entries.destroy', [props.p1.file.id, props.p1.entries.data[props.state.row].id]),
            entry_form.delete(route('entries.destroy', { file: theEntry.file_id, entry: theEntry.id } ),            
                {   onSuccess: () => {
                        the_mode.value = 'browse';
                    },
                    preserveState: (page) => Object.keys(page.props.errors).length,
                });  // end delete
        }
    } else if (action == 'revert') {
        entry_form.reset();
        Object.keys(saved_entry_form).forEach(function (key, idx, arr) {    // copy the saved value back to the form
            entry_form[key] = saved_entry_form[key];
        });
        entry_form.pending_contact_roles = [];                              // clear pending contact roles on revert
        known_role_contact_ids.value = [...(props.p1.contact_role_ids || [])]; // reset known role contact ids

        if (props.state.mode === 'entry_add') {
            display_modal('addcancelled', false);   // close the modal    
        }
        else if (props.state.mode === 'entry_edit') {
            display_modal('entrychanged', false);   // close the modal
        }

        the_mode.value = 'browse';                // set action to browse
        update_disp();
    }
}


function submit_file_add() {                                                            // function to submit a new entry to a file
    entry_form.file_id = props.p1.file.id;                                      // add file_id (passed in) to the form
    entry_form.formtype = 'file'                                                        // formtype = 'file'
    let action_url = '/files/' + entry_form.file_id + '/entries';                   // set the action_url for the post (to store in EntryController)

/*
    // entry_form.new_contact_added = isNewFileContact();                                  // is there a new file contact in this form?    
    // let refresh_arr = ['entries','view_folder_id'];
    // if( entry_form.new_contact_added === true ) refresh_arr.push('file_contacts');      // set in isNewFileContact()
    // if( entry_form.new_entrytype_added === true ) refresh_arr.push('folders');          // set when a new entrytype is added
*/

    if( entry_form.file_id ) {                                                      // if the form has a file_id specified
        entry_form.post( action_url,                                                    // post to action_url
            {   preserveState: (page) => Object.keys(page.props.errors).length,         // preserveState if there are errors
            });
    } else alert('Please select a file for this entry.');                               // else, no file specified, so alert

    
}

function submit_view_add() {
    // entry_form.file_id = props.p1.file.id;                                      // add file_id (passed in) to the form
    entry_form.formtype = 'view'                                                        // formtype = 'file'
    let action_url = '/views';                   // set the action_url for the post (to store in EntryController)

    /*
    // entry_form.new_contact_added = isNewFileContact();                                  // is there a new file contact in this form?    
    // let refresh_arr = ['entries','view_folder_id'];
    // if( entry_form.new_contact_added === true ) refresh_arr.push('file_contacts');      // set in isNewFileContact()
    // if( entry_form.new_entrytype_added === true ) refresh_arr.push('folders');          // set when a new entrytype is added
    */
   
    if( entry_form.file_id ) {                                                          // if the form has a file id specified
        entry_form.post( action_url,                                                        // post to action_url
            {   preserveState: (page) => Object.keys(page.props.errors).length,             // preserveState if there are errors
            });
    } else alert('Please select a file for this entry.');                                   // else, no file specified, so alert

    
}

function submit_file_edit() {                                                                   // submit an edited entry to a file
    display_modal('entrychanged', false);                                                       // close entrychanged modal if it's open
    let theEntry = props.p1.entries.data[props.state.row];

    if( objectChanged( entry_form, saved_entry_form ) ) {                                       // if data on the the form changed
        entry_form.filepart = props.state.folder_name;                                          // put the currently selected filepart in the form
        entry_form.put( route( 'entries.update', { file: theEntry.file_id, entry: theEntry.id }),        // submit the update
            {   preserveState: (page) => Object.keys(page.props.errors).length,                 // preserveState - true if there are errors
                onError: (errors) => console.log(errors),
            });
    } else {                                                                                    // else, form data did not change
        the_mode.value = 'browse';                                                              // clicked Ok/Save, but no changes on the form, so just set to browse mode
    }
}

function submit_view_edit() {                                                                   // submit an edited entry to a view
    display_modal('entrychanged', false);                                                       // close entrychanged modal if it's open

    if( objectChanged( entry_form, saved_entry_form ) ) {                                       // if data on the the form changed
        if( props.state.view === 'memos' ) entry_form.filepart = 'memos';                       // set the entry_form.filepart based on the current view
        else if( props.state.view === 'phone' ) entry_form.filepart = 'phone';      
        else if( props.state.view === 'todo' ) entry_form.filepart = 'todo';

        entry_form.put( route( 'views.update', { view: entry_form.entry_id } ),                         // submit the update
            {   preserveState: (page) => Object.keys(page.props.errors).length,                 // preserveState - true if there are errors
                onError: (errors) => console.log(errors),
            });
    } else {                                                                                    // else, form data did not change
        the_mode.value = 'browse';                                                              // clicked Ok/Save, but no changes on the form, so just set to browse mode
    }
    
}

function isNewFileContact() {
    let new_contact_found = false;                                                                                  // start as false

    let test_from_id = props.p1.file_contacts.find( (contact) => contact.id === entry_form.from_contact_id );       // find from_contact_id in file_contacts
    if( test_from_id === undefined ) new_contact_found = true;                                                      // if undefined, it was not found, so new contact is true

    if( props.getFolderData('hide_to_prompt') === false ) {                                                               // if the form has a 'to' field
        let test_to_id = props.p1.file_contacts.find( (contact) => contact.id === entry_form.to_contact_id );       // find to_contact_id in file_contacts
        if( test_to_id === undefined ) new_contact_found = true;                                                    // if undefined, it was not found, so new contact is true
    }

    return new_contact_found;
}


function checkContactRole(contact_id, contact_name) {
    if (!contact_id) return;
    if (entry_form.file_id === 1) return;
    // Already has a role for this file
    if (known_role_contact_ids.value.includes(contact_id)) return;
    // Already pending assignment
    if (entry_form.pending_contact_roles.some(p => p.contact_id === contact_id)) return;

    // Open the role modal
    role_modal.contact_id = contact_id;
    role_modal.contact_name = contact_name || '';
    role_modal.selected_role = '';
    role_modal.custom_role_label = '';
    role_modal.show = true;
    nextTick(() => {
        document.getElementById('role_assign_modal').showModal();
    });
}

function clicked_roleModal_button(action) {
    if (action === 'assign') {
        const roleLabel = role_modal.selected_role === 'other'
            ? role_modal.custom_role_label
            : (props.p1.role_options[role_modal.selected_role] || role_modal.selected_role);
        entry_form.pending_contact_roles.push({
            contact_id: role_modal.contact_id,
            role: role_modal.selected_role,
            role_label: roleLabel,
        });
        known_role_contact_ids.value.push(role_modal.contact_id);
    }
    // Close modal for both assign and skip
    role_modal.show = false;
    document.getElementById('role_assign_modal').close();
}

function objectChanged(obj_1, obj_2) {
    return JSON.stringify(obj_1) !== JSON.stringify(obj_2);
}


function update_disp() {
    suppress_role_check = true;                                                                     // suppress role check while populating form
    if (props.p1.entries.data.length && ( props.state.row < props.p1.entries.data.length) ) {       // if there are entries (and active row is within range), update the entry_form
        disp.show_entry_form = true;                                                                // display the entry form on the screen

        let theEntry = props.p1.entries.data[props.state.row];                                      // shortcut to the highlighted entry for cleaner code

        entry_form.file_id = theEntry.file_id;                                              // set the file id in the form
        fileSpecific.value = (theEntry.file_id !== 1) ? "true" : "false";
        display_name.file = (theEntry.file_id === 1) ? 'Not File Related' : getFileName(); // set the display file name for the form

        entry_form.folder_id = theEntry.folder_id;                                                  // folder_id
        entry_form.entry_id = theEntry.id;                                                          // entry_id
        entry_form.date1 = theEntry.date1;                                                          // date1
        entry_form.date2 = theEntry.date2;                                                          // date2
        entry_form.all_day = theEntry.all_day ?? false;                                             // all_day
        entry_form.entrytype_id = theEntry.entrytype_id;                                            // entrytype_id

        entry_form.input_time = props.p1.folders[theEntry.folder_id - 1].input_time;                // input_time (T/F)
        entry_form.hide_date2_prompt = props.p1.folders[theEntry.folder_id - 1].hide_date2_prompt;  // hide date2 (T/F)

        entry_form.from_contact_id = theEntry.from_contact_id;                                      // from_contact_id

        if( props.file_view === 'file' ) {                                                          // if viewing a 'file' entry
            display_name.from = findFileContact( theEntry.from_contact_id );                        // set displayname.from from FileContacts
        } else if( props.file_view === 'view' ) {                                                   // else if viewing a 'view'
            display_name.from = theEntry.contact_from.display_last_first;                           // set displayname.from from the entry
        }

        if (theEntry.to_contact_id) {                                                               // if there's a 'to' contact_id
            entry_form.to_contact_id = theEntry.to_contact_id;                                      // copy that to the form

            if( props.file_view === 'file' ) {                                                      // if viewing a 'file' entry
                display_name.to = findFileContact( theEntry.to_contact_id );                        // set displayname.to from FileContacts
            } else if( props.file_view === 'view' && theEntry.contact_to.display_last_first !== undefined ) {  // else if viewing a 'view' && there's a to_contact attached
                display_name.to = theEntry.contact_to.display_last_first;                           // set displayname.to from the entry
            }
        } else {                                                                                    // else, no contact_to_id
            entry_form.to_contact_id = null;                                                        // so set to null
            display_name.to = '';                                                                   // clear display_name.tow
        }

        if( entry_form.folder_id === 5 || entry_form.folder_id === 8 ) {                            // if it's a 'memo' or 'phone' entry
            let thebox = document.getElementById("read_checkbox");                                  // find the read_checkbox element
            if( thebox && entry_form.date2 ) {                                                      // if checkbox element is found && the form has a date2
                if('checked' in thebox ) thebox.checked = true;                                     // if 'checked' is a property of the box, set it to true
            } else if( thebox ) {                                                                   // else if we found checkbox element, but no date2 in form
                if('checked' in thebox ) thebox.checked = false;                                    // if 'checked' is a property of the box, set it to false
            }
        }

        entry_form.note = theEntry.note;                                                            // note
        entry_form.linked_document_path = theEntry.linked_document_path ?? null;                    // linked_document_path
        entry_form.date_response_expected = theEntry.date_response_expected;                        // date_response_expected
        entry_form.amount = theEntry.amount;                                                        // amount

        disp.entry_responses_received = theEntry.responses_received;                                // display responses received to this entry

        nextTick(() => {
            if (theEntry.expecting_response) {                                                      // if entry is/was expecting a response, determine status of the expectation
                disp.response_status = determine_response_expectation(theEntry.date_response_expected);
            } else disp.response_status = "";                                                       // else, clear response status

            // now set the response status color
            switch (disp.response_status) {
                case '(Full response was received on time)':
                case 'Awaiting response':
                case '':
                    disp.response_color = 'text-success';                                         // display green for on time or awaiting or empty status
                    break;
                default:
                    disp.response_color = 'text-error';                                           // otherwise display as red
                    break;
            }

            if( theEntry.response ) {                                                               // if this entry is a response to another, set response type and id of related entry

                let theRelated = theEntry.response.response_to;                                     // shortcut to related entry for cleaner code

                entry_form.was_a_response = theEntry.response.response_type;                        // just starting, so save what type of response is this (and was at the start)
                entry_form.was_response_to = theRelated.id;                                         // response to what entry
                entry_form.is_a_response = theEntry.response.response_type;                         // and again, for what type is it
                entry_form.is_response_to = theRelated.id;                                          // what's the id of the entry this is a response to

                related_entry.date = theRelated.date1;                                              // write fields to display the related entry, date
                related_entry.from = theRelated.contact_from.display_last_first;                    // from (display_last_first)
                related_entry.type = findEntryType(theRelated.folder_id, theRelated.entrytype_id);  // entrytype of related entry

                related_entry.expecting_response = theRelated.expecting_response;                   // this field is used to properly display responsive entries - see the html part
            } else {                                                                                // else, this entry is not a response, so set the form accordingly
                entry_form.was_a_response = "N";
                entry_form.is_a_response = "N";
                entry_form.was_response_to = null;
                entry_form.is_response_to = null;
            }

            saved_entry_form = { ...entry_form };                                                   // clone a copy of the entry_form
            suppress_role_check = false;                                                            // re-enable role check after form is populated
        }); // end nextTick()
    } // end if length
    else {                                                                                          // else, no entries found
        disp.show_entry_form = false;                                                               // so, do not show the entry_form on the screen
        disp.folder_name = '';
        entry_form.reset();                                                                         // reset the form
        saved_entry_form = { ...entry_form }; // clone a copy of the entry_form                     // clone the empty entry_form
        suppress_role_check = false;                                                                // re-enable role check
    }

    if( keep_row.value > 0 ) keep_row.value = 0                                                    // if there's a keep_row value, reset it to 0
} // end update_disp function


const fileContactsWithRoles = computed(() => {
    if (!props.p1.file_contacts || !props.p1.file_contacts.length) return [];

    const roleMap = {};
    if (props.p1.file_contact_roles && props.p1.file_contact_roles.length) {
        props.p1.file_contact_roles.forEach(cr => {
            if (!roleMap[cr.contact_id]) {
                roleMap[cr.contact_id] = [];
            }
            roleMap[cr.contact_id].push(cr.role_name);
        });
    }

    return props.p1.file_contacts.map(contact => ({
        id: contact.id,
        display_last_first: contact.display_last_first,
        role_display: roleMap[contact.id] ? roleMap[contact.id].join(', ') : 'Not specified',
    }));
});


function findFileContact( find_contact_id ) {
    // return props.p1.file_contacts.find( (contact) => contact.id === find_contact_id ).display_last_first;
    return fileContactsWithRoles.value.find( (contact) => contact.id === find_contact_id )?.display_last_first ?? '';
}


function findEntryType( find_folder_id = 0, find_entrytype_id= 0 ) {
    let folder_id = 0;
    if( find_folder_id > 0 ) folder_id = find_folder_id - 1;

    if( find_entrytype_id !== 0 ) {
        return props.p1.folders[ folder_id ].entrytypes.find( (entrytype) => entrytype.id === find_entrytype_id ).name;
    } else return '';
}

function findEntryTypeID( find_folder_id = 0, find_entrytype_name = "" ) {
    let sendback = null;
    if( find_folder_id > 0 && find_entrytype_name !== "" ) {
        let folder_row = find_folder_id - 1;
        sendback = props.p1.folders[ folder_row ].entrytypes.find( (entrytype) => entrytype.name === find_entrytype_name ).id;
    }
    if( sendback === null ) console.log( 'Error finding EntryTypeID for ' + find_entrytype_name );
    return sendback;
}

function getFileName() {                                                    // note: this function avoids an error if looking for undefined 'file' in 'file' entries
    if (props.p1.entries.data[props.state.row]?.file_id === 1) return 'Not File Related';
    if( 'file' in props.p1.entries.data[props.state.row] ) {                // if the prop 'file' is in the entries object, return the name
            return props.p1.entries.data[props.state.row].file.name;
    } else return '';                                                           // else, return empty string
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

function format_response_li(response) {                                         // function to format the display of a received response
    let dateof = '';
    let resptype = '';
    let stringback = '';

    if( response.response_type == 'P' ) resptype = 'partial response';
    else if( response.response_type == 'F' ) resptype = 'full response';

    dateof = reformat_date(response.response_date);

    // stringback = String.fromCharCode(0x2022) + ' Received a ' + resptype + ' on ' + dateof;
    stringback = '- Received a ' + resptype + ' on ' + dateof;

    return stringback;
}

function determine_response_status(date_expected, date_received, fullYN) {
    let date1 = new Date(date_expected);
    let date2 = new Date(date_received);
    let lateYN = date2 > date1 ? "late" : "on time";

    let sendback = fullYN == 'F' ? '(Full response was received' : '(Partial response was received';
    sendback += ' ' + lateYN + ')';
    return sendback;
}

function determine_response_expectation(date_expected) {
    let date1 = new Date(date_expected);
    let date2 = new Date();
    let sendback = date2 > date1 ? "Awaiting response (overdue)" : "Awaiting response";

    return sendback;
}

function selectFileContact( var_in = "from" ){
    if( var_in === "cancel" ) {                                                            // if the user clicked cancel, then just close the modal
        disp.show_file_contacts = 'off';                                                        // close the file contacts modal
        disp.show_contact_id = null;                                                             // clear the contact id
    } else if( disp.show_file_contacts === 'from' ) {                                                     // if the file contacts are being selected from the 'from' field
        entry_form.from_contact_id = disp.show_contact_id
        display_name.from = findFileContact( disp.show_contact_id );                            // set the display name for 'from' contact
        disp.show_file_contacts = 'off';
        disp.show_contact_id = null;
        checkEditMode();                                                                      // check if the form changed to set edit mode
    } else if( disp.show_file_contacts === 'to' ) {                                               
        entry_form.to_contact_id = disp.show_contact_id
        display_name.to = findFileContact( disp.show_contact_id );                            // set the display name for 'from' contact
        disp.show_file_contacts = 'off';
        disp.show_contact_id = null;
        checkEditMode();                                                                      // check if the form changed to set edit mode
    }
}

function setup_add() {                                                                                  // function to setup addition of new entry
    disp.show_entry_form = true;                                                                        // display the form on the screen
    entry_form.reset();                                                                                 // reset the form
    fileSpecific.value = "true";
    entry_form.pending_contact_roles = [];                                                              // clear pending contact roles
    known_role_contact_ids.value = [...(props.p1.contact_role_ids || [])];                               // reset known role contact ids
    objectClear( display_name );                                                                        // clear display names
    disp.response_status = '';                                                                          // clear the display of response status and the responses received array
    disp.entry_responses_received = [];

    let addFolderRow = props.state.add_folder_id - 1;                                                   // set the addFolderRow
    if( addFolderRow < 0 ) addFolderRow = 0;

    nextTick(() => {
        entry_form.file_id = props.file_view === 'file' ? props.p1.file.id : null;              // set the file id, if 'view', then null

        entry_form.folder_id = props.state.add_folder_id;                                               // set the new entry folder id
        if( props.getFolderData('hide_entrytype_prompt' ) == 1) {                                       // if the folder hides the entrytype prompt
            entry_form.entrytype_id = props.p1.folders[addFolderRow].entrytypes[0].id;                  // use id of the first (and only) entrytype for this folder 
        } else if( props.state.add_folder_id === 1 ) {                                                  // else if it's the correspondence folder
            entry_form.entrytype_id = findEntryTypeID( 1, 'Letter' );                                   // initially set the entrytype_id to the id for 'Letter'
        }
        entry_form.was_a_response = 'N';                                                                // set form was and is response to No
        entry_form.is_a_response = 'N';                                                                 
        entry_form.input_time = props.getFolderData('input_time');                                      // are we inputing time with the date
        entry_form.hide_date2_prompt = props.getFolderData('hide_date2_prompt');                        // are we hiding the 2nd date field
        entry_form.all_day = false;                                                                     // initialize all_day to false

        saved_entry_form = { ...entry_form };                                                           // clone a copy of the entry_form
    });

    nextTick(() => {
        if( props.file_view === 'file' ) document.getElementById("dp-input-entry_date1").focus();       // if 'file' addition - set focus to date1 
        else if( props.file_view === 'view' ) document.getElementById("file_lookup").focus();           // else if 'view' addition - set focus to file_lookup
    });
}

function objectClear(obj) {
    var key;
    for (key in obj) {
        obj[key] = '';
    }
}

function handle_hotkey_press(e) {
    if (e.key === 'Escape') {                                                                           // if Escape key is pressed
        if (props.state.mode === 'entry_add') {                                                         // if esc while adding entry, show modal
            display_modal('addcancelled', true);
        } else if (props.state.mode === 'entry_edit') {                                                 // else if esc while editing entry
            if (objectChanged(saved_entry_form, entry_form) == true) {                                  // if there are changes on the form, show modal
                display_modal('entrychanged', true);
            } else {                                                                                    // else, just revert
                entry_actions('revert');
            }
        } else if( false ){}
         // end if props.state.mode
    } // end if e.key === 'Escape'

    if( props.state.mode === 'entry_add' ) {                                                            // if entry_add mode,the hotkey is from addcancelled_modal (Y or N)
        if( e.key === 'y' || e.key === 'Y') entry_actions('revert');                                    // if y or Y, then revert, otherwise close the modal
        else if( e.key === 'n' || e.key === 'N' ) document.getElementById('addcancelled_modal').close();
    }
    if( props.state.mode === 'entry_edit' ) {                                                           // if entry_edit mode, hotkey is from addcancelled_model (Y or N)
        if( e.key === 'y' || e.key === 'Y') clicked_entryform_button('ok');                             // if y or Y, save changes
        else if( e.key === 'n' || e.key === 'N' ) entry_actions('revert');                              // else if n or N, then revert
    }    
}


onBeforeUnmount(() => {                     // For some reason, this event gets called when switching between folders (I don't think it should unMount in that situation)
    let afield = [                          // entry_form.isDirty (an inertia form variable) is being set (I'm not sure where), and therefore the entry_form and saved_entry_form weren't matching
        'file_id',                      // when switching between a folder with no entries to one with entries.  Therefore, using the objectChanged() test found changes (due to isDirty)
        'folder_id',                        // though there were no actual changes
        'entry_id',
        'entrytype_id',                     // So, now onBeforeUnmount, test only for changes in these form fields, and if there's a change, ask if the user wants to save the changes
        'from_contact_id',
        'to_contact_id',
        'date1',
        'date2',
        'note',
        'linked_document_path',
        'date_response_expected',
        'is_a_response',
        'is_response_to',
    ];

    let shouldAsk = false;
    let key = '';
    for ( key in entry_form ) {
        if( afield.includes(key) && entry_form[key] !== saved_entry_form[key] ) shouldAsk = true; // if the key is in the list and doesn't match, then shouldAsk is true
    }

    if ( shouldAsk === true && save_clicked === false) {    // save_clicked is set when the save button is clicked and is used to avoid this warning appearing on unMount from a save
        let r = confirm("There are unsaved changes to this entry. Do you want to save the changes to the entry?\n\nClick 'Ok' to save the changes, or 'Cancel' to discard the changes.");
        if (r == true) entry_actions('submit', false);
    }
});


update_disp();

</script>

<template>
    <div v-if="disp.show_entry_form === true">
        <!-- START of This Entry Form -->
        <form id="entry_form" @submit.prevent="submit_ThisEventForm" class="max-w-5xl mx-auto mt-1 p-2 pb-6">
                <!-- File Row (for use on a view only, while adding) -->
            <div v-if="props.file_view === 'view' && state.mode === 'entry_add'" class="flex items-baseline mb-4">
                <label class="text-sm font-semibold w-28">File:</label>
                <div>
                    <div class="flex items-baseline">
                        <input type="radio" id="fileSpecific" v-model="fileSpecific" value="true" />
                        <label for="fileSpecific" class="ml-1 mr-2 text-sm"> - </label>
                        <FileLookup_form
                            v-if="fileSpecific === 'true'"
                            v-model:file_id="entry_form.file_id"
                            id="file_lookup_input"
                            :state="state"
                            :hideLabel="true"
                        />
                        <span v-else class="ml-1 text-sm">File Related</span>
                    </div>
                    <div class="flex items-baseline pt-2">
                        <input type="radio" id="not_fileSpecific" v-model="fileSpecific" value="false" />
                        <label for="not_fileSpecific" class="ml-1 text-sm"> - Not File Related</label>
                    </div>
                </div>
            </div>
                <!-- File Row (for view only, but not adding, so display disabled) -->
            <div v-else-if="props.file_view === 'view'">
                <div class="flex items-baseline mb-4">
                    <label for="display_casename" class="text-sm font-semibold w-28">File:</label>
                    <input :value="entry_form.file_id === 1 ? 'Not File Related' : display_name.file"
                        id="display_casename" autocomplete="off" disabled
                        class="input input-bordered input-sm text-sm rounded-sm w-64 disabled:input-bordered disabled:border-base-300 disabled:text-base-content"/>
                </div>
            </div>
            <!-- Folder row - HIDDEN - Should probably remove-->
            <!-- <div v-if="false" class="flex">
                <label for="file_folder_name" class="text-sm font-semibold pl-4">
                    Folder:
                </label>
                <input type="text" v-model="props.p1.entries.data[props.state.row].folder.name" id="file_folder_name"
                    readonly class="w-64 text-sm bg-sky-50 " />
                <input type="hidden" id="entry_id" v-model="entry_form.entry_id" />
            </div> -->

            <!-- All Day Event checkbox (for events only) -->
            <div v-if="entry_form.folder_id === 6" class="flex items-center mb-1 ml-29">
                <input v-model="entry_form.all_day" type="checkbox" id="all_day_checkbox"
                    @change="checkEditMode()">
                <span class="text-sm ml-1">- All Day Event</span>
            </div>

            <!-- Dates 1 & 2 Row -->
            <div class="flex items-baseline">
                <!-- Date 1 Row -->
                <div class="flex items-baseline">
                    <label for="dp-input-entry_date1" class="text-sm font-semibold w-28">
                        {{ props.getFolderData('date1_prompt') }}
                    </label>
                    <div class="">
                        <VueDatePicker v-model="entry_form.date1" :model-type="'yyyy-MM-dd HH:mm:ss'"
                        class="" :class="isTimePicker() ? 'vdtp_main_time vdtp_time' : 'vdtp_main_date vdtp_date'"
                        :time-config="{ enableTimePicker: isTimePicker(), is24: false, timePickerInline: true, minutesIncrement: 5 }"
                        week-start="0"
                        :auto-apply="isTimePicker() ? false : true"
                        text-input :input-attrs="{ id: 'dp-input-entry_date1', clearable: false, hideInputIcon: true }"
                        @closed="entry_datepicker_closed('date1')" required :dark="isDark"
                         />

                        <InputError class="mt-1 ml-5" :message="entry_form.errors.date1" />
                    </div>
                </div>
                <!-- Read/Unread row (for memos and phone msg) -->
                <div v-if="entry_form.folder_id === 5 || entry_form.folder_id === 8" class="">
                    <div class="flex items-center ml-6">
                        <input v-model="mark_read.checked" type="checkbox" id="read_checkbox" class="" @click="clicked_mark_read()">
                        <span class="text-sm ml-1">- <u>R</u>ead</span>
                    </div>
                </div>
                <!-- Date 2 Row -->
                <div v-else-if="props.getFolderData('hide_date2_prompt') === false" class="flex items-baseline">
                    <label for="dp-input-entry_date2" class="text-sm font-semibold ml-4">
                        {{ props.getFolderData('date2_prompt') }}
                    </label>
                    <VueDatePicker v-model="entry_form.date2" :model-type="'yyyy-MM-dd HH:mm:ss'"
                        class="pl-1 ml-2" :class="isTimePicker() ? 'vdtp_main_time vdtp_time' : 'vdtp_main_date vdtp_date'"
                        :time-config="{ enableTimePicker: isTimePicker(), is24: false, timePickerInline: true, minutesIncrement: 5 }"
                        week-start="0"
                        :auto-apply="isTimePicker() ? false : true"
                        text-input :input-attrs="{ id: 'dp-input-entry_date2', clearable: false, hideInputIcon: true }"
                        @closed="entry_datepicker_closed('date2')" @blur="checkEditMode()" :dark="isDark"/>
                </div>
            </div>

            <!-- Entrytype Row -->
            <div v-show="props.getFolderData('hide_entrytype_prompt') === false" class="flex items-baseline mt-5">
                <label for="entry_entrytype_select" class="text-sm font-semibold w-28">
                    {{ props.getFolderData('entrytype_prompt') }}
                </label>
                <div>

                    <select v-model="entry_form.entrytype_id" id="entry_entrytype_select" @blur="checkEditMode()" @change="checkEditMode()"
                        class="w-72 select select-bordered select-sm rounded-md font-normal text-sm text-base-content bg-base-100">
                        <option v-for="etype, index in props.p1.folders[props.getFolderRow()].entrytypes" :key="etype.id" :value="etype.id">
                            {{ etype.name }}
                        </option>
                    </select>

                    <button type="button" class="btn btn-xs btn-outline w-32 ml-7"
                        @click="clicked_AddTypeButton()">
                        Add New Type
                    </button>
                    <InputError class="mt-1 ml-5" :message="entry_form.errors.entrytype_id" />
                </div>
            </div>


            <!-- From Row -->
            <div class="flex place-items-baseline mt-3">
                <label for="entry_from" class="text-sm font-semibold w-28">
                    {{ props.getFolderData('from_prompt') }}
                </label>

                <ContactLookup
                    v-model:contact_id="entry_form.from_contact_id"
                    v-model:contact_name="display_name.from"
                    v-model:the_mode="the_mode"
                    v-model:added_contact_obj="added_contact_obj"
                    :id="'entry_from'"
                    :folder_id="entry_form.folder_id"
                    :next_field="props.getFolderData('hide_to_prompt') == true ? 'entry_note' : 'entry_to'"
                    :state="props.state"
                    :firm_members="props.p1.firm_members"
                    :file_contacts="props.p1.file_contacts" />

                <InputError class="mt-2 ml-2" :message="entry_form.errors.from_contact_id" />
                <!-- <InputError class="mt-2 ml-2" :message="entry_form.errors.from_display_last_first" /> -->
                <button v-if="props.file_view === 'file'" type="button" class="btn btn-xs btn-ghost ml-1" @click="disp.show_file_contacts = 'from'">&darr;</button>

            </div>

            <!-- To Row -->
            <div v-if="props.getFolderData('hide_to_prompt') === false" class="flex place-items-baseline mt-3">
                <label for="entry_to" class="text-sm font-semibold w-28">
                    {{ props.getFolderData('to_prompt') }}
                </label>

                <ContactLookup
                    v-model:contact_id="entry_form.to_contact_id"
                    v-model:contact_name="display_name.to"
                    v-model:the_mode="the_mode"
                    v-model:added_contact_obj="added_contact_obj"
                    :id="'entry_to'"
                    :folder_id="entry_form.folder_id"
                    :next_field="'entry_note'"
                    :state="props.state"
                    :firm_members="props.p1.firm_members"
                    :file_contacts="props.p1.file_contacts" />

                <InputError class="mt-2" :message="entry_form.errors.to_contact_id" />
                <InputError class="mt-2" :message="entry_form.errors.to_display_last_first" />
                <button v-if="props.file_view === 'file'" type="button" class="btn btn-xs btn-ghost ml-1" @click="disp.show_file_contacts = 'to'">&darr;</button>

            </div>

            <!-- Note Row -->
            <div class="flex place-items-baseline mt-4">
                <label for="entry_note" class="text-sm font-semibold w-28">
                    {{ props.getFolderData('note_prompt') }}
                </label>
                <textarea v-model="entry_form.note" id="entry_note" rows="3" @blur="checkEditMode()" @input="checkEditMode()" @change="checkEditMode()"
                    class="w-117.5 textarea textarea-bordered disabled:border-0 text-sm resize-none py-0 px-2 ml-1 text-base-content bg-base-100" />
            </div>

            <!-- Linked Document Row > Only shown when firm has a document base path configured -->
            <div v-if="props.p1.firm_document_base_path" class="flex place-items-baseline mt-4">
                <label for="entry_linked_document" class="text-sm font-semibold w-28">
                    Linked Doc
                </label>
                <input
                    v-model="entry_form.linked_document_path"
                    id="entry_linked_document"
                    type="text"
                    placeholder="e.g., 2024\Smith\contract.pdf"
                    @blur="checkEditMode()"
                    @input="checkEditMode()"
                    class="input input-bordered input-sm rounded-sm w-80 text-sm text-base-content bg-base-100"
                />
                <button
                    type="button"
                    class="btn btn-xs btn-outline ml-2"
                    @click="showDocumentPicker = true"
                >
                    Browse…
                </button>
                <InputError class="mt-1 ml-2" :message="entry_form.errors.linked_document_path" />
            </div>

            <!-- Response Expected Date Row > Hide/Show based on folder -->
            <div v-if="props.getFolderData('hide_responseexpected_prompt') === false" class="mt-4 min-w-full">
                <div class="flex place-items-baseline">
                    <label for="dp-input-entry_date_response_expected" class="text-sm font-semibold w-56 mr-1">
                        Response Expected By:
                    </label>
                    <VueDatePicker v-model="entry_form.date_response_expected"
                        :model-type="'yyyy-MM-dd HH:mm:ss'" :time-config="{ enableTimePicker: false }" week-start="0"
                        class="pl-1 text-sm font-normal vdtp_main_date vdtp_date" text-input auto-apply
                        :input-attrs="{ id: 'dp-input-entry_date_response_expected', clearable: false, hideInputIcon: true }"
                        @closed="entry_datepicker_closed('date_response_expected')" :dark="isDark" />                        
                </div>

            <!-- Response Status Message -->
                <div v-if="disp.response_status" class="text-xs font-normal w-48 ml-44 mt-1" :class="disp.response_color">
                    {{ disp.response_status }}
                </div>
            </div>

            <!-- list of any responses recieved to this entry -->
            <ul v-if="disp.entry_responses_received.length" class="text-xs text-error ml-44 mt-1">
                <li v-for="response in disp.entry_responses_received">
                    {{ format_response_li(response) }}
                </li>
            </ul>

            <!-- Amount Row > Hide/Show based on folder -->
            <div v-if="props.getFolderData('hide_amount_prompt') === false" class="flex items-baseline mt-4">
                <label for="entry_amount" class="text-sm font-semibold w-24 mr-4">
                    {{ props.getFolderData('amount_prompt') }}
                </label>
                <input v-model="entry_form.amount" type="number" step=".01" id="entry_amount" class="input input-bordered input-sm"/>
            </div>


            <div v-show="props.getFolderData('hide_isResponsive') == 0"
                class="divider before:bg-base-300 after:bg-base-300 my-2"></div>

            <!-- Is This a Response Row -->

            <!-- Show this part if not hidden (by folder) -->
            <div class="flex mt-3 items-center " v-show="props.getFolderData('hide_isResponsive') === false">
                <label for="entry_is_a_response" class="text-sm font-semibold">
                    Is responsive?
                </label>
                <select v-model="entry_form.is_a_response" id="entry_is_a_response" @blur="checkEditMode()"
                    @change="onIsAResponseChange()" class="w-40 ml-3 select select-bordered select-sm rounded-md font-normal text-sm text-base-content bg-base-100">
                    <option value="N">No</option>
                    <option value="P">Partial Response</option>
                    <option value="F">Full Response</option>
                </select>
            </div>

            <!-- Is Response To Row - displays the currently selected responsive entry and a button to open the picker modal -->
            <div v-show="entry_form.is_a_response != 'N' && props.getFolderData('hide_isResponsive') === false" class="flex mt-3 items-center">
                <label class="text-sm font-semibold w-24">
                    Response to:
                </label>
                <template v-if="currentResponseDisplay">
                    <span class="ml-2 pl-2 pr-4 py-1 border-x border-base-content/30 text-xs text-base-content">
                        {{ currentResponseDisplay }}
                    </span>
                    <button type="button" class="btn btn-xs btn-primary btn-outline ml-3" @click="openResponseModal()">
                        Change
                    </button>
                </template>
            </div>

            <!-- Entry Form Button Row -->
            <div v-if="props.state.mode == 'entry_edit' || props.state.mode == 'entry_add'" class="flex justify-center">
                <div class="mt-10 ">
                    <button type="button" class="btn btn-primary w-28 gap-0" @click="clicked_entryform_button('ok')">
                        <u>S</u>ave
                    </button>
                    <button type="button" class="btn btn-primary ml-8" @click="clicked_entryform_button('cancel')">
                        Cancel
                    </button>
                </div>
            </div>
        </form>
    </div>
    <div v-else>
    </div>

    <!-- Response Entry Picker Modal -->
    <dialog ref="responseModalRef" class="modal">
        <div class="modal-box max-w-3xl">
            <h3 class="font-semibold text-lg mb-3">Select the entry this is a response to</h3>
            <div v-if="responseRows.length" class="max-h-96 overflow-y-auto">
                <table class="table table-sm">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>From</th>
                            <th>Type</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="row in responseRows" :key="row.id"
                            class="cursor-pointer hover:bg-base-200"
                            :class="{ 'bg-base-200': row.id === entry_form.is_response_to }"
                            @click="pickResponseEntry(row.id)">
                            <td>{{ reformat_date(row.date) }}</td>
                            <td>{{ row.from }}</td>
                            <td>{{ row.type }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <div v-else class="text-sm italic text-base-content/70 py-4">
                No entries in this file are expecting a response.
            </div>
            <div class="modal-action">
                <form method="dialog">
                    <button class="btn btn-sm">Cancel</button>
                </form>
            </div>
        </div>
        <form method="dialog" class="modal-backdrop">
            <button>close</button>
        </form>
    </dialog>


<!-- Note: Here is the AddContactForm Component which is used by the ContactLookup component -->

    <AddContactForm v-model:added_contact_obj="added_contact_obj" :id="'contact_modal_form'" />

    <!-- Put this part before </body> tag - Entrytype Modal -->
    <dialog id="entrytype_modal" class="modal">
        <div class="modal-box w-11/12 max-w-3xl">
            <h3 class="font-bold text-2xl text-center">
                Enter a New <span class="font-normal">({{ props.getFolderData('name', 'singular') }})</span> Type
            </h3>
            <form>
                <div class="flex mt-8 items-baseline">
                    <label for="type_name" class="text-xl">
                        {{ props.getFolderData('entrytype_prompt') }}
                    </label>
                    <input v-model="entrytype_form.name" id="type_name" name="type_name" @input="lookup_entrytype()"
                        class="input input-bordered ml-4 w-125" autocomplete="off" />
                </div>
                <table
                    v-if="matching.entrytypes.length > 0 && entrytype_form.name.length > 0 && entrytype_form.isChosen == false"
                    class="mt-2 ml-32 border w-80">
                    <tr v-for="entrytype, index in matching.entrytypes" :key="entrytype.id"
                        @click="clicked_matchingEntrytype(index)">
                        <td class="pl-4 py-1 text-sm hover:bg-base-200 hover:cursor-default">
                            {{ entrytype.name }}
                        </td>
                    </tr>
                </table>
            </form>
            <div class="modal-action justify-center mt-12">
                <button type="button" class="btn btn-primary mr-10 w-28 gap-0"
                    @click="clicked_entrytypeModal_button('ok')"><u>O</u>k</button>
                <button type="button" class="btn btn-primary gap-0"
                    @click="display_modal('entrytype', false)">Cancel</button>
            </div>
        </div>
    </dialog>


    <!-- Put this part before </body> tag - Confirm Entry Change modal-->
    <dialog id="entrychanged_modal" class="modal">
        <div class="modal-box w-11/12 max-w-3xl">
            <h3 class="font-bold text-2xl text-center">Confirm: Entry Changes</h3>
            <p class="text-xl mt-4">Information in this entry has been changed.</p>
            <p class="text-xl mt-4">Do you want to save these changes?</p>
            <div class="modal-action justify-center mt-12">
                <button type="button" class="btn mr-10 w-20 gap-0"
                    @click="clicked_entryform_button('ok')"><u>Y</u>es</button>
                <button type="button" class="btn gap-0" @click="entry_actions('revert')"><u>N</u>o</button>
            </div>
        </div>
    </dialog>


    <!-- Put this part before </body> tag - Confirm Add Cancelled modal-->
    <dialog id="addcancelled_modal" class="modal">
        <div class="modal-box w-11/12 max-w-3xl">
            <h3 class="font-bold text-2xl text-center">Cancel New Entry?</h3>
            <!-- <p class="text-xl mt-4">Cancel new entry?</p> -->
            <!-- <p class="text-xl mt-4">Do you want to save these changes?</p> -->
            <div class="modal-action justify-center mt-12">
                <button type="button" class="btn mr-10 w-20 gap-0" @click="entry_actions('revert')"><u>Y</u>es</button>
                <button type="button" class="btn gap-0" @click="display_modal('addcancelled', false)"><u>N</u>o</button>
            </div>
        </div>
    </dialog>

    <!-- Contact Role Assignment Modal -->
    <dialog id="role_assign_modal" class="modal">
        <div class="modal-box w-11/12 max-w-md">
            <h3 class="font-bold text-xl text-center">Assign Role</h3>
            <p class="text-sm text-center mt-2">{{ role_modal.contact_name }}</p>
            <div class="flex items-baseline mt-6">
                <label for="role_select" class="text-sm font-semibold w-20">Role:</label>
                <select v-model="role_modal.selected_role" id="role_select"
                    class="select select-bordered select-sm rounded-md font-normal text-sm text-base-content bg-base-100 w-64">
                    <option value="">-- Select a role --</option>
                    <option v-for="(label, key) in props.p1.role_options" :key="key" :value="key">
                        {{ label }}
                    </option>
                </select>
            </div>
            <div v-if="role_modal.selected_role === 'other'" class="flex items-baseline mt-4">
                <label for="custom_role_label" class="text-sm font-semibold w-20">Label:</label>
                <input v-model="role_modal.custom_role_label" id="custom_role_label" type="text"
                    placeholder="Enter custom role label"
                    class="input input-bordered input-sm w-64 text-sm text-base-content bg-base-100" />
            </div>
            <div class="modal-action justify-center mt-8">
                <button type="button" class="btn btn-primary mr-6 w-24"
                    @click="clicked_roleModal_button('assign')">Assign</button>
                <button type="button" class="btn w-24"
                    @click="clicked_roleModal_button('skip')">Skip</button>
            </div>
        </div>
    </dialog>

    <!-- Put this part before </body> tag - File Contacts modal-->
    <dialog id="file_contacts_modal" class="modal">
        <div class="modal-box">
            <button class="btn btn-sm btn-circle btn-ghost absolute right-2 top-2" @click="selectFileContact( 'cancel' )">✕</button>
            <h3 class="font-bold text-2xl text-center mb-2">Select File Contact</h3>
            <p class="text-center text-sm mb-4">{{ disp.show_file_contacts === 'from' ? props.getFolderData('from_prompt') : props.getFolderData('to_prompt') }}</p>

            <!-- Scrollable list of contacts -->
            <div class="max-h-96 overflow-y-auto border border-base-300 rounded-md">
                <div
                    v-for="(contact, index) in fileContactsWithRoles"
                    :key="index"
                    @click="disp.show_contact_id = contact.id; selectFileContact()"
                    class="grid grid-cols-2 gap-4 px-4 py-2 hover:bg-base-200 cursor-pointer border-b border-base-300 last:border-b-0"
                >
                    <span>{{ contact.display_last_first }}</span>
                    <span class="text-xs text-base-content/50">{{ contact.role_display }}</span>
                </div>
            </div>
        </div>
    </dialog>

    <!-- Document Picker modal -->
    <DocumentPicker
        :show="showDocumentPicker"
        @close="showDocumentPicker = false"
        @select="(path) => { entry_form.linked_document_path = path; showDocumentPicker = false; checkEditMode(); }"
    />

</template>
