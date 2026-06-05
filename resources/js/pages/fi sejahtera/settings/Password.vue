<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';

import PasswordController from '@/actions/App/Http/Controllers/Settings/PasswordController';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import FiSejahteraNavbar from '@/components/fi-sejahtera/FiSejahteraNavbar.vue';
import FiSejahteraSettingsNav from '@/components/fi-sejahtera/FiSejahteraSettingsNav.vue';
import FiSejahteraSidebar from '@/components/fi-sejahtera/FiSejahteraSidebar.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
</script>

<template>
    <Head title="Tetapan Kata Laluan Fi Sejahtera" />

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
                            title="Kemas kini kata laluan"
                            description="Pastikan akaun anda menggunakan kata laluan yang panjang dan rawak untuk kekal selamat"
                        />

                        <Form
                            v-bind="PasswordController.update.form()"
                            :options="{ preserveScroll: true }"
                            reset-on-success
                            :reset-on-error="['password', 'password_confirmation', 'current_password']"
                            class="space-y-6"
                            v-slot="{ errors, processing, recentlySuccessful }"
                        >
                            <div class="grid gap-2">
                                <Label for="current_password">Kata laluan semasa</Label>
                                <Input
                                    id="current_password"
                                    name="current_password"
                                    type="password"
                                    class="mt-1 block w-full"
                                    autocomplete="current-password"
                                    placeholder="Kata laluan semasa"
                                />
                                <InputError :message="errors.current_password" />
                            </div>

                            <div class="grid gap-2">
                                <Label for="password">Kata laluan baru</Label>
                                <Input
                                    id="password"
                                    name="password"
                                    type="password"
                                    class="mt-1 block w-full"
                                    autocomplete="new-password"
                                    placeholder="Kata laluan baru"
                                />
                                <InputError :message="errors.password" />
                            </div>

                            <div class="grid gap-2">
                                <Label for="password_confirmation">Sahkan kata laluan</Label>
                                <Input
                                    id="password_confirmation"
                                    name="password_confirmation"
                                    type="password"
                                    class="mt-1 block w-full"
                                    autocomplete="new-password"
                                    placeholder="Sahkan kata laluan"
                                />
                                <InputError :message="errors.password_confirmation" />
                            </div>

                            <div class="flex items-center gap-4">
                                <Button :disabled="processing">Simpan kata laluan</Button>

                                <Transition
                                    enter-active-class="transition ease-in-out"
                                    enter-from-class="opacity-0"
                                    leave-active-class="transition ease-in-out"
                                    leave-to-class="opacity-0"
                                >
                                    <p v-show="recentlySuccessful" class="text-sm text-neutral-600">
                                        Disimpan.
                                    </p>
                                </Transition>
                            </div>
                        </Form>
                    </div>
                </div>
            </main>
        </div>
    </div>
</template>
