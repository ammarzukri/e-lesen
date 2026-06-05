<script setup lang="ts">
import { Link } from '@inertiajs/vue3';

import { home } from '@/routes';

defineProps<{
    title?: string;
    description?: string;
}>();
</script>

<template>
    <div
        class="flex min-h-screen flex-col items-center p-6 md:p-10"
        style="background-image: url('/images/T-Stay Background.jpeg'); background-size: cover; background-position: center;"
    >
        <div class="flex w-full flex-1 items-center justify-center">
            <div class="w-full max-w-lg">
                <div class="bg-white/95 dark:bg-[#0f0f0f] rounded-2xl shadow-lg p-8 md:p-10 lg:p-12">
                    <div class="flex flex-col gap-8">
                        <div class="flex flex-col items-center gap-4">
                            <Link
                                :href="home()"
                                class="flex flex-col items-center gap-2 font-medium"
                            >
                                <div class="mb-1 flex h-12 w-12 items-center justify-center">
                                    <img src="/images/jata-negeri.png" alt="Jata Negeri" class="h-20 w-auto object-contain" />
                                </div>
                                <span class="sr-only">{{ title }}</span>
                            </Link>
                            <div class="space-y-2 text-center">
                                <h1 class="text-xl font-medium">{{ title }}</h1>
                                <p class="text-center text-sm text-muted-foreground">
                                    {{ description }}
                                </p>
                            </div>
                        </div>
                        <slot />
                    </div>
                </div>
            </div>
        </div>
        <div class="flex items-center justify-center gap-4">
            <h1 class="text-sm font-extrabold text-gray-200 drop-shadow-lg mt-6">
                Sistem Fi Sejahtera © 2026 Hak Cipta Terpelihara Kerajaan Negeri Terengganu. Powered by TAJDID Corporation Sdn. Bhd.
            </h1>
        </div>
    </div>
</template>
