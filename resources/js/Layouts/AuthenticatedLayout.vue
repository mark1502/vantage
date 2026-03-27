<script setup>
import { ref, computed, onMounted, onUnmounted, provide } from 'vue';
import ApplicationLogo from '@/Components/ApplicationLogo.vue';
import Dropdown from '@/Components/Dropdown.vue';
import DropdownLink from '@/Components/DropdownLink.vue';
import HoverDropdown from '@/Components/HoverDropdown.vue';
import NavLink from '@/Components/NavLink.vue';
import ResponsiveNavLink from '@/Components/ResponsiveNavLink.vue';
import { Link, usePage, router } from '@inertiajs/vue3';

import { useTheme } from '@/Composables/useTheme'

const { theme, setTheme, initTheme } = useTheme()

// Initialize theme when the layout component mounts
onMounted(() => {
    initTheme()
})

// Provide the theme and setTheme function to all child components
// Use a Symbol for the key to prevent name collisions
// You can also just provide 'theme' if you don't need to change it from children
provide('currentTheme', theme);
provide('setThemeFunction', setTheme);

const toggleTheme = () => {
    setTheme(theme.value === 'light' ? 'dark' : 'light')
}

const showingNavigationDropdown = ref(false);

// Compute the current icon based on theme
const currentIcon = computed(() => {
    return theme.value === 'light' ? '/images/dark_mode3_16.png' : '/images/light_mode_16.png'
})

// Compute alt text for accessibility
const iconAlt = computed(() => {
    return `Switch to ${theme.value === 'light' ? 'dark' : 'light'} mode`
})

const tooltipText = computed(() => {
    return `Switch to ${theme.value === 'light' ? 'dark' : 'light'} mode`
})

const user = usePage().props.auth.user;
let isAdmin = ref(false);

isAdmin = user.user_type == 'Admin' ? true : false;

const recentFiles = ref([]);

const fetchRecentFiles = async () => {
    try {
        const response = await fetch(route('recent-files.index'));
        if (response.ok) {
            recentFiles.value = await response.json();
        }
    } catch (e) {
        // silently fail — dropdown just won't show recent files
    }
};

onMounted(() => {
    fetchRecentFiles();
});

const fileNavActive = computed(() => {
    return route().current('files.index') || route().current('entries.index');
});

const fileNavClasses = computed(() =>
    fileNavActive.value
        ? 'inline-flex items-center px-1 pt-1 border-b-2 border-indigo-400 dark:border-b-3 dark:border-blue-400 text-sm font-medium leading-5 text-gray-900 focus:outline-none focus:border-indigo-700 transition duration-150 ease-in-out'
        : 'inline-flex items-center px-1 pt-1 border-b-2 border-transparent text-sm font-medium leading-5 text-gray-500 hover:text-gray-700 hover:border-gray-300 focus:outline-none focus:text-gray-700 focus:border-gray-300 transition duration-150 ease-in-out',
);

// Refresh recent files list after navigating to a file's entries
const removeNavigateListener = router.on('navigate', (event) => {
    if (event.detail.page.url.includes('/entries')) {
        fetchRecentFiles();
    }
});

onUnmounted(() => {
    removeNavigateListener();
});

</script>

<template>
    <div>
        <div class="bg-neutral-600">
            <nav class="bg-gray-50 border-b border-gray-100 relative z-[100]">
                <!-- Primary Navigation Menu -->
                <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                    <div class="flex justify-between h-16">
                        <div class="flex">
                            <!-- Logo -->
                            <div class="shrink-0 flex items-center">
                                <Link :href="route('dashboard')">
                                <ApplicationLogo class="block h-8" />
                                </Link>
                            </div>

                            <!-- Navigation Links -->
                            <div class="hidden space-x-8 sm:-my-px sm:ml-10 sm:flex">
                                <HoverDropdown align="left" width="56" class="ml-3 flex items-center">
                                    <template #trigger>
                                        <span :class="fileNavClasses" class="cursor-pointer">
                                            <img class="block h-4" src="/images/File_icon_1.png">
                                            <span class="ml-2">File</span>
                                        </span>
                                    </template>

                                    <template #content>
                                        <Link :href="route('files.index')"
                                            class="block w-full px-4 py-2 text-start text-sm font-semibold leading-5 text-gray-700 transition duration-150 ease-in-out hover:bg-gray-100 focus:bg-gray-100 focus:outline-none dark:text-gray-300 dark:hover:bg-gray-800 dark:focus:bg-gray-800">
                                            Show File List
                                        </Link>
                                        <div v-if="recentFiles.length > 0">
                                            <div class="border-t border-gray-200 dark:border-gray-600 px-4 py-1 text-xs text-gray-400 dark:text-gray-500 uppercase tracking-wider">
                                                Recently used files
                                            </div>
                                            <Link v-for="file in recentFiles" :key="file.id"
                                                :href="route('entries.index', { file: file.id, filepart: file.filepart, page: file.page, show: file.show })"
                                                class="block w-full px-4 py-2 text-start text-sm leading-5 text-gray-700 transition duration-150 ease-in-out hover:bg-gray-100 focus:bg-gray-100 focus:outline-none dark:text-gray-300 dark:hover:bg-gray-800 dark:focus:bg-gray-800">
                                                {{ file.name }}
                                            </Link>
                                        </div>
                                    </template>
                                </HoverDropdown>

                                <NavLink :href="route('views.index', { view: 'memos' })" :active="route().current('views.index')" class="ml-3">
                                    <img class="block h-4" src="/images/Office_3.png">
                                    <span class="ml-2">Office</span>
                                </NavLink>

                                <NavLink :href="route('calendar.index')" :active="route().current('calendar.index')" class="ml-3">
                                    <img class="block h-4" src="/images/Calendar_2.png">
                                    <span class="ml-2">Calendar</span>
                                </NavLink>

                                <NavLink :href="route('contacts.index', {show: 10})" :active="route().current('contacts.index')" class="ml-3">
                                    <img class="block h-4" src="/images/Contacts_1.png">
                                    <span class="ml-2">Contact</span>
                                </NavLink>
                            </div>
                        </div>

                                                
                        <div class="hidden sm:flex sm:items-center sm:ml-6">
                            <!-- Settings Dropdown -->
                            <div class="ml-3 relative flex">
                                <div class="tooltip-wrapper tooltip tooltip-left tooltip-info" :data-tip="tooltipText">
                                    <button type="button" class="mr-3 mt-2" @click="toggleTheme()"><img class="" :src=currentIcon :alt=iconAlt></button>
                                </div>

                                <Dropdown align="right" width="48">
                                    <template #trigger>
                                        <span class="inline-flex rounded-md">
                                            <button type="button"
                                                class="inline-flex items-center px-3 py-2 border border-transparent text-sm leading-4 font-medium rounded-md text-gray-500 bg-gray-50 hover:text-gray-700 focus:outline-none transition ease-in-out duration-150"
                                            >
                                                {{ $page.props.auth.user.name }}

                                                <svg
                                                    class="ml-2 -mr-0.5 h-4 w-4"
                                                    xmlns="http://www.w3.org/2000/svg"
                                                    viewBox="0 0 20 20"
                                                    fill="currentColor"
                                                >
                                                    <path
                                                        fill-rule="evenodd"
                                                        d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z"
                                                        clip-rule="evenodd"
                                                    />
                                                </svg>
                                            </button>
                                        </span>
                                    </template>

                                    <template #content>
                                        <DropdownLink :href="route('profile.edit')"> Profile </DropdownLink>
                                        <DropdownLink v-if="$page.props.auth.user.user_type === 'Admin'" :href="route('adminmenu')">Admin Menu</DropdownLink>
                                        <DropdownLink :href="route('preferences.index', $page.props.auth.user.id)"> Preferences </DropdownLink>
                                        <DropdownLink :href="route('logout')" method="post" as="button">
                                            Log Out
                                        </DropdownLink>
                                    </template>
                                </Dropdown>
                            </div>
                        </div>

                        <!-- Hamburger -->
                        <div class="-mr-2 flex items-center sm:hidden">
                            <button
                                @click="showingNavigationDropdown = !showingNavigationDropdown"
                                class="inline-flex items-center justify-center p-2 rounded-md text-gray-400 hover:text-gray-500 hover:bg-gray-100 focus:outline-none focus:bg-gray-100 focus:text-gray-500 transition duration-150 ease-in-out"
                            >
                                <svg class="h-6 w-6" stroke="currentColor" fill="none" viewBox="0 0 24 24">
                                    <path
                                        :class="{
                                            hidden: showingNavigationDropdown,
                                            'inline-flex': !showingNavigationDropdown,
                                        }"
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        stroke-width="2"
                                        d="M4 6h16M4 12h16M4 18h16"
                                    />
                                    <path
                                        :class="{
                                            hidden: !showingNavigationDropdown,
                                            'inline-flex': showingNavigationDropdown,
                                        }"
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        stroke-width="2"
                                        d="M6 18L18 6M6 6l12 12"
                                    />
                                </svg>
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Responsive Navigation Menu -->
                <div class="sm:hidden"
                    :class="{ block: showingNavigationDropdown, hidden: !showingNavigationDropdown }"
                >
                    <div class="pt-2 pb-3 space-y-1">
                        <ResponsiveNavLink :href="route('dashboard')" :active="route().current('dashboard')">
                            Dashboard
                        </ResponsiveNavLink>
                    </div>

                    <!-- Responsive Settings Options -->
                    <div class="pt-4 pb-1 border-t border-gray-200">
                        <div class="px-4">
                            <div class="font-medium text-base text-gray-800">
                                {{ $page.props.auth.user.name }}
                            </div>
                            <div class="font-medium text-sm text-gray-500">{{ $page.props.auth.user.email }}</div>
                        </div>

                        <div class="mt-3 space-y-1">
                            <ResponsiveNavLink :href="route('profile.edit')"> Profile </ResponsiveNavLink>
                            <ResponsiveNavLink :href="route('preferences.index', $page.props.auth.user.id)"> Preferences </ResponsiveNavLink>
                            <ResponsiveNavLink :href="route('logout')" method="post" as="button">
                                Log Out
                            </ResponsiveNavLink>
                        </div>
                    </div>
                </div>
            </nav>

            <!-- Page Heading -->
            <header class="bg-base-100 shadow" v-if="$slots.header">
                <div class="max-w-7xl mx-auto py-2 px-4 sm:px-6 lg:px-8 bg-base-100">
                    <slot name="header" />
                </div>
            </header>

            <!-- Page Content -->
            <main>
                <slot />
            </main>
        </div>
    </div>
</template>

<style scoped>
.tooltip-wrapper[data-tip]:before {
    transition-delay: 500ms;
    transition-duration: 200ms;
}

.tooltip-wrapper[data-tip]:after {
    transition-delay: 300ms;
    transition-duration: 200ms;
}
</style>