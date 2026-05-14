<script setup lang="ts">
import { computed } from 'vue';
import { Head, router, Link, Form, usePage } from '@inertiajs/vue3';

import { dashboard, login, register } from '@/routes';
import { store } from '@/routes/login';
import { request } from '@/routes/password';

import InputError from '@/components/InputError.vue';
import TextLink from '@/components/TextLink.vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';

const props = withDefaults(
    defineProps<{
        canRegister: boolean;
        canResetPassword?: boolean;
        status?: string;
        fiSejahteraBlocked?: boolean;
        fiSejahteraMessage?: string | null;
    }>(),
    {
        canRegister: true,
        canResetPassword: false,
        fiSejahteraBlocked: false,
        fiSejahteraMessage: null,
    },
);

function logout() {
    router.post('/logout');
}

const page = usePage();

const fiSejahteraHref = computed(() =>
    ['admin', 'bkt_admin', 'pbt_admin'].includes(page.props.auth?.user?.role ?? '')
        ? '/fi-sejahtera/dashboard'
        : '/fi-sejahtera/apply',
);

const fiSejahteraNotice = computed(() => {
    if (props.fiSejahteraBlocked) {
        return props.fiSejahteraMessage ?? 'Lesen Penginapan Tamat Tempoh';
    }

    return ((page.props as any).flash?.error as string) ?? '';
});
</script>

<template>
    <!-- <Head title="Welcome">
        <link rel="preconnect" href="https://rsms.me/" />
        <link rel="stylesheet" href="https://rsms.me/inter/inter.css" />
    </Head> -->
    <div
        class="flex min-h-screen flex-col items-center p-6 text-[#1b1b18] lg:justify-center lg:p-8"
        style="background-image: url('/images/T-Stay Background 2.jpeg'); background-size: cover; background-position: center;"
    >
        <header
            class="mb-6 w-full max-w-83.75 text-sm not-has-[nav]:hidden lg:max-w-4xl"
        >
            <nav class="flex items-center justify-end gap-4">
                <button
                @click="logout"
                class="inline-block rounded-sm border border-gray-300 bg-white px-6 py-2 text-base leading-normal text-gray-800 transition-all duration-150 ease-out dark:bg-[#161615] dark:text-[#EDEDEC] hover:scale-105 hover:border-gray-400"
            >
                Log Keluar
            </button>
            </nav>
        </header>
        <div class="flex w-full items-center justify-center opacity-100 transition-opacity duration-750 lg:grow starting:opacity-0">
            <main class="w-full max-w-6xl">
                <!-- Title -->
                 <div class="text-center">
                    <img src="/images/jata-negeri.png" 
                        alt="Logo 1" 
                        class="w-30 h-30 object-contain mb-3 inline-block" />
                </div>
                
                <h1 class="text-5xl font-extrabold text-center text-white drop-shadow-lg mb-7">
                    Sistem Fi Sejahtera
                </h1>

                <div
                    v-if="fiSejahteraNotice"
                    class="mx-auto mb-6 max-w-2xl rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-center text-sm font-medium text-red-700"
                >
                    {{ fiSejahteraNotice }}
                </div>

                <!-- Cards Container -->
                <div class="flex flex-col md:flex-row items-center justify-center gap-12">
                    
                    <!-- Card 1 - Fi Sejahtera -->
                    <Link
                        v-if="!props.fiSejahteraBlocked"
                        :href="fiSejahteraHref"
                        class="bg-white/90 backdrop-blur-md rounded-3xl shadow-xl w-80 h-96 dark:bg-[#161615] 
                                flex flex-col items-center justify-center 
                                transition transform hover:scale-105 hover:shadow-2xl cursor-pointer"
                    >
                        <img src="/images/bed.png" 
                            alt="Logo 1" 
                            class="w-36 h-36 object-contain mb-6 dark:brightness-0 dark:invert" />

                        <h2 class="text-2xl font-bold text-gray-800 dark:text-[#EDEDEC] text-center px-4">
                            Caj Fi Sejahtera
                        </h2>
                    </Link>

                    <div
                        v-else
                        class="bg-white/80 backdrop-blur-md rounded-3xl shadow-xl w-80 h-96 flex flex-col items-center justify-center opacity-75 cursor-not-allowed"
                    >
                        <img src="/images/bed.png"
                            alt="Logo 1"
                            class="w-36 h-36 object-contain mb-6 dark:brightness-0 dark:invert" />

                        <h2 class="text-2xl font-bold text-gray-800 dark:text-[#EDEDEC] text-center px-4">
                            Caj Fi Sejahtera
                        </h2>
                        <p class="mt-3 rounded-full bg-red-100 px-3 py-1 text-xs font-semibold text-red-700">
                            ⚠ Lesen Penginapan Tamat Tempoh
                        </p>
                    </div>

                    <!-- Card 2 - E-Lesen -->
                    <Link
                        href="/dashboard"
                        class="bg-white/90 backdrop-blur-md rounded-3xl shadow-xl w-80 h-96 dark:bg-[#161615] 
                                flex flex-col items-center justify-center 
                                transition transform hover:scale-105 hover:shadow-2xl cursor-pointer"
                    >
                        <img src="/images/license.png" 
                            alt="Logo 2" 
                            class="w-36 h-36 object-contain mb-6 dark:brightness-0 dark:invert" />

                        <h2 class="text-xl text-center font-bold text-gray-800 dark:text-[#EDEDEC]">
                            Permohonan/Pembaharuan Lesen Perniagaan
                        </h2>
                    </Link>
                </div>
            </main>
        </div>
        <div class="hidden lg:block"></div>

        <div class="flex items-center justify-center gap-4">
            <h1 class="text-sm font-extrabold text-gray-200 drop-shadow-lg">
                Sistem Fi Sejahtera © 2026 Hak Cipta Terpelihara Kerajaan Negeri Terengganu. Powered by TAJDID Corporation Sdn. Bhd.
            </h1>
        </div>
    </div>
</template>
