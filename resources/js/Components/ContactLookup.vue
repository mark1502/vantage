<script setup>
    import { reactive, computed, nextTick, watch } from 'vue';

    const contact_id = defineModel('contact_id');
    const contact_name = defineModel('contact_name');
    const the_mode = defineModel('the_mode');
    let added_contact_obj = defineModel('added_contact_obj');

    const props = defineProps([ 'id', 'folder_id', 'next_field', 'state', 'firm_members', 'file_contacts' ]);

    const local = reactive({
        id: contact_id.value,
        name: contact_name.value,
        starting_id: contact_id.value,
        starting_name: contact_name.value,
    });

    const lookup = reactive({                                                               // #5: initialized with proper default instead of Object constructor
        contact: false,
        contact_list: { data: [] },
    });

    const showLookupList = computed(() =>                                                    // #4: computed to replace repeated template conditions
        local.name.length > 0 && local.name !== local.starting_name
    );

    const isFirmOnlyFolder = computed(() =>                                                  // #4: computed for firm-only folder check
        props.folder_id > 4 && props.folder_id < 8
    );

        // watch added_contact_obj - if accept is true && the field matches the id on the entry form, then copy the info to the contact_id and contact_name
    watch( added_contact_obj.value, ( newValue ) => {                                        // #8: removed waitForTicks(4) — the single nextTick is sufficient
        if( newValue.accept === true && newValue.field === props.id ) {       // props.id is the id of the input on the entryform (entry_from or entry_to)
            contact_id.value = newValue.id;
            contact_name.value = newValue.name;

            nextTick(() => {
                newValue.id = 0;
                newValue.name = '';
                newValue.field = '';
                newValue.accept = false;
            });
        }
    });

    watch( contact_name, (name_in) => {             // watch the entry form name, which is set in parent (update_disp) and update this component value
        nextTick(() => {
            local.id = contact_id.value;
            local.name = contact_name.value;
            local.starting_id = contact_id.value;
            local.starting_name = contact_name.value;
        });
    });

    function lookup_contact() {                                                              // #1: removed unnecessary nextTick wrappers and nested nextTick
        let is_firm_only = isFirmOnlyFolder.value;                                           // folders 5,6 or 7 are firm-only lookups
        if( props.id === 'entry_to' && props.folder_id == 8 ) is_firm_only = true;           // Phone messages (folder 8) are only To firm members

        local.name = space_after_comma( local.name );                                        // Be sure there's a space after a comma

        if( local.name !== local.starting_name ) {                                           // if the component value does not match the value from the entry, start the lookup process
            local.id = null;
            contact_id.value = null;
            if( props.state.mode === 'browse' || props.state.mode === 'view' ) {             // trigger edit mode on the form (because the user is typing)
                the_mode.value = 'set_edit';
            }
        }

        let results = [];
        if( is_firm_only ) results = filter_contacts( props.firm_members );                  // #2: unified filter function with data source param
        else if( props.file_contacts.length !== 0 ) results = filter_contacts( props.file_contacts );

        if( results.length ) lookup.contact_list = { data: results };                        // found matching contacts, so show those
        else {                                                                               // else, no matching contacts, so lookup contact from server
            axios.post('/lookup_contact', { search: local.name, firm_only: is_firm_only })
                .then( function (response) { lookup.contact_list = response.data; });
        }
    }


    function filter_contacts( contacts ) {                                                   // #2: single filter function replaces filter_firm_members and filter_file_contacts
        return contacts.filter( contact => contact.display_last_first.slice( 0, local.name.length ).toLowerCase() === local.name.toLowerCase() );
    }


    function space_after_comma( var_in ) {                                                   // #3: simplified with regex
        return var_in.replace(/,([^ ])/, ', $1');
    }


    function clicked_contact_list( index ) {                    // user clicked a name on the list - note: the rest of the values are automatically reset by the watch on contact_name
        contact_id.value = lookup.contact_list.data[index].id;
        contact_name.value = lookup.contact_list.data[index].display_last_first;
    }


    function clicked_AddNewContact() {                          // user clicked button to add a new contact
        added_contact_obj.value.field = props.id;                   // set the object field to the id of the input field
        added_contact_obj.value.display_modal = true;               // show the modal
    }


    function handleBlur() {                                     // either blur off field before selection, or esc before selection, so reset to starting values
        if( local.name !== local.starting_name ) {              // if the name is not the starting name, then reset everything to the starting name and id
            local.id = local.starting_id;
            local.name = local.starting_name;
            contact_id.value = local.starting_id;
            contact_name.value = local.starting_name;
        }
    }


    function handleTab() {                                                                   // #6: removed unused id_in parameter
        document.getElementById( props.next_field ).focus();    // focus on the next field
    }


    function handleShiftTab() {                                                              // #6: removed unused id_in parameter, uses props.id directly
        if( props.id === 'entry_from'){
            document.getElementById('entry_entrytype_select').focus();
        } else if( props.id === 'entry_to'){
            document.getElementById('entry_from').focus();
        }
    }


    function handleKeyDown( event ) {                                       // called on keydown of the input element
        if( event.shiftKey && event.key === 'Tab' ) {
            event.preventDefault();
            handleShiftTab();                                                                // #6: no longer passing id_in
        } else if( event.key === 'Tab' ) {
            event.preventDefault();
            handleTab();                                                                     // #6: no longer passing id_in
        } else if( event.key === 'Escape' ) {                                               // #7: removed console.log
            event.preventDefault();
            handleBlur();
        }
    }
</script>

<template>
    <input v-model="local.name" :id="props.id"  class="input input-bordered input-sm text-sm rounded-sm w-64" placeholder="Enter name: ( Last, First, M. )"
       @input="lookup_contact()" @blur="handleBlur()" @keydown="handleKeyDown" autocomplete="off"
    />

    <table v-if="showLookupList" class="mt-8 ml-4 border border-gray-500 suggestion-table bg-white w-64" >
        <tr v-for="contact, index in lookup.contact_list.data" :key="contact.id" @mousedown="clicked_contact_list(index)">
            <td class="pl-4 py-1 text-sm text-base-content bg-base-300 hover:bg-base-100 hover:cursor-default">
                {{ contact.display_last_first }}
            </td>
        </tr>
        <tr v-if="lookup.contact_list.total == 0 && !isFirmOnlyFolder">
            <td class="text-sm bg-white text-gray-900 text-center border-4 border-cyan-500">
                <p class="mt-2">Contact Not Found</p>
                <button type="button" class="btn btn-outline btn-sm normal-case m-4" @mousedown="clicked_AddNewContact">
                    Click Here To Add New Contact
                </button>
            </td>
        </tr>
        <tr v-if="lookup.contact_list.total == 0 && isFirmOnlyFolder">
            <td class="text-sm bg-white text-gray-900 text-center">
                <p class="p-2">Firm Member Not Found</p>
            </td>
        </tr>
    </table>
</template>
