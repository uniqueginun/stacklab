<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import {
    SidebarGroup,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { useCurrentUrl } from '@/composables/useCurrentUrl';
import type { NavItem } from '@/types';

defineProps<{
    items: NavItem[];
}>();

const { isCurrentUrl, isCurrentOrParentUrl } = useCurrentUrl();
</script>

<template>
    <SidebarGroup class="p-0">
        <SidebarMenu class="gap-1">
            <SidebarMenuItem v-for="item in items" :key="item.title">
                <SidebarMenuButton
                    as-child
                    :is-active="
                        item.title === 'Settings'
                            ? isCurrentOrParentUrl('/settings')
                            : isCurrentUrl(item.href) ||
                              (item.title === 'Servers' &&
                                  isCurrentOrParentUrl('/servers')) ||
                              (item.title === 'Sites' &&
                                  isCurrentOrParentUrl('/sites'))
                    "
                    :tooltip="item.title"
                    class="h-9 rounded-md px-3 text-sm font-medium text-sl-ink data-[active=true]:bg-[color-mix(in_oklab,var(--sl-accent)_12%,white)] data-[active=true]:font-semibold data-[active=true]:text-sl-accent"
                >
                    <Link :href="item.href">
                        <component :is="item.icon" class="size-4" />
                        <span>{{ item.title }}</span>
                    </Link>
                </SidebarMenuButton>
            </SidebarMenuItem>
        </SidebarMenu>
    </SidebarGroup>
</template>
