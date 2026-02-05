<script setup>
import GuestLayout from '@/Layouts/GuestLayout.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';

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

const submit = () => {
    form.post(route('register'), {
        onFinish: () => form.reset('password', 'password_confirmation'),
    });
};
</script>

<template>
    <GuestLayout>
        <Head title="Register" />
        <p class="text-xl text-center my-3">Law Firm Registration</p>

        <form @submit.prevent="submit">

            <div class="mx-auto border border-primary rounded p-3 mb-4" style="width: 98%">
                <div>
                <InputLabel for="firm_name" value="Law Firm Name:" />
                <TextInput id="firm_name" type="text" class="form-input mt-1 block w-full" v-model="form.firm_name" autocomplete="off" required autofocus/>
                <InputError class="mt-2" :message="form.errors.firm_name" />
                </div>
                <!--
                <div class="mt-4">
                <InputLabel for="phone" value="Phone:" />
                <TextInput id="phone" type="text" class="mt-1 block w-full" v-model="form.phone" required/>
                <InputError class="mt-2" :message="form.errors.phone" />
                </div>
                -->
                <div class="mt-4">
                <InputLabel for="name" value="Your Name:" />
                <TextInput id="name" type="text" class="form-input mt-1 block w-full" v-model="form.name" autocomplete="off" required/>
                <InputError class="mt-2" :message="form.errors.name" />
                </div>
                <div class="mt-4">
                <InputLabel for="email" value="Email:" />
                <TextInput id="email" type="email" class="form-input mt-1 block w-full" v-model="form.email" autocomplete="off" required/>
                <InputError class="mt-2" :message="form.errors.email" />
                </div>
                <div class="mt-4">
                <InputLabel for="password" value="Password:" />
                <TextInput id="password" type="password" class="form-input mt-1 block w-full" v-model="form.password" autocomplete="off" required/>
                <InputError class="mt-2" :message="form.errors.password" />
                </div>
                <div class="mt-4">
                <InputLabel for="password_confirmation" value="Confirm Password:" />
                <TextInput id="password_confirmation" type="password" class="form-input mt-1 block w-full" v-model="form.password_confirmation" autocomplete="off" required/>
                <InputError class="mt-2" :message="form.errors.password_confirmation" />
                </div>
            </div>

            <div class="flex items-center justify-end mt-4">
                <Link :href="route('login')" class="underline text-sm text-gray-600 hover:text-gray-900">
                    Already registered?
                </Link>

                <PrimaryButton class="ml-4" :class="{ 'opacity-25': form.processing }" :disabled="form.processing">
                    Register
                </PrimaryButton>

            </div>
        </form>
    </GuestLayout>
</template>
