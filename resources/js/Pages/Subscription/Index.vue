<script setup>
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout.vue";
import { Head, Link, useForm, usePage } from "@inertiajs/vue3";
import { computed, onMounted, onUnmounted } from "vue";

const props = defineProps({
    plan: Object,
    seatCount: Number,
    isSubscribed: Boolean,
    subscription: Object,
});

const page = usePage();
const flash = computed(() => page.props.flash?.message);

const form = useForm({
    interval: 'monthly',
});

const intervalLabels = {
    monthly: 'Monthly',
    quarterly: 'Quarterly',
    yearly: 'Yearly',
};

const pricePerSeat = computed(() => {
    if (!props.plan) return 0;
    const key = `base_price_${form.interval}`;
    return props.plan[key] ?? 0;
});

const totalPrice = computed(() => {
    return pricePerSeat.value * props.seatCount;
});

function formatCents(cents) {
    return (cents / 100).toFixed(2);
}

function subscribe() {
    form.post(route('subscription.checkout'));
}

function handleEsc(e) {
    if (e.key === 'Escape') {
        window.location.href = route('adminmenu');
    }
}

onMounted(() => document.addEventListener('keydown', handleEsc));
onUnmounted(() => document.removeEventListener('keydown', handleEsc));
</script>

<template>
    <Head title="Subscription" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="font-normal text-sm text-base-content leading-tight ml-2">
                <Link :href="route('adminmenu')">Admin</Link> > Subscription
            </h2>
        </template>

        <div class="py-3">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                <div class="bg-base-300 overflow-hidden sm:rounded-lg min-h-dvh">
                    <p class="mt-3 text-3xl font-bold text-center text-blue-600">
                        Subscription
                    </p>

                    <!-- Flash message -->
                    <div v-if="flash" class="max-w-2xl mx-auto mt-4">
                        <div class="alert alert-success">
                            <span>{{ flash }}</span>
                        </div>
                    </div>

                    <!-- No plan available -->
                    <div v-if="!plan" class="max-w-2xl mx-auto mt-8 text-center text-base-content">
                        <p>No subscription plan is currently available. Please contact support.</p>
                    </div>

                    <!-- Subscribed state -->
                    <div v-else-if="isSubscribed" class="max-w-2xl mx-auto mt-8 text-base-content">
                        <div class="bg-base-200 rounded-lg p-6">
                            <h3 class="text-xl font-semibold mb-4">Current Subscription</h3>

                            <div class="grid grid-cols-2 gap-4">
                                <div>
                                    <p class="text-sm text-base-content/60">Plan</p>
                                    <p class="font-medium">{{ plan.name }}</p>
                                </div>
                                <div>
                                    <p class="text-sm text-base-content/60">Status</p>
                                    <p class="font-medium capitalize">{{ subscription.status }}</p>
                                </div>
                                <div>
                                    <p class="text-sm text-base-content/60">Seats</p>
                                    <p class="font-medium">{{ subscription.quantity }}</p>
                                </div>
                                <div v-if="subscription.ends_at">
                                    <p class="text-sm text-base-content/60">
                                        {{ subscription.on_grace_period ? 'Access Until' : 'Ends At' }}
                                    </p>
                                    <p class="font-medium">{{ subscription.ends_at }}</p>
                                </div>
                            </div>

                            <div class="mt-6">
                                <a :href="route('subscription.billing-portal')" class="btn btn-primary text-white">
                                    Manage Billing
                                </a>
                            </div>
                        </div>
                    </div>

                    <!-- Not subscribed state -->
                    <div v-else class="max-w-2xl mx-auto mt-8 text-base-content">
                        <div class="bg-base-200 rounded-lg p-6">
                            <h3 class="text-xl font-semibold mb-2">{{ plan.name }}</h3>
                            <p v-if="plan.description" class="text-base-content/70 mb-6">{{ plan.description }}</p>

                            <div class="mb-6">
                                <p class="text-sm text-base-content/60 mb-2">Billing Interval</p>
                                <div class="flex gap-4">
                                    <label
                                        v-for="(label, key) in intervalLabels"
                                        :key="key"
                                        class="flex items-center gap-2 cursor-pointer"
                                    >
                                        <input
                                            type="radio"
                                            :value="key"
                                            v-model="form.interval"
                                            class="radio radio-primary"
                                            :disabled="!plan[`stripe_price_${key}`]"
                                        />
                                        <span :class="{ 'text-base-content/40': !plan[`stripe_price_${key}`] }">{{ label }}</span>
                                    </label>
                                </div>
                            </div>

                            <div class="bg-base-300 rounded p-4 mb-6">
                                <div class="flex justify-between mb-2">
                                    <span>Price per seat</span>
                                    <span>${{ formatCents(pricePerSeat) }} / {{ form.interval }}</span>
                                </div>
                                <div class="flex justify-between mb-2">
                                    <span>Active seats</span>
                                    <span>{{ seatCount }}</span>
                                </div>
                                <div class="divider my-1"></div>
                                <div class="flex justify-between font-semibold text-lg">
                                    <span>Total</span>
                                    <span>${{ formatCents(totalPrice) }} / {{ form.interval }}</span>
                                </div>
                            </div>

                            <button
                                @click="subscribe"
                                class="btn btn-primary text-white w-full"
                                :disabled="form.processing"
                            >
                                <span v-if="form.processing" class="loading loading-spinner loading-sm"></span>
                                Subscribe
                            </button>

                            <p v-if="form.errors.interval" class="text-error text-sm mt-2">{{ form.errors.interval }}</p>
                        </div>

                        <p class="text-center text-sm text-base-content/50 mt-4">
                            You are currently on the free plan (limited to 10 files).
                            Subscribe to unlock unlimited files.
                        </p>
                    </div>

                    <div class="text-center mt-8 mb-8">
                        <Link :href="route('adminmenu')" class="btn btn-primary w-32 text-white">Back</Link>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
