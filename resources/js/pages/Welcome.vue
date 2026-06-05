<script setup lang="ts">
import { Head, Link, Form } from '@inertiajs/vue3';

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
import { useAppearance } from '@/composables/useAppearance';

const { resolvedAppearance, updateAppearance } = useAppearance();

function toggleAppearance() {
    updateAppearance(resolvedAppearance.value === 'dark' ? 'light' : 'dark');
}

withDefaults(
    defineProps<{
        canRegister: boolean;
        canResetPassword?: boolean;
        status?: string;
    }>(),
    {
        canRegister: true,
        canResetPassword: false,
    },
);
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
                <!-- <button
                    type="button"
                    @click="toggleAppearance"
                    :title="resolvedAppearance === 'dark' ? 'Switch to light mode' : 'Switch to dark mode'"
                    :aria-label="resolvedAppearance === 'dark' ? 'Switch to light mode' : 'Switch to dark mode'"
                    class="inline-flex h-10 w-10 items-center justify-center rounded-sm border border-gray-300 bg-white text-lg leading-none text-gray-800 transition-all duration-150 ease-out dark:bg-[#161615] hover:scale-105 hover:border-gray-400"
                >
                    {{ resolvedAppearance === 'dark' ? '☀' : '🌙' }}
                </button> -->
                <Link
                    v-if="$page.props.auth.user"
                    :href="dashboard()"
                    class="inline-block rounded-sm border border-gray-300 bg-white px-5 py-1.5 text-sm leading-normal text-[#1b1b18] hover:border-[#1915014a] dark:border-[#3E3E3A] dark:bg-[#161615] dark:text-[#EDEDEC] dark:hover:border-[#62605b]"
                >
                    Dashboard
                </Link>
                <template v-else>
                    <!-- <Link
                        :href="login()"
                        class="inline-block rounded-sm border border-transparent px-5 py-1.5 text-sm leading-normal text-[#ececec] hover:border-[#e7e7e735] dark:text-[#EDEDEC] dark:hover:border-[#3E3E3A]"
                    >
                        Log in
                    </Link> -->
                    <button
                        type="button"
                        @click="toggleAppearance"
                        :title="resolvedAppearance === 'dark' ? 'Switch to light mode' : 'Switch to dark mode'"
                        :aria-label="resolvedAppearance === 'dark' ? 'Switch to light mode' : 'Switch to dark mode'"
                        class="inline-flex h-10 w-10 items-center justify-center rounded-sm border border-gray-300 bg-white text-lg leading-none text-gray-800 transition-all duration-150 ease-out dark:bg-[#161615] hover:scale-105 hover:border-gray-400"
                    >
                        {{ resolvedAppearance === 'dark' ? '☀' : '🌙' }}
                    </button>
                    <Link
                        v-if="canRegister"
                        :href="register()"
                        class="inline-block rounded-sm border border-gray-300 bg-white px-6 py-2 text-base leading-normal text-gray-800 transition-all duration-150 ease-out dark:bg-[#161615] dark:text-[#EDEDEC] hover:scale-105 hover:border-gray-400"
                    >
                        Daftar
                    </Link>
                </template>
            </nav>
        </header>
        <div class="flex w-full items-center justify-center opacity-100 transition-opacity duration-750 lg:grow starting:opacity-0">
            <main class="w-full max-w-83.75 lg:max-w-4xl">
                <div class="w-full overflow-hidden rounded-2xl shadow-[inset_0px_0px_0px_1px_rgba(26,26,0,0.08)] bg-white dark:bg-[#161615] lg:flex">
                    <div class="relative shrink-0 w-full lg:w-[320px] bg-[#f8f8f8] p-14 flex items-center justify-center dark:bg-[#161615]">
                        <img class="max-w-67.5 max-h-77.5 object-contain" src="/images/jata-negeri.png" alt="Jata Negeri" />
                        <div class="absolute inset-0 lg:overflow-hidden dark:shadow-[inset_0px_0px_0px_1px_#fffaed2d]"></div>
                    </div>

                    <!-- vertical separator on large screens, horizontal on small -->
                    <div class="hidden lg:block w-px bg-[#e7e7e7] dark:bg-[#2b2b2b]"></div>
                    <div class="block lg:hidden h-px bg-[#e7e7e7] dark:bg-[#2b2b2b]"></div>

                    <div class="flex-1 p-6 pb-12 text-[13px] leading-5 lg:p-20">
                        <div v-if="status" class="mb-4 text-center text-sm font-medium text-green-600">{{ status }}</div>
                        <h1 class="w-full text-center text-3xl font-extrabold text-black dark:text-white drop-shadow-sm mb-6">Sistem Pengurusan <br> Fi Sejahtera (SPS)</h1>

                        <Form v-bind="store.form()" :reset-on-success="['password']" v-slot="{ errors, processing }" class="flex flex-col gap-6">
                            <div class="grid gap-6">
                                <div class="grid gap-2">
                                    <Label for="email" class="text-slate-700 dark:text-slate-200">Alamat Emel</Label>
                                    <Input id="email" type="email" name="email" required autofocus :tabindex="1" autocomplete="email" placeholder="email@example.com" class="dark:bg-slate-900 dark:text-slate-100 dark:border-slate-700" />
                                    <InputError :message="errors.email" />
                                </div>

                                <div class="grid gap-2">
                                    <div class="flex items-center justify-between">
                                        <Label for="password" class="text-slate-700 dark:text-slate-200">Kata Laluan</Label>
                                        <TextLink v-if="canResetPassword" :href="request()" class="text-sm dark:text-slate-200" :tabindex="5">Lupa kata laluan?</TextLink>
                                    </div>
                                    <Input id="password" type="password" name="password" required :tabindex="2" autocomplete="current-password" placeholder="Kata Laluan" class="dark:bg-slate-900 dark:text-slate-100 dark:border-slate-700" />
                                    <InputError :message="errors.password" />
                                </div>

                                <div class="flex items-center justify-between">
                                    <Label for="remember" class="flex items-center space-x-3">
                                        <Checkbox id="remember" name="remember" :tabindex="3" />
                                        <span>Ingat saya</span>
                                    </Label>
                                </div>

                                <Button type="submit" class="mt-4 w-full" :tabindex="4" :disabled="processing">
                                    <Spinner v-if="processing" />
                                    Log masuk
                                </Button>
                            </div>

                            <div class="text-center text-sm text-muted-foreground" v-if="canRegister">
                                Tiada akaun?
                                <TextLink :href="register()" :tabindex="5">Daftar</TextLink>
                            </div>
                        </Form>
                    </div>
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
