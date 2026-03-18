<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { reactive, ref, computed, watch, onMounted, onUnmounted, nextTick } from "vue";
import { Head, Link, useForm, router } from '@inertiajs/vue3';

const queryString = window.location.search;
const urlParams = new URLSearchParams(queryString);

const props = defineProps({
    preferences: Object,
    user_id: Number,
    user_initials: String,
});

const user_prefs = reactive({
    event_bg: '',
    event_text: '',
    event_bg_saved: '',
    event_text_saved: '',
    event_hover_placement: 'upper_right',
    event_hover_placement_saved: 'upper_right',
});

for (var i = 0; i < props.preferences.length; i++) {
    switch (props.preferences[i].name) {
        case 'event_bg':
            user_prefs.event_bg = props.preferences[i].setting;
            user_prefs.event_bg_saved = props.preferences[i].setting;
            break;
        case 'event_text':
            user_prefs.event_text = props.preferences[i].setting;
            user_prefs.event_text_saved = props.preferences[i].setting;
            break;
        case 'event_hover_placement':
            user_prefs.event_hover_placement = props.preferences[i].setting;
            user_prefs.event_hover_placement_saved = props.preferences[i].setting;
            break;
    } // end switch
}

// use this form for basic settings
const form_user_pref = useForm({
    user_id: props.user_id,
    name: '',
    setting: '',
});

// use this form to set event colors
const form_event_colors = useForm({
    user_id: props.user_id,
    event_bg: user_prefs.event_bg,
    event_text: user_prefs.event_text,
});

const handleTheKeypress = (e) => {
};

function updateTextColor() {
    console.log('Text change: ' + user_prefs.event_text);
}

function updateBackgroundColor() {
    console.log('BG change: ' + user_prefs.event_bg);
}

function saveEventColors() {
    form_event_colors.event_bg = user_prefs.event_bg;
    form_event_colors.event_text = user_prefs.event_text;
    form_event_colors.post('/preferences/eventcolors', { preserveState: true,
        onSuccess: () => {
        user_prefs.event_bg_saved = user_prefs.event_bg;
        user_prefs.event_text_saved = user_prefs.event_text;
        }
     });
}

function revertEventColors() {
    user_prefs.event_bg = user_prefs.event_bg_saved;
    user_prefs.event_text = user_prefs.event_text_saved;
}

function saveHoverPlacement() {
    const form = useForm({
        user_id: props.user_id,
        event_hover_placement: user_prefs.event_hover_placement,
    });
    form.post('/preferences/hover_placement', { preserveState: true,
        onSuccess: () => {
            user_prefs.event_hover_placement_saved = user_prefs.event_hover_placement;
        }
    });
}

function revertToDefaultColors() {
    form_event_colors.event_bg = '#0c6cc0';
    form_event_colors.event_text = '#ffffff';
    form_event_colors.post('/preferences/eventcolors', { preserveState: true,
        onSuccess: () => {
        user_prefs.event_bg = form_event_colors.event_bg;
        user_prefs.event_text = form_event_colors.event_text;
        user_prefs.event_bg_saved = form_event_colors.event_bg;
        user_prefs.event_text_saved = form_event_colors.event_text;
        }
     });
}

onMounted(() => document.addEventListener('keydown', handleTheKeypress));
onUnmounted(() => document.removeEventListener('keydown', handleTheKeypress));

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
                            <p class="text-lg font-bold mb-2 text-base-content">Calendar - Event Colors</p>
                            <div class="flex">
                                <label for="background-color" class="mx-2 font-semibold text-base-content">Background Color:</label>
                                <input type="color" id="background-color" v-model="user_prefs.event_bg"
                                    @change="updateBackgroundColor" />
                                <label for="text-color" class="ml-7 mr-2 font-semibold text-base-content">Text Color:</label>
                                <input type="color" id="text-color" v-model="user_prefs.event_text"
                                    @change="updateTextColor" />
                            </div>
                            <p class="mt-2 text-sm ml-2 text-base-content">Preview:</p>
                            <div class="flex">
                                <div class="ml-2 mb-3 p-4 border border-base-300 rounded w-[360px]" :style="{ backgroundColor: user_prefs.event_bg, color: user_prefs.event_text }">
                                    This is a preview of calendar event colors.
                                </div>
                                <div class="py-1 ml-6 mt-1 mb-3 flex flex-col gap-3 w-1/4">
                                    <a class="btn btn-primary btn-sm" @click="saveEventColors">Save Colors</a>
                                    <a class="btn btn-primary btn-sm" @click="revertEventColors">Revert to Last Saved</a>
                                    <a class="btn btn-primary btn-sm" @click="revertToDefaultColors">Revert to Default</a>
                                </div>
                           

                            </div>
                        </div>

                        <div class="border border-gray-600 mt-8 p-4 w-[700px] rounded-sm">
                            <p class="text-lg font-bold mb-2 text-base-content">Calendar - Event Tooltip Display</p>
                            <div class="flex items-center ">
                                <label for="hover-placement" class="ml-2 mr-3 font-semibold text-base-content">Display Event Tooltip:</label>
                                <select v-model="user_prefs.event_hover_placement" id="hover-placement"
                                    class="border border-gray-300 dark:border-gray-200/50 rounded-md p-1 bg-base-100 text-base-content">
                                    <option value="upper_right">Upper Right Corner</option>
                                    <option value="near_cursor">Near Cursor</option>
                                </select>
                                <a class="btn btn-primary btn-sm ml-12 w-1/4" @click="saveHoverPlacement">Save</a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
