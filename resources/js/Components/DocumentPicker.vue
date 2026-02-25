<script setup>
import { ref, computed, watch } from 'vue';
import Modal from '@/Components/Modal.vue';

const props = defineProps({
    show: {
        type: Boolean,
        default: false,
    },
});

const emit = defineEmits(['close', 'select']);

const currentPath = ref('');
const parentPath  = ref(null);
const items       = ref([]);
const loading     = ref(false);
const errorMsg    = ref('');

// Reset and load root whenever the modal opens
watch(() => props.show, (val) => {
    if (val) {
        currentPath.value = '';
        parentPath.value  = null;
        items.value       = [];
        errorMsg.value    = '';
        fetchDirectory('');
    }
});

async function fetchDirectory(path) {
    loading.value  = true;
    errorMsg.value = '';
    try {
        const params = path !== '' ? { path } : {};
        const response = await axios.get('/firm/browse-directory', { params });
        currentPath.value = response.data.current_path;
        parentPath.value  = response.data.parent;
        items.value       = response.data.items;
    } catch (e) {
        errorMsg.value = e.response?.data?.error ?? 'Failed to load directory.';
        items.value    = [];
    } finally {
        loading.value = false;
    }
}

// Breadcrumb segments derived from the current relative path
const breadcrumbs = computed(() =>
    currentPath.value ? currentPath.value.split(/[/\\]/) : []
);

function navigateToRoot() {
    fetchDirectory('');
}

function navigateToBreadcrumb(index) {
    const path = breadcrumbs.value.slice(0, index + 1).join('/');
    fetchDirectory(path);
}

function goUp() {
    fetchDirectory(parentPath.value ?? '');
}

function openFolder(name) {
    const path = currentPath.value ? currentPath.value + '/' + name : name;
    fetchDirectory(path);
}

function selectFile(name) {
    const filePath = currentPath.value ? currentPath.value + '/' + name : name;
    emit('select', filePath);
}

function formatSize(bytes) {
    if (bytes < 1024) return bytes + ' B';
    if (bytes < 1048576) return (bytes / 1024).toFixed(1) + ' KB';
    return (bytes / 1048576).toFixed(1) + ' MB';
}
</script>

<template>
    <Modal :show="show" max-width="xl" @close="$emit('close')">
        <div class="p-5">
            <!-- Header -->
            <div class="flex items-center justify-between mb-3">
                <h3 class="text-base font-semibold">Browse for Document</h3>
                <button type="button" class="btn btn-xs btn-ghost" @click="$emit('close')">✕</button>
            </div>

            <!-- Breadcrumbs -->
            <div class="text-xs breadcrumbs mb-2">
                <ul>
                    <li>
                        <button type="button" class="link link-hover" @click="navigateToRoot">
                            Root
                        </button>
                    </li>
                    <li v-for="(part, i) in breadcrumbs" :key="i">
                        <button
                            type="button"
                            class="link link-hover"
                            :class="{ 'font-semibold pointer-events-none': i === breadcrumbs.length - 1 }"
                            @click="i < breadcrumbs.length - 1 && navigateToBreadcrumb(i)"
                        >
                            {{ part }}
                        </button>
                    </li>
                </ul>
            </div>

            <!-- Loading indicator -->
            <div v-if="loading" class="flex justify-center items-center py-10 text-sm text-base-content/50">
                <span class="loading loading-spinner loading-sm mr-2"></span> Loading…
            </div>

            <!-- Error state -->
            <div v-else-if="errorMsg" class="alert alert-error text-sm py-2">
                {{ errorMsg }}
            </div>

            <!-- Directory listing -->
            <div v-else class="border border-base-300 rounded overflow-y-auto max-h-72 text-sm">

                <!-- Up row -->
                <div
                    v-if="parentPath !== null"
                    class="flex items-center gap-2 px-3 py-2 hover:bg-base-200 cursor-pointer border-b border-base-300 select-none"
                    @click="goUp"
                >
                    <span class="opacity-60">⬆</span>
                    <span class="text-base-content/70">..</span>
                </div>

                <!-- Items -->
                <div
                    v-for="item in items"
                    :key="item.name"
                    class="flex items-center gap-2 px-3 py-2 hover:bg-base-200 cursor-pointer border-t border-base-100 select-none"
                    @click="item.type === 'directory' ? openFolder(item.name) : selectFile(item.name)"
                >
                    <span>{{ item.type === 'directory' ? '📁' : '📄' }}</span>
                    <span class="flex-1 truncate">{{ item.name }}</span>
                    <span v-if="item.type === 'file' && item.size !== null" class="text-xs text-base-content/40 shrink-0">
                        {{ formatSize(item.size) }}
                    </span>
                </div>

                <!-- Empty directory -->
                <div v-if="items.length === 0" class="px-3 py-6 text-sm text-base-content/40 text-center">
                    No files or folders found.
                </div>
            </div>

            <!-- Footer -->
            <div class="flex justify-end mt-4">
                <button type="button" class="btn btn-sm btn-ghost" @click="$emit('close')">
                    Cancel
                </button>
            </div>
        </div>
    </Modal>
</template>
