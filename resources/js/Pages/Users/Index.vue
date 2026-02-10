<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, useForm, router } from '@inertiajs/vue3';
import { reactive, ref, computed, watch, onMounted, onUnmounted } from "vue";
import Pagination from '@/Components/Pagination.vue';

const queryString = window.location.search;
const urlParams = new URLSearchParams(queryString);

const props = defineProps({ users: Object });

let form = useForm({
    formtype: "contact_short",
    current_page: urlParams.get('page'),
    show: urlParams.get('show'),
});


// let search = ref('');

const state = reactive({
    hover: false,
    current_row: 0,
    show: props.users.per_page,
    display: 'last_first',
    sort_on: 'last_first',
    sort_order: 'asc'
});

const disp = reactive({ id: 0, display_name: '', address: '', email: '', editurl: '', createurl: '', preferencesurl: '', note: '' });


function update_disp() {

    if (props.users.data.length) {
        // disp.display_name = props.users.data[state.current_row].display_name;

        // disp.address = "";
        // disp.address += props.users.data[state.current_row].email + "\n\n";
        // disp.address += props.users.data[state.current_row].member_initials ? "User Initials: " + props.users.data[state.current_row].member_initials + "\n\n" : "";
        // disp.address += props.users.data[state.current_row].firm_role ? props.users.data[state.current_row].firm_role + "\n" : "";
        // disp.address += props.users.data[state.current_row].user_type ? props.users.data[state.current_row].user_type + " User \n" : "";
        disp.id = props.users.data[state.current_row].id;
        // disp.editurl = '/users/' + disp.id + '/edit?page=' + props.users.current_page + '&show=' + state.show;
        // disp.preferencesurl = '/users/' + disp.id + '/preferences?page=' + props.users.current_page + '&show=' + state.show;
        disp.editurl = route( 'users.edit', { user: disp.id, page: props.users.current_page, show: state.show });
        disp.preferencesurl = route( 'preferences.index', { user: disp.id, page: props.users.current_page, show: state.show });
    }
    else {
        // disp.display_name = '';
        // disp.address = '';
        disp.email = '';
        disp.id = '';
        disp.editurl = '';
    } // end if

    // disp.createurl = '/users/create?page=' + props.users.current_page + '&show=' + state.show;
    disp.createurl = route( 'users.create', { page: props.users.current_page, show: state.show });
}


function user_clicked(index) {
    state.current_row = index;
    update_disp();
}

function user_dblclick(index) {
    user_clicked(index);  // update state and display
    router.get(disp.editurl);
}

function showChanged() {
    router.get( route('users.index', { page: 1, show: state.show }) );
}

function deleteClicked() {
    let r = confirm("Do you want to delete this contact?\n\nName: " + props.users.data[state.current_row].display_name + "\n\nClick Ok to delete");
    if (r == true) {
        form.delete("/users/" + props.users.data[state.current_row].id + "?page=" + props.users.current_page + "&show=" + state.show, {
        });
    }
}

const handleTheKeypress = (e) => {
    let changeit = false;
    if (e.altKey && e.key === 'a') {        // Alt-A (Add button)
        e.preventDefault();
        router.get(disp.createurl);
    }
    else if (e.altKey && e.key === 'c') {   // Alt-C (Change button)
        e.preventDefault();
        router.get(disp.editurl);
    }
    /*    
        else if (e.code >= 'KeyA' && e.code <= 'KeyZ') {    // Between A & Z, add to search field
            e.preventDefault();
            search.value = search.value + e.key;
            searchInput.focus();
        }
    */
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
                if (props.users.current_page === 1) { } // already on 1st page, do nothing
                else {
                    router.visit(props.users.links[props.users.current_page - 1].url, { method: 'get' });
                }
                break;
            case 'PageDown':
                e.preventDefault();
                if (props.users.current_page === props.users.last_page) { }  // already on last page, do nothing
                else {
                    router.visit(props.users.links[props.users.current_page + 1].url, { method: 'get' });
                }
                break;
            case 'Escape':
                e.preventDefault();
                router.get('/adminmenu');
                break;
        } // end switch

        if (changeit === true) {
            // update_disp(props.users.data[state.current_row]);
            update_disp();
        } // end if changeit
    } // end if keycode
};

onMounted(() => {
    // console.log('here 2');
    document.addEventListener('keydown', handleTheKeypress);
});
onUnmounted(() => document.removeEventListener('keydown', handleTheKeypress));

/*
watch(search, value => {
    if (value) {
        // document.getElementById('disp_card').style.visibility= 'hidden';
        disp.display_name = '';
        disp.address = '';
        disp.email = '';
        disp.id = '';
        disp.editurl = '';
    }
    router.get('/users', { search: value }, { preserveState: true, replace: true });
    state.current_row = 0;
    update_disp();
    // document.getElementById('searchInput').focus();
    // document.getElementById('searchInput').select();
})
*/

update_disp();

</script>

<template>

    <Head title="users" />

    <AuthenticatedLayout>

        <template #header>
            <!--
            <h2 class="font-semibold text-sm text-gray-800">
                Admin > Users
            </h2>
         -->
            <div class="flex">
                <div class="w-1/3 text-sm text-gray-800">
                    <Link href="/adminmenu" class="hover:underline hover:text-blue-600">Admin</Link> > Users
                </div>
                <div class="w-1/3 font-bold text-3xl text-center text-base-content">Law Firm Users</div>
                <div class="w-1/3"></div>
            </div>
        </template>

        <div id="users_index_screen" class="py-3">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                <div class="bg-base-300 p-4 min-h-dvh sm:rounded-lg" id="UserScreen">
                    <div class="mt-6 w-3/4 mx-auto">
                        <div v-if="users.data.length" class="">
                            <table
                                class="table table-compact w-full border border-base-content text-base font-sans font-normal"
                                id="userlist">
                                <thead class="text-left text-md bg-gray-200">
                                    <tr>
                                        <th class="border-b border-r border-gray-700 text-gray-900 text-center">Name
                                        </th>
                                        <th class="border-b border-r border-gray-700 text-gray-900 text-center">Initials
                                        </th>
                                        <th class="border-b border-r border-gray-700 text-gray-900 text-center">Email
                                        </th>
                                        <th class="border-b border-r border-gray-700 text-gray-900 text-center">Firm
                                            Role</th>
                                        <th class="border-b border-r border-gray-700 text-gray-900 text-center">User
                                            Type</th>
                                        <th class="border-b border-r border-gray-700 text-gray-900 text-center">Status
                                        </th>

                                    </tr>
                                </thead>
                                <tr v-for="user, index in users.data" :key="user.id" class="border-b border-blue-900"
                                    :class="(index == state.current_row ? 'text-white bg-blue-800' : 'text-gray-900 bg-white')"
                                    @click="user_clicked(index)" @dblclick="user_dblclick(index)">
                                    <td class="px-6 py-2 whitespace-nowrap border-r border-gray-800 text-base">
                                        {{ user.name }}
                                    </td>
                                    <td class="px-6 py-2 whitespace-nowrap border-r border-gray-800 text-base">
                                        {{ user.member_initials }}
                                    </td>
                                    <td class="px-6 py-2 whitespace-nowrap border-r border-gray-800 text-base">
                                        {{ user.email }}
                                    </td>
                                    <td class="px-6 py-2 whitespace-nowrap border-r border-gray-800 text-base">
                                        {{ user.firm_role }}
                                    </td>
                                    <td class="px-6 py-2 whitespace-nowrap border-r border-gray-800 text-base">
                                        {{ user.user_type }}
                                    </td>
                                    <td class="px-6 py-2 whitespace-nowrap border-r border-gray-800 text-base">
                                        {{ user.current == 'F' ? 'Former' : 'Current' }}
                                    </td>

                                </tr>
                            </table>
                            <div class="flex justify-between items-center mt-3">
                                <div class="ml-2 flex items-center">
                                    <label class="font-bold">
                                        Display:
                                    </label>
                                    <select v-model="state.show" class="select select-sm select-bordered ml-2"
                                        id="showSelect" @change="showChanged">
                                        <option>6</option>
                                        <option>8</option>
                                        <option selected>10</option>
                                        <option>12</option>
                                        <option>15</option>
                                    </select>
                                </div>

                                <Pagination :links="users.links" class="mr-2" />
                            </div>
                        </div>
                        <div v-else class="border p-4 text-xl text-center">No Data Found!
                        </div>
                        <div name="control_buttons" class="flex mt-8 justify-around">
                            <Link :href='disp.createurl' class="btn btn-outline btn-primary gap-0">+
                                &nbsp;<u>A</u>dd
                            </Link>
                            <Link id="editbutton" name="editbutton" :href='disp.editurl'
                                class="btn btn-outline btn-primary gap-0">△
                                &nbsp;<u>C</u>hange
                            </Link>
                            <Link id="deletebutton" name="deletebutton" href='' @click="deleteClicked"
                                class="btn btn-outline btn-error">- &nbsp;Delete
                            </Link>
                            <Link id="prefsbutton" :href='disp.preferencesurl'
                                class="btn btn-outline btn-primary gap-0">⋈
                                &nbsp;<u>P</u>references
                            </Link>
                        </div>




                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
