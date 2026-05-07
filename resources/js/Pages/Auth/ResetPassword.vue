<script setup>
import GuestLayout from '@/Layouts/GuestLayout.vue';
import { Head, useForm } from '@inertiajs/vue3';
import { onMounted, ref } from 'vue';

const props = defineProps({
    email: {
        type: String,
        required: true,
    },
    token: {
        type: String,
        required: true,
    },
});

const form = useForm({
    token: props.token,
    email: props.email,
    password: '',
    password_confirmation: '',
});

const emailInput = ref(null);

onMounted(() => {
    emailInput.value.focus();
});

const submit = () => {
    form.post(route('password.store'), {
        onFinish: () => form.reset('password', 'password_confirmation'),
    });
};
</script>

<template>
    <GuestLayout>
        <Head title="Reset Password" />

        <form @submit.prevent="submit">
            <div>
                <label for="email" class="block text-md font-medium text-gray-700">Email</label>

                <input
                    id="email"
                    type="email"
                    class="input border border-gray-300 rounded bg-white text-gray-800 outline-indigo-700 mt-1 block w-full"
                    v-model="form.email"
                    ref="emailInput"
                    required
                    autocomplete="username"
                />

                <p v-show="form.errors.email" class="mt-2 text-sm text-red-600">{{ form.errors.email }}</p>
            </div>

            <div class="mt-4">
                <label for="password" class="block text-md font-medium text-gray-700">Password</label>

                <input
                    id="password"
                    type="password"
                    class="input border border-gray-300 rounded bg-white text-gray-800 outline-indigo-700 mt-1 block w-full"
                    v-model="form.password"
                    required
                    autocomplete="new-password"
                />

                <p v-show="form.errors.password" class="mt-2 text-sm text-red-600">{{ form.errors.password }}</p>
            </div>

            <div class="mt-4">
                <label for="password_confirmation" class="block text-md font-medium text-gray-700">Confirm Password</label>

                <input
                    id="password_confirmation"
                    type="password"
                    class="input border border-gray-300 rounded bg-white text-gray-800 outline-indigo-700 mt-1 block w-full"
                    v-model="form.password_confirmation"
                    required
                    autocomplete="new-password"
                />

                <p v-show="form.errors.password_confirmation" class="mt-2 text-sm text-red-600">{{ form.errors.password_confirmation }}</p>
            </div>

            <div class="mt-4 flex items-center justify-end">
                <button
                    type="submit"
                    class="inline-flex items-center rounded-md border border-transparent bg-gray-800 px-4 py-2 text-xs font-semibold uppercase tracking-widest text-white transition duration-150 ease-in-out hover:bg-gray-700 focus:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 active:bg-gray-900"
                    :class="{ 'opacity-25': form.processing }"
                    :disabled="form.processing"
                >
                    Reset Password
                </button>
            </div>
        </form>
    </GuestLayout>
</template>
