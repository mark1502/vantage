<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { reactive, ref, computed, watch, onMounted, onUnmounted } from "vue";
import { Head, Link, useForm, router } from '@inertiajs/vue3';

const queryString = window.location.search;
const urlParams = new URLSearchParams(queryString);

const props = defineProps({ folders: Object });

let form = useForm({
    formtype: "folder_short",
    current_page: urlParams.get('page'),
    show: urlParams.get('show'),
});


let search = ref('');

const state = reactive({
    hover: false,
    current_row: 0,
    show: props.folders.per_page,
    display: 'name',
    sort_on: 'name',
    sort_order: 'asc'
});

const disp = reactive({ id: 0, folder_name: '', prompts: '', email: '', editurl: '', createurl: '', typesurl: '', note: '' });

function update_disp() {

    if (props.folders.data.length) {

         disp.folder_name = 'Folder: ' + props.folders.data[state.current_row].name;

        disp.prompts = "";
        disp.prompts += "Input time: ";
        disp.prompts += props.folders.data[state.current_row].input_time == 1 ? "true" : "false";
        disp.prompts += "\n";
        disp.prompts += 'Date1 prompt: ' + props.folders.data[state.current_row].date1_prompt + "\n";
        disp.prompts += 'Date2 prompt: ' + props.folders.data[state.current_row].date2_prompt + "\n";
        disp.prompts += 'Hide date2: ';
        disp.prompts += props.folders.data[state.current_row].hide_date2_prompt == 1 ? "true" : "false";
        disp.prompts += "\n";
        disp.prompts += 'From prompt: ' + props.folders.data[state.current_row].from_prompt + "\n";
        disp.prompts += 'To prompt: ' + props.folders.data[state.current_row].to_prompt + "\n";
        disp.prompts += 'Hide to: ';
        disp.prompts += props.folders.data[state.current_row].hide_to_prompt ? "true" : "false";
        disp.prompts += "\n";
        disp.prompts += 'Entrytype prompt: ' + props.folders.data[state.current_row].entrytype_prompt + "\n";
        disp.prompts += 'Note prompt: ' + props.folders.data[state.current_row].note_prompt + "\n";
        disp.prompts += 'Response expected prompt: ' + props.folders.data[state.current_row].responseexpected_prompt + "\n";

        disp.id = props.folders.data[state.current_row].id;
        disp.editurl = '/folders/' + disp.id + '/edit?page=' + props.folders.current_page + '&show=' + state.show;
        disp.typesurl = '/entrytypes?folder_id=' + disp.id;
    }
    else {
        disp.folder_name = '';
        disp.prompts = '';
        disp.email = '';
        disp.id = '';
        disp.editurl = '';
        disp.typesurl = '';
    } // end if

    disp.createurl = '/folders/create?page=' + props.folders.current_page + '&show=' + state.show;
}

function folder_clicked(index) {
    state.current_row = index;
    // document.getElementById('disp_card').style.visibility = 'visible';
    update_disp();
}

function folder_dblclick(index) {
    state.current_row = index;
    update_disp();
    router.visit(disp.editurl, { method: 'get' });
}

function showChanged() {
    router.visit('/folders?page=' + props.folders.current_page + '&show=' + state.show, { method: 'get' });
}

function deleteClicked() {
    let r = confirm("Do you want to delete this folder?\n\nName: " + props.folders.data[state.current_row].display_name + "\n\nClick Ok to delete");
    if (r == true) {
       form.delete("/folders/" + props.folders.data[state.current_row].id + "?page=" + props.folders.current_page + "&show=" + state.show, {
        });
    }
}

function typesClicked()
{
    router.visit(disp.typesurl, {method: 'get' });
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
     else if(e.altKey && e.key==='t') { 
        e.preventDefault();
        typesClicked();
     }
    else if (e.code >= 'KeyA' && e.code <= 'KeyZ') {
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
                if (props.folders.current_page === 1) {
                }
                else {
                    router.visit(props.folders.links[props.folders.current_page - 1].url, { method: 'get' });
                }
                // alert('PgUp');
                break;
            case 'PageDown':
                e.preventDefault();
                if (props.folders.current_page === props.folders.last_page) {
                }
                else {
                    router.visit(props.folders.links[props.folders.current_page + 1].url, { method: 'get' });
                }
                // alert('PgDown');
                break;
        } // end switch

        if (changeit === true) { update_disp(props.folders.data[state.current_row]); }
    } // end if
};

onMounted(() => document.addEventListener('keydown', handleTheKepress));
onUnmounted(() => document.removeEventListener('keydown', handleTheKepress));


watch(search, value => {
    if (value) {
        disp.folder_name = '';
        disp.prompts = '';
        disp.email = '';
        disp.id = '';
        disp.editurl = '';
    }
    router.get('/folders', { search: value }, { preserveState: true, replace: true });
    state.current_row = 0;
    update_disp();
    // document.getElementById('searchInput').focus();
    // document.getElementById('searchInput').select();
})

update_disp();

</script>

<template>

    <Head title="Folders" />

    <AuthenticatedLayout>

        <template #header>
            <h2 class="font-semibold text-xl text-base-content">
                Folders
            </h2>
        </template>

        <div class="py-3">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                <div class="bg-base-100 overflow-hidden sm:rounded-lg" id="ContactScreen" name="ContactScreen">
                    <div class="p-4 bg-base-100 border-b border-base-300 flex min-h-[680px] justify-center">
                        <div class="p-2 flex items-center">
                            <div name="left-side" class="">
                                <div class="flex justify-between pb-2">
                                    <div class="ml-2">
                                        <label class="font-bold">
                                            Display:
                                        </label>
                                        <select v-model="state.show" class="select" id="showSelect"
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

                                    <!-- <h1 class="text-2xl">Contacts</h1> -->
                                    <input v-model="search" id="searchInput" name="searchInput" type="text"
                                        placeholder="Search ..." class="border px-2 rounded" />
                                </div>
                                <div v-if="folders.data.length">
                                    <table class="table table-compact w-full border border-base-300" id="folderlist">
                                        <tr v-for="folder, index in folders.data" :key="folder.id"
                                            :class="(index == state.current_row ? 'text-primary-content bg-primary' : 'text-base-content bg-base-100')"
                                            class="border-b border-base-content"
                                            @click="folder_clicked(index)" @dblclick="folder_dblclick(index)">
                                            <td class="px-6 py-2 whitespace-nowrap">
                                                <div class="flex items-center">
                                                    <div class="text-base font-sans font-normal">
                                                        {{ folder.name }}
                                                    </div>
                                                </div>
                                            </td>
                                        </tr>
                                    </table>
                                    <div class="btn-group flex justify-center mt-2">
                                        <button v-for="link in folders.links" :href="link.url" class="btn btn-sm"
                                            :class="{ 'text-base-content/50': !link.url, 'btn-active': link.active }">
                                            <Link :href=link.url method="get"
                                                v-html=link.label />
                                        </button>
                                    </div>
                                </div>
                                <div v-else class="border p-4 text-xl text-center">No Data Found!
                                </div>
                                <div name="control_buttons" class="flex mt-8 justify-around">
                                    <Link :href='disp.createurl' class="btn btn-outline btn-primary">+ &nbsp;<u>A</u>dd</Link>
                                    <Link id="editbutton" name="editbutton" :href='disp.editurl'
                                        class="btn btn-outline btn-primary">△ &nbsp;<u>C</u>hange
                                    </Link>
                                    <Link id="deletebutton" name="deletebutton" href='' @click="deleteClicked"
                                        class="btn btn-outline btn-error">- &nbsp;Delete
                                    </Link>
                                </div>
                                <div class="mt-6 text-center">
                                    <Link id="openbutton" href="" name="openbutton" @click="typesClicked" class="btn btn-outline btn-primary w-96">Folder Entry&nbsp;<u>T</u>ypes</Link>
                                </div>
                            </div>
                            <div name="right.side" class="ml-16">

                                <div class="rounded-lg w-[430px] h-[360px] bg-base-300" id="disp_card">
                                    <div v-if="folders.data.length" class="card-body p-4 text-base-content">
                                        <h2 class="card-title">{{ disp.folder_name }}</h2>
                                        <textarea class="bg-base-300 text-base font-mono font-medium" rows="12"
                                            style="resize: none; border: none; padding: 0px 0px;">{{ disp.prompts }}</textarea>
                                    </div>
                                    <div v-else class="card-body"> </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
