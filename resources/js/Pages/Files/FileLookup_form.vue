<script setup>
import { ref, reactive, computed, nextTick, onUnmounted, onMounted, watch } from 'vue';
import { useForm, router } from '@inertiajs/vue3';

import axios from 'axios';


// const switch_file = defineModel('switch_file');
const file_id = defineModel('file_id');

const props = defineProps({
    state: Object,
    hideLabel: { type: Boolean, default: false },
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


let lookupTimeout = null;

const searchMode = ref('starts');                                       // 'starts' uses the firm_id/name index; 'includes' is a full scan

function lookup_file( immediate = false ) {
    if (lookup.file_isChosen == false || lookup.file_chosen_name !== display_file.name) {       // name isn't chosen or display doesn't match chosen name
        display_file.id = 0;
        clearTimeout(lookupTimeout);
        if( display_file.name.length ) {                                // if something is entered into display_file.name
            lookup.file = false;                                            // clear the lookup flag and the list of matching files
            lookup.matching_files = [];
            const doLookup = () => {
                axios.post('/lookup_file', { search: display_file.name, search_mode: searchMode.value })       // lookup search and list the response data
                .then(function (response) {
                    lookup.matching_files = response.data;
                    lookup.file = true;
                });
            };
            if( immediate ) doLookup();                                 // mode switch is a deliberate click, so skip the debounce
            else lookupTimeout = setTimeout(doLookup, 300);              // 300ms debounce, matches Files/Index.vue
        } else {
            searchMode.value = 'starts';                                // field cleared, so go back to the fast default
        } // end if name.length
    } // end if not chosen or no match
}

function searchAnywhere() {                                             // widen the current lookup to match anywhere in the name
    searchMode.value = 'includes';
    lookup.file_isChosen = false;
    lookup_file(true);
}


function clicked_file_list( index ) {
    display_file.name = lookup.matching_files.data[index].name;
    display_file.id = lookup.matching_files.data[index].id;

    file_id.value = display_file.id;

    lookup.file_isChosen = true;
    lookup.file_chosen_name = display_file.name;
    lookup.file = false;
}


function handleKeyDown( event ) {
    if( event.key === 'Escape' ) {
        event.stopImmediatePropagation();
        lookup.file = false;
        // switch_file.value = false;
    }
}
</script>

<template>
    <div class="relative">
        <div :class="['flex items-baseline', { 'mb-4': !hideLabel }]">
            <label v-if="!hideLabel" for="file_lookup" class="text-sm font-semibold w-28">
                File:
            </label>

            <input v-model="display_file.name" id="file_lookup" @input="lookup_file()" autocomplete="off"
                class="input input-sm rounded-sm w-64" @keydown="handleKeyDown"/>

            <table v-if="display_file.name.length && lookup.file == true"
                class="mt-8 border border-base-300 suggestion-table bg-base-100 w-64">
                <tr v-for="afile, index in lookup.matching_files.data" :key="afile.id" @click="clicked_file_list(index)" >
                    <td class="pl-4 py-1 text-sm text-base-content bg-base-300 hover:bg-base-100 hover:cursor-default">
                        {{ afile.name }}
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
    </div>



</template>