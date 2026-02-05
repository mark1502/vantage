<script setup>
    import { reactive, nextTick, watch, ref } from 'vue';

    const contact_id = defineModel('contact_id');
    const contact_name = defineModel('contact_name');
    const the_mode = defineModel('the_mode');
    const added_contact_obj = defineModel('added_contact_obj');

    const props = defineProps([ 'id', 'folder_id', 'next_field', 'state', 'firm_members', 'file_contacts', 'dialog_id' ]);

    /*
    const local = reactive({
        id: contact_id.value,
        name: contact_name.value,
        starting_id: contact_id.value,
        starting_name: contact_name.value,
    });

    const lookup = reactive({
        contact: false,
        contact_list: Object,
    });

        // watch added_contact_obj - if accept is true && the field matches the id on the entry form, then copy the info to the contact_id and contact_name
    watch( added_contact_obj.value, ( newValue ) => {         
        if( newValue.accept === true && newValue.field === props.id ) {       // props.id is the id of the input on the entryform (entry_from or entry_to)
            contact_id.value = newValue.id;
            contact_name.value = newValue.name;

            nextTick(() => {
                newValue.id = 0;
                newValue.name = '';
                newValue.field = '';
                newValue.accept = false;
                waitForTicks(4);
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

    function lookup_contact() {
        let is_firm_only = (props.folder_id > 4 && props.folder_id < 8) ? true : false;         // folders 5,6 or 7 are firm-only lookups
        if( props.id === 'entry_to' && props.folder_id == 8 ) is_firm_only = true;              // Phone messages (folder 8) are only To firm members

        let results = [];
    
        local.name = space_after_comma( local.name );                                           // Be sure there's a space after a comma

        nextTick(() => {
            if( local.name !== local.starting_name ) {                                          // if the component value does not match the value from the entry, start the lookup process
                local.id = null;
                contact_id.value = null;
                nextTick(() => {
                    if( props.state.mode === 'browse' ) the_mode.value = 'set_edit';            // trigger edit mode on the form (because the user is typing)
                    else if( props.state.mode === 'view' ) the_mode.value = 'set_edit';         // trigger edit mode on the form (because the user is typing)
                })
            }

            if( is_firm_only == true ) results = filter_firm_members();                         // if is_firm_only, filter from firm_members, to avoid server lookup
            else if( props.file_contacts.length  !== 0 ) results = filter_file_contacts();      // else if there are file_contacts, filter those first, to avoid server lookup if possible
                

            if( results.length ) lookup.contact_list = { data: results };                       // found matching contacts, so show those
            else {                                                                              // else, no matching contacts, so lookup contact from server
                axios.post('/lookup_contact', { search: local.name, firm_only: is_firm_only })  // do the lookup search and list the response data
                    .then( function (response) { lookup.contact_list = response.data; });
            }
        });
    }


    function filter_firm_members() {
        return props.firm_members.filter( contact => contact.display_last_first.slice( 0, local.name.length ).toLowerCase() === local.name.toLowerCase() );
    }


    function filter_file_contacts() {
        return props.file_contacts.filter( contact => contact.display_last_first.slice( 0, local.name.length ).toLowerCase() === local.name.toLowerCase() );
    }


    function space_after_comma( var_in ) {                                                      // This function checks for a space after the comma and adds one if necessary
        let comma_at = var_in.search(",");

        if( comma_at !== -1 && comma_at !== var_in.length - 1) {                                // if there's a comma && it's not the last char
            if( var_in.substring( comma_at + 1, comma_at + 2 ) !== ' ') {                       // if the char after the comma is not a space, then add the space
                var_in = var_in.substring( 0, comma_at + 1 ) + ' ' + var_in.substring( comma_at + 1, var_in.length + 1 );
            }
        }

        return var_in
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


    function handleTab(id_in) {
        document.getElementById( props.next_field ).focus();    // focus on the next field
    }


    function handleShiftTab(id_in) {
        if( id_in === 'entry_from'){
            document.getElementById('entry_entrytype_select').focus();
        } else if(id_in === 'entry_to'){
            document.getElementById('entry_from').focus();
        }
    }


    function handleKeyDown( event ) {                                       // called on keydown of the input element
        if( event.shiftKey && event.key === 'Tab' ) {
            event.preventDefault();
            handleShiftTab(props.id);
        } else if( event.key === 'Tab' ) {
            event.preventDefault();
            handleTab(props.id);
        } else if( event.key === 'Escape' ) {
            console.log('escape pressed');
            event.preventDefault();
            handleBlur();
        }
    }


    async function waitForTicks(count) {
        for (let i = 0; i < count; i++) {
            await nextTick();
        }
    }

*/
</script>

<template>
    <button type="button" class="btn btn-xs btn-ghost ml-1" @click="">&darr;</button>


    <input type="checkbox" id="my_modal_6" class="modal-toggle" />
    <div class="modal" role="dialog">
    <div class="modal-box">
        <h3 class="text-lg font-bold">Hello!</h3>
        <p class="py-4">This modal works with a hidden checkbox!</p>
        <div class="modal-action">
        <label for="my_modal_6" class="btn">Close!</label>
        </div>
    </div>
    </div>





        <!-- <dialog :id="props.dialog_id" class="modal">
        <div class="modal-box w-11/12 max-w-6xl z-300">
             <div class="modal-action">
            <form method="dialog">
            <div class="flex items-center">
                <span class="">File Contacts:</span>
                <select v-model="contact_id" class="select select-sm select-bordered w-44 ml-2">
                    <option v-for="(contact, index) in props.file_contacts" :key="index" :value="contact.id">
                        {{ contact.display_last_first }}
                    </option>
                </select>
            </div> 

                <button class="btn">Close</button>
            </form>
            </div>
        </div>
        </dialog> -->
</template>