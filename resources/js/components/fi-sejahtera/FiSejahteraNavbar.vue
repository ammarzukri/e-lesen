<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';

import UserMenuContent from '@/components/UserMenuContent.vue';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { getInitials } from '@/composables/useInitials';
import { type AppPageProps } from '@/types';

const page = usePage<AppPageProps>();
</script>

<template>
    <header class="sticky top-0 z-40 border-b border-border bg-background px-6 py-4">
        <div class="flex items-center justify-end">
            <DropdownMenu>
                <DropdownMenuTrigger :as-child="true">
                    <Button
                        variant="ghost"
                        size="icon"
                        class="relative size-12 w-auto rounded-full p-1"
                    >
                        <Avatar class="size-10 overflow-hidden rounded-full">
                            <AvatarImage
                                v-if="page.props.auth.user.avatar"
                                :src="page.props.auth.user.avatar"
                                :alt="page.props.auth.user.name"
                            />
                            <AvatarFallback
                                class="rounded-lg bg-muted font-semibold text-foreground"
                            >
                                {{ getInitials(page.props.auth.user.name) }}
                            </AvatarFallback>
                        </Avatar>
                    </Button>
                </DropdownMenuTrigger>
                <DropdownMenuContent align="end" class="w-56">
                    <UserMenuContent :user="page.props.auth.user" settings-href="/fi-sejahtera/settings/profile" />
                </DropdownMenuContent>
            </DropdownMenu>
        </div>
    </header>
</template>
