<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { reactive, ref, watch, onMounted, onUnmounted } from "vue";
import { Head, Link, router } from '@inertiajs/vue3';
import { useTheme } from '@/Composables/useTheme';
import Pagination from '@/Components/Pagination.vue'

// Get access to theme management
const { theme, setTheme } = useTheme();

const queryString = window.location.search;
const urlParams = new URLSearchParams(queryString);

const props = defineProps({ contacts: Object});

const search = ref('');             // search field, set to the search parameter, or empty if not present
const contact1 = ref(null);         // contact1 is the currently selected contact, set to null initially

const state = reactive({            // state object to hold reactive data
    current_row: 0,
    show: props.contacts.per_page,
    display: 'last_first',          // not currently used, but could be used to display contacts in different formats
    sort_on: 'last_first',          // not currently used, but could be used to sort contacts by different fields
    sort_order: 'asc',              // not currently used, but could be used to sort contacts in ascending or descending order
    editurl: '',
    createurl: '',
});

watch(search, value => {            // watch function on the search field
    if (value) {
        state.editurl = '';
    }

    router.get('/contacts', { search: value }, 
    {   preserveState: true, 
        replace: true,
        onSuccess: () => {
            state.current_row = 0;
        },
        onFinish: visit => {
            update_disp();
        },
    });
});

function update_disp() {
    if (props.contacts.data.length) {
        contact1.value = props.contacts.data[state.current_row];
        state.editurl = route('contacts.edit', { contact: contact1.value.id, page: props.contacts.current_page, show: state.show });
    }
    else {
        state.editurl = '';
    } // end if

    state.createurl = route('contacts.create', { page: props.contacts.current_page, show: state.show });
}

function contact_clicked(index) {
    state.current_row = index;
    update_disp();
}

function contact_dblclick(index) {
    state.current_row = index;
    update_disp();
    router.get( state.editurl ) ;
}

function showChanged() {
    router.get( route('contacts.index', { page: 1, show: state.show }) );
}

function deleteClicked() {
    document.activeElement.blur();                                  // Remove focus from the button
    const delete_modal = document.getElementById('delete_modal');   // Get the delete modal dialog
    delete_modal.showModal();                                       // Show the modal dialog
}

function deleteContact() {
    router.delete( route('contacts.destroy', { contact: contact1.value.id, page: props.contacts.current_page, show: state.show }) );
}

function deleteCancelled() {
    const delete_modal = document.getElementById('delete_modal');
    delete_modal.close();
}

const handleTheKepress = (e) => {
    let changeit = false;
    if(e.altKey && e.key==='a') { 
        e.preventDefault();
        router.get(state.createurl);
     }
     else if(e.altKey && e.key==='c') { 
        e.preventDefault();
        router.get(state.editurl);
     }
    else if (e.code >= 'KeyA' && e.code <= 'KeyZ' && !e.ctrlKey && !e.altKey) {
        e.preventDefault();
        search.value = search.value + e.key;
        searchInput.focus();
    } else {
        const theseKeys = [ 'Escape', 'ArrowDown', 'ArrowUp', 'Enter', 'PageUp', 'PageDown' ];       // preventDefault on these keypresses
        if (theseKeys.includes(e.key)) e.preventDefault();

        if( e.key === 'Escape' ) {
            document.activeElement.blur();                                  // Remove focus from search input
            search.value = '';
        } else if( e.key === 'ArrowDown' && state.current_row < props.contacts.data.length - 1 ) {
            state.current_row++;
            changeit = true;
        } else if( e.key === 'ArrowUp' && state.current_row > 0 ) {
            state.current_row--;
            changeit = true;
        } else if( e.key === 'Enter' ) {
            router.get(state.editurl);
        } else if( e.key === 'PageUp' && props.contacts.prev_page_url ) {
            router.get(props.contacts.prev_page_url);
        } else if( e.key === 'PageDown' && props.contacts.next_page_url ) {
            router.get(props.contacts.next_page_url);
        }
        

        if (changeit === true) { update_disp(); }
    } // end if
};

onMounted(() => document.addEventListener('keydown', handleTheKepress));
onUnmounted(() => document.removeEventListener('keydown', handleTheKepress));


update_disp();

</script>

<template>

    <Head title="Contacts" />

    <AuthenticatedLayout>

        <template #header>
            <h2 class="font-semibold text-xl text-base-content ml-2">
                Contacts
            </h2>
        </template>

        <div class="py-3">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                <div class="bg-base-300 overflow-hidden sm:rounded-lg min-h-dvh" id="ContactScreen" name="ContactScreen">


                    <div class="flex min-w-full justify-between">
                        <!-- Left half - fixed width -->
                        <div class="w-1/2 mx-4">
                            <!-- Table container - takes full width of parent -->
                            <div class="">
                                <div class="flex justify-end mt-2 pb-2">
                                    <label v-if="search" for="searchInput" class="text-base-content text-lg font-semibold mr-2">Searching:</label>
                                    <input v-model="search" id="searchInput" name="searchInput" placeholder="Search ..." class="input input-bordered input-sm w-56 px-2" autocomplete="off" />
                                </div>
                                <div v-if="contacts.data.length">
                                    <table class="border-collapse border border-gray-500 w-full" id="contactlist">
                                        <thead>
                                            <tr>
                                                <th class="normal-case text-base font-bold border-b-2 border-gray-800 bg-gray-200 text-gray-800 text-left pl-5">NAME: <span class="ml-2 font-normal">(Last, First)</span></th>
                                            </tr>
                                        </thead>
                                        <tr v-for="contact, index in contacts.data" :key="contact.id" class="border-b border-base-content"
                                            :class="(index == state.current_row ? 'text-white bg-blue-800' : 'text-base-content bg-base-100')"
                                            @click="contact_clicked(index)" @dblclick="contact_dblclick(index)">
                                            <td class="px-2 py-2 whitespace-nowrap">
                                                <div class="flex items-center">
                                                    <div class="text-base font-sans font-normal">
                                                        {{ contact.display_last_first }}
                                                    </div>
                                                </div>
                                            </td>
                                        </tr>
                                    </table>
                                    <div class="min-w-full btn-group flex justify-between mt-2 items-center">
                                        <div class="flex items-baseline ml-2">
                                            <label class="font-bold mr-2">
                                                Show:
                                            </label>
                                            <select v-model="state.show" class="select select-bordered select-sm" id="showSelect"
                                                @change="showChanged">
                                                <option>6</option>
                                                <option>8</option>
                                                <option selected>10</option>
                                                <option>12</option>
                                                <option>15</option>
                                                <option>20</option>
                                                <option>25</option>
                                            </select>
                                        </div>
                                        <Pagination :links="contacts.links" :only="['contacts','files']" />
                                    </div>
                                </div>
                                <div v-else class="border p-4 text-xl text-center">No Contacts Found!
                                </div>
                                <div name="control_buttons" class="flex mt-8 justify-around">
                                    <Link id="addbutton" :href='state.createurl' class="btn btn-primary gap-0">
                                        + &nbsp;<u>A</u>dd
                                    </Link>
                                    <Link v-if="props.contacts.total !== 0" id="editbutton" :href='state.editurl' class="btn btn-primary gap-0">
                                        △ &nbsp;<u>C</u>hange
                                    </Link>
                                    <button v-if="props.contacts.total !== 0" type="button" id="deletebutton" class="btn btn-outline btn-error" @click="deleteClicked()" >
                                        - &nbsp;Delete
                                    </button>
                                </div>

                            </div>                            
                        </div>
                        
                        <!-- Right half - for text areas -->
                        <div class="w-1/2 mx-4">
                            <div class="text-xl font-md text-base-content mt-2 ml-2">
                                Contact Info:
                            </div>
                                
                            <div v-if="contacts.data.length" class="bg-base-200 text-base-content text-base font-sans rounded-lg mt-3 px-4 py-2">
                                <h2 class="">{{ contact1.display_name }}</h2>
                                <p v-if="contact1.business_title" class="">{{ contact1.business_title }}</p>
                                <p v-if="contact1.company && contact1.title !== 'Co.'" class="">
                                    {{ contact1.company }}
                                </p>
                                <p v-if="contact1.address" class="whitespace-pre-wrap">{{ contact1.address }}</p>
                                <p v-if="contact1.email" class="mt-2">email: &nbsp;{{ contact1.email }}</p>
                                <p v-if="contact1.email_alt" class="">email: &nbsp;{{ contact1.email_alt }} (alt)</p>
                                <div v-if="contact1.work_phone || contact1.home_phone" class="flex mt-2 font-mono">
                                    <p class="w-56">Work: &nbsp;{{ contact1.work_phone }}</p>
                                    <p class="">Home: {{ contact1.home_phone }}</p>
                                </div>
                                <div v-if="contact1.cell_phone || contact1.fax_phone" class="flex font-mono">
                                    <p class="w-56">Cell: &nbsp;{{ contact1.cell_phone }}</p>
                                    <p class="">Fax: &nbsp;{{ contact1.fax_phone }}</p>
                                </div>

                                <p v-if="contact1.other_phone" class="font-mono">Other: {{ contact1.other_phone }}</p>
                            <!-- moved untile the end of div from outside the dive ti inside, due to no records -->
                            <div class="pt-3">
                                <p class="text-lg font-md text-base-content ml-2">Associated Files:</p>
                                <p v-if="contact1.files.length" class="bg-base-200 text-base text-left p-2 rounded-lg">
                                    <div v-for="file in contact1.files" :key="file.id">
                                        &bull; {{ file.name }}
                                    </div>
                                </p>
                                <p v-else class="bg-base-200 whitespace-pre-wrap text-left p-2 rounded-lg">
                                    None
                                </p>
                            </div>
                            <div class="pt-3">
                                <p class="text-lg font-md text-base-content ml-2">Notes:</p>
                                <p class="bg-base-200 whitespace-pre-wrap text-left p-2 rounded-lg min-h-20">{{ contact1.note }}</p>
                            </div>
                                    
                            </div>
                            <div v-else class=""> </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Delete Modal -->
        <dialog id="delete_modal" class="modal">
        <div class="modal-box">
            <h3 class="text-xl font-bold text-center">Confirm Delete</h3>
            <p v-if="contacts.data.length" class="py-4 text-lg">Delete contact: "{{ contact1.display_name }}"?</p>
            <p class="">Are you sure?</p>
            <form method="dialog">
                <div class="mt-8 flex justify-center gap-10">
                    <button class="btn btn-primary" @click="deleteContact()" >
                        Delete
                    </button>
                    <button class="btn btn-primary" @click="deleteCancelled()" >
                        Cancel
                    </button>
                </div>
            </form>
        </div>
        </dialog>
    </AuthenticatedLayout>
</template>
