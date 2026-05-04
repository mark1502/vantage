<script setup>
import { ref, computed, onMounted, onUnmounted, provide } from 'vue';
import ApplicationLogo from '@/Components/ApplicationLogo.vue';
import Dropdown from '@/Components/Dropdown.vue';
import DropdownLink from '@/Components/DropdownLink.vue';
import HoverDropdown from '@/Components/HoverDropdown.vue';
import NavLink from '@/Components/NavLink.vue';
import ResponsiveNavLink from '@/Components/ResponsiveNavLink.vue';
import { Link, usePage, router, useForm } from '@inertiajs/vue3';
import { FolderOpenIcon, BuildingOffice2Icon, CalendarDaysIcon, IdentificationIcon } from '@heroicons/vue/24/outline';

import { useTheme } from '@/Composables/useTheme'

const { theme, themes, setTheme, initTheme } = useTheme()

onMounted(() => {
    initTheme()
})

const showThemeDialog = ref(false)
const pendingTheme = ref(null)

function onThemeSelected(t) {
    setTheme(t)
    pendingTheme.value = t
    showThemeDialog.value = true
}

function confirmThemeSave() {
    const page = usePage()
    const form = useForm({ user_id: page.props.auth.user.id, theme: pendingTheme.value })
    form.post(route('preferences.theme'), { preserveState: true })
    showThemeDialog.value = false
}

function declineThemeSave() {
    showThemeDialog.value = false
}

provide('currentTheme', theme);
provide('setThemeFunction', setTheme);

const showingNavigationDropdown = ref(false);

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
        ? 'inline-flex items-center px-1 pt-1 border-b-2 border-primary text-sm font-medium leading-5 text-base-content focus:outline-none focus:border-primary transition duration-150 ease-in-out'
        : 'inline-flex items-center px-1 pt-1 border-b-2 border-transparent text-sm font-medium leading-5 text-base-content/60 hover:text-base-content hover:border-base-300 focus:outline-none focus:text-base-content focus:border-base-300 transition duration-150 ease-in-out',
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
        <div class="bg-neutral">
            <nav class="bg-base-200 border-b border-base-300 relative z-[100]">
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
                                        <Link :href="route('files.index')" :class="fileNavClasses" class="cursor-pointer">
                                            <!-- <svg class="block h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 9.776c.112-.017.227-.026.344-.026h15.812c.117 0 .232.009.344.026m-16.5 0a2.25 2.25 0 0 0-1.883 2.542l.857 6a2.25 2.25 0 0 0 2.227 1.932H19.05a2.25 2.25 0 0 0 2.227-1.932l.857-6a2.25 2.25 0 0 0-1.883-2.542m-16.5 0V6A2.25 2.25 0 0 1 6 3.75h3.879a1.5 1.5 0 0 1 1.06.44l2.122 2.12a1.5 1.5 0 0 0 1.06.44H18A2.25 2.25 0 0 1 20.25 9v.776" />
                                            </svg> -->
                                            <FolderOpenIcon class="w-4 h-4" />
                                            <span class="ml-2">File</span>
                                        </Link>
                                    </template>

                                    <template #content>
                                        <Link :href="route('files.index')"
                                            class="block w-full px-4 py-2 text-start text-sm font-semibold leading-5 text-base-content/80 transition duration-150 ease-in-out hover:bg-base-200 focus:bg-base-200 focus:outline-none">
                                            Show File List
                                        </Link>
                                        <div v-if="recentFiles.length > 0">
                                            <div class="border-t border-base-300 px-4 py-1 text-xs text-base-content/50 uppercase tracking-wider">
                                                Recently used files
                                            </div>
                                            <Link v-for="file in recentFiles" :key="file.id"
                                                :href="route('entries.index', { file: file.id, filepart: file.filepart, page: file.page, show: file.show })"
                                                class="block w-full px-4 py-2 text-start text-sm leading-5 text-base-content/80 transition duration-150 ease-in-out hover:bg-base-200 focus:bg-base-200 focus:outline-none">
                                                {{ file.name }}
                                            </Link>
                                        </div>
                                    </template>
                                </HoverDropdown>

                                <NavLink :href="route('views.index', { view: 'memos' })" :active="route().current('views.index')" class="ml-3">
                                    <!-- <svg class="block h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 21h19.5m-18-18v18m10.5-18v18m6-13.5V21M6.75 6.75h.75m-.75 3h.75m-.75 3h.75m3-6h.75m-.75 3h.75m-.75 3h.75M6.75 21v-3.375c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21M3 3h12m-.75 4.5H21m-3.75 3h.008v.008h-.008v-.008Zm0 3h.008v.008h-.008v-.008Zm0 3h.008v.008h-.008v-.008Z" />
                                    </svg> -->
                                    <BuildingOffice2Icon class="w-4 h-4" />
                                    <span class="ml-2">Office</span>
                                </NavLink>

                                <NavLink :href="route('calendar.index')" :active="route().current('calendar.index')" class="ml-3">
                                    <!-- <svg class="block h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 0 1 2.25-2.25h13.5A2.25 2.25 0 0 1 21 7.5v11.25m-18 0A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75m-18 0v-7.5A2.25 2.25 0 0 1 5.25 9h13.5A2.25 2.25 0 0 1 21 11.25v7.5m-9-6h.008v.008H12v-.008ZM12 15h.008v.008H12V15Zm0 2.25h.008v.008H12v-.008ZM9.75 15h.008v.008H9.75V15Zm0 2.25h.008v.008H9.75v-.008ZM7.5 15h.008v.008H7.5V15Zm0 2.25h.008v.008H7.5v-.008Zm6.75-4.5h.008v.008h-.008v-.008Zm0 2.25h.008v.008h-.008V15Zm0 2.25h.008v.008h-.008v-.008Zm2.25-4.5h.008v.008H16.5v-.008Zm0 2.25h.008v.008H16.5V15Z" />
                                    </svg> -->
                                    <CalendarDaysIcon class="w-4 h-4" />
                                    <span class="ml-2">Calendar</span>
                                </NavLink>

                                <NavLink :href="route('contacts.index', {show: 10})" :active="route().current('contacts.index')" class="ml-3">
                                    <!-- <svg class="block h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 9h3.75M15 12h3.75M15 15h3.75M4.5 19.5h15a2.25 2.25 0 0 0 2.25-2.25V6.75A2.25 2.25 0 0 0 19.5 4.5h-15a2.25 2.25 0 0 0-2.25 2.25v10.5A2.25 2.25 0 0 0 4.5 19.5Zm6-10.125a1.875 1.875 0 1 1-3.75 0 1.875 1.875 0 0 1 3.75 0Zm-3.375 6.166a4.21 4.21 0 0 1 3.375-1.916 4.21 4.21 0 0 1 3.375 1.916" />
                                    </svg> -->
                                    <IdentificationIcon class="w-4 h-4" />
                                    <span class="ml-2">Contact</span>
                                </NavLink>
                            </div>
                        </div>

                                                
                        <div class="hidden sm:flex sm:items-center sm:ml-6">
                            <!-- Settings Dropdown -->
                            <div class="ml-3 relative flex">
                                <Dropdown align="right" width="48">
                                    <template #trigger>
                                        <button type="button"
                                            class="inline-flex items-center px-2 py-2 mr-2 border border-transparent text-sm leading-4 font-medium rounded-md text-base-content/60 bg-base-200 hover:text-base-content focus:outline-none transition ease-in-out duration-150"
                                        >
                                            <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M4.098 19.902a3.75 3.75 0 005.304 0l6.401-6.402M6.75 21A3.75 3.75 0 013 17.25V4.125C3 3.504 3.504 3 4.125 3h1.5c.621 0 1.125.504 1.125 1.125v4.072M6.75 21a3.75 3.75 0 003.75-3.75V8.197M6.75 21h13.125c.621 0 1.125-.504 1.125-1.125v-1.5c0-.621-.504-1.125-1.125-1.125h-4.072M10.5 8.197l2.88-2.88c.438-.439 1.15-.439 1.59 0l3.712 3.713c.44.44.44 1.152 0 1.59l-2.879 2.88M6.75 17.25h.008v.008H6.75v-.008z" />
                                            </svg>
                                            <span class="ml-1 text-xs capitalize">{{ theme }}</span>
                                        </button>
                                    </template>

                                    <template #content>
                                        <button
                                            v-for="t in themes"
                                            :key="t"
                                            @click="onThemeSelected(t)"
                                            class="block w-full px-4 py-2 text-start text-sm leading-5 text-base-content/80 transition duration-150 ease-in-out hover:bg-base-200 focus:bg-base-200 focus:outline-none capitalize"
                                            :class="{ 'font-bold bg-base-200': theme === t }"
                                        >
                                            {{ t }}
                                        </button>
                                    </template>
                                </Dropdown>

                                <Dropdown align="right" width="48">
                                    <template #trigger>
                                        <span class="inline-flex rounded-md">
                                            <button type="button"
                                                class="inline-flex items-center px-3 py-2 border border-transparent text-sm leading-4 font-medium rounded-md text-base-content/60 bg-base-200 hover:text-base-content focus:outline-none transition ease-in-out duration-150"
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
                                class="inline-flex items-center justify-center p-2 rounded-md text-base-content/50 hover:text-base-content/60 hover:bg-base-200 focus:outline-none focus:bg-base-200 focus:text-base-content/60 transition duration-150 ease-in-out"
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
                    <div class="pt-4 pb-1 border-t border-base-300">
                        <div class="px-4">
                            <div class="font-medium text-base text-base-content">
                                {{ $page.props.auth.user.name }}
                            </div>
                            <div class="font-medium text-sm text-base-content/60">{{ $page.props.auth.user.email }}</div>
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

        <!-- Theme save confirmation dialog -->
        <dialog :open="showThemeDialog" class="modal modal-bottom sm:modal-middle">
            <div class="modal-box">
                <h3 class="font-bold text-lg">Save Theme Preference?</h3>
                <p class="py-4 text-base-content/80">Would you like to save <span class="font-semibold capitalize">{{ pendingTheme }}</span> as your default theme?</p>
                <div class="modal-action">
                    <button class="btn btn-ghost" @click="declineThemeSave">No, just for now</button>
                    <button class="btn btn-primary" @click="confirmThemeSave">Yes, save it</button>
                </div>
            </div>
        </dialog>
    </div>
</template>

