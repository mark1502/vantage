<script setup>
import GuestLayout from '@/Layouts/GuestLayout.vue';
import { Head, useForm } from '@inertiajs/vue3';
import { onMounted, ref } from 'vue';

const form = useForm({
    password: '',
});

const passwordInput = ref(null);

onMounted(() => {
    passwordInput.value.focus();
});

const submit = () => {
    form.post(route('password.confirm'), {
        onFinish: () => form.reset(),
    });
};
</script>

<template>
    <GuestLayout>
        <Head title="Confirm Password" />

        <div class="mb-4 text-sm text-gray-600">
            This is a secure area of the application. Please confirm your
            password before continuing.
        </div>

        <form @submit.prevent="submit">
            <div>
                <label for="password" class="block text-md font-medium text-gray-700">Password</label>
                <input
                    id="password"
                    type="password"
                    class="input border border-gray-300 rounded bg-white text-gray-800 outline-indigo-700 mt-1 block w-full"
                    v-model="form.password"
                    ref="passwordInput"
                    required
                    autocomplete="current-password"
                />
                <p v-show="form.errors.password" class="mt-2 text-sm text-red-600">{{ form.errors.password }}</p>
            </div>

            <div class="mt-4 flex justify-end">
                <button
                    type="submit"
                    class="ms-4 inline-flex items-center rounded-md border border-transparent bg-gray-800 px-4 py-2 text-xs font-semibold uppercase tracking-widest text-white transition duration-150 ease-in-out hover:bg-gray-700 focus:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 active:bg-gray-900"
                    :class="{ 'opacity-25': form.processing }"
                    :disabled="form.processing"
                >
                    Confirm
                </button>
            </div>
        </form>
    </GuestLayout>
</template>
