<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, usePage } from '@inertiajs/vue3';
import { onMounted } from 'vue';
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
    theme_preference: String,
});

const { initTheme } = useTheme();

onMounted(() => {
    if (props.theme_preference) {
        initTheme(props.theme_preference);
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
                        <div v-for="section in sections" :key="section.label" class="flex items-start gap-4 px-6 py-5" >
                            <component :is="section.icon" class="mt-0.5 h-6 w-6 shrink-0 text-primary" />
                            <div>
                                <h3 class="font-semibold text-base-content">{{ section.label }}</h3>
                                <p v-for="key in section.messages" :key="key" class="mt-1 text-sm text-base-content/70" >
                                    {{ props[key] }}
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
