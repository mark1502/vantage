<script setup>
import InputError from "@/Components/InputError.vue";
import InputLabel from "@/Components/InputLabel.vue";
import PrimaryButton from "@/Components/PrimaryButton.vue";
import TextInput from "@/Components/TextInput.vue";

// import { Head, Link, useForm } from "@inertiajs/inertia-vue3";
import { Head, Link, useForm } from '@inertiajs/vue3';
import { reactive, computed } from "vue";
import { EMPTY_ARR } from "@vue/shared";

import Pagination from '@/Components/Pagination.vue'

const state = reactive({ count: 0, addUser: 0 });

const steps = reactive([
    { id: 1, label: "Welcome", active: true },
    { id: 2, label: "Law Firm Information", active: false },
    { id: 3, label: "My User Information", active: false },
    { id: 4, label: "Additional Users", active: false },
    { id: 5, label: "Completed", active: false },
]);

function increment() {
    if (state.count < 4) {
        state.count++;
    }
    isActive();
}

function decrement() {
    if (state.count > 0) {
        state.count--;
    }
    isActive();
}

function isActive() {
    for ( let i = 0; i < 5; i++ ) {
        steps[i].active = state.count === i ? true : false;
    }
}

const props = defineProps({
    firm_name: String,
    firm_address: String,
    firm_phone: String,
    user_email: String,
    contact_title: String,
    contact_first_name: String,
    contact_middle_name: String,
    contact_last_name: String,
    contact_srjr: String,
    contact_firm_role: String,
    contact_user_type: String,
    contact_member_initials: String,
    contact_address: String,
    contact_work_phone: String,
    contact_home_phone: String,
    contact_cell_phone: String,
    contact_display_name: String,
    contact_display_last_first: String,
    // userlist: Object,
    // user2edit: Object,
});


let form = useForm({            // form for law firm info
    formtype: "firm",
    name: "",
    address: "",
    phone: "",
});

let form2 = useForm({           // form for admin user info
    formtype: "user",
    title: "",
    first_name: "",
    middle_name: "",
    last_name: "",
    srjr: "",
    member_initials: "",
    firm_role: "",
    work_phone: "",
    home_phone: "",
    cell_phone: "",
    display_name: "",
    display_last_first: "",
});

let form3 = useForm({           // form for added attorney, if needed
    formtype: "addUser",
    title: "",
    first_name: "",
    middle_name: "",
    last_name: "",
    srjr: "",
    member_initials: "",
    firm_role: "Attorney",
    user_type: "",
    email: "",
    password: "",
    password_confirmation: "",
    display_name: "",
    display_last_first: "",
    change_password: "",
});

let form4 = useForm({           // form indicating completion of welcome admin
    formtype: "doneWelcomeAdmin",
});


form.name = props.firm_name;
form.address = props.firm_address;
form.phone = props.firm_phone;

form2.title = props.contact_title;
form2.first_name = props.contact_first_name;
form2.middle_name = props.contact_middle_name;
form2.last_name = props.contact_last_name;
form2.srjr = props.contact_srjr;
form2.firm_role = props.contact_firm_role;
form2.member_initials = props.contact_member_initials;
form2.work_phone = props.contact_work_phone;
form2.home_phone = props.contact_home_phone;
form2.cell_phone = props.contact_cell_phone;
form2.display_name = props.contact_display_name;
form2.display_last_first = props.contact_display_last_first;


function submitFirm() {
    form.post("/welcomeadmin", {
        onSuccess: () => increment(),
    });
}

function submitUserInfo() {
    initialsFocus();
    buildNames_form2();
    form2.post("/welcomeadmin", {
        onSuccess: () => increment(),
    });
}

function submitAddUser() {
    initials_3_Focus();
    buildNames_form3();
    form3.post("/welcomeadmin", {
        onSuccess: () => {
            form3.reset();
            state.addUser = 0;
            state.count += 1;
        },
    });
}

function submitForm4() {
    form4.post("/donewelcomeadmin");
}

function initialsFocus() {
    if( form2.member_initials.trim().length < 2 ) {     // if initials length is less than 2, redo the initials
        let fi = form2.first_name.trim().length ? form2.first_name.charAt(0) : "";
        let mi = form2.middle_name.trim().length ? form2.middle_name.charAt(0) : "";
        let li = form2.last_name.trim().length ? form2.last_name.charAt(0) : "";
        form2.member_initials = fi + mi + li;
        form2.member_initials = form2.member_initials.toUpperCase();
    }
}

function initials_form3() {
    if( form3.member_initials.trim().length < 2 ) {     // if initials length is less than 2, redo the initials
        let fi = form3.first_name.trim().length ? form3.first_name.charAt(0) : "";
        let mi = form3.middle_name.trim().length ? form3.middle_name.charAt(0) : "";
        let li = form3.last_name.trim().length ? form3.last_name.charAt(0) : "";
        form3.member_initials = fi + mi + li;
        form3.member_initials = form3.member_initials.toUpperCase();
    }
}

function buildNames_form2() {
    if (form2.middle_name === null || form2.middle_name === undefined) { form2.middle_name = ''; }
    if (form2.srjr === null || form2.srjr === undefined) { form2.srjr = ''; }

    form2.display_name = form2.first_name.trim();
    form2.display_name += form2.middle_name.trim().length != 0 ? ' ' + form2.middle_name.trim() : "";
    form2.display_name += ' ' + form2.last_name.trim();
    form2.display_name += form2.srjr.trim().length != 0 ? ', ' + form2.srjr.trim() : "";

    form2.display_last_first = form2.last_name.trim() + ', ' + form2.first_name.trim();
    form2.display_last_first += form2.middle_name.trim().length != 0 ? ' ' + form2.middle_name.trim() : "";
    form2.display_last_first += form2.srjr.trim().length != 0 ? ', ' + form2.srjr.trim() : "";
}

function buildNames_form3() {
    if (form3.middle_name === null || form3.middle_name === undefined) { form3.middle_name = ''; }
    if (form3.srjr === null || form3.srjr === undefined) { form3.srjr = ''; }

    form3.display_name = form3.first_name.trim();
    form3.display_name += form3.middle_name.trim().length != 0 ? ' ' + form3.middle_name.trim() : "";
    form3.display_name += ' ' + form3.last_name.trim();
    form3.display_name += form3.srjr.trim().length != 0 ? ', ' + form3.srjr.trim() : "";

    form3.display_last_first = form3.last_name.trim() + ', ' + form3.first_name.trim();
    form3.display_last_first += form3.middle_name.trim().length != 0 ? ' ' + form3.middle_name.trim() : "";
    form3.display_last_first += form3.srjr.trim().length != 0 ? ', ' + form3.srjr.trim() : "";
}

function initials_3_Focus() {
    if (form3.member_initials.trim().length < 2) {
        form3.member_initials = '';
        form3.member_initials = form3.first_name.trim().length != 0 ? form3.first_name.charAt(0) : "";
        form3.member_initials += form3.middle_name.trim().length != 0 ? form3.middle_name.charAt(0) : "";
        form3.member_initials += form3.last_name.trim().length != 0 ? form3.last_name.charAt(0) : "";
        form3.member_initials = form3.member_initials.toUpperCase();
    }
}


function addUserClicked() {     // this is now just to add an attorney if necessary
    form3.reset();
    state.addUser = 1;
}

function addUser_Cancel() {     // closes the attorney form
    state.addUser = 0;
}


// function editUser(user_in) {
//     form3.reset();
//     form3.clearErrors();
//     // fill in form fields from the passed data
//     for (let i = 0; i < props.userlist.data.length; i++) {
//         if (props.userlist.data[i].email == user_in) {
//             form3.title = props.userlist.data[i].title;
//             form3.first_name = props.userlist.data[i].first_name;
//             form3.middle_name = props.userlist.data[i].middle_name;
//             form3.last_name = props.userlist.data[i].last_name;
//             form3.srjr = props.userlist.data[i].srjr;
//             form3.member_initials = props.userlist.data[i].member_initials;
//             form3.email = props.userlist.data[i].email;
//             form3.firm_role = props.userlist.data[i].firm_role;

//             form3.user_type = props.userlist.data[i].user_type;

//             form3.formtype = 'editUser';
//             form3.change_password = false;
//             break;
//         }
//     }

//     state.addUser = 1;
// }

</script>

<template>

    <Head title="Dashboard" />

    <div class="bg-neutral-600 min-h-screen">
        <header class="bg-base-100 shadow">
            <div class="max-w-7xl mx-auto py-2 px-4 sm:px-6 lg:px-8 bg-base-100">
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                    Welcome - Initial Setup Information
                </h2>
            </div>
        </header>

        <div class="py-3 min-h-screen">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 min-h-screen">
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="pt-4 pb-6">
                        <ul class="w-full steps">
                            <li v-for="({ id, label, active }, index) in steps" :key="id" class="step"
                                :class="{ 'step-primary': active }">
                                {{ label }}
                            </li>
                        </ul>
                    </div>
                    <div class="border-t-2 border-indigo-300 text-lg h-[500px]">
                        <div v-if="state.count == 0">
                            <p class="mt-4 text-3xl font-bold text-center text-blue-800">
                                Welcome!
                            </p>
                            <div class="pt-8 px-[80px] text-2xl">
                                <p class="py-1">
                                    This is the <b><span class="text-green-600">first time</span></b> you have logged
                                    in, so let's start by entering your law
                                    firm information.
                                </p>
<!-- 
                                <p class="py-4">
                                    This initial setup is done just once, but can be
                                    modified later, as needed.
                                </p>
 -->
                                 <p class="pt-10 pb-6 text-3xl">Click 'Next' to continue ...</p>
                            </div>
                        </div>

                        <!-- DIV count == 1 -->
                        <div v-if="state.count == 1">
                            <p class="mt-3 text-3xl font-bold text-center text-blue-800">
                                Law Firm Information
                            </p>
                            <form @submit.prevent="submitFirm" autocomplete="off" class="max-w-md mx-auto mt-8">
                                <p class="text-red-800 text-sm text-right">( * ) - required fields</p>

                                <label for="name" class="label_sm-700 block ml-2">
                                    <span class="red_star-700-2">*</span>Law Firm Name:
                                </label>
                                <input v-model="form.name" id="name" class="input input-bordered w-full max-w-xs" required />
                                <InputError class="mt-2" :message="form.errors.name" />

                                <label for="address" class="label_sm-700 block ml-2 mt-6">
                                    <span class="red_star-700-2">*</span>Address:
                                </label>
                                <textarea v-model="form.address" id="address" class="textarea textarea-bordered p-2 h-24 w-full" required></textarea>
                                <InputError class="mt-2" :message="form.errors.address" />

                                <label for="phone" class="label_sm-700 block ml-2 mt-6">
                                    <span class="red_star-700-2">*</span>Phone:
                                </label>
                                <input v-model="form.phone" id="phone" class="input input-bordered w-full max-w-xs mb-3" required />
                                <InputError class="mt-2" :message="form.errors.phone" />
                            </form>
                        </div>
                        <!-- END DIV 1 -->

                        <!-- DIV count == 2 -->
                        <div v-if="state.count == 2">
                            <p class="mt-3 text-3xl font-bold text-center text-blue-800">
                                My User Information
                            </p>
                            <!-- <p class="mt-2 ml-24 font-semibold">Enter your information:</p> -->
                            <!-- Form 2 starts here-->
                            <form @submit.prevent="submitUserInfo" autocomplete="off" class="max-w-5xl mx-auto mt-0">
                                <p class="text-red-800 text-sm text-right">( * ) - required fields</p>
                                <!-- Form2 - Name Line -->
                                <div class="flex mt-8">

                                    <div class="form-control w-40 max-w-xs mr-4">
                                        <label for="form2.title" class="label_sm-700">
                                            <span class="red_star-700-2">*</span>Title:
                                        </label>
                                        <select v-model="form2.title" id="form2.title" class="select select-bordered" >
                                            <option value="" disabled selected>Pick one</option>
                                            <option>Mr.</option>
                                            <option>Ms.</option>
                                            <option>Mrs.</option>
                                            <option>Miss</option>
                                            <option>Dr.</option>
                                            <option>Hon.</option>
                                        </select>
                                        <InputError class="mt-2" :message="form2.errors.title" />
                                    </div>

                                    <div class="form-control w-56 max-w-xs mr-4">
                                        <label for="form2.first_name" class="label_sm-700">
                                            <span class="red_star-700-2">*</span>First Name:
                                        </label>
                                        <input v-model="form2.first_name" id="form2.first_name" class="input input-bordered w-full max-w-xs" required />
                                        <InputError class="mt-2" :message="form2.errors.first_name" />
                                    </div>

                                    <div class="form-control w-36 max-w-xs mr-4">
                                        <label for="form2.middle_name" class="label_sm-700">
                                            Middle:
                                        </label>
                                        <input v-model="form2.middle_name" id="form2.middle_name" class="input input-bordered w-full max-w-xs" />
                                        <InputError class="mt-2" :message="form2.errors.middle_name" />
                                    </div>

                                    <div class="form-control w-56 max-w-xs mr-4">
                                        <label for="form2.last_name" class="label_sm-700">
                                            <span class="red_star-700-2">*</span>Last Name:
                                        </label>
                                        <input v-model="form2.last_name" id="form2.last_name" class="input input-bordered w-full max-w-xs" @blur="initialsFocus" required />
                                        <InputError class="mt-2" :message="form2.errors.last_name" />
                                    </div>

                                    <div class="form-control w-36 max-w-xs">
                                        <label for="form2.srjr" class="label_sm-700">
                                            Sr/Jr:
                                        </label>
                                        <input v-model="form2.srjr" id="form2.srjr" class="input input-bordered w-full max-w-xs" @focus="initialsFocus" />
                                        <InputError class="mt-2" :message="form2.errors.srjr" />
                                    </div>
                                </div>
                                <InputError class="mt-2" :message="form2.errors.display_name" />

                                <!-- Form2 - Initials Line-->
                                <div class="flex items-baseline mt-4 mr-12 justify-between">
                                    <div class="flex items-baseline pt-2">
                                        <label for="form2.member_initials" class="label_sm-700 mr-2">
                                            <span class="red_star-700-2">*</span>Initials:
                                        </label>
                                        <div>
                                            <input v-model="form2.member_initials" id="form2.member_initials" class="input input-bordered w-36 max-w-xs" @focus="initialsFocus" />
                                        </div>
                                    </div>

                                    <div class="flex items-center pr-2">
                                        <label for="form2.firm_role" class="label_sm-700 mr-3">
                                            <span class="red_star-700-2">*</span>Role In Firm:
                                        </label>
                                        <table>
                                            <tbody>
                                                <tr>
                                                    <td>
                                                        <select v-model="form2.firm_role" id="form2.firm_role" class="select select-bordered">
                                                            <option value="" disabled selected>
                                                                Pick One
                                                            </option>
                                                            <option value="Attorney">
                                                                Attorney
                                                            </option>
                                                            <option value="Paralegal">
                                                                Paralegal
                                                            </option>
                                                            <option value="Clerical">
                                                                Clerical
                                                            </option>
                                                        </select>
                                                    </td>
                                                </tr>
                                                <tr>
                                                    <td>
                                                        <InputError class="mt-2" :message="form2.errors.firm_role" />
                                                    </td>
                                                </tr>
                                            </tbody>
                                        </table>
                                    </div>

                                    <div class="mt-12 mr-6 items-baseline">
                                        <div class="flex items-baseline">
                                            <label for="form2.work_phone" class="label_sm-700 mr-4">Work:</label>
                                            <input v-model="form2.work_phone" id="form2.work_phone" class="input input-bordered w-36 max-w-xs" />
                                            <InputError class="mt-2" :message="form2.errors.work_phone" />
                                        </div>
                                        <div class="flex items-baseline mt-3">
                                            <label for="form2.home_phone" class="label_sm-700 mr-2">Home:</label>
                                            <input v-model="form2.home_phone" id="form2.home_phone" class="input input-bordered w-36 max-w-xs" />
                                            <InputError class="mt-2" :message="form2.errors.home_phone" />
                                        </div>
                                        <div class="flex items-baseline mt-3">
                                            <label for="form2.cell_phone" class="label_sm-700 mr-6.5">Cell:</label>
                                            <input v-model="form2.cell_phone" id="form2.cell_phone" class="input input-bordered w-36 max-w-xs"/>
                                            <InputError class="mt-2" :message="form2.errors.cell_phone" />
                                        </div>
                                    </div>
                                </div>
                                <InputError class="mt-2" :message="form2.errors.member_initials" />
                            </form>
                        </div>
                        <!-- END DIV 2 -->

                        <!-- DIV count == 3 -->
                        <div v-if="state.count == 3">
                            <p v-if="state.addUser != 1" class="mt-3 text-3xl font-bold text-center text-blue-800">
                                Additional Users
                            </p>
                            <p v-if="state.addUser == 1 && form3.formtype != 'editUser'"
                                class="mt-3 text-3xl font-bold text-center text-blue-800">
                                Add New Attorney
                            </p>
                            <p v-if="state.addUser == 1 && form3.formtype == 'editUser'"
                                class="mt-3 text-3xl font-bold text-center text-blue-800">
                                Edit User
                            </p>

                            <div v-if="state.addUser == 0">
                                <p class="pt-4 mx-24">
                                    Anyone in your law firm who will use this application should be added as a user. &nbsp;Administrative users are responsible for managing who can log into the application.
                                </p>
                                <p class="pt-4 mx-24">
                                    Because you are currently an <b>Administrative User</b>,
                                    you can add, edit and remove the users of the application. &nbsp;Additional users must first be added to the system
                                     by an adminstrator and assigned a temporary passwords before they can use the application.
                                </p>
                                <p class="pt-4 mx-24">
                                    The management of users, and all other administrative features, are accessible from the  
                                    <b>'Admin Menu'</b> which is found on the upper right of the main screen.
                                </p>
                                <p v-if="form2.firm_role != 'Attorney'" class="pt-4 mx-24">
                                    NOTE: Each law firm must have at least one attorney. &nbsp;Please click the button below to add an attorney for your law firm.
                                </p>
                                <button v-if="form2.firm_role != 'Attorney'" @click="addUserClicked"
                                    class="btn bg-emerald-600 hover:bg-emerald-500 mt-4 mb-4 text-center ml-48 w-40">Add Attorney
                                </button>
                            </div>

                            <div v-if="state.addUser == 1">

                                <!-- Form 3 starts here -->
                                <form v-if="state.addUser == 1" @submit.prevent="submitAddUser"
                                    class="max-w-5xl mx-auto mt-4">

                                <p v-if="state.count == 3 && state.addUser == 1" class="text-red-800 text-sm text-right">( * ) - required fields</p>
                                    <!-- Form 3 - Name Line starts-->
                                    <div class="flex mt-8">
                                        <div class="form-control w-40 max-w-xs mr-4">
                                            <label for="form3.title" class="label_sm-700">
                                                <span class="red_star-700-2">*</span>Title:
                                            </label>
                                            <select v-model="form3.title" id="form3.title" class="select select-bordered">
                                                <option value="" disabled selected>Pick one</option>
                                                <option>Mr.</option>
                                                <option>Ms.</option>
                                                <option>Mrs.</option>
                                                <option>Miss</option>
                                                <option>Dr.</option>
                                                <option>Hon.</option>
                                            </select>
                                            <InputError class="mt-2" :message="form3.errors.title" />
                                        </div>

                                        <div class="form-control w-56 max-w-xs mr-4">
                                            <label for="form3.first_name" class="label_sm-700">
                                                <span class="red_star-700-2">*</span>First Name:
                                            </label>
                                            <input v-model="form3.first_name" id="form3.first_name" class="input input-bordered w-full max-w-xs" required />
                                            <InputError class="mt-2" :message="form3.errors.first_name" />
                                        </div>

                                        <div class="form-control w-36 max-w-xs mr-4">
                                            <label for="form3.middle_name" class="label_sm-700">
                                                Middle:
                                            </label>
                                            <input v-model="form3.middle_name" id="form3.middle_name" class="input input-bordered w-full max-w-xs" />
                                            <InputError class="mt-2" :message="form3.errors.middle_name" />
                                        </div>

                                        <div class="form-control w-56 max-w-xs mr-4">
                                            <label for="form3.last_name" class="label_sm-700">
                                                <span class="red_star-700-2">*</span>Last Name:
                                            </label>
                                            <input v-model="form3.last_name" id="form3.last_name" class="input input-bordered w-full max-w-xs" required @blur="initials_form3"/>
                                            <InputError class="mt-2" :message="form3.errors.last_name" />
                                        </div>

                                        <div class="form-control w-36 max-w-xs">
                                            <label for="form3.srjr" class="label_sm-700">
                                                Sr/Jr:
                                            </label>
                                            <input v-model="form3.srjr" id="form3.srjr" class="input input-bordered w-full max-w-xs" />
                                            <InputError class="mt-2" :message="form3.errors.srjr" />
                                        </div>
                                    </div>
                                    <InputError class="mt-2" :message="form3.errors.display_name" />

                                    <!-- Form 3 Initials line starts-->
                                    <div class="flex items-baseline pt-8 mr-12 justify-between">
                                        <div class="flex items-baseline pt-2">
                                            <label for="form3.member_initials" class="label_sm-700 mr-2">
                                                <span class="red_star-700-2">*</span>Initials:
                                            </label>
                                            <div>
                                                <input v-model="form3.member_initials" id="form3.member_initials" class="input input-bordered w-36 max-w-xs" required @focus="initials_form3"/>
                                            </div>
                                        </div>

                                        <div class="flex items-center pr-2">
                                            <label for="form3.firm_role" class="label_sm-700 mr-3">
                                                <span class="red_star-700-2">*</span>Role In Firm:
                                            </label>
                                            <div>
                                                <!-- <select v-model="form3.firm_role" id="form3.firm_role" class="select select-bordered" value="Attorney">
                                                    <option value="Attorney" selected>
                                                        Attorney
                                                    </option>
                                                </select> -->
                                                <p class="text-base">Attorney</p>
                                                <!-- <input v-model="form3.firm_role" id="form3.firm_role" value="Attorney" class="input input-bordered" hidden /> -->
                                                <InputError class="mt-2" :message="form3.errors.firm_role" />
                                            </div>
                                        </div>

                                        <div class="flex items-center mr-4">
                                            <label for="form3.user_type" class="label_sm-700 mr-3">
                                                <span class="red_star-700-2">*</span>User type:
                                            </label>
                                            <div>
                                                <select v-model="form3.user_type" id="form3.user_type" class="select select-bordered w-48">
                                                    <option value="" disabled selected>
                                                        Pick One
                                                    </option>
                                                    <option value="Standard" selected>
                                                        Standard User
                                                    </option>
                                                    <option value="Admin">
                                                        Administrative User
                                                    </option>
                                                </select>
                                                <InputError class="mt-2" :message="form3.errors.user_type" />
                                            </div>
                                        </div>

                                    </div>
                                    <InputError class="mt-2" :message="form3.errors.member_initials" />

                                    <!-- Form 3 email line starts-->
                                    <div class="flex items-baseline mt-10 justify-between">
                                        <div class="flex items-baseline">
                                            <label for="form3.email" class="label_sm-700 pr-6">
                                                <span class="red_star-700-2">*</span>Email:
                                            </label>
                                            <div>
                                                <input v-model="form3.email" id="form3.email" class="input input-bordered w-80 max-w-xs"
                                                    :disabled="form3.formtype == 'editUser'" />
                                                <InputError class="mt-2" :message="form3.errors.email" />
                                            </div>
                                        </div>
                                        <div v-if="form3.formtype == 'editUser' && form3.change_password == false">
                                            <button class="btn btn-outline btn-primary normal-case mr-16"
                                                @click="form3.change_password = true">Edit User Password</button>
                                        </div>
                                        <div v-else-if="form3.formtype == 'addUser' || form3.change_password == true">
                                            <div class="flex items-baseline mr-16">
                                                <label for="form3.password" class="label_sm-700 mr-2">
                                                    <span class="red_star-700-2">*</span>Temporary Password:
                                                </label>
                                                <div>
                                                    <input v-model="form3.password" id="form3.password" class="input input-bordered w-48 max-w-xs" />
                                                    <InputError class="mt-2" :message="form3.errors.password" />
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <div v-if="form3.formtype == 'addUser' || form3.change_password == true">
                                        <div class="items-baseline mt-4 absolute right-[390px]">
                                            <div class="flex items-baseline">
                                                <label for="form3.password_confirmation" class="label_sm-700 mr-2">
                                                    <span class="red_star-700-2">*</span>Confirmation:
                                                </label>
                                                <input v-model="form3.password_confirmation" id="form3.password_confirmation" class="input input-bordered w-48 max-w-xs" />
                                            </div>
                                            <InputError class="mt-2" :message="form3.errors.password_confirmation" />
                                        </div>
                                    </div>
                                </form>
                            </div>

                        </div>
                        <!-- END DIV 3 -->


                        <!-- DIV count == 4 -->
                        <div v-if="state.count == 4">
                            <p class="mt-3 text-3xl font-bold text-center text-blue-800">
                                Setup Completed
                            </p>
                            <p class="pt-12 ml-24 text-2xl">
                                The initial setup process has been completed!
                            </p>
                            <p class="pt-12 ml-24 text-2xl">
                                Click "DONE" to proceed!
                            </p>

                        </div>

                        <!-- END DIV 4 -->

                    </div>
                    <div>
                        <!-- <p v-if="state.count == 1 || state.count == 2 || (state.count == 3 && state.addUser == 1)"
                            class="absolute top-[266px] right-[340px] text-red-800">( * ) - required fields</p> -->
                        <div class="pb-[50px] mt-2 flex justify-center">
                            <button v-if="state.addUser != 1" class="btn btn-primary mr-24" @click="decrement"
                                :disabled="state.count === 0">
                                Previous
                            </button>
                            <button v-if="state.count == 0" class="btn btn-primary" @click="increment"
                                :disabled="state.count === 3">
                                Next
                            </button>
                            <button v-if="state.count == 1" class="btn btn-primary" @click="submitFirm">
                                Next
                            </button>
                            <button v-if="state.count == 2" class="btn btn-primary" @click="submitUserInfo">
                                Next
                            </button>
                            <button v-if="state.count == 3 && state.addUser != 1" class="btn btn-primary"
                                @click="increment">
                                Next
                            </button>
                            <button v-if="state.count == 4" class="btn btn-primary btn-wide" @click="submitForm4">
                                Done
                            </button>
                            <div class="flex justify-center">
                                <button v-if="state.addUser == 1" @click="submitAddUser"
                                    class="btn bg-emerald-600 hover:bg-emerald-500 w-24 mr-16">Ok
                                </button>
                                <button v-if="state.addUser == 1" @click="addUser_Cancel"
                                    class="btn bg-emerald-600 hover:bg-emerald-500">Cancel
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>