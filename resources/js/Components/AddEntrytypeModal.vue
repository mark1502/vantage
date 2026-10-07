<script setup>
import { ref, computed, watch, nextTick } from 'vue';
import axios from 'axios';
import InputError from '@/Components/InputError.vue';
import { activeEntrytypes } from '@/Utils/entrytypes.js';

const props = defineProps({
    show: Boolean,
    types: { type: Array, default: () => [] },
    folderId: Number,
    label: { type: String, default: 'Type:' },
});

const emit = defineEmits(['close', 'added', 'selected']);

const dialogRef = ref(null);
const name = ref('');
const error = ref('');

const suggestions = computed(() => {                                    // up to 6 active types containing the typed text; hidden on an exact match
    const typed = name.value.trim().toLowerCase();
    if (typed === '') return [];
    const active = activeEntrytypes(props.types);
    if (active.some(t => t.name.toLowerCase() === typed)) return [];
    return active.filter(t => t.name.toLowerCase().includes(typed)).slice(0, 6);
});

watch(() => props.show, (show) => {                                     // open or close the dialog when the parent toggles show
    if (show) {
        name.value = '';
        error.value = '';
        dialogRef.value?.showModal();
        nextTick(() => document.getElementById('type_name')?.focus());
    } else {
        dialogRef.value?.close();
    }
});

function choose(id) {                                                   // pick an existing type and close
    emit('selected', id);
    emit('close');
}

function clicked_ok() {
    const typed = name.value.trim();
    if (typed === '') return;
    const existingMatch = activeEntrytypes(props.types).find(t => t.name.toLowerCase() === typed.toLowerCase());
    if (existingMatch) {
        choose(existingMatch.id);
        return;
    }
    error.value = '';
    axios.post(route('entries.add.entrytype'), { name: typed, folder_id: props.folderId })
        .then(({ data }) => {
            emit('added', data);                                        // parent inserts / un-deletes it first, so the <option> exists...
            choose(data.id);                                            // ...before the id is selected
        })
        .catch(err => {
            const errors = err.response?.data?.errors ?? {};
            error.value = errors.name?.[0] ?? errors.folder_id?.[0] ?? 'Could not add the type.';
        });
}
</script>

<template>
    <dialog ref="dialogRef" class="modal" @close="emit('close')" @keydown.esc.stop>
        <div class="modal-box w-11/12 max-w-3xl">
            <h3 class="font-bold text-2xl text-center">
                <slot name="title">Add New Type</slot>
            </h3>
            <form @submit.prevent="clicked_ok">
                <div class="flex mt-8 items-baseline">
                    <label for="type_name" class="text-xl">
                        {{ label }}
                    </label>
                    <input v-model="name" id="type_name" name="type_name"
                        class="input ml-4 w-125" autocomplete="off" />
                </div>
                <InputError class="mt-1 ml-20" :message="error" />
                <table v-if="suggestions.length" class="mt-2 ml-32 border w-80">
                    <tbody>
                        <tr v-for="type in suggestions" :key="type.id" @click="choose(type.id)">
                            <td class="pl-4 py-1 text-sm hover:bg-base-200 hover:cursor-default">
                                {{ type.name }}
                            </td>
                        </tr>
                    </tbody>
                </table>
            </form>
            <div class="modal-action justify-center mt-12">
                <button type="button" class="btn btn-primary mr-10 w-28 gap-0"
                    @click="clicked_ok()"><u>O</u>k</button>
                <button type="button" class="btn btn-primary gap-0"
                    @click="emit('close')">Cancel</button>
            </div>
        </div>
    </dialog>
</template>
