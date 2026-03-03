<script setup>
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout.vue";
import InputError from "@/Components/InputError.vue";
import InputLabel from "@/Components/InputLabel.vue";
import PrimaryButton from "@/Components/PrimaryButton.vue";
import TextInput from "@/Components/TextInput.vue";

import { reactive, computed, onMounted, onUnmounted } from "vue";
import { EMPTY_ARR, isIntegerKey } from "@vue/shared";
import { Head, Link, useForm, router } from "@inertiajs/vue3";

const props = defineProps({
    contact: Object,
    user: Object,
})
const queryString = window.location.search;
const urlParams = new URLSearchParams(queryString);

const state = reactive({ change_email: false, change_password: false });

let form3 = useForm({
    formtype: "editAUser",
    title: props.contact.title,
    first_name: props.contact.first_name,
    middle_name: props.contact.middle_name,
    last_name: props.contact.last_name,
    srjr: props.contact.srjr,
    member_initials: props.contact.member_initials,
    work_phone: props.contact.work_phone,
    home_phone: props.contact.home_phone,
    cell_phone: props.contact.cell_phone,
    firm_role: props.contact.firm_role,
    user_type: props.user.user_type,
    account_status: props.contact.account_status,
    email: props.user.email,
    password: "",
    password_confirmation: "",
    display_name: "",
    display_last_first: "",
    change_password: false,
    change_email: false,
    current_page: urlParams.get('page'),
    show: urlParams.get('show'),
});


function submitForm3() {
    buildNames_form3();
    form3.put("/users/" + props.user.id);
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


onMounted(() => document.addEventListener('keydown', handleEsc));
onUnmounted(() => document.removeEventListener('keydown', handleEsc));

</script>

<template>

    <Head title="Edit User" />

    <AuthenticatedLayout>
        <template #header>
            <!-- <h2 class="font-semibold text-xl text-base-content leading-tight ml-2">
                Admin >
                Users > Edit User
            </h2> -->
            <div class="flex">
                <div class="w-1/3 text-base text-base-content">
                    <Link href="/adminmenu" class="hover:underline hover:text-blue-600">Admin</Link> > 
                    <Link href="/users" class="hover:underline hover:text-blue-600">Users</Link> > <b>Edit User</b>
                </div>
                <div class="w-1/3 font-bold text-3xl text-center text-blue-700">Edit User</div>
                <div class="w-1/3"></div>
            </div>

        </template>

        <div class="py-3 min-h-screen">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 min-h-screen">
                <div class="bg-base-300 overflow-hidden shadow-sm sm:rounded-lg">

                    <form @submit.prevent="submitForm3" class="max-w-5xl mx-auto mt-4">

                        <!-- Name Line starts-->
                        <div class="grid grid-cols-[160px_224px_144px_224px_144px] gap-4 mt-8">
                            <div class="form-control">
                                <label class="label_sm-700">
                                    <span class="text-sm text-red-600 align-baseline mr-1">*</span>
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
                                    <span class="text-sm text-red-600 align-baseline mr-1">*</span>
                                    First Name:
                                </label>
                                <input v-model="form3.first_name" type="text"
                                    class="input input-sm input-bordered w-full" name="form3.first_name"
                                    id="form3.first_name" required />
                                <InputError class="mt-2" :message="form3.errors.first_name" />
                            </div>

                            <div class="form-control">
                                <label class="label_sm-700">
                                    Middle:
                                </label>
                                <input v-model="form3.middle_name" type="text"
                                    class="input input-sm input-bordered w-full" name="form3.middle_name"
                                    id="form3.middle_name" />
                                <InputError class="mt-2" :message="form3.errors.middle_name" />
                            </div>

                            <div class="form-control">
                                <label class="label_sm-700">
                                    <span class="text-sm text-red-600 align-baseline mr-1">*</span>
                                    Last Name:
                                </label>
                                <input v-model="form3.last_name" type="text"
                                    class="input input-sm input-bordered w-full" name="form3.last_name"
                                    id="form3.last_name" required />
                                <InputError class="mt-2" :message="form3.errors.last_name" />
                            </div>

                            <div class="form-control">
                                <label class="label_sm-700">
                                    Sr/Jr:
                                </label>
                                <input v-model="form3.srjr" type="text" class="input input-sm input-bordered w-full"
                                    name="form3.srjr" id="form3.srjr" />
                                <InputError class="mt-2" :message="form3.errors.srjr" />
                            </div>

                        </div>

                        <div class="divider"></div>

                        <!-- Member Initials, role, type, status line starts here-->
                        <div class="grid grid-cols-4 gap-4 mt-6">

                            <div class="form-control">
                                <label class="label_sm-700">
                                    <span class="text-sm text-red-600 align-baseline mr-1">*</span>
                                    Member Initials:
                                </label>
                                <input v-model="form3.member_initials" id="form3.member_initials" class="input input-sm input-bordered w-48" required />
                            </div>

                            <div class="form-control">
                                <label class="label_sm-700">
                                    <span class="text-sm text-red-600 align-baseline mr-1">*</span>
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
                                    <span class="text-sm text-red-600 align-baseline mr-1">*</span>
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

                            <div class="form-control">
                                <label class="label_sm-700">
                                    <span class="text-sm text-red-600 align-baseline mr-1">*</span>
                                    Account Status:
                                </label>
                                <select v-model="form3.account_status" class="select select-sm select-bordered w-48">
                                    <option value="A">Active</option>
                                    <option value="I">Inactive</option>
                                </select>
                                <InputError class="mt-2" :message="form3.errors.account_status" />
                            </div>

                        </div>
                        <InputError class="mt-2" :message="form3.errors.member_initials" />

                        <div class="divider"></div>

                        <div class="grid grid-cols-[1fr_auto_1fr] gap-6 mt-8 mb-6 items-start">
                            <div class="grid gap-4">
                                <div>
                                    <label for="change_email">
                                        <input type="checkbox" id="change_email" name="change_email"
                                            v-model="form3.change_email" value=true class="rounded">
                                        <span class="ml-4">Enable email change</span>
                                    </label>
                                </div>
                                <div class="form-control">
                                    <label class="label_sm-700">
                                        Email:
                                    </label>
                                    <input v-model="form3.email" class="input input-sm input-bordered w-full disabled:bg-base-200 disabled:opacity-70"
                                        type="text" :disabled="form3.change_email == false" name="form3.email"
                                        id="form3.email" />
                                    <InputError class="mt-2" :message="form3.errors.email" />
                                    <div v-if="form3.change_email == true">
                                        <p class="mt-2 text-sm">Note: This email address is used to login to the application.
                                            Be sure the user can receive email at this address so they can verify their login, if necessary.
                                        </p>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="divider divider-horizontal"></div>
                            
                            <div class="grid gap-4">
                                <div>
                                    <label for="change_password">
                                        <input type="checkbox" id="change_password" name="change_password"
                                            v-model="form3.change_password" value=true class="rounded">
                                        <span class="ml-4">Enable password change</span>
                                    </label>
                                </div>
                                <div class="form-control">
                                    <label class="label_sm-700">New Password:</label>
                                    <input v-model="form3.password" type="text"
                                        class="input input-sm input-bordered w-full disabled:bg-base-200 disabled:opacity-70"
                                        :disabled="form3.change_password == false" name="form3.password"
                                        id="form3.password" />
                                    <InputError class="mt-2" :message="form3.errors.password" />
                                </div>
                                <div class="form-control">
                                    <label class="label_sm-700">
                                        Confirmation:
                                    </label>
                                    <input v-model="form3.password_confirmation" type="text"
                                        class="input input-sm input-bordered w-full disabled:bg-base-200 disabled:opacity-70" 
                                        :disabled="form3.change_password == false" name="form3.password_confirmation"
                                        id="form3.password_confirmation" />
                                    <InputError class="mt-2" :message="form3.errors.password_confirmation" />
                                </div>
                            </div>
                        </div>

                        <div class="divider"></div>

                        <!-- Phone line starts here-->
                        <div class="grid grid-cols-3 gap-4 mt-6">
                            <div class="form-control">
                                <label class="label_sm-700 ml-2">
                                    Cell Phone:
                                </label>
                                <input v-model="form3.cell_phone" class="input input-sm input-bordered w-full" type="text"
                                    name="cell_phone" id="cell_phone" />
                                <InputError class="mt-2" :message="form3.errors.cell_phone" />
                            </div>

                            <div class="form-control">
                                <label class="label_sm-700 ml-2">
                                    Home Phone:
                                </label>
                                <input v-model="form3.home_phone" class="input input-sm input-bordered w-full" type="text"
                                    name="home_phone" id="home_phone" />
                                <InputError class="mt-2" :message="form3.errors.home_phone" />
                            </div>

                            <div class="form-control">
                                <label class="label_sm-700 ml-2">
                                    Work Phone:
                                </label>
                                <input v-model="form3.work_phone" class="input input-sm input-bordered w-full" type="text"
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