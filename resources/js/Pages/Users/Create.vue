<script setup>
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout.vue";
import InputError from "@/Components/InputError.vue";
import InputLabel from "@/Components/InputLabel.vue";
import PrimaryButton from "@/Components/PrimaryButton.vue";
import TextInput from "@/Components/TextInput.vue";

import { reactive, computed, onMounted, onUnmounted } from "vue";
import { EMPTY_ARR, isIntegerKey } from "@vue/shared";
import { Head, Link, useForm, router } from "@inertiajs/vue3";

/*
const props = defineProps({
    contact: Object,
    user: Object,
})
 */

const queryString = window.location.search;
const urlParams = new URLSearchParams(queryString);

const state = reactive({ change_email: false, change_password: false });

let form3 = useForm({
    formtype: "AddUser",
    title: "",
    first_name: "",
    middle_name: "",
    last_name: "",
    srjr: "",
    member_initials: "",
    work_phone: "",
    home_phone: "",
    cell_phone: "",
    firm_role: "",
    user_type: "",
    email: "",
    password: "",
    password_confirmation: "",
    display_name: "",
    display_last_first: "",
    current_page: urlParams.get('page'),
    show: urlParams.get('show'),
});


function submitForm3() {
    buildNames_form3();
    form3.post("/users");
}


function deleteClicked() {
    let r = confirm("Do you want to delete this contact?\n\nClick Ok to delete");
    if (r == true) {
        form.delete("/contacts/" + props.contact.id, {
        });
    }
}


function handleEsc(e) {
    if (e.key === 'Escape') {
        router.get('/users?page=' + form3.current_page + '&show=' + form3.show)
    }
}

onMounted(() => document.addEventListener('keydown', handleEsc));
onUnmounted(() => document.removeEventListener('keydown', handleEsc));

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

</script>

<template>

    <Head title="Add New User" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex">
                <div class="w-1/3 text-base text-base-content">
                    <Link href="/adminmenu" class="hover:underline hover:text-blue-600">Admin</Link> > 
                    <Link href="/users" class="hover:underline hover:text-blue-600">Users</Link> > <b>Add New User</b>
                </div>
                <div class="w-1/3 font-bold text-3xl text-center text-blue-700">Add New User</div>
                <div class="w-1/3"></div>
            </div>
        </template>

        <div class="py-3 min-h-screen">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 min-h-screen">
                <div class="bg-base-300 overflow-hidden shadow-sm sm:rounded-lg">

                    <form @submit.prevent="submitForm3" class="max-w-5xl mx-auto mt-4" autocomplete="off">

                        <!-- Name Line starts-->
                        <div class="grid grid-cols-[160px_224px_144px_224px_144px] gap-4 mt-8">
                            <div class="form-control">
                                <label class="label_sm-700">
                                    <span class="text-sm text-error align-baseline mr-1">*</span>
                                    Title:
                                </label>
                                <select v-model="form3.title" class="select select-sm select-bordered" name="form3.title"
                                    id="form3.title">
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

                            <div class="form-control">
                                <label class="label_sm-700">
                                    <span class="text-sm text-error align-baseline mr-1">*</span>
                                    First Name:
                                </label>
                                <input v-model="form3.first_name" type="text"
                                    class="input input-sm w-full" name="form3.first_name"
                                    id="form3.first_name" required />
                                <InputError class="mt-2" :message="form3.errors.first_name" />
                            </div>

                            <div class="form-control">
                                <label class="label_sm-700">
                                    Middle:
                                </label>
                                <input v-model="form3.middle_name" type="text"
                                    class="input input-sm w-full" name="form3.middle_name"
                                    id="form3.middle_name" />
                                <InputError class="mt-2" :message="form3.errors.middle_name" />
                            </div>

                            <div class="form-control">
                                <label class="label_sm-700">
                                    <span class="text-sm text-error align-baseline mr-1">*</span>
                                    Last Name:
                                </label>
                                <input v-model="form3.last_name" type="text"
                                    class="input input-sm w-full" name="form3.last_name"
                                    id="form3.last_name" required />
                                <InputError class="mt-2" :message="form3.errors.last_name" />
                            </div>

                            <div class="form-control">
                                <label class="label_sm-700">
                                    Sr/Jr:
                                </label>
                                <input v-model="form3.srjr" type="text" class="input input-sm w-full"
                                    name="form3.srjr" id="form3.srjr" />
                                <InputError class="mt-2" :message="form3.errors.srjr" />
                            </div>

                        </div>
                        <InputError class="mt-2" :message="form3.errors.display_name" />
                        <InputError class="mt-2" :message="form3.errors.display_last_first" />

                        <div class="divider"></div>

                        <!-- Member Initials, role, type line starts here-->
                        <div class="grid grid-cols-4 gap-4 mt-6">

                            <div class="form-control">
                                <label class="label_sm-700">
                                    <span class="text-sm text-error align-baseline mr-1">*</span>
                                    Member Initials:
                                </label>
                                <input v-model="form3.member_initials" 
                                    class="input input-sm w-48" @focus="initials_3_Focus"
                                    id="form3.member_initials" required />
                                <InputError class="mt-2" :message="form3.errors.member_initials" />
                            </div>

                            <div class="form-control">
                                <label class="label_sm-700">
                                    <span class="text-sm text-error align-baseline mr-1">*</span>
                                    Role In Firm:
                                </label>
                                <select v-model="form3.firm_role" class="select select-sm select-bordered w-full"
                                    name="form3.firm_role" id="form3.firm_role">
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
                                <InputError class="mt-2" :message="form3.errors.firm_role" />
                            </div>

                            <div class="form-control">
                                <label class="label_sm-700">
                                    <span class="text-sm text-error align-baseline mr-1">*</span>
                                    User type:
                                </label>
                                <select v-model="form3.user_type" class="select select-sm select-bordered"
                                    name="form3.user_type" id="form3.user_type">
                                    <option value="" disabled selected>
                                        Pick One
                                    </option>
                                    <option value="Standard" selected>
                                        Standard
                                    </option>
                                    <option value="Admin">
                                        Administrator
                                    </option>
                                </select>
                                <InputError class="mt-2" :message="form3.errors.user_type" />
                            </div>

                        </div>

                        <div class="divider"></div>

                        <div class="grid grid-cols-[1fr_auto_1fr] gap-6 mt-8 mb-6 items-start">
                            <div class="grid gap-4">
                                <div class="form-control">
                                    <label class="label_sm-700">
                                        <span class="text-sm text-error align-baseline mr-1">*</span>
                                        Email:
                                    </label>
                                    <input v-model="form3.email" class="input input-sm w-full"
                                        type="text" name="form3.email" id="form3.email" required />
                                    <InputError class="mt-2" :message="form3.errors.email" />
                                </div>
                            </div>
                            
                            <div class="divider divider-horizontal"></div>
                            
                            <div class="grid gap-4">
                                <div class="form-control">
                                    <label class="label_sm-700">
                                        <span class="text-sm text-error align-baseline mr-1">*</span>
                                        New Password:
                                    </label>
                                    <input v-model="form3.password" type="text"
                                        class="input input-sm w-full"
                                        name="form3.password" id="form3.password" required />
                                    <InputError class="mt-2" :message="form3.errors.password" />
                                </div>
                                <div class="form-control">
                                    <label class="label_sm-700">
                                        <span class="text-sm text-error align-baseline mr-1">*</span>
                                        Confirmation:
                                    </label>
                                    <input v-model="form3.password_confirmation" type="text"
                                        class="input input-sm w-full" 
                                        name="form3.password_confirmation" id="form3.password_confirmation" required />
                                    <InputError class="mt-2" :message="form3.errors.password_confirmation" />
                                </div>
                            </div>
                        </div>

                        <div class="divider"></div>

                        <!-- Phone line starts here-->
                        <div class="grid grid-cols-3 gap-8 mt-6">
                            <div class="form-control">
                                <label class="label_sm-700 ml-2">
                                    Cell Phone:
                                </label>
                                <input v-model="form3.cell_phone" class="input input-sm w-full" type="text"
                                    name="cell_phone" id="cell_phone" />
                                <InputError class="mt-2" :message="form3.errors.cell_phone" />
                            </div>

                            <div class="form-control">
                                <label class="label_sm-700 ml-2">
                                    Home Phone:
                                </label>
                                <input v-model="form3.home_phone" class="input input-sm w-full" type="text"
                                    name="home_phone" id="home_phone" />
                                <InputError class="mt-2" :message="form3.errors.home_phone" />
                            </div>

                            <div class="form-control">
                                <label class="label_sm-700 ml-2">
                                    Work Phone:
                                </label>
                                <input v-model="form3.work_phone" class="input input-sm w-full" type="text"
                                    name="work_phone" id="work_phone" />
                                <InputError class="mt-2" :message="form3.errors.work_phone" />
                            </div>
                        </div>


                        <div class="flex justify-center mt-16 mb-4">
                            <Link href="" class="btn btn-primary w-40 mr-10" @click="submitForm3">OK</Link>
                            <Link :href="('/users?page=' + form3.current_page + '&show=' + form3.show)" class="btn btn-primary mr-16">Cancel</Link>

                        </div>

                    </form>

                    <div class="h-7">

                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>