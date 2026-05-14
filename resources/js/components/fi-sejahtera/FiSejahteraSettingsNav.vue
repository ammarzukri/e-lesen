<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';

const page = usePage();

const navItems = [
    { label: 'Profil', href: '/fi-sejahtera/settings/profile' },
    { label: 'Kata Laluan', href: '/fi-sejahtera/settings/password' },
    { label: 'Paparan', href: '/fi-sejahtera/settings/appearance' },
];

const isActive = (href: string) => page.url.startsWith(href);
</script>

<template>
    <div class="flex flex-wrap gap-2">
        <Link
            v-for="item in navItems"
            :key="item.href"
            :href="item.href"
            class="inline-flex items-center rounded-md px-3 py-2 text-sm font-medium transition-colors"
            :class="isActive(item.href)
                ? 'bg-muted text-foreground'
                : 'text-muted-foreground hover:bg-muted/70 hover:text-foreground'"
        >
            {{ item.label }}
        </Link>
    </div>
</template>
