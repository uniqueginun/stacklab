<script setup lang="ts">
import AppContent from '@/components/AppContent.vue';
import AppShell from '@/components/AppShell.vue';
import AppSidebar from '@/components/AppSidebar.vue';
import AppSidebarHeader from '@/components/AppSidebarHeader.vue';
import { Toaster } from '@/components/ui/sonner';
import type { BreadcrumbItem } from '@/types';

type Props = {
    breadcrumbs?: BreadcrumbItem[];
};

withDefaults(defineProps<Props>(), {
    breadcrumbs: () => [],
});
</script>

<template>
    <div class="sl-app min-h-svh">
        <AppShell variant="sidebar">
            <AppSidebar />
            <AppContent variant="sidebar" class="overflow-x-hidden bg-[#f7f7f5]">
                <AppSidebarHeader :breadcrumbs="breadcrumbs" />
                <div class="flex flex-1 flex-col p-4 md:p-6">
                    <slot />
                </div>
            </AppContent>
            <Toaster />
        </AppShell>
    </div>
</template>
