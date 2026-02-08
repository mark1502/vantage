<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { reactive, ref, computed, watch, onMounted, onUnmounted, nextTick } from "vue";
import { Head, Link, useForm, router } from '@inertiajs/vue3';
import Pagination from '@/Components/Pagination.vue';
import InputLabel from "@/Components/InputLabel.vue";




const queryString = window.location.search;
const urlParams = new URLSearchParams(queryString);

const props = defineProps({ 
    files: Object
});

const search = ref(urlParams.get('search') || '');      // search field, set to the search parameter in the URL, or empty if not present
const file1 = ref(null);                                // ref used for code shortcut to the file

const state = reactive({
    hover: false,
    current_row: 0,
    show: props.files.per_page ?? 15,               // set in the controller - if not show, then 15
    display: 'name',
    sort_on: 'name',
    sort_order: 'asc'
});

const disp = reactive({ 
    editurl: '', 
    createurl: '', 
    openurl: '', 
 });

watch( search, (value) => {                                                                                // watch function on the search field
    if (value) {                                                                                        // if there's a value, then clear the file display
        disp.editurl = '';
    }

    router.get('/files', { search: value, page: 1, show: state.show }, { preserveState: true, replace: true,
        onSuccess: () => {
            state.current_row = 0;
        },
        onFinish: visit => {
            update_disp();
        },
    });
});

function update_disp() {                                                                                            // update_disp function - updates the display about the highlighted file

    if (props.files.data.length) {                                                                              // if there are files
        file1.value = props.files.data[state.current_row];                                                      // set a shortcut "the" for readability
        disp.editurl = route( 'entries.index', { file: file1.value, filepart: 'info', page: 1, show: state.show } );      // set the editurl (not used for now)
        disp.openurl = route( 'entries.index', { file: file1.value, filepart: 'correspondence', page: 1, show: state.show } );  // set the openurl to the route for opening the file
    } else {
        disp.editurl = "";
        disp.openurl = "";
    } // end if

    disp.createurl = route( 'files.create', { page: props.files.current_page, show: state.show } );               // set the createurl to the route for creating a file
}

const disable_button = computed(() => {                                                                             // computed function to disable button if not files
    if( props.files.data.length ) return false;
    else return true;
});

function reformat_date(dt) {
    if (dt === null || dt === undefined) return '';                                                                // if date is null or undefined, return empty string
    dt = dt.toString();                                                                                           // convert date to string
    return  dt.slice(5,7) + '-' + dt.slice(8,10) + '-' + dt.slice(0,4);    
}

function file_clicked(index) {                                                                                  // clicked on a file
    state.current_row = index;                                                                                      // set the row to the clicked file
    update_disp();                                                                                                  // update the display
}

function file_dblclick(index) {                                                                                 // dblclick on a file
    state.current_row = index;                                                                                      // set the row to the clicked file
    update_disp();                                                                                                  // update display
    router.get(disp.openurl);                                                                                       // get the openurl
}

function showChanged() {
    router.get( route('files.index'), { page: 1, show: state.show } );
}


function deleteClicked() {
    let r = confirm("Do you want to delete this file?\n\nName: " + props.files.data[state.current_row].name + "\n\nClick Ok to delete");
    if (r == true) {
       form.delete("/files/" + props.files.data[state.current_row].id + "?page=" + props.files.current_page + "&show=" + state.show, {
        });
    }
}


function handleTheKeypress( e ) {
    let changeit = false;
    if(e.altKey && e.key==='a') {                           // alt-a (Add)
        e.preventDefault();
        router.get(disp.createurl);
    } else if(e.altKey && e.key==='c') {                    // alt-c (Change)
        e.preventDefault();
        router.get(disp.editurl);
    } else if(e.altKey && e.key==='o') {                    // alt-o (Open)
        e.preventDefault();
        router.get(disp.openurl);
    } else if (e.code >= 'KeyA' && e.code <= 'KeyZ') {      // A-Z (search)
        e.preventDefault();
        search.value = search.value + e.key;
        searchInput.focus();
    }

    let theseKeys = ['Escape', 'ArrowDown', 'ArrowUp', 'Enter', 'PageUp', 'PageDown'];                  // preventDefault on these keypresses
    if( theseKeys.includes(e.key) ) e.preventDefault();
    
    if( e.key === 'Enter' ) {                                                                           // if Enter pressed, open the file
        router.get( disp.openurl );
    } else if( e.key === 'ArrowUp' && state.current_row > 0 ) {                                         // else if ArrowUp && not currently on the top row
        state.current_row--;
        changeit = true;
    } else if( e.key === 'ArrowDown' && state.current_row < (props.files.data.length - 1) ) {       // else if ArrowDown and not currently on the last entry
        state.current_row++;
        changeit = true;
    } else if( e.key === 'PageUp' && props.files.current_page !== 1 ) {                             // else if PageUp and not currenlty on the first page
        router.get( props.files.links[props.files.current_page - 1].url );
    } else if( e.key === 'PageDown' && props.files.current_page !== props.files.last_page ) {   // else if PageDown and not currently on the last page
        router.get( props.files.links[props.files.current_page + 1].url );
    } else if( e.key === 'Escape' ) {                                                              // else if Escape, clear the search field
        document.activeElement.blur();                                  // Remove focus from search input
        search.value = '';
    }

    if( changeit === true ) update_disp();                                                              // if changeit is true, update the display
}


function setEntryClass( index ) {                                                       // sets the color of the listbox entries
    let textcolor = 'text-base-content';
    let bgcolor = 'bg-base-100';

    if ( index === state.current_row ) {                                                        // if this is the highlighted row
        textcolor = 'text-white'
        bgcolor = 'bg-blue-800';                                                        // set background color of highlighted row 
    }

    return textcolor + ' ' + bgcolor;
}


onMounted(() => document.addEventListener('keydown', handleTheKeypress));
onUnmounted(() => document.removeEventListener('keydown', handleTheKeypress));


update_disp();                                                                                          // end of script, so start off by updating the display

</script>

<template>

    <Head title="Files" />

    <AuthenticatedLayout>

        <template #header>
            <div class="items-baseline">
                <div class="font-semibold text-lg bg-base-100 text-base-content ml-6">
                    File List
                </div>
            </div>
        </template>

        <div id="file_index_screen" class="py-3">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                <div class="bg-base-300 p-4 min-h-dvh sm:rounded-lg" id="FilesScreen" name="FilesScreen">
                    <div class="flex w-full">

                    <!-- Left Side Starts Here -->
                        <div name="left-side" class="w-1/2 ml-2">

                        <!-- Search input here -->
                            <div v-if="files.data.length" class="flex justify-end pb-2">
                                <div v-show="search" class="mr-2">Searching: </div>  
                                <input v-model="search" id="searchInput" placeholder="Search ..." class="border px-2 mr-2 rounded" autocomplete="off"/>
                            </div>

                        <!-- Table Starts Here -->
                            <div v-if="files.data.length">
                                <table class="w-full border border-base-content text-base font-sans font-normal" id="filelist">
                                    <thead class="text-left text-md bg-gray-200">
                                        <tr>
                                            <th class="border-b border-r border-gray-700 text-gray-900 pl-4 ">
                                                File Name
                                            </th>
                                            <!-- <th class="border-b border-r border-gray-700">Attorney:</th> -->
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr v-for="file, index in files.data" :key="file.id" class="border-b border-base-content" 
                                            :class="setEntryClass(index)" @click="file_clicked(index)" @dblclick="file_dblclick(index)">
                                            <td class="px-2 py-2 w-[480px]">
                                                {{ file.name }}
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>

                            <!-- Paginator Line Here-->
                                <div class="btn-group flex justify-between mt-2 items-center">
                                    <div class="flex items-center ">
                                        <label for="state_show" class="font-bold ml-2 mr-1">
                                            Show:
                                        </label>
                                        <select v-model="state.show" id="state_show"  @change="showChanged"
                                            class="select select-bordered select-sm">
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
                                        <Pagination :links="files.links" :only="['files']"/>
                                    </div>
                                </div>    
                            </div>

                        <!-- If no files, then display this -->
                            <div v-else class="border p-4 mt-8 text-xl font-bold text-center">No Matching Files Found!
                            </div>

                        <!-- Here are the Buttons -->
                            <div name="control_buttons" class="flex mt-8 justify-around">
                                <Link id="addbutton" :href='disp.createurl' class="btn btn-primary gap-0 w-24">
                                    + &nbsp;<u>A</u>dd
                                </Link>
                                <!-- <Link v-if="files.total !== 0" id="editbutton" :href='disp.editurl' class="btn btn-outline btn-primary gap-0"> △ &nbsp;<u>C</u>hange</Link> -->
                                <Link v-if="files.total !== 0" id="openbutton" :href='disp.openurl' class="btn btn-primary gap-0 w-32" :disable="disable_button">
                                    🗁 &nbsp;<u>O</u>pen
                                </Link>
                                <Link v-if="files.total !== 0" id="deletebutton" href='' @click="deleteClicked" class="btn btn-outline btn-error" >
                                    - &nbsp;Delete
                                </Link>
                            </div>
                        </div>

                    <!-- Left Side Ends Here -->
                    <!-- Right Side Starts Here -->
                        <div v-if="files.data.length" name="right.side" class="w-1/2 ml-12">
                            <p v-if="files.data.length" class="text-xl font-bold ml-2">File Details:</p>

                            <div class="rounded-lg w-[430px] mt-2 bg-base-200 border border-gray-400" id="disp_card">
                                <div v-if="files.data.length" class="p-4 text-gray-900">
                                    <h2 class="card-title text-base-content">{{ file1.name }}</h2>
                                    <p class="mt-2">File Type: &nbsp;{{ file1.filetype.name }}</p>
                                    <p class="">Attorney: &nbsp;{{ file1.contact.display_name }}</p>
                                    <div class="flex font-mono mt-2">
                                        <p class="w-1/2">Opened: {{ file1.date_opened ? reformat_date(file1.date_opened) : "" }}</p>
                                        <p class="">Closed: {{ file1.date_closed ? reformat_date(file1.date_closed) : "No" }}</p>
                                    </div>
                                    <div class="flex font-mono">
                                        <p class="w-1/2">S.O.L.: {{ file1.date_sol ? reformat_date(file1.date_sol) : "Not set" }}</p>
                                        <p class="">Filed: &nbsp;{{ file1.date_filed ? reformat_date(file1.date_filed) : "No" }}</p>
                                    </div>
                                        
                                    <p v-if="file1.date_filed" class="mt-2">Court: &nbsp;{{ file1.court_filed }}</p>
                                    <p v-if="file1.date_filed" class="">Docket #: &nbsp;{{ file1.docket_number }}</p>
                                    <p class="mt-2">Our File #: &nbsp;{{ file1.file_number ? file1.file_number : "Not Specified"}}</p>

                                </div>
                                <!-- <div v-else class="card-body"> </div> -->
                            </div>
                            <div v-if="files.data.length" class="pt-4">
                                <p>Summary:</p>
                                <textarea rows="3" readonly style="resize: none;" class="bg-base-200 text-base font-sans font-medium w-[430px] rounded-lg border border-gray-400 p-2" >{{ file1.summary }}</textarea>
                            </div>
                            <div class="flex">
                                <Link v-if="files.total !== 0" id="openbutton" :href='disp.editurl' class="btn btn-primary btn-sm btn-outline gap-0 w-44 ml-auto mr-36 mt-3" :disable="disable_button">
                                    🗁 &nbsp;<u>C</u>hange Details
                                </Link>
                            </div>
                        </div>
                    </div>
                </div>
                <!-- </div> -->
            </div>
        </div>
    </AuthenticatedLayout>
</template>
