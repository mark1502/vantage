<script setup>
import GuestLayout from '@/Layouts/GuestLayout.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { onMounted, ref } from 'vue';

const form = useForm({
    firm_name: '',
    address: '',
    phone: '',
    name: '',
    email: '',
    password: '',
    password_confirmation: '',
    terms: false,
});

const firmNameInput = ref(null);

onMounted(() => {
    firmNameInput.value.focus();
});

const submit = () => {
    form.post(route('register'), {
        onFinish: () => form.reset('password', 'password_confirmation'),
    });
};
</script>

<template>
    <GuestLayout>
        <Head title="Register" />
        <p class="text-xl text-center text-gray-800 font-bold my-3">Law Firm Registration</p>

        <form @submit.prevent="submit">

            <div class="mx-auto border border-gray-300 rounded p-3 mb-4" style="width: 98%">
                <div>
                <label for="firm_name" class="block text-md font-medium text-gray-700">Law Firm Name:</label>
                <input id="firm_name" type="text" class="input border border-gray-300 rounded bg-white text-gray-800 outline-indigo-700 mt-1 block w-full" v-model="form.firm_name" ref="firmNameInput" autocomplete="off" required />
                <p v-show="form.errors.firm_name" class="mt-2 text-sm text-red-600">{{ form.errors.firm_name }}</p>
                </div>

                <div class="mt-4">
                <label for="name" class="block text-md font-medium text-gray-700">Your Name:</label>
                <input id="name" type="text" class="input border border-gray-300 bg-white text-gray-800 outline-indigo-700 rounded mt-1 block w-full" v-model="form.name" autocomplete="off" required />
                <p v-show="form.errors.name" class="mt-2 text-sm text-red-600">{{ form.errors.name }}</p>
                </div>
                <div class="mt-4">
                <label for="email" class="block text-md font-medium text-gray-700">Email:</label>
                <input id="email" type="email" class="input border border-gray-300 bg-white text-gray-800 outline-indigo-700 rounded mt-1 block w-full" v-model="form.email" autocomplete="off" required />
                <p v-show="form.errors.email" class="mt-2 text-sm text-red-600">{{ form.errors.email }}</p>
                </div>
                <div class="mt-4">
                <label for="password" class="block text-md font-medium text-gray-700">Password:</label>
                <input id="password" type="password" class="input border border-gray-300 rounded bg-white text-gray-800 outline-indigo-700 mt-1 block w-full" v-model="form.password" autocomplete="off" required />
                <p v-show="form.errors.password" class="mt-2 text-sm text-red-600">{{ form.errors.password }}</p>
                </div>
                <div class="mt-4">
                <label for="password_confirmation" class="block text-md font-medium text-gray-700">Confirm Password:</label>
                <input id="password_confirmation" type="password" class="input border border-gray-300 rounded bg-white text-gray-800 outline-indigo-700 mt-1 block w-full" v-model="form.password_confirmation" autocomplete="off" required />
                <p v-show="form.errors.password_confirmation" class="mt-2 text-sm text-red-600">{{ form.errors.password_confirmation }}</p>
                </div>
            </div>

            <div class="flex items-center justify-end mt-4">
                <Link :href="route('login')" class="underline text-sm text-gray-600 hover:text-gray-900">
                    Already registered?
                </Link>

                <button
                    type="submit"
                    class="ml-4 inline-flex items-center rounded-md border border-transparent bg-gray-800 px-4 py-2 text-xs font-semibold uppercase tracking-widest text-white transition duration-150 ease-in-out hover:bg-gray-700 focus:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 active:bg-gray-900"
                    :class="{ 'opacity-25': form.processing }"
                    :disabled="form.processing"
                >
                    Register
                </button>

            </div>
        </form>
    </GuestLayout>
</template>
