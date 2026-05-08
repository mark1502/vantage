<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, router, usePage } from '@inertiajs/vue3';
import { computed, onMounted } from 'vue';
import {
    CalendarDaysIcon,
    ClockIcon,
    ClipboardDocumentListIcon,
    PhoneIcon,
    EnvelopeIcon,
} from '@heroicons/vue/24/outline';
import { useTheme } from '@/Composables/useTheme';

const props = defineProps({
    msg_events: String,
    msg_dueFrom: String,
    msg_dueTo: String,
    msg_todo: String,
    msg_phone: String,
    msg_memo: String,
    member_initials: String,
    user_contact_id: Number,
    theme_preference: String,
});

const eventsDisabled = computed(() =>
    props.msg_events?.startsWith('You have no')
);

const dueDisabled = computed(() =>
    props.msg_dueFrom?.startsWith('There are no') && props.msg_dueTo?.startsWith('You are not')
);

const todoDisabled = computed(() =>
    props.msg_todo?.startsWith('You have no')
);

const phoneDisabled = computed(() =>
    props.msg_phone?.startsWith('You have 0')
);

const memosDisabled = computed(() =>
    props.msg_memo?.startsWith('You have no')
);

function viewEvents() {
    const today = new Date().toISOString().slice(0, 10);
    router.get(route('calendar.index'), {
        user: props.user_contact_id,
    });
}

function viewDue() {
    router.get(route('views.index'), {
        view: 'due',
        view_for: props.member_initials,
        from_to: 'both',
    });
}

function viewTodo() {
    router.get(route('views.index'), {
        view: 'todo',
        view_for: props.member_initials,
        read: 'unread',
    });
}

function viewPhone() {
    router.get(route('views.index'), {
        view: 'phone',
        view_for: props.member_initials,
        read: 'unread',
    });
}

function viewMemos() {
    router.get(route('views.index'), {
        view: 'memos',
        view_for: props.member_initials,
        from_to: 'to',
        read: 'unread',
    });
}

const { setTheme } = useTheme();

onMounted(() => {
    if (props.theme_preference) {
        setTheme(props.theme_preference);
    }
});

const sections = [
    { label: 'Today', icon: CalendarDaysIcon, messages: ['msg_events'] },
    { label: 'Due', icon: ClockIcon, messages: ['msg_dueFrom', 'msg_dueTo'] },
    { label: 'To-Do', icon: ClipboardDocumentListIcon, messages: ['msg_todo'] },
    { label: 'Phone', icon: PhoneIcon, messages: ['msg_phone'] },
    { label: 'Memos', icon: EnvelopeIcon, messages: ['msg_memo'] },
];
</script>

<template>
    <Head title="Dashboard" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-base-content">
                Vantage Summary
            </h2>
        </template>

        <div class="py-12">
            <div class="mx-auto max-w-7xl sm:px-6 lg:px-8">
                <div class="overflow-hidden bg-base-100 shadow-sm sm:rounded-lg">
                    <div class="divide-y divide-base-300">
                        <div v-for="section in sections" :key="section.label" class="flex items-center px-6 py-5" >
                            <div class="flex w-2/5 items-start gap-4">
                                <component :is="section.icon" class="mt-0.5 h-6 w-6 shrink-0 text-primary" />
                                <div>
                                    <h3 class="font-semibold text-base-content">{{ section.label }}</h3>
                                    <p v-for="key in section.messages" :key="key" class="mt-1 text-sm text-base-content/70" >
                                        {{ props[key] }}
                                    </p>
                                </div>
                            </div>
                            <div class="w-3/5">
                                <button
                                    v-if="section.label === 'Today'"
                                    class="btn btn-primary btn-sm w-48"
                                    :disabled="eventsDisabled"
                                    @click="viewEvents"
                                >
                                    View My Events Today
                                </button>
                                <button
                                    v-if="section.label === 'Due'"
                                    class="btn btn-primary btn-sm w-48"
                                    :disabled="dueDisabled"
                                    @click="viewDue"
                                >
                                    View What's Due
                                </button>
                                <button
                                    v-if="section.label === 'To-Do'"
                                    class="btn btn-primary btn-sm w-48"
                                    :disabled="todoDisabled"
                                    @click="viewTodo"
                                >
                                    View Pending To-Do
                                </button>
                                <button
                                    v-if="section.label === 'Phone'"
                                    class="btn btn-primary btn-sm w-48"
                                    :disabled="phoneDisabled"
                                    @click="viewPhone"
                                >
                                    View Phone Messages
                                </button>
                                <button
                                    v-if="section.label === 'Memos'"
                                    class="btn btn-primary btn-sm w-48"
                                    :disabled="memosDisabled"
                                    @click="viewMemos"
                                >
                                    View Unread Memos
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
