<script setup lang="ts">
import { Head } from '@inertiajs/vue3';

import AppearanceTabs from '@/components/AppearanceTabs.vue';
import Heading from '@/components/Heading.vue';
import FiSejahteraNavbar from '@/components/fi-sejahtera/FiSejahteraNavbar.vue';
import FiSejahteraSettingsNav from '@/components/fi-sejahtera/FiSejahteraSettingsNav.vue';
import FiSejahteraSidebar from '@/components/fi-sejahtera/FiSejahteraSidebar.vue';
</script>

<template>
    <Head title="Tetapan Paparan Fi Sejahtera" />

    <div class="flex min-h-screen bg-muted/30">
        <FiSejahteraSidebar />

        <div class="flex min-h-screen flex-1 flex-col">
            <FiSejahteraNavbar />

            <main class="flex-1 space-y-6 p-6">
                <div>
                    <h1 class="text-2xl font-bold text-foreground">Tetapan</h1>
                    <p class="text-sm text-muted-foreground">Urus tetapan akaun anda dalam sistem Fi Sejahtera.</p>
                </div>

                <FiSejahteraSettingsNav />

                <div class="rounded-2xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900/60 p-6">
                    <div class="space-y-6">
                        <Heading
                            variant="small"
                            title="Tetapan Paparan"
                            description="Kemas kini tetapan paparan akaun anda"
                        />

                        <AppearanceTabs />
                    </div>
                </div>
            </main>
        </div>
    </div>
</template>
