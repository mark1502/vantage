<script setup>
import { ref } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import VantageMark from '@/Components/VantageMark.vue';
import PageBackdrop from '@/Components/PageBackdrop.vue';
import {
    FolderOpenIcon,
    QueueListIcon,
    CalendarDaysIcon,
    ExclamationTriangleIcon,
    DocumentTextIcon,
    UsersIcon,
    ArrowPathRoundedSquareIcon,
    ClockIcon,
    BellAlertIcon,
    InboxArrowDownIcon,
    LockClosedIcon,
    BuildingOffice2Icon,
    ShieldCheckIcon,
    ServerStackIcon,
    Bars3Icon,
    XMarkIcon,
    CheckIcon,
} from '@heroicons/vue/24/outline';

defineProps({
    canLogin: {
        type: Boolean,
    },
    canRegister: {
        type: Boolean,
    },
});

const mobileOpen = ref(false);

const navLinks = [
    { label: 'Features', href: '#features' },
    { label: 'How it works', href: '#workflow' },
    { label: 'Security', href: '#security' },
];

const features = [
    {
        icon: FolderOpenIcon,
        title: 'Matter files that hold everything',
        body: 'Open a file and see the whole matter: file type, client, assigned attorney, opposing counsel, and every person attached to it — each with the role they actually play.',
    },
    {
        icon: QueueListIcon,
        title: 'A running record of every entry',
        body: 'Calls, letters, emails, pleadings, tasks and deadlines are logged as entries on the file, with who it came from, who it went to, and the response it is waiting on.',
    },
    {
        icon: CalendarDaysIcon,
        title: 'A calendar that stays in sync',
        body: 'Any entry can appear on the calendar. Drag to reschedule, resize to change duration, and color-code by entry type — the file record updates with it.',
    },
    {
        icon: ExclamationTriangleIcon,
        title: 'Deadlines you cannot afford to miss',
        body: 'Set the statute of limitations date on a file and Vantage places it on the calendar for the responsible attorney automatically — and keeps it there if the date moves.',
    },
    {
        icon: DocumentTextIcon,
        title: 'Documents linked in place',
        body: 'Attach the actual document to the entry that produced it. Vantage links to your firm’s existing document store, so nothing has to be re-filed or migrated.',
    },
    {
        icon: UsersIcon,
        title: 'One roster for your whole firm',
        body: 'Attorneys, paralegals and clerical staff carry their own initials and role. External contacts live alongside them, so every entry names a real person on both ends.',
    },
];

const responseHighlights = [
    {
        icon: ClockIcon,
        title: 'Flag it when you send it',
        body: 'Mark any entry as expecting a response and give it a date you expect one by.',
    },
    {
        icon: InboxArrowDownIcon,
        title: 'Close the loop on arrival',
        body: 'Log the reply as its own entry and link it back to what it answers. The item clears itself.',
    },
    {
        icon: BellAlertIcon,
        title: 'See what is still open',
        body: 'Anything unanswered stays on the outstanding list — by file, by person, and by how late it is.',
    },
];

const workflow = [
    {
        step: '01',
        title: 'Open the file',
        body: 'Create the matter, choose its file type, and attach the client, the responsible attorney, and opposing counsel with their roles.',
    },
    {
        step: '02',
        title: 'Log the work',
        body: 'Every call, document, task and deadline goes on the file as an entry — filed into a folder, typed, dated, and pointed at the contacts involved.',
    },
    {
        step: '03',
        title: 'Watch the calendar',
        body: 'Dated entries surface on the firm calendar. Deadlines, hearings and follow-ups stay in front of the people responsible for them.',
    },
];

const securityPoints = [
    {
        icon: BuildingOffice2Icon,
        title: 'Firm-level separation',
        body: 'Every file, entry, contact and calendar item is scoped to your firm at the data layer — not just hidden in the interface.',
    },
    {
        icon: LockClosedIcon,
        title: 'Controlled access',
        body: 'Administrators control who joins the firm, what role they hold, and whether their account stays active.',
    },
    {
        icon: ServerStackIcon,
        title: 'Your documents stay yours',
        body: 'Vantage links to documents where your firm already keeps them. Your files are not copied into someone else’s storage.',
    },
    {
        icon: ShieldCheckIcon,
        title: 'Encrypted in transit',
        body: 'All traffic between your staff and Vantage is served over TLS, with authenticated sessions on every request.',
    },
];

const closingPoints = [
    'Set up your firm in minutes',
    'Bring your own document storage',
    'No per-matter charges',
];
</script>

<template>
    <Head title="Vantage — Legal Case Management" />

    <div class="min-h-screen bg-white text-slate-900 antialiased">
        <!-- ── Top bar ─────────────────────────────────────────────── -->
        <header class="sticky top-0 z-50 border-b border-slate-200/80 bg-white/85 backdrop-blur">
            <div class="mx-auto flex h-16 max-w-7xl items-center gap-8 px-4 sm:px-6 lg:px-8">
                <a href="#top" class="flex shrink-0 items-center gap-3">
                    <VantageMark class="w-9 text-indigo-700" />
                    <span class="text-lg font-bold tracking-tight text-slate-900">Vantage</span>
                </a>

                <nav class="hidden flex-1 items-center gap-8 md:flex">
                    <a
                        v-for="link in navLinks"
                        :key="link.href"
                        :href="link.href"
                        class="text-sm font-medium text-slate-600 transition hover:text-slate-900"
                    >
                        {{ link.label }}
                    </a>
                </nav>

                <div class="ml-auto hidden items-center gap-3 md:flex">
                    <Link
                        v-if="canLogin"
                        :href="route('login')"
                        class="rounded-md px-3 py-2 text-sm font-semibold text-slate-700 transition hover:bg-slate-100 hover:text-slate-900 focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500"
                    >
                        Log in
                    </Link>
                    <Link
                        v-if="canRegister"
                        :href="route('register')"
                        class="rounded-md bg-indigo-700 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-indigo-800 focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500 focus-visible:ring-offset-2"
                    >
                        Get started
                    </Link>
                </div>

                <button
                    type="button"
                    class="ml-auto inline-flex h-10 w-10 items-center justify-center rounded-md text-slate-600 transition hover:bg-slate-100 md:hidden"
                    :aria-expanded="mobileOpen"
                    aria-label="Toggle navigation"
                    @click="mobileOpen = !mobileOpen"
                >
                    <XMarkIcon v-if="mobileOpen" class="h-6 w-6" />
                    <Bars3Icon v-else class="h-6 w-6" />
                </button>
            </div>

            <div v-if="mobileOpen" class="border-t border-slate-200 bg-white md:hidden">
                <div class="space-y-1 px-4 py-4">
                    <a
                        v-for="link in navLinks"
                        :key="link.href"
                        :href="link.href"
                        class="block rounded-md px-3 py-2 text-base font-medium text-slate-700 hover:bg-slate-50"
                        @click="mobileOpen = false"
                    >
                        {{ link.label }}
                    </a>
                    <div class="mt-3 flex flex-col gap-2 border-t border-slate-200 pt-3">
                        <Link
                            v-if="canLogin"
                            :href="route('login')"
                            class="rounded-md px-3 py-2 text-base font-semibold text-slate-700 hover:bg-slate-50"
                        >
                            Log in
                        </Link>
                        <Link
                            v-if="canRegister"
                            :href="route('register')"
                            class="rounded-md bg-indigo-700 px-3 py-2 text-center text-base font-semibold text-white hover:bg-indigo-800"
                        >
                            Get started
                        </Link>
                    </div>
                </div>
            </div>
        </header>

        <!-- ── Hero ────────────────────────────────────────────────── -->
        <section id="top" class="relative overflow-hidden bg-slate-50">
            <PageBackdrop />

            <div class="relative mx-auto max-w-7xl px-4 pt-20 pb-24 sm:px-6 sm:pt-24 lg:px-8">
                <div class="mx-auto max-w-3xl text-center">
                    <span
                        class="inline-flex items-center gap-2 rounded-full border border-indigo-200 bg-white px-3.5 py-1.5 text-xs font-semibold tracking-wide text-indigo-700 uppercase"
                    >
                        <span class="h-1.5 w-1.5 rounded-full bg-amber-500"></span>
                        Case management for small and mid-size firms
                    </span>

                    <h1 class="mt-6 text-4xl font-extrabold tracking-tight text-slate-900 sm:text-5xl lg:text-6xl">
                        Every matter. Every deadline.
                        <span class="block text-indigo-700">One clear view.</span>
                    </h1>

                    <p class="mx-auto mt-6 max-w-2xl text-lg leading-relaxed text-slate-600">
                        Vantage keeps the full history of a case in one place, tracks every response
                        your firm is still waiting on, and puts the deadlines that matter on the
                        calendar before anyone has to remember them.
                    </p>

                    <div class="mt-9 flex flex-col items-center justify-center gap-3 sm:flex-row">
                        <Link
                            v-if="canRegister"
                            :href="route('register')"
                            class="w-full rounded-lg bg-indigo-700 px-7 py-3.5 text-center text-base font-semibold text-white shadow-lg shadow-indigo-700/20 transition hover:bg-indigo-800 focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500 focus-visible:ring-offset-2 sm:w-auto"
                        >
                            Set up your firm
                        </Link>
                        <a
                            href="#features"
                            class="w-full rounded-lg border border-slate-300 bg-white px-7 py-3.5 text-center text-base font-semibold text-slate-700 transition hover:border-slate-400 hover:bg-slate-50 sm:w-auto"
                        >
                            See how it works
                        </a>
                    </div>

                    <p class="mt-5 text-sm text-slate-500">
                        Built for litigation practices · Works with your existing document folders
                    </p>
                </div>

                <!-- Product visual -->
                <div class="mx-auto mt-16 max-w-5xl">
                    <div class="rounded-2xl border border-slate-200 bg-white/70 p-2 shadow-2xl shadow-slate-900/10 ring-1 ring-slate-900/5">
                        <div class="overflow-hidden rounded-xl border border-slate-200 bg-white">
                            <!-- window chrome -->
                            <div class="flex items-center gap-2 border-b border-slate-200 bg-slate-50 px-4 py-3">
                                <span class="h-2.5 w-2.5 rounded-full bg-slate-300"></span>
                                <span class="h-2.5 w-2.5 rounded-full bg-slate-300"></span>
                                <span class="h-2.5 w-2.5 rounded-full bg-slate-300"></span>
                                <div class="ml-4 hidden h-6 flex-1 items-center rounded-md bg-white px-3 text-[11px] font-medium text-slate-400 ring-1 ring-slate-200 sm:flex">
                                    vantage — File 2024-0418 · Whitfield v. Cordova Freight
                                </div>
                            </div>

                            <div class="grid gap-0 sm:grid-cols-[13rem_1fr]">
                                <!-- sidebar -->
                                <aside class="hidden border-r border-slate-200 bg-slate-50/70 p-4 sm:block">
                                    <p class="px-2 text-[10px] font-bold tracking-widest text-slate-400 uppercase">
                                        Folders
                                    </p>
                                    <ul class="mt-3 space-y-1 text-sm">
                                        <li class="rounded-md bg-indigo-50 px-2 py-1.5 font-semibold text-indigo-800">
                                            Correspondence
                                        </li>
                                        <li class="px-2 py-1.5 text-slate-600">Pleadings</li>
                                        <li class="px-2 py-1.5 text-slate-600">Discovery</li>
                                        <li class="px-2 py-1.5 text-slate-600">Notes</li>
                                        <li class="px-2 py-1.5 text-slate-600">Tasks</li>
                                        <li class="px-2 py-1.5 text-slate-600">Deadlines</li>
                                    </ul>

                                    <p class="mt-6 px-2 text-[10px] font-bold tracking-widest text-slate-400 uppercase">
                                        Assigned
                                    </p>
                                    <div class="mt-3 flex items-center gap-2 px-2">
                                        <span class="flex h-7 w-7 items-center justify-center rounded-full bg-indigo-700 text-[11px] font-bold text-white">
                                            RJM
                                        </span>
                                        <span class="text-sm text-slate-600">Attorney</span>
                                    </div>
                                </aside>

                                <!-- entry list -->
                                <div class="p-4 sm:p-6">
                                    <div class="flex flex-wrap items-start justify-between gap-3">
                                        <div>
                                            <h3 class="text-base font-bold text-slate-900">
                                                Whitfield v. Cordova Freight
                                            </h3>
                                            <p class="mt-0.5 text-xs text-slate-500">
                                                Personal Injury · Client: Dana Whitfield · Opened 04 Mar
                                            </p>
                                        </div>
                                        <span class="inline-flex items-center gap-1.5 rounded-full bg-amber-50 px-3 py-1 text-xs font-semibold text-amber-800 ring-1 ring-amber-200">
                                            <ExclamationTriangleIcon class="h-3.5 w-3.5" />
                                            SOL 12 Nov
                                        </span>
                                    </div>

                                    <div class="mt-5 space-y-2.5">
                                        <div
                                            v-for="row in [
                                                { date: '08 Aug', type: 'Letter sent', who: 'RJM → Opposing counsel', tone: 'indigo', doc: true },
                                                { date: '05 Aug', type: 'Phone call', who: 'Client → RJM', tone: 'slate', doc: false },
                                                { date: '02 Aug', type: 'Deposition', who: 'Calendared · 9:30 AM', tone: 'emerald', doc: false },
                                                { date: '29 Jul', type: 'Medical records', who: 'Received · Mercy General', tone: 'slate', doc: true },
                                            ]"
                                            :key="row.date + row.type"
                                            class="flex items-center gap-3 rounded-lg border border-slate-200 px-3 py-2.5"
                                        >
                                            <span class="w-12 shrink-0 text-xs font-semibold text-slate-400">{{ row.date }}</span>
                                            <span
                                                class="h-6 w-1 shrink-0 rounded-full"
                                                :class="{
                                                    'bg-indigo-500': row.tone === 'indigo',
                                                    'bg-emerald-500': row.tone === 'emerald',
                                                    'bg-slate-300': row.tone === 'slate',
                                                }"
                                            ></span>
                                            <div class="min-w-0 flex-1">
                                                <p class="truncate text-sm font-semibold text-slate-800">{{ row.type }}</p>
                                                <p class="truncate text-xs text-slate-500">{{ row.who }}</p>
                                            </div>
                                            <DocumentTextIcon v-if="row.doc" class="h-4 w-4 shrink-0 text-slate-400" />
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- ── Value bar ───────────────────────────────────────────── -->
        <section class="border-y border-slate-200 bg-white">
            <div class="mx-auto grid max-w-7xl gap-8 px-4 py-12 sm:px-6 lg:grid-cols-3 lg:px-8">
                <div v-for="item in [
                        { k: 'One file, whole history', v: 'Correspondence, documents, tasks and dates live on the matter — not in four different systems.' },
                        { k: 'Nothing goes unanswered', v: 'Every entry expecting a reply is tracked until it arrives — outstanding items surface instead of slipping.' },
                        { k: 'Deadlines calendar themselves', v: 'Statute-of-limitations dates land on the responsible attorney’s calendar the moment they are entered.' },
                    ]"
                    :key="item.k"
                    class="border-l-2 border-indigo-600 pl-5"
                >
                    <h3 class="text-base font-bold text-slate-900">{{ item.k }}</h3>
                    <p class="mt-2 text-sm leading-relaxed text-slate-600">{{ item.v }}</p>
                </div>
            </div>
        </section>

        <!-- ── Features ────────────────────────────────────────────── -->
        <section id="features" class="bg-white py-24">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                <div class="max-w-2xl">
                    <p class="text-sm font-bold tracking-widest text-indigo-700 uppercase">Features</p>
                    <h2 class="mt-3 text-3xl font-extrabold tracking-tight text-slate-900 sm:text-4xl">
                        The parts of a case, held together
                    </h2>
                    <p class="mt-4 text-lg leading-relaxed text-slate-600">
                        Vantage is built around how a matter actually moves through a firm — so the
                        record stays complete without anyone maintaining it twice.
                    </p>
                </div>

                <!-- Spotlight: response tracking -->
                <div class="mt-14 overflow-hidden rounded-2xl border border-slate-200 bg-slate-50">
                    <div class="grid gap-10 p-8 sm:p-10 lg:grid-cols-2 lg:gap-14 lg:p-12">
                        <div>
                            <span class="inline-flex items-center gap-2 rounded-full bg-indigo-700 px-3 py-1 text-xs font-bold tracking-wide text-white uppercase">
                                <ArrowPathRoundedSquareIcon class="h-3.5 w-3.5" />
                                Response tracking
                            </span>
                            <h3 class="mt-5 text-2xl font-extrabold tracking-tight text-slate-900 sm:text-3xl">
                                Nothing waits on an answer without someone knowing
                            </h3>
                            <p class="mt-4 text-base leading-relaxed text-slate-600">
                                Most things that go wrong in a case are things nobody answered. Vantage
                                tracks responses as part of the record: every entry that expects a reply
                                is followed until the reply arrives and is linked back to it, so open
                                items surface on their own instead of living in someone’s memory.
                            </p>

                            <dl class="mt-8 space-y-5">
                                <div v-for="item in responseHighlights" :key="item.title" class="flex gap-4">
                                    <span class="mt-0.5 inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-white text-indigo-700 ring-1 ring-slate-200">
                                        <component :is="item.icon" class="h-5 w-5" />
                                    </span>
                                    <div>
                                        <dt class="text-sm font-bold text-slate-900">{{ item.title }}</dt>
                                        <dd class="mt-1 text-sm leading-relaxed text-slate-600">{{ item.body }}</dd>
                                    </div>
                                </div>
                            </dl>
                        </div>

                        <!-- Outstanding items visual -->
                        <div class="lg:pt-2">
                            <div class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-lg shadow-slate-900/5">
                                <div class="flex items-center justify-between border-b border-slate-200 px-5 py-3.5">
                                    <p class="text-sm font-bold text-slate-900">Outstanding responses</p>
                                    <span class="rounded-full bg-rose-50 px-2.5 py-0.5 text-xs font-bold text-rose-700 ring-1 ring-rose-200">
                                        3 overdue
                                    </span>
                                </div>

                                <ul class="divide-y divide-slate-100">
                                    <li
                                        v-for="row in [
                                            { what: 'Records request — Mercy General', file: 'Whitfield v. Cordova', due: '12 days late', late: true },
                                            { what: 'Settlement demand — opposing counsel', file: 'Alvarez matter', due: '4 days late', late: true },
                                            { what: 'Signed retainer — client', file: 'Bhatt intake', due: '1 day late', late: true },
                                            { what: 'Interrogatory answers', file: 'Whitfield v. Cordova', due: 'Due in 6 days', late: false },
                                            { what: 'Expert availability', file: 'Nakamura appeal', due: 'Due in 2 weeks', late: false },
                                        ]"
                                        :key="row.what"
                                        class="flex items-center gap-3 px-5 py-3.5"
                                    >
                                        <span
                                            class="h-2 w-2 shrink-0 rounded-full"
                                            :class="row.late ? 'bg-rose-500' : 'bg-amber-400'"
                                        ></span>
                                        <div class="min-w-0 flex-1">
                                            <p class="truncate text-sm font-semibold text-slate-800">{{ row.what }}</p>
                                            <p class="truncate text-xs text-slate-500">{{ row.file }}</p>
                                        </div>
                                        <span
                                            class="shrink-0 text-xs font-semibold"
                                            :class="row.late ? 'text-rose-600' : 'text-slate-400'"
                                        >
                                            {{ row.due }}
                                        </span>
                                    </li>
                                </ul>

                                <div class="border-t border-slate-100 bg-slate-50 px-5 py-3 text-xs text-slate-500">
                                    Cleared automatically when the reply is logged against the entry.
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="mt-6 grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
                    <article
                        v-for="feature in features"
                        :key="feature.title"
                        class="group rounded-xl border border-slate-200 bg-white p-7 transition hover:-translate-y-0.5 hover:border-indigo-200 hover:shadow-lg hover:shadow-slate-900/5"
                    >
                        <span class="inline-flex h-11 w-11 items-center justify-center rounded-lg bg-indigo-50 text-indigo-700 transition group-hover:bg-indigo-700 group-hover:text-white">
                            <component :is="feature.icon" class="h-6 w-6" />
                        </span>
                        <h3 class="mt-5 text-lg font-bold text-slate-900">{{ feature.title }}</h3>
                        <p class="mt-2.5 text-sm leading-relaxed text-slate-600">{{ feature.body }}</p>
                    </article>
                </div>
            </div>
        </section>

        <!-- ── Workflow ────────────────────────────────────────────── -->
        <section id="workflow" class="border-t border-slate-200 bg-slate-50 py-24">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                <div class="max-w-2xl">
                    <p class="text-sm font-bold tracking-widest text-indigo-700 uppercase">How it works</p>
                    <h2 class="mt-3 text-3xl font-extrabold tracking-tight text-slate-900 sm:text-4xl">
                        Three habits, and the file keeps itself
                    </h2>
                </div>

                <div class="mt-14 grid gap-6 lg:grid-cols-3">
                    <div
                        v-for="stage in workflow"
                        :key="stage.step"
                        class="relative rounded-xl border border-slate-200 bg-white p-8"
                    >
                        <span class="text-4xl font-extrabold tracking-tight text-slate-200">{{ stage.step }}</span>
                        <h3 class="mt-3 text-lg font-bold text-slate-900">{{ stage.title }}</h3>
                        <p class="mt-2.5 text-sm leading-relaxed text-slate-600">{{ stage.body }}</p>
                    </div>
                </div>
            </div>
        </section>

        <!-- ── Security ────────────────────────────────────────────── -->
        <section id="security" class="bg-slate-900 py-24 text-slate-300">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                <div class="grid gap-14 lg:grid-cols-[minmax(0,26rem)_1fr] lg:gap-20">
                    <div>
                        <p class="text-sm font-bold tracking-widest text-amber-400 uppercase">Security</p>
                        <h2 class="mt-3 text-3xl font-extrabold tracking-tight text-white sm:text-4xl">
                            Client confidences, treated that way
                        </h2>
                        <p class="mt-5 text-base leading-relaxed text-slate-400">
                            A case management system holds privileged material. Vantage is built so
                            your firm’s data stays separated, access stays deliberate, and your
                            documents stay in your own hands.
                        </p>
                    </div>

                    <div class="grid gap-6 sm:grid-cols-2">
                        <div
                            v-for="point in securityPoints"
                            :key="point.title"
                            class="rounded-xl border border-white/10 bg-white/5 p-6"
                        >
                            <span class="inline-flex h-10 w-10 items-center justify-center rounded-lg bg-amber-400/10 text-amber-400">
                                <component :is="point.icon" class="h-5 w-5" />
                            </span>
                            <h3 class="mt-4 text-base font-bold text-white">{{ point.title }}</h3>
                            <p class="mt-2 text-sm leading-relaxed text-slate-400">{{ point.body }}</p>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- ── Closing CTA ─────────────────────────────────────────── -->
        <section class="bg-white py-24">
            <div class="mx-auto max-w-5xl px-4 sm:px-6 lg:px-8">
                <div class="relative overflow-hidden rounded-2xl bg-indigo-700 px-8 py-14 text-center sm:px-14">
                    <div
                        class="pointer-events-none absolute -top-24 -right-16 h-72 w-72 rounded-full opacity-30 blur-3xl"
                        style="background: radial-gradient(closest-side, rgb(251 191 36), transparent)"
                    ></div>

                    <div class="relative">
                        <h2 class="text-3xl font-extrabold tracking-tight text-white sm:text-4xl">
                            Put your practice on the record
                        </h2>
                        <p class="mx-auto mt-4 max-w-xl text-lg leading-relaxed text-indigo-100">
                            Create your firm, invite your staff, and start logging matters today.
                        </p>

                        <div class="mt-9 flex flex-col items-center justify-center gap-3 sm:flex-row">
                            <Link
                                v-if="canRegister"
                                :href="route('register')"
                                class="w-full rounded-lg bg-white px-7 py-3.5 text-center text-base font-semibold text-indigo-800 shadow-lg transition hover:bg-indigo-50 sm:w-auto"
                            >
                                Set up your firm
                            </Link>
                            <Link
                                v-if="canLogin"
                                :href="route('login')"
                                class="w-full rounded-lg border border-white/40 px-7 py-3.5 text-center text-base font-semibold text-white transition hover:bg-white/10 sm:w-auto"
                            >
                                Log in
                            </Link>
                        </div>

                        <ul class="mt-9 flex flex-wrap items-center justify-center gap-x-7 gap-y-3 text-sm text-indigo-100">
                            <li v-for="point in closingPoints" :key="point" class="inline-flex items-center gap-2">
                                <CheckIcon class="h-4 w-4 text-amber-300" />
                                {{ point }}
                            </li>
                        </ul>
                    </div>
                </div>
            </div>
        </section>

        <!-- ── Footer ──────────────────────────────────────────────── -->
        <footer class="border-t border-slate-200 bg-slate-50">
            <div class="mx-auto flex max-w-7xl flex-col items-center justify-between gap-6 px-4 py-10 sm:px-6 lg:flex-row lg:px-8">
                <div class="flex items-center gap-2.5">
                    <VantageMark class="w-7 text-indigo-700" />
                    <span class="text-sm font-bold text-slate-900">Vantage</span>
                    <span class="text-sm text-slate-500">· Legal case management</span>
                </div>

                <nav class="flex flex-wrap items-center justify-center gap-x-7 gap-y-2 text-sm text-slate-600">
                    <a v-for="link in navLinks" :key="link.href" :href="link.href" class="transition hover:text-slate-900">
                        {{ link.label }}
                    </a>
                    <Link v-if="canLogin" :href="route('login')" class="transition hover:text-slate-900">Log in</Link>
                </nav>

                <p class="text-sm text-slate-500">© {{ new Date().getFullYear() }} Vantage</p>
            </div>
        </footer>
    </div>
</template>
