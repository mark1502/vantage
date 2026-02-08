<script setup>
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

function lookup_file() {
    if (lookup.file_isChosen == false || lookup.file_chosen_name !== display_file.name) {       // name isn't chosen or display doesn't match chosen name
        display_file.id = 0;
        if( display_file.name.length) {
            lookup.file = false;
            lookup.matching_files = [];
            axios.post('/lookup_file', { search: display_file.name })       // lookup search and list the response data
            .then(function (response) { 
                lookup.matching_files = response.data; 
                lookup.file = true;
            });
        }
    }

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
            class="input input-bordered input-sm rounded-sm w-64" @keydown="handleKeyDown"/>
        <table v-if="display_file.name.length && lookup.file == true"
            class="mt-1 border border-gray-400 suggestion-table bg-white w-80 ">
            <tr v-for="thefile, index in lookup.matching_files.data" :key="thefile.id"
                @click="clicked_file_list(index)" >
                <td class="pl-4 py-1 text-sm hover:bg-gray-200 hover:cursor-default">
                    {{ thefile.name }}
                </td>
            </tr>
            <tr v-if="display_file.name.length > 0 && lookup.matching_files.from == null && lookup.file == true">
                <td class="text-sm bg-white text-gray-900 text-center border-4 border-amber-500">
                    <p class="my-2">No Matching Files Found</p>
                </td>
            </tr>
        </table>

    </div>



</template>