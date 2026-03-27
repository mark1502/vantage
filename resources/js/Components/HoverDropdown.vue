<script setup>
import { computed, onMounted, onUnmounted, ref, nextTick } from 'vue';

const props = defineProps({
    align: {
        type: String,
        default: 'left',
    },
    width: {
        type: String,
        default: '56',
    },
    contentClasses: {
        type: String,
        default: 'py-1 bg-white dark:bg-gray-700',
    },
});

let closeTimeout = null;
const open = ref(false);
const triggerRef = ref(null);
const panelStyle = ref({});

const updatePosition = () => {
    if (!triggerRef.value) return;
    const rect = triggerRef.value.getBoundingClientRect();
    const style = {
        top: `${rect.bottom + 8}px`,
    };

    if (props.align === 'right') {
        style.right = `${window.innerWidth - rect.right}px`;
    } else {
        style.left = `${rect.left}px`;
    }

    panelStyle.value = style;
};

const openDropdown = () => {
    clearTimeout(closeTimeout);
    open.value = true;
    nextTick(updatePosition);
};

const scheduleClose = () => {
    closeTimeout = setTimeout(() => {
        open.value = false;
    }, 150);
};

const closeOnEscape = (e) => {
    if (open.value && e.key === 'Escape') {
        open.value = false;
    }
};

onMounted(() => document.addEventListener('keydown', closeOnEscape));
onUnmounted(() => {
    document.removeEventListener('keydown', closeOnEscape);
    clearTimeout(closeTimeout);
});

const widthClass = computed(() => {
    return {
        48: 'w-48',
        56: 'w-56',
        64: 'w-64',
    }[props.width.toString()];
});
</script>

<template>
    <div class="relative" @mouseenter="openDropdown" @mouseleave="scheduleClose">
        <div ref="triggerRef">
            <slot name="trigger" />
        </div>

        <Transition
            enter-active-class="transition ease-out duration-200"
            enter-from-class="opacity-0 scale-95"
            enter-to-class="opacity-100 scale-100"
            leave-active-class="transition ease-in duration-75"
            leave-from-class="opacity-100 scale-100"
            leave-to-class="opacity-0 scale-95"
        >
            <div
                v-show="open"
                class="fixed z-[100] rounded-md shadow-lg"
                :class="[widthClass]"
                :style="panelStyle"
            >
                <div
                    class="rounded-md ring-1 ring-black ring-opacity-5"
                    :class="contentClasses"
                >
                    <slot name="content" />
                </div>
            </div>
        </Transition>
    </div>
</template>
