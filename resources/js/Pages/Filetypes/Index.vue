<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import Pagination from '@/Components/Pagination.vue';
import { reactive, ref, computed, watch, onMounted, onUnmounted, nextTick } from "vue";
import { Head, Link, useForm, router } from '@inertiajs/vue3';

const queryString = window.location.search;
const urlParams = new URLSearchParams(queryString);

const props = defineProps({ filetypes: Object });

let form = useForm({
    formtype: "filetype_short",
    current_page: urlParams.get('page'),
    show: urlParams.get('show'),
});


let search = ref('');

const state = reactive({
    hover: false,
    current_row: 0,
    show: props.filetypes.per_page,
    display: 'last_first',
    sort_on: 'last_first',
    sort_order: 'asc'
});

const disp = reactive({ 
    id: 0, 
    name: '',
    set_as_default: false, 
    editurl: '', 
    createurl: ''
});

const checks = reactive({
    has_correspondence: false,
    has_pleadings: false,
    has_discovery: false,
    has_documents: false,
    has_memos: false,
    has_events: false,
    has_todo: false,
    has_phone: false,
    has_medrecs: false,
    has_medbills: false,
    has_costs: false,
    enable_file_SOL: false,
})

function update_disp() {

    if (props.filetypes.data.length) {

        let thetype = props.filetypes.data[state.current_row];

        disp.id = thetype.id;
        // disp.name = thetype.set_as_default === 1 ? thetype.name + ' (Default File Type)' : thetype.name;
        disp.name = thetype.name;
        disp.set_as_default = thetype.set_as_default;
        disp.editurl = '/filetypes/' + disp.id + '/edit?page=' + props.filetypes.current_page + '&show=' + state.show;

        checks.has_correspondence = thetype.has_correspondence === 1 ? true : false;
        checks.has_pleadings = thetype.has_pleadings === 1 ? true : false;
        checks.has_discovery = thetype.has_discovery === 1 ? true : false;
        checks.has_documents = thetype.has_documents === 1 ? true : false;
        checks.has_memos = thetype.has_memos === 1 ? true : false;
        checks.has_events = thetype.has_events === 1 ? true : false;
        checks.has_todo = thetype.has_todo === 1 ? true : false;
        checks.has_phone = thetype.has_phone === 1 ? true : false;
        checks.has_medrecs = thetype.has_medrecs === 1 ? true : false;
        checks.has_medbills = thetype.has_medbills === 1 ? true : false;
        checks.has_costs = thetype.has_costs === 1 ? true : false;
        checks.enable_file_SOL = thetype.enable_file_SOL === 1 ? true : false;
    } else {
        disp.display_name = '';
        disp.address = '';
        disp.email = '';
        disp.id = '';
        disp.set_as_default = false;
        disp.editurl = '';
    } // end if

    disp.createurl = '/filetypes/create?page=' + props.filetypes.current_page + '&show=' + state.show;
}

async function doTick() {
    state.current_row = 0;
    await nextTick();
}

function contact_clicked(index) {
    state.current_row = index;
    // document.getElementById('disp_card').style.visibility = 'visible';
    update_disp();
}

function contact_dblclick(index) {
    state.current_row = index;
    update_disp();
    router.visit(disp.editurl, { method: 'get' });
}

function showChanged() {
    router.visit('/filetypes?page=' + props.filetypes.current_page + '&show=' + state.show, { method: 'get' });
}

function deleteClicked() {
    let r = confirm("Do you want to delete this contact?\n\nName: " + props.filetypes.data[state.current_row].display_name + "\n\nClick Ok to delete");
    if (r == true) {
       form.delete("/contacts/" + props.filetypes.data[state.current_row].id + "?page=" + props.filetypes.current_page + "&show=" + state.show, {
        });
    }
}

function setDefaultFileType() {
    router.post('/setDefaultFileType',
        { default_id: disp.id },
        {   onSuccess: () => {
                update_disp();
            }
        } );
}


function format_name( name_in ) {
    let len_search = search.value.length;

    if( len_search == 0 ) {
        return name_in;
    }

    let name_i = name_in.toLowerCase();
    let search_i = search.value.toLowerCase();
    let startat = name_i.indexOf(search_i);

    if( startat == -1 ) {
        return name_in;
    }

    let name_out = "";

    for( let x = 0; x < name_in.length ; x++){
        if( x == startat ) {
            name_out += "<b>";
        }
        name_out += name_in.charAt(x);
        if( x == startat + len_search - 1) {
            name_out += "</b>";
        }
    }
    return name_out;

}


const handleTheKepress = (e) => {
    let changeit = false;
    if(e.altKey && e.key==='a') { 
        e.preventDefault();
        router.get(disp.createurl);
     }
     else if(e.altKey && e.key==='c') { 
        e.preventDefault();
        router.get(disp.editurl);
     }
    else if (e.code >= 'KeyA' && e.code <= 'KeyZ' && !e.ctrlKey && !e.altKey) {
        e.preventDefault();
        search.value = search.value + e.key;
        searchInput.focus();
    }
    else {
        switch (e.key) {
            case 'ArrowDown':
                e.preventDefault();
                if (state.current_row < state.show - 1) {
                    state.current_row++;
                    changeit = true;
                }
                break;
            case 'ArrowUp':
                e.preventDefault();
                if (state.current_row > 0) {
                    state.current_row--;
                    changeit = true;
                }
                break;
            case 'Enter':
                e.preventDefault();
                router.visit(disp.editurl, { method: 'get' });
                break;
            case 'PageUp':
                e.preventDefault();
                if (props.filetypes.current_page === 1) {
                } else {
                    router.visit(props.filetypes.links[props.filetypes.current_page - 1].url, { method: 'get' });
                }
                break;
            case 'PageDown':
                e.preventDefault();
                if (props.filetypes.current_page === props.filetypes.last_page) {
                } else {
                    router.visit(props.filetypes.links[props.filetypes.current_page + 1].url, { method: 'get' });
                }
                break;
        } // end switch

        if (changeit === true) { update_disp(props.filetypes.data[state.current_row]); }
    } // end if
};


function checker( var_in ) {
    let symbol = var_in ? '✓ ' : '✗ ';
    return symbol;
}


function setClass( var_in ) {
    let theClass = var_in ? 'text-success' : 'text-error';
    theClass += var_in ? ' font-semibold' : ' font-light';
    return theClass;
}

const emptyRows = computed(() => {
    return Math.max(0, state.show - props.filetypes.data.length);
});

function setEntryClass( index ) {
    if ( index === state.current_row ) {
        return 'text-base-content bg-primary/20 border-l-4 border-l-blue-600';
    }
    return 'text-base-content bg-base-100';
}


onMounted(() => document.addEventListener('keydown', handleTheKepress));
onUnmounted(() => document.removeEventListener('keydown', handleTheKepress));


watch(search, value => {
    if (value) {
        // document.getElementById('disp_card').style.visibility= 'hidden';
        disp.display_name = '';
        disp.address = '';
        disp.email = '';
        disp.id = '';
        disp.editurl = '';
    }

    router.get('/contacts', { search: value }, { preserveState: true, replace: true,
        onSuccess: () => {
            return Promise.all([
                doTick()
            ])
        },
        onFinish: visit => {
            update_disp();
        },
    });
});


update_disp();

</script>

<template>

    <Head title="File Types" />

    <AuthenticatedLayout>

        <template #header>
            <h2 class="font-semibold text-xl text-base-content">
                <Link :href="'/adminmenu'" class="text-blue-600 hover:underline">Admin Menu</Link> > File Types
            </h2>
        </template>

        <div class="py-3">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                <div class="bg-base-300 overflow-hidden sm:rounded-lg min-h-dvh" id="ContactScreen" name="ContactScreen">
                    <div class="p-4 flex min-h-[680px] justify-center ">
                        <div class="p-2 flex items-start ">
                            <div name="left-side" class="w-[460px]">
                                <div class="flex justify-end pb-2">
                                    <input v-model="search" id="searchInput" name="searchInput" type="text"
                                        placeholder="Search ..." class="input input-bordered input-sm w-56 px-2" autocomplete="off" />
                                </div>
                                <div v-if="filetypes.data.length">
                                    <table class="w-full border border-base-content text-base font-sans font-normal" id="contactlist">
                                        <thead class="text-left bg-base-300">
                                            <tr>
                                            <th class="text-base font-semibold pl-2 text-base-content border-b-2 border-base-content">File Type:</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <tr v-for="filetype, index in filetypes.data" :key="filetype.id"
                                                :class="setEntryClass(index)"
                                                class="border-b border-base-content"
                                                @click="contact_clicked(index)" @dblclick="contact_dblclick(index)">
                                                <td class="px-6 py-2 whitespace-nowrap">
                                                    <div class="flex items-center">
                                                        <div class="text-base font-sans font-normal">
                                                              {{ filetype.name }}{{ filetype.set_as_default === 1 ? ' >> ( Default )' : '' }}
                                                        </div>
                                                    </div>
                                                </td>
                                            </tr>
                                            <tr v-for="n in emptyRows" :key="'empty-' + n" class="border-b border-base-content bg-base-100">
                                                <td class="px-6 py-2">&nbsp;</td>
                                            </tr>
                                        </tbody>
                                    </table>
                                    <div class="btn-group flex justify-between mt-2 items-center">
                                        <div class="flex items-center">
                                            <label for="showSelect" class="font-bold ml-2 mr-1">
                                                Show:
                                            </label>
                                            <select v-model="state.show" id="showSelect" @change="showChanged"
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
                                            <Pagination :links="filetypes.links" :only="['filetypes']"/>
                                        </div>
                                    </div>

                                </div>
                                <div v-else class="border p-4 text-xl text-center text-base-content">No Data Found!
                                </div>
                                <div name="control_buttons" class="flex mt-8 justify-around">
                                    <Link :href='disp.createurl' class="btn btn-outline btn-primary gap-0">+ &nbsp;<u>A</u>dd</Link>
                                    <Link id="editbutton" name="editbutton" :href='disp.editurl'
                                        class="btn btn-outline btn-primary gap-0">△ &nbsp;<u>C</u>hange
                                    </Link>
                                    <Link id="deletebutton" name="deletebutton" href='' @click="deleteClicked"
                                        class="btn btn-outline btn-error">- &nbsp;Delete
                                    </Link>
                                </div>
                            </div>
                            <div name="right.side" class="ml-16">

                                <div class="rounded-lg w-[530px] h-[360px] bg-base-200 border border-base-300" id="disp_card">
                                    <div v-if="filetypes.data.length" class="card-body p-4 text-base-content">
                                            <h2 class="card-title font-bold text-xl">
                                                <!-- {{ props.filetypes.data[state.current_row].set_as_default === true ? disp.name + 'default' : disp.name }} -->
                                                  {{ disp.name }}{{ disp.set_as_default === 1 ? ' (default)' : '' }}
                                            </h2>
                                        <h4 class="ml-4 mt-2 font-semibold text-base-content">Folders available for this file type:</h4>
                                        <table class="ml-10">
                                            <tbody>
                                                <tr class="flex">
                                                    <td class="w-[200px] flex" :class="setClass(checks.has_correspondence)">{{ checker(checks.has_correspondence) }} Correspondence</td>
                                                    <td class="flex" :class="setClass(checks.has_pleadings)">{{ checker(checks.has_pleadings) }} Pleadings</td>
                                                </tr>
                                                <tr class="flex">
                                                    <td class="w-[200px] flex" :class="setClass(checks.has_discovery)">{{ checker(checks.has_discovery) }} Discovery</td>
                                                    <td class="flex" :class="setClass(checks.has_documents)">{{ checker(checks.has_documents) }} Documents</td>
                                                </tr>
                                                <tr class="flex">
                                                    <td class="w-[200px] flex" :class="setClass(checks.has_memos)">{{ checker(checks.has_memos) }} Memos</td>
                                                    <td class="flex" :class="setClass(checks.has_events)">{{ checker(checks.has_events) }} Events</td>
                                                </tr>
                                                <tr class="flex">
                                                    <td class="w-[200px] flex" :class="setClass(checks.has_todo)">{{ checker(checks.has_todo) }} To-Do</td>
                                                    <td class="flex" :class="setClass(checks.has_phone)">{{ checker(checks.has_phone) }} Phone Messages</td>
                                                </tr>
                                                <tr class="flex">
                                                    <td class="w-[200px] flex" :class="setClass(checks.has_medrecs)">{{ checker(checks.has_medrecs) }} Medical Records</td>
                                                    <td class="flex" :class="setClass(checks.has_medbills)">{{ checker(checks.has_medbills) }} Medical Bills</td>
                                                </tr>
                                                <tr class="flex">
                                                    <td class="w-[200px] flex" :class="setClass(checks.has_costs)">{{ checker(checks.has_costs) }} Case Costs</td>
                                                    <td class="flex">
                                                    </td>
                                                </tr>
                                            </tbody>
                                        </table>

                                        <div class="ml-10 mt-6 font-semibold text-base-content">
                                            {{  checks.enable_file_SOL === true
                                                ? 'Files of this type may have a Statue of Limitations.'
                                                : 'Files of this type DO NOT have a Statute of Limitiations.'
                                            }}
                                        </div>

                                    </div>
                                    <div v-else class="card-body"> </div>
                                </div>
                                <div v-if="filetypes.data.length" class="mt-8 text-center">
                                    <Link class="btn btn-outline btn-primary gap-0" @click="setDefaultFileType()" href="">
                                        Set '{{ disp.name }}' as the Default type
                                    </Link>

                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
