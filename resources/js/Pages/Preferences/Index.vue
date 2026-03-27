<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { reactive, ref, watch } from "vue";
import { Head, useForm, router } from '@inertiajs/vue3';

const props = defineProps({
    preferences: Object,
    user_id: Number,
    user_initials: String,
});

const user_prefs = reactive({
    event_bg: '',
    event_text: '',
    event_hover_placement: 'upper_right',
    file_open_to: 'correspondence',
    file_recent_spot: false,
});

for (var i = 0; i < props.preferences.length; i++) {
    switch (props.preferences[i].name) {
        case 'event_bg':
            user_prefs.event_bg = props.preferences[i].setting;
            break;
        case 'event_text':
            user_prefs.event_text = props.preferences[i].setting;
            break;
        case 'event_hover_placement':
            user_prefs.event_hover_placement = props.preferences[i].setting;
            break;
        case 'file_open_to':
            user_prefs.file_open_to = props.preferences[i].setting;
            break;
        case 'file_recent_spot':
            user_prefs.file_recent_spot = props.preferences[i].setting === 'true';
            break;
    }
}

// Saved indicator state per section
const savedIndicator = reactive({
    colors: false,
    hover: false,
    fileOpen: false,
});

let savedTimers = {};

function showSaved(section) {
    savedIndicator[section] = true;
    clearTimeout(savedTimers[section]);
    savedTimers[section] = setTimeout(() => {
        savedIndicator[section] = false;
    }, 1500);
}

// Debounce timer for color picker
let colorDebounceTimer = null;

function saveEventColors() {
    const form = useForm({
        user_id: props.user_id,
        event_bg: user_prefs.event_bg,
        event_text: user_prefs.event_text,
    });
    form.post('/preferences/eventcolors', {
        preserveState: true,
        onSuccess: () => showSaved('colors'),
    });
}

function debouncedSaveColors() {
    clearTimeout(colorDebounceTimer);
    colorDebounceTimer = setTimeout(saveEventColors, 400);
}

function revertToDefaultColors() {
    user_prefs.event_bg = '#0c6cc0';
    user_prefs.event_text = '#ffffff';
    saveEventColors();
}

function saveHoverPlacement() {
    const form = useForm({
        user_id: props.user_id,
        event_hover_placement: user_prefs.event_hover_placement,
    });
    form.post('/preferences/hover_placement', {
        preserveState: true,
        onSuccess: () => showSaved('hover'),
    });
}

function saveFileOpen() {
    const form = useForm({
        user_id: props.user_id,
        file_open_to: user_prefs.file_open_to,
        file_recent_spot: user_prefs.file_recent_spot ? 'true' : 'false',
    });
    form.post('/preferences/file_open', {
        preserveState: true,
        onSuccess: () => showSaved('fileOpen'),
    });
}

// Auto-save watchers
watch(() => user_prefs.event_bg, debouncedSaveColors);
watch(() => user_prefs.event_text, debouncedSaveColors);
watch(() => user_prefs.event_hover_placement, saveHoverPlacement);
watch(() => user_prefs.file_open_to, saveFileOpen);
watch(() => user_prefs.file_recent_spot, saveFileOpen);

</script>

<template>
    <Head title="User Preferences" />

    <AuthenticatedLayout>

        <template #header>
            <h2 class="font-semibold text-xl text-base-content">
                User Preferences
            </h2>
        </template>

        <div class="py-3">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                <div class="bg-base-100 overflow-hidden sm:rounded-lg" id="ContactScreen" name="ContactScreen">
                    <div class="p-6 bg-base-300 border-b border-base-300 min-h-[680px] justify-center">
                        <p class="text-lg font-semibold mb-3 text-base-content">Preferences for User: "{{ props.user_initials }}"</p>
                        <div class="border border-gray-600 rounded-sm mt-5 p-4 w-[700px]">
                            <div class="flex items-center justify-between mb-2">
                                <p class="text-lg font-bold text-base-content">Calendar - Event Colors</p>
                                <span v-if="savedIndicator.colors" class="text-sm text-success transition-opacity">Saved</span>
                            </div>
                            <div class="flex items-center">
                                <label for="background-color" class="mx-2 font-semibold text-base-content">Background Color:</label>
                                <input type="color" id="background-color" v-model="user_prefs.event_bg" />
                                <label for="text-color" class="ml-7 mr-2 font-semibold text-base-content">Text Color:</label>
                                <input type="color" id="text-color" v-model="user_prefs.event_text" />
                            </div>
                            <p class="mt-2 text-sm ml-2 text-base-content">Preview:</p>
                            <div class="flex items-end justify-between">
                                <div class="ml-2 mb-3 p-4 border border-base-300 rounded w-[360px]" :style="{ backgroundColor: user_prefs.event_bg, color: user_prefs.event_text }">
                                    This is a preview of calendar event colors.
                                </div>
                                <a class="text-sm mb-3 mr-2 link link-primary cursor-pointer" @click="revertToDefaultColors">Revert to Default</a>
                            </div>
                        </div>

                        <div class="border border-gray-600 mt-8 p-4 w-[700px] rounded-sm">
                            <div class="flex items-center justify-between mb-2">
                                <p class="text-lg font-bold text-base-content">Calendar - Event Tooltip Display</p>
                                <span v-if="savedIndicator.hover" class="text-sm text-success transition-opacity">Saved</span>
                            </div>
                            <div class="flex items-center">
                                <label for="hover-placement" class="ml-2 mr-3 font-semibold text-base-content">Display Event Tooltip:</label>
                                <select v-model="user_prefs.event_hover_placement" id="hover-placement"
                                    class="border border-gray-300 dark:border-gray-200/50 rounded-md p-1 bg-base-100 text-base-content">
                                    <option value="upper_right">Upper Right Corner</option>
                                    <option value="near_cursor">Near Cursor</option>
                                </select>
                            </div>
                        </div>

                        <div class="border border-gray-600 mt-8 p-4 w-[700px] rounded-sm">
                            <div class="flex items-center justify-between mb-2">
                                <p class="text-lg font-bold text-base-content">File - Default Open Location</p>
                                <span v-if="savedIndicator.fileOpen" class="text-sm text-success transition-opacity">Saved</span>
                            </div>
                            <div class="flex items-center mb-4">
                                <label for="file-open-to" class="ml-2 mr-3 font-semibold text-base-content">Open Files at:</label>
                                <select v-model="user_prefs.file_open_to" id="file-open-to"
                                    class="border border-gray-300 dark:border-gray-200/50 rounded-md p-1 bg-base-100 text-base-content">
                                    <option value="correspondence">Correspondence</option>
                                    <option value="pleadings">Pleadings and Motions</option>
                                    <option value="discovery">Discovery</option>
                                    <option value="documents">Documents</option>
                                    <option value="memos">Memos</option>
                                    <option value="events">Events</option>
                                    <option value="todo">To-Do</option>
                                    <option value="phone">Phone Calls</option>
                                    <option value="medrecs">Medical Records</option>
                                    <option value="medbills">Medical Bills</option>
                                    <option value="costs">Case Costs</option>
                                    <option value="all">File Timeline</option>
                                    <option value="info">File Details</option>
                                </select>
                            </div>
                            <div class="flex items-center ml-2">
                                <input type="checkbox" id="file-recent-spot" v-model="user_prefs.file_recent_spot"
                                    class="checkbox checkbox-sm mr-2" />
                                <label for="file-recent-spot" class="font-semibold text-base-content">Open recent files where I left off</label>
                            </div>
                            <p class="text-sm ml-8 mt-1 text-base-content opacity-70">(overrides above setting when opening from the recent files menu)</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
