<script setup>
/* NOTE: This FileLookup is only used in the Entries/Index as a way for the user to switch files without going back out to the files index
    It's imported into the Views Index, but not used, so that will be removed later.  I think axios is used so the data is returned without calling 
    inertia render again.  This could be revisited after inertia 3 is used.

    This is separate from the FileLookup_form component.  That is intended to be used on a form when a file name is to be selected on the form.
*/

import { ref, reactive, computed, nextTick, onUnmounted, onMounted, watch } from 'vue';
import { useForm, router } from '@inertiajs/vue3';

import axios from 'axios';


const switch_file = defineModel('switch_file');

const props = defineProps({
    state: Object,
});

const lookup = reactive({
    file: false,
    file_isChosen: false,
    file_chosen_name: "",
    matching_files: Object,
});

const display_file = reactive({
    name: '',
    id: 0,
});

const searchMode = ref('starts');                                           // 'starts' uses the firm_id/name index; 'includes' is a full scan

function lookup_file() {
    if (lookup.file_isChosen == false || lookup.file_chosen_name !== display_file.name) {       // name isn't chosen or display doesn't match chosen name
        display_file.id = 0;
        if( display_file.name.length) {
            lookup.file = false;
            lookup.matching_files = [];
            axios.post('/lookup_file', { search: display_file.name, search_mode: searchMode.value })       // lookup search and list the response data
            .then(function (response) {
                lookup.matching_files = response.data;
                lookup.file = true;
            });
        } else {
            searchMode.value = 'starts';                                    // field cleared, so go back to the fast default
        }
    }

}

function searchAnywhere() {                                                 // widen the current lookup to match anywhere in the name
    searchMode.value = 'includes';
    lookup.file_isChosen = false;
    lookup_file();
}

function clicked_file_list( index ) {
    router.get( '/files/' + lookup.matching_files.data[index].id + '/entries' + 
                '?page=1' + 
                '&show=' + props.state.show + 
                '&filepart=' + props.state.folder_name, {
            onError: (errors) => {
                console.log('refresh file search - error from router');
                console.log(errors);
            },
        });        
}

function handleKeyDown( event ) {
    if( event.key === 'Escape' ) {
        event.stopImmediatePropagation();
        lookup.file = false;
        switch_file.value = false;
    }
}


</script>
<template>
    <div class="relative">
        <label for="file_lookup" class="text-sm font-semibold mr-4">
            Find File:
        </label>

        <input v-model="display_file.name" id="file_lookup" @input="lookup_file()"
            autocomplete="off" placeholder="Enter file name"
            class="input input-sm rounded-sm w-64" @keydown="handleKeyDown"/>
        <table v-if="display_file.name.length && lookup.file == true"
            class="mt-1 border border-base-300 suggestion-table bg-base-100 w-80 ">
            <tr v-for="thefile, index in lookup.matching_files.data" :key="thefile.id"
                @click="clicked_file_list(index)" >
                <td class="pl-4 py-1 text-sm text-base-content bg-base-300 hover:bg-base-100 hover:cursor-default">
                    {{ thefile.name }}
                </td>
            </tr>
            <tr v-if="display_file.name.length > 0 && lookup.matching_files.from == null && lookup.file == true">
                <td class="text-sm bg-base-100 text-base-content text-center border-4 border-amber-500">
                    <p class="my-2">No Matching Files Found</p>
                </td>
            </tr>
            <tr v-if="searchMode === 'starts'">
                <td class="text-xs bg-base-100 text-center py-3">
                    <button type="button" @click="searchAnywhere" class="link link-primary">
                        Search anywhere in name (slower)
                    </button>
                </td>
            </tr>
        </table>

    </div>



</template>