<script setup lang="ts">
import { Link, router } from '@inertiajs/vue3';
import { LogOut, Settings, MonitorCog } from 'lucide-vue-next';

import {
    DropdownMenuGroup,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
} from '@/components/ui/dropdown-menu';
import UserInfo from '@/components/UserInfo.vue';
import { logout } from '@/routes';
import choose from '@/routes/choose';
import { edit } from '@/routes/profile';
import type { User } from '@/types';

interface Props {
    user: User;
    settingsHref?: string;
}

const handleLogout = () => {
    router.flushAll();
};

const props = defineProps<Props>();
</script>

<template>
    <DropdownMenuLabel class="p-0 font-normal">
        <div class="flex items-center gap-2 px-1 py-1.5 text-left text-sm">
            <UserInfo :user="user" :show-email="true" />
        </div>
    </DropdownMenuLabel>
    <DropdownMenuSeparator />
    <DropdownMenuGroup>
        <DropdownMenuItem :as-child="true">
            <Link class="block w-full cursor-pointer" :href="props.settingsHref ?? edit()" prefetch>
                <Settings class="mr-2 h-4 w-4" />
                Tetapan
            </Link>
        </DropdownMenuItem>
    </DropdownMenuGroup>
    <DropdownMenuSeparator />
    <DropdownMenuItem :as-child="true">
        <Link class="block w-full cursor-pointer" :href="choose.system()" prefetch>
            <MonitorCog class="mr-2 h-4 w-4" />
            Pilih Sistem
        </Link>
    </DropdownMenuItem>
    <DropdownMenuSeparator />
    <DropdownMenuItem :as-child="true">
        <Link
            class="block w-full cursor-pointer"
            :href="logout()"
            @click="handleLogout"
            as="button"
            data-test="logout-button"
        >
            <LogOut class="mr-2 h-4 w-4" />
            Log keluar
        </Link>
    </DropdownMenuItem>
</template>
