<script setup lang="ts">
import { Globe, LayoutGrid, Server, Settings } from '@lucide/vue';
import AppLogo from '@/components/AppLogo.vue';
import NavMain from '@/components/NavMain.vue';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { dashboard } from '@/routes';
import { edit as editProfile } from '@/routes/profile';
import { index as serversIndex } from '@/routes/servers';
import { index as sitesIndex } from '@/routes/sites';
import type { NavItem } from '@/types';

const mainNavItems: NavItem[] = [
    {
        title: 'Dashboard',
        href: dashboard(),
        icon: LayoutGrid,
    },
    {
        title: 'Servers',
        href: serversIndex(),
        icon: Server,
    },
    {
        title: 'Sites',
        href: sitesIndex(),
        icon: Globe,
    },
    {
        title: 'Settings',
        href: editProfile(),
        icon: Settings,
    },
];
</script>

<template>
    <Sidebar collapsible="icon" variant="sidebar" class="border-r border-sl-border">
        <SidebarHeader class="border-b border-sl-border px-3 py-4">
            <SidebarMenu>
                <SidebarMenuItem>
                    <AppLogo />
                </SidebarMenuItem>
            </SidebarMenu>
        </SidebarHeader>

        <SidebarContent class="px-2 py-4">
            <NavMain :items="mainNavItems" />
        </SidebarContent>

        <SidebarFooter class="border-t border-sl-border px-4 py-3">
            <p
                class="truncate font-mono text-[11px] tracking-wide text-sl-muted group-data-[collapsible=icon]:hidden"
            >
                StackLab 1.2.0
            </p>
        </SidebarFooter>
    </Sidebar>
    <slot />
</template>
