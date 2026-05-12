<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { reactive, ref, computed, onMounted, onUnmounted } from "vue";
import { Head, Link, router } from '@inertiajs/vue3';
import Pagination from '@/Components/Pagination.vue';

const props = defineProps({
    entrytypes: Object,
    folders: Array,
    selectedFolderId: Number,
    filter: String,
    activeCount: Number,
    solEntrytypeId: Number,
});

const isProtected = computed(() => selected.value?.id === props.solEntrytypeId);

const activeFolderId = ref(props.selectedFolderId);
const activeFilter = ref(props.filter || 'current');
const addName = ref('');
const editName = ref('');

const state = reactive({
    current_row: 0,
    show: props.entrytypes.per_page,
});

const selected = computed(() => props.entrytypes.data[state.current_row] ?? null);
const isDeleted = computed(() => selected.value?.faux_deleted ?? false);

const emptyRows = computed(() => Math.max(0, state.show - props.entrytypes.data.length));

function queryParams(extra = {}) {
    return {
        folder_id: activeFolderId.value,
        show: state.show,
        filter: activeFilter.value,
        ...extra,
    };
}

function folderChanged() {
    router.get('/entrytypes', queryParams({ page: 1 }), {
        preserveState: true,
        replace: true,
        onSuccess: () => { state.current_row = 0; },
    });
}

function filterChanged() {
    router.get('/entrytypes', queryParams({ page: 1 }), {
        preserveState: true,
        replace: true,
        onSuccess: () => {
            document.activeElement.blur();
            state.current_row = 0;
        },
    });
}

function showChanged() {
    router.get(route('entrytypes.index', queryParams({ page: 1 })));
}

function rowClicked(index) {
    state.current_row = index;
}

function rowDblClicked(index) {
    state.current_row = index;
    if (props.entrytypes.data[index]?.id === props.solEntrytypeId) {
        document.getElementById('protected_modal').showModal();
        return;
    }
    if (props.entrytypes.data[index]?.faux_deleted) {
        document.getElementById('deleted_edit_modal').showModal();
        return;
    }
    openEditModal();
}

function setRowClass(index) {
    if (index === state.current_row) {
        return 'text-base-content bg-primary/20 border-l-4 border-l-blue-600';
    }
    return 'text-base-content bg-base-100';
}

// Add modal
function openAddModal() {
    addName.value = '';
    const modal = document.getElementById('add_modal');
    modal.showModal();
    setTimeout(() => document.getElementById('add_name_input')?.focus(), 50);
}

function submitAdd() {
    if (!addName.value.trim()) return;
    router.post(route('entrytypes.store'), {
        name: addName.value.trim(),
        folder_id: activeFolderId.value,
        show: state.show,
        filter: activeFilter.value,
    }, {
        onSuccess: () => document.getElementById('add_modal').close(),
    });
}

// Edit modal
function openEditModal() {
    if (!selected.value || isDeleted.value) return;
    editName.value = selected.value.name;
    const modal = document.getElementById('edit_modal');
    modal.showModal();
    setTimeout(() => {
        const input = document.getElementById('edit_name_input');
        input?.focus();
        input?.select();
    }, 50);
}

function submitEdit() {
    if (!editName.value.trim() || !selected.value) return;
    router.put(route('entrytypes.update', selected.value.id), {
        name: editName.value.trim(),
        folder_id: activeFolderId.value,
        page: props.entrytypes.current_page,
        show: state.show,
        filter: activeFilter.value,
    }, {
        onSuccess: () => document.getElementById('edit_modal').close(),
    });
}

// Delete
function openDeleteModal() {
    document.activeElement.blur();
    if (props.activeCount <= 1) {
        document.getElementById('cannot_delete_modal').showModal();
        return;
    }
    document.getElementById('delete_modal').showModal();
}

function submitDelete() {
    if (!selected.value) return;
    router.delete(route('entrytypes.destroy', selected.value.id), {
        data: {
            folder_id: activeFolderId.value,
            page: props.entrytypes.current_page,
            show: state.show,
            filter: activeFilter.value,
        },
    });
}

// Restore
function restoreEntrytype() {
    if (!selected.value) return;
    router.patch(route('entrytypes.restore', selected.value.id), {
        folder_id: activeFolderId.value,
        page: props.entrytypes.current_page,
        show: state.show,
        filter: activeFilter.value,
    });
}

// Keyboard navigation
const handleKeypress = (e) => {
    const activeEl = document.activeElement;
    const isInModal = activeEl?.closest('dialog[open]');
    if (isInModal) return;

    if (e.altKey && e.key === 'a') {
        e.preventDefault();
        if (!isDeleted.value && activeFilter.value !== 'deleted') openAddModal();
    } else if (e.altKey && e.key === 'c') {
        e.preventDefault();
        if (!isDeleted.value && !isProtected.value) openEditModal();
    } else {
        const navKeys = ['Escape', 'ArrowDown', 'ArrowUp', 'Enter', 'PageUp', 'PageDown'];
        if (navKeys.includes(e.key)) e.preventDefault();

        if (e.key === 'Escape') {
            document.activeElement.blur();
        } else if (e.key === 'ArrowDown' && state.current_row < props.entrytypes.data.length - 1) {
            state.current_row++;
        } else if (e.key === 'ArrowUp' && state.current_row > 0) {
            state.current_row--;
        } else if (e.key === 'Enter') {
            if (!isDeleted.value && !isProtected.value && selected.value) openEditModal();
        } else if (e.key === 'PageUp' && props.entrytypes.prev_page_url) {
            router.get(props.entrytypes.prev_page_url);
        } else if (e.key === 'PageDown' && props.entrytypes.next_page_url) {
            router.get(props.entrytypes.next_page_url);
        }
    }
};

onMounted(() => document.addEventListener('keydown', handleKeypress));
onUnmounted(() => document.removeEventListener('keydown', handleKeypress));
</script>

<template>
    <Head title="Entry Types" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="font-semibold text-xl text-base-content ml-3">
                <Link :href="route('adminmenu')" class="hover:underline hover:text-blue-700">Admin</Link> > Edit Entry Types
            </h2>
        </template>

        <div class="py-3">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                <div class="bg-base-300 overflow-hidden sm:rounded-lg min-h-dvh p-4">

                    <!-- Folder selector -->
                    <div class="max-w-3xl mx-auto">
                    <div class="flex items-center gap-4 mt-6 mb-6">
                        <label class="text-lg font-bold text-base-content">Folder:</label>
                        <select v-model="activeFolderId" class="select select-bordered select-sm text-base w-64"
                            @change="folderChanged">
                            <option v-for="folder in folders" :key="folder.id" :value="folder.id">
                                {{ folder.name }}
                            </option>
                        </select>
                    </div>

                    <h3 class="text-lg font-bold text-base-content mb-1">Entry Types:</h3>

                    <!-- Table -->
                    <div v-if="entrytypes.data.length">
                        <table class="border border-base-content w-full">
                            <thead>
                                <tr>
                                    <th class="normal-case text-base font-bold border-b-2 border-base-content bg-base-300 text-base-content text-left pl-5">
                                        NAME
                                    </th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="(entrytype, index) in entrytypes.data" :key="entrytype.id"
                                    class="border-b border-base-content/20 cursor-pointer"
                                    :class="setRowClass(index)"
                                    @click="rowClicked(index)" @dblclick="rowDblClicked(index)">
                                    <td class="px-2 py-1 whitespace-nowrap">
                                        <div class="flex items-center gap-2">
                                            <div class="text-base font-sans font-normal"
                                                :class="entrytype.faux_deleted ? 'line-through text-base-content/50' : ''">
                                                {{ entrytype.name }}
                                            </div>
                                            <span v-if="activeFilter === 'all' && entrytype.faux_deleted"
                                                class="badge badge-sm badge-warning">deleted</span>
                                        </div>
                                    </td>
                                </tr>
                                <tr v-for="n in emptyRows" :key="'empty-' + n"
                                    class="border-b bg-base-100"
                                    :class="n === emptyRows ? 'border-base-content' : 'border-base-content/20'">
                                    <td class="px-2 py-2">&nbsp;</td>
                                </tr>
                            </tbody>
                        </table>

                        <!-- Pagination & controls -->
                        <div class="flex justify-between mt-2 items-center">
                            <div class="flex items-baseline ml-2 gap-4">
                                <div class="flex items-baseline">
                                    <label class="font-bold mr-2">Show:</label>
                                    <select v-model="state.show" class="select select-bordered select-sm"
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
                                <div class="flex items-baseline">
                                    <label class="font-bold mr-2">Filter:</label>
                                    <select v-model="activeFilter" class="select select-bordered select-sm"
                                        @change="filterChanged">
                                        <option value="current">Current</option>
                                        <option value="deleted">Deleted</option>
                                        <option value="all">All</option>
                                    </select>
                                </div>
                            </div>
                            <Pagination :links="entrytypes.links" :only="['entrytypes']" />
                        </div>
                    </div>

                    <!-- Empty state -->
                    <div v-else class="border p-4 text-xl text-center flex flex-col items-center">
                        <span>{{ activeFilter === 'deleted' ? 'No Deleted Entry Types Found!' : 'No Entry Types Found!' }}</span>
                        <button v-if="activeFilter === 'deleted'" class="btn btn-primary btn-sm mt-6"
                            @click="activeFilter = 'current'; filterChanged()">
                            View Current Entry Types
                        </button>
                    </div>

                    <!-- Action buttons -->
                    <div class="flex mt-8 justify-center gap-12">
                        <button v-if="activeFilter !== 'deleted'" class="btn btn-primary gap-0"
                            @click="openAddModal()">
                            + &nbsp;<u>A</u>dd
                        </button>
                        <button v-if="entrytypes.total !== 0" class="btn btn-primary gap-0"
                            :class="(isDeleted || isProtected) ? 'btn-disabled' : ''"
                            @click="openEditModal()">
                            △ &nbsp;<u>C</u>hange
                        </button>
                        <button v-if="entrytypes.total !== 0 && !isDeleted && !isProtected" class="btn btn-outline btn-error"
                            @click="openDeleteModal()">
                            - &nbsp;Delete
                        </button>
                        <button v-if="entrytypes.total !== 0 && isDeleted" class="btn btn-outline btn-success"
                            @click="restoreEntrytype()">
                            ↩ &nbsp;Restore
                        </button>
                    </div>
                    </div>

                </div>
            </div>
        </div>

        <!-- Add Modal -->
        <dialog id="add_modal" class="modal">
            <div class="modal-box">
                <h3 class="text-xl font-bold text-center">Add Entry Type</h3>
                <div class="py-4">
                    <label class="font-bold">Name:</label>
                    <input id="add_name_input" v-model="addName" type="text" class="input input-bordered w-full mt-2"
                        maxlength="255" autocomplete="off" @keydown.enter.prevent="submitAdd()" />
                </div>
                <div class="mt-4 flex justify-center gap-10">
                    <button class="btn btn-primary" @click="submitAdd()">Save</button>
                    <form method="dialog"><button class="btn btn-primary">Cancel</button></form>
                </div>
            </div>
            <form method="dialog" class="modal-backdrop"><button>close</button></form>
        </dialog>

        <!-- Edit Modal -->
        <dialog id="edit_modal" class="modal">
            <div class="modal-box">
                <h3 class="text-xl font-bold text-center">Edit Entry Type</h3>
                <div class="py-4">
                    <label class="font-bold">Name:</label>
                    <input id="edit_name_input" v-model="editName" type="text" class="input input-bordered w-full mt-2"
                        maxlength="255" autocomplete="off" @keydown.enter.prevent="submitEdit()" />
                </div>
                <div class="mt-4 flex justify-center gap-10">
                    <button class="btn btn-primary" @click="submitEdit()">Save</button>
                    <form method="dialog"><button class="btn btn-primary">Cancel</button></form>
                </div>
            </div>
            <form method="dialog" class="modal-backdrop"><button>close</button></form>
        </dialog>

        <!-- Delete Modal -->
        <dialog id="delete_modal" class="modal">
            <div class="modal-box">
                <h3 class="text-xl font-bold text-center">Confirm Delete</h3>
                <p v-if="selected" class="py-4 text-lg">Remove "{{ selected.name }}" entry type from active list?</p>
                <p>Are you sure?</p>
                <form method="dialog">
                    <div class="mt-8 flex justify-center gap-10">
                        <button class="btn btn-primary" @click="submitDelete()">Delete</button>
                        <button class="btn btn-primary">Cancel</button>
                    </div>
                </form>
            </div>
            <form method="dialog" class="modal-backdrop"><button>close</button></form>
        </dialog>

        <!-- Cannot Delete Last Entry Type Modal -->
        <dialog id="cannot_delete_modal" class="modal">
            <div class="modal-box">
                <h3 class="text-xl font-bold text-center">Cannot Delete Entry Type</h3>
                <p class="py-4 text-lg text-center">This entry type cannot be deleted because each folder must have at least one entry type.</p>
                <form method="dialog">
                    <div class="mt-4 flex justify-center">
                        <button class="btn btn-primary">OK</button>
                    </div>
                </form>
            </div>
            <form method="dialog" class="modal-backdrop"><button>close</button></form>
        </dialog>

        <!-- Protected Entrytype Modal -->
        <dialog id="protected_modal" class="modal">
            <div class="modal-box">
                <h3 class="text-xl font-bold text-center">Protected Entry Type</h3>
                <p class="py-4 text-lg text-center">The "Deadline - Statute of Limitations" entry type is used by the system to manage SOL calendar events.</p>
                <p class="text-base text-center">It cannot be renamed or deleted.</p>
                <form method="dialog">
                    <div class="mt-8 flex justify-center">
                        <button class="btn btn-primary">OK</button>
                    </div>
                </form>
            </div>
            <form method="dialog" class="modal-backdrop"><button>close</button></form>
        </dialog>

        <!-- Deleted Edit Blocked Modal -->
        <dialog id="deleted_edit_modal" class="modal">
            <div class="modal-box">
                <h3 class="text-xl font-bold text-center">Cannot Edit Deleted Entry Type</h3>
                <p class="py-4 text-lg text-center">Deleted entry types cannot be edited.</p>
                <p class="text-base text-center">You must first <strong>Restore</strong> this entry type before it can be edited.</p>
                <form method="dialog">
                    <div class="mt-8 flex justify-center">
                        <button class="btn btn-primary">OK</button>
                    </div>
                </form>
            </div>
            <form method="dialog" class="modal-backdrop"><button>close</button></form>
        </dialog>

    </AuthenticatedLayout>
</template>
