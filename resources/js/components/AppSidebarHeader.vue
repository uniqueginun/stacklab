<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import { ChevronsUpDown } from '@lucide/vue';
import { computed } from 'vue';
import Breadcrumbs from '@/components/Breadcrumbs.vue';
import UserMenuContent from '@/components/UserMenuContent.vue';
import { Avatar, AvatarFallback } from '@/components/ui/avatar';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { SidebarTrigger } from '@/components/ui/sidebar';
import { useInitials } from '@/composables/useInitials';
import type { BreadcrumbItem } from '@/types';

withDefaults(
    defineProps<{
        breadcrumbs?: BreadcrumbItem[];
    }>(),
    {
        breadcrumbs: () => [],
    },
);

const page = usePage();
const user = computed(() => page.props.auth.user);
const { getInitials } = useInitials();
</script>

<template>
    <header
        class="flex h-14 shrink-0 items-center justify-between gap-4 border-b border-sl-border bg-sl-surface px-4 md:px-6"
    >
        <div class="flex min-w-0 items-center gap-2">
            <SidebarTrigger class="-ml-1 text-sl-muted" />
            <template v-if="breadcrumbs.length > 0">
                <Breadcrumbs :breadcrumbs="breadcrumbs" />
            </template>
        </div>

        <DropdownMenu v-if="user">
            <DropdownMenuTrigger
                class="flex items-center gap-2.5 rounded-md px-1.5 py-1 text-left transition-colors hover:bg-sl-background focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-sl-focus"
            >
                <Avatar class="size-8 rounded-full">
                    <AvatarFallback
                        class="rounded-full bg-neutral-200 text-xs font-semibold text-sl-ink"
                    >
                        {{ getInitials(user.name) }}
                    </AvatarFallback>
                </Avatar>
                <div class="hidden leading-tight sm:block">
                    <p class="text-sm font-medium text-sl-ink">
                        {{ user.name }}
                    </p>
                    <p class="text-xs text-sl-muted">Administrator</p>
                </div>
                <ChevronsUpDown class="size-4 text-sl-muted" />
            </DropdownMenuTrigger>
            <DropdownMenuContent class="min-w-56" align="end">
                <UserMenuContent :user="user" />
            </DropdownMenuContent>
        </DropdownMenu>
    </header>
</template>
