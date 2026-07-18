<script setup>
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout.vue";
import { Head, Link } from "@inertiajs/vue3";
import { ref } from "vue";

const testResult = ref(null);

function testProtocol() {
    testResult.value = 'pending';
    window.location.href = 'vantage://open?path=' + encodeURIComponent('C:\\Windows\\notepad.exe');
    setTimeout(() => {
        testResult.value = 'sent';
    }, 1000);
}
</script>

<template>
    <Head title="Protocol Handler Setup" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="font-normal text-sm text-base-content leading-tight ml-2">
                <Link :href="route('adminmenu')">Admin</Link> >
                <Link :href="route('firm.edit')">Firm Settings</Link> >
                Protocol Handler Setup
            </h2>
        </template>

        <div class="py-3">
            <div class="max-w-4xl mx-auto sm:px-6 lg:px-8">
                <div class="bg-base-300 overflow-hidden sm:rounded-lg min-h-dvh p-8">
                    <p class="text-3xl font-bold text-center text-blue-600 mb-8">
                        Document Protocol Handler Setup
                    </p>

                    <div class="max-w-3xl mx-auto text-base-content space-y-8">

                        <!-- Overview -->
                        <div class="bg-base-200 rounded-lg p-5">
                            <h3 class="text-lg font-semibold mb-2">What is this?</h3>
                            <p class="text-sm leading-relaxed">
                                Vantage uses a custom <code class="bg-base-100 px-1 rounded">vantage://</code> protocol to open
                                linked documents directly from the app. When you click "Open Doc" on an entry, the browser
                                hands the request to Windows, which opens the file with its default application &mdash;
                                Word documents open in Word, PDFs in your PDF reader, etc.
                            </p>
                            <p class="text-sm leading-relaxed mt-2">
                                Each user's PC needs a <strong>one-time setup</strong> (takes about 1 minute).
                            </p>
                        </div>

                        <!-- Step 1 -->
                        <div>
                            <h3 class="text-lg font-semibold mb-3">Step 1: Download the handler files</h3>
                            <div class="flex gap-4">
                                <a href="/downloads/vantage-handler.bat"
                                   download
                                   class="btn btn-primary btn-sm">
                                    Download vantage-handler.bat
                                </a>
                                <a href="/downloads/vantage-protocol.reg"
                                   download
                                   class="btn btn-primary btn-sm">
                                    Download vantage-protocol.reg
                                </a>
                            </div>
                        </div>

                        <!-- Step 2 -->
                        <div>
                            <h3 class="text-lg font-semibold mb-3">Step 2: Place the handler script</h3>
                            <ol class="list-decimal list-inside text-sm space-y-2 ml-2">
                                <li>Create a folder called <code class="bg-base-100 px-1 rounded">C:\VantageHandler</code></li>
                                <li>Move the downloaded <code class="bg-base-100 px-1 rounded">vantage-handler.bat</code> file into that folder</li>
                                <li>The final path should be: <code class="bg-base-100 px-1 rounded">C:\VantageHandler\vantage-handler.bat</code></li>
                            </ol>
                        </div>

                        <!-- Step 3 -->
                        <div>
                            <h3 class="text-lg font-semibold mb-3">Step 3: Register the protocol</h3>
                            <ol class="list-decimal list-inside text-sm space-y-2 ml-2">
                                <li>Double-click the downloaded <code class="bg-base-100 px-1 rounded">vantage-protocol.reg</code> file</li>
                                <li>Windows will ask if you want to allow changes to the registry &mdash; click <strong>Yes</strong></li>
                                <li>You should see a confirmation that the keys were added successfully</li>
                            </ol>
                            <p class="text-xs text-base-content/60 mt-2 ml-2">
                                Note: This writes to HKEY_CURRENT_USER only &mdash; no administrator rights are needed.
                            </p>
                        </div>

                        <!-- Step 4: Test -->
                        <div>
                            <h3 class="text-lg font-semibold mb-3">Step 4: Test the setup</h3>
                            <p class="text-sm mb-3">
                                Click the button below to test. If everything is set up correctly, Notepad should open.
                            </p>
                            <button @click="testProtocol" class="btn btn-outline btn-sm">
                                Test Protocol Handler
                            </button>
                            <p v-if="testResult === 'sent'" class="text-sm text-success mt-2">
                                Protocol request sent. If Notepad opened, the setup is working correctly.
                                If your browser showed a prompt asking permission, allow it.
                                If nothing happened, double-check steps 2 and 3 above.
                            </p>
                        </div>

                        <!-- Troubleshooting -->
                        <div class="bg-base-200 rounded-lg p-5">
                            <h3 class="text-lg font-semibold mb-2">Troubleshooting</h3>
                            <ul class="list-disc list-inside text-sm space-y-2">
                                <li><strong>Browser shows a security prompt:</strong> This is normal the first time. Allow the <code class="bg-base-100 px-1 rounded">vantage://</code> protocol and optionally check "Always allow."</li>
                                <li><strong>Nothing happens when clicking "Open Doc":</strong> Re-run the .reg file and make sure the .bat file is at <code class="bg-base-100 px-1 rounded">C:\VantageHandler\vantage-handler.bat</code></li>
                                <li><strong>"Windows cannot find" error:</strong> The file path in the entry may be incorrect, or the file has been moved/deleted.</li>
                            </ul>
                        </div>

                    </div>

                    <div class="text-center mt-12 mb-8">
                        <Link :href="route('firm.edit')" class="btn btn-primary w-32">Back</Link>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
