<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import Pagination from '@/Components/Pagination.vue';
import { reactive, ref, computed, watch, onMounted, onUnmounted, nextTick } from "vue";
import { Head, Link, useForm, router } from '@inertiajs/vue3';

const queryString = window.location.search;
const urlParams = new URLSearchParams(queryString);

const props = defineProps({ filetypes: Object, filetypeCount: Number, search: String });

let search = ref(props.search ?? '');

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
        disp.name = '';
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

function row_clicked(index) {
    state.current_row = index;
    update_disp();
}

function row_dblclick(index) {
    state.current_row = index;
    update_disp();
    router.visit(disp.editurl, { method: 'get' });
}

function showChanged() {
    router.get('/filetypes', { page: props.filetypes.current_page, show: state.show, search: search.value || undefined });
}

const deleteBlockReason = ref('');

function openDeleteModal() {
    document.activeElement?.blur();
    const selected = props.filetypes.data[state.current_row];
    if (!selected) {
        return;
    }
    if (selected.files_count > 0) {
        deleteBlockReason.value = 'This file type cannot be deleted because one or more files (open or closed) use it.';
    } else if (selected.set_as_default) {
        deleteBlockReason.value = 'The default file type cannot be deleted. Set another file type as the default first.';
    } else if (props.filetypeCount <= 1) {
        deleteBlockReason.value = 'The last remaining file type cannot be deleted.';
    } else {
        deleteBlockReason.value = '';
        document.getElementById('delete_modal').showModal();
        return;
    }
    document.getElementById('cannot_delete_modal').showModal();
}

function submitDelete() {
    const selected = props.filetypes.data[state.current_row];
    if (!selected) {
        return;
    }
    router.delete(route('filetypes.destroy', selected.id), {
        data: { page: props.filetypes.current_page, show: state.show, search: search.value || undefined },
        onSuccess: () => {
            state.current_row = 0;
            update_disp();
        },
        onError: (errors) => {
            if (errors.delete) {
                deleteBlockReason.value = errors.delete;
                document.getElementById('cannot_delete_modal').showModal();
            }
        },
    });
}

function setDefaultFileType() {
    router.post('/setDefaultFileType',
        { default_id: disp.id },
        {   onSuccess: () => {
                update_disp();
            }
        } );
}


function nameParts(name) {
    const term = search.value.trim();
    const start = term ? name.toLowerCase().indexOf(term.toLowerCase()) : -1;
    if (start === -1) {
        return { before: name, match: '', after: '' };
    }
    return {
        before: name.slice(0, start),
        match: name.slice(start, start + term.length),
        after: name.slice(start + term.length),
    };
}


const handleTheKepress = (e) => {
    if (document.querySelector('dialog[open]')) {
        return;
    }
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
    router.get('/filetypes', { search: value || undefined, show: state.show }, {
        preserveState: true,
        replace: true,
        onSuccess: () => doTick(),
        onFinish: () => update_disp(),
    });
});


update_disp();

</script>

<template>

    <Head title="File Types" />

    <AuthenticatedLayout>

        <template #header>
            <h2 class="font-bold text-xl text-base-content ml-3">
                <Link :href="'/adminmenu'" class="hover:text-blue-600 hover:underline">Admin</Link> > File Types
            </h2>
        </template>

        <div class="py-3">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                <div class="bg-base-300 overflow-hidden sm:rounded-lg min-h-dvh" id="FiletypeScreen" name="FiletypeScreen">
                    <div class="p-4 flex min-h-[680px] justify-center ">
                        <div class="p-2 flex items-start ">
                            <div name="left-side" class="w-[460px]">
                                <div class="flex justify-end pb-2">
                                    <input v-model="search" id="searchInput" name="searchInput" type="text"
                                        placeholder="Search ..." class="input input-sm w-56 px-2" autocomplete="off" />
                                </div>
                                <div v-if="filetypes.data.length">
                                    <table class="w-full border border-base-content text-base font-sans font-normal" id="filetypelist">
                                        <thead class="text-left bg-base-300">
                                            <tr>
                                            <th class="text-base font-semibold pl-2 text-base-content border-b-2 border-base-content">File Type:</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <tr v-for="filetype, index in filetypes.data" :key="filetype.id"
                                                :class="setEntryClass(index)"
                                                class="border-b border-base-content"
                                                @click="row_clicked(index)" @dblclick="row_dblclick(index)">
                                                <td class="px-6 py-2 whitespace-nowrap">
                                                    <div class="flex items-center">
                                                        <div class="text-base font-sans font-normal">
                                                            <template v-for="parts in [nameParts(filetype.name)]" :key="'p' + filetype.id">
                                                                {{ parts.before }}<b>{{ parts.match }}</b>{{ parts.after }}
                                                            </template>{{ filetype.set_as_default === 1 ? ' >> ( Default )' : '' }}
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
                                    <button type="button" id="deletebutton" name="deletebutton"
                                        class="btn btn-outline btn-error"
                                        :disabled="!filetypes.data.length" @click="openDeleteModal">- &nbsp;Delete
                                    </button>
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
                                    <Link class="btn btn-outline btn-primary gap-0" @click="setDefaultFileType()" href="" 
                                    title="Set the Default suggested type for new files.  But new files can be any type when created.">
                                        Set Default Type to '{{ disp.name }}'
                                    </Link>

                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <dialog id="delete_modal" class="modal">
            <div class="modal-box">
                <h3 class="text-xl font-bold text-center">Confirm Delete</h3>
                <p class="py-4 text-lg">Permanently delete the "{{ disp.name }}" file type?</p>
                <p>This cannot be undone. Are you sure?</p>
                <form method="dialog">
                    <div class="mt-8 flex justify-center gap-10">
                        <button class="btn btn-error" @click="submitDelete()">Delete</button>
                        <button class="btn btn-primary">Cancel</button>
                    </div>
                </form>
            </div>
            <form method="dialog" class="modal-backdrop"><button>close</button></form>
        </dialog>

        <dialog id="cannot_delete_modal" class="modal">
            <div class="modal-box">
                <h3 class="text-xl font-bold text-center">Cannot Delete File Type</h3>
                <p class="py-4 text-lg text-center">{{ deleteBlockReason }}</p>
                <form method="dialog">
                    <div class="mt-4 flex justify-center">
                        <button class="btn btn-primary">OK</button>
                    </div>
                </form>
            </div>
            <form method="dialog" class="modal-backdrop"><button>close</button></form>
        </dialog>
    </AuthenticatedLayout>
</template>
