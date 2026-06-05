<script setup lang="ts">
import { Form, Head, Link, usePage } from '@inertiajs/vue3';

import ProfileController from '@/actions/App/Http/Controllers/Settings/ProfileController';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import FiSejahteraNavbar from '@/components/fi-sejahtera/FiSejahteraNavbar.vue';
import FiSejahteraSettingsNav from '@/components/fi-sejahtera/FiSejahteraSettingsNav.vue';
import FiSejahteraSidebar from '@/components/fi-sejahtera/FiSejahteraSidebar.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import verification from '@/routes/verification';

interface Props {
    mustVerifyEmail: boolean;
    status?: string;
}

defineProps<Props>();

const page = usePage();
const user = page.props.auth.user;
</script>

<template>
    <Head title="Tetapan Profil Fi Sejahtera" />

    <div class="flex min-h-screen bg-muted/30">
        <FiSejahteraSidebar />

        <div class="flex min-h-screen flex-1 flex-col">
            <FiSejahteraNavbar />

            <main class="flex-1 space-y-6 p-6">
                <div>
                    <h1 class="text-2xl font-bold text-foreground">Tetapan Profil</h1>
                    <p class="text-sm text-muted-foreground">Kemas kini maklumat akaun anda tanpa keluar dari sistem Fi Sejahtera.</p>
                </div>

                <FiSejahteraSettingsNav />

                <div class="rounded-2xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900/60 p-6">
                    <div class="space-y-6">
                        <Heading
                            variant="small"
                            title="Maklumat Profil"
                            description="Kemas kini nama dan alamat emel anda"
                        />

                        <Form
                            v-bind="ProfileController.update.form()"
                            :options="{ preserveScroll: true }"
                            class="space-y-6"
                            v-slot="{ errors, processing, recentlySuccessful }"
                        >
                            <div class="grid gap-2">
                                <Label for="name">Nama</Label>
                                <Input
                                    id="name"
                                    class="mt-1 block w-full"
                                    name="name"
                                    :default-value="user.name"
                                    required
                                    autocomplete="name"
                                    placeholder="Nama penuh"
                                />
                                <InputError class="mt-2" :message="errors.name" />
                            </div>

                            <div class="grid gap-2">
                                <Label for="email">Alamat emel</Label>
                                <Input
                                    id="email"
                                    type="email"
                                    class="mt-1 block w-full"
                                    name="email"
                                    :default-value="user.email"
                                    required
                                    autocomplete="username"
                                    placeholder="Alamat emel"
                                />
                                <InputError class="mt-2" :message="errors.email" />
                            </div>

                            <div v-if="mustVerifyEmail && !user.email_verified_at">
                                <p class="-mt-4 text-sm text-muted-foreground">
                                    Emel anda belum disahkan.
                                    <Link
                                        :href="verification.send()"
                                        as="button"
                                        class="text-foreground underline decoration-neutral-300 underline-offset-4 transition-colors duration-300 ease-out hover:decoration-current! dark:decoration-neutral-500"
                                    >
                                        Klik di sini untuk menghantar semula emel pengesahan.
                                    </Link>
                                </p>

                                <div
                                    v-if="status === 'verification-link-sent'"
                                    class="mt-2 text-sm font-medium text-green-600"
                                >
                                    Pautan pengesahan baru telah dihantar ke alamat emel anda.
                                </div>
                            </div>

                            <div class="flex items-center gap-4">
                                <Button :disabled="processing">Simpan</Button>

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
