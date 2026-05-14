<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, router, usePage } from '@inertiajs/vue3';
import { computed, onMounted, ref } from 'vue';
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
    sol_summary: {
        type: Object,
        default: null,
    },
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

const solModal = ref(null);
const solModalTitle = ref('');
const solModalFiles = ref([]);

const bucketLabels = {
    next90: 'Within Next 90 Days',
    next90_unfiled: 'Within Next 90 Days (unfiled)',
    expired: 'Expired (unfiled or late)',
    unspecified: 'Unspecified S.O.L. Date',
};

const scopeLabels = {
    mine: 'Your files',
    all: 'Entire Office',
};

function formatSolDate(d) {
    if (!d) return '—';
    const [y, m, day] = d.split('-');
    return `${m}/${day}/${y}`;
}

function openSolModal(scope, bucket) {
    const row = scope === 'mine' ? props.sol_summary?.your_files : props.sol_summary?.all_files;
    if (!row) return;
    const files = row.files?.[bucket] ?? [];
    if (files.length === 0) return;
    solModalTitle.value = `${scopeLabels[scope]} — ${bucketLabels[bucket]}`;
    solModalFiles.value = files;
    solModal.value?.showModal();
}

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

                <div v-if="sol_summary" class="mt-2 overflow-hidden bg-base-100 shadow-sm sm:rounded-lg">
                    <div class="px-6 py-5">
                        <h3 class="text-lg font-semibold text-base-content">
                            Statutes of Limitations
                        </h3>
                        <table class="table mt-4 w-full border border-base-300 [&_td]:border-l [&_td]:border-base-300 [&_td:first-child]:border-l-0 [&_th]:border-l [&_th]:border-base-300 [&_th:first-child]:border-l-0">
                            <thead>
                                <tr>
                                    <th></th>
                                    <th class="text-center">Within Next 90 Days</th>
                                    <th class="text-center">Expired (unfiled or late)</th>
                                    <th class="text-center">Unspecified S.O.L. Date</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-if="sol_summary.is_attorney && sol_summary.your_files">
                                    <th>Your files</th>
                                    <td class="text-center">
                                        <button v-if="sol_summary.your_files.next90_total > 0" type="button" class="link link-primary" @click="openSolModal('mine', 'next90')">
                                            {{ sol_summary.your_files.next90_total }}
                                        </button>
                                        <span v-else>{{ sol_summary.your_files.next90_total }}</span>
                                        (<button v-if="sol_summary.your_files.next90_unfiled > 0" type="button" class="link link-primary" @click="openSolModal('mine', 'next90_unfiled')">{{ sol_summary.your_files.next90_unfiled }}</button><span v-else>{{ sol_summary.your_files.next90_unfiled }}</span> unfiled)
                                    </td>
                                    <td class="text-center">
                                        <button v-if="sol_summary.your_files.expired > 0" type="button" class="link link-primary" @click="openSolModal('mine', 'expired')">
                                            {{ sol_summary.your_files.expired }}
                                        </button>
                                        <span v-else>{{ sol_summary.your_files.expired }}</span>
                                    </td>
                                    <td class="text-center">
                                        <button v-if="sol_summary.your_files.unspecified > 0" type="button" class="link link-primary" @click="openSolModal('mine', 'unspecified')">
                                            {{ sol_summary.your_files.unspecified }}
                                        </button>
                                        <span v-else>{{ sol_summary.your_files.unspecified }}</span>
                                    </td>
                                </tr>
                                <tr>
                                    <th>Entire Office</th>
                                    <td class="text-center">
                                        <button v-if="sol_summary.all_files.next90_total > 0" type="button" class="link link-primary" @click="openSolModal('all', 'next90')">
                                            {{ sol_summary.all_files.next90_total }}
                                        </button>
                                        <span v-else>{{ sol_summary.all_files.next90_total }}</span>
                                        (<button v-if="sol_summary.all_files.next90_unfiled > 0" type="button" class="link link-primary" @click="openSolModal('all', 'next90_unfiled')">{{ sol_summary.all_files.next90_unfiled }}</button><span v-else>{{ sol_summary.all_files.next90_unfiled }}</span> unfiled)
                                    </td>
                                    <td class="text-center">
                                        <button v-if="sol_summary.all_files.expired > 0" type="button" class="link link-primary" @click="openSolModal('all', 'expired')">
                                            {{ sol_summary.all_files.expired }}
                                        </button>
                                        <span v-else>{{ sol_summary.all_files.expired }}</span>
                                    </td>
                                    <td class="text-center">
                                        <button v-if="sol_summary.all_files.unspecified > 0" type="button" class="link link-primary" @click="openSolModal('all', 'unspecified')">
                                            {{ sol_summary.all_files.unspecified }}
                                        </button>
                                        <span v-else>{{ sol_summary.all_files.unspecified }}</span>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <dialog ref="solModal" class="modal">
            <div class="modal-box max-w-2xl">
                <h3 class="text-lg font-semibold">{{ solModalTitle }}</h3>
                <div class="mt-4 max-h-96 overflow-y-auto">
                    <table class="table table-sm w-full">
                        <thead>
                            <tr>
                                <th>File Name</th>
                                <th class="w-40">S.O.L. Date</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="(file, idx) in solModalFiles" :key="idx">
                                <td>{{ file.name }}</td>
                                <td>{{ formatSolDate(file.date_sol) }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <div class="modal-action">
                    <form method="dialog">
                        <button class="btn">Close</button>
                    </form>
                </div>
            </div>
            <form method="dialog" class="modal-backdrop">
                <button>close</button>
            </form>
        </dialog>
    </AuthenticatedLayout>
</template>
