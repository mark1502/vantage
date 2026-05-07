<script setup>
import GuestLayout from '@/Layouts/GuestLayout.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { onMounted, ref } from 'vue';

defineProps({
    canResetPassword: {
        type: Boolean,
    },
    status: {
        type: String,
    },
});

const form = useForm({
    email: '',
    password: '',
    remember: false,
});

const emailInput = ref(null);

onMounted(() => {
    emailInput.value.focus();
});

const submit = () => {
    form.post(route('login'), {
        onFinish: () => form.reset('password'),
    });
};
</script>

<template>
    <GuestLayout>
        <Head title="Log in" />

        <div v-if="status" class="mb-4 font-medium text-sm text-green-600">
            {{ status }}
        </div>

        <div class="text-2xl font-bold text-center text-gray-800 bg-gray-200 border border-black rounded-lg p-2 mb-5">
            Vantage
        </div>
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
                    autocomplete="current-password"
                />

                <p v-show="form.errors.password" class="mt-2 text-sm text-red-600">{{ form.errors.password }}</p>
            </div>

            <div class="block mt-4">
                <label class="flex items-center">
                    <input
                        type="checkbox"
                        name="remember"
                        v-model="form.remember"
                        class="checkbox checkbox-sm rounded border-gray-300 text-gray-800"
                    />
                    <span class="ml-2 text-sm text-gray-600">Remember me</span>
                </label>
            </div>

            <div class="flex items-center justify-end mt-4">
                <Link
                    v-if="canResetPassword"
                    :href="route('password.request')"
                    class="underline text-sm text-gray-600 hover:text-gray-900 rounded-md focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500"
                >
                    Forgot your password?
                </Link>

                <button
                    type="submit"
                    class="ml-4 inline-flex items-center rounded-md border border-transparent bg-gray-800 px-4 py-2 text-xs font-semibold uppercase tracking-widest text-white transition duration-150 ease-in-out hover:bg-gray-700 focus:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 active:bg-gray-900"
                    :class="{ 'opacity-25': form.processing }"
                    :disabled="form.processing"
                >
                    Log in
                </button>
            </div>
        </form>
    </GuestLayout>
</template>
