<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import StatusDot from '@/components/StatusDot.vue';
import { create, index, show } from '@/routes/sites';

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Sites',
                href: index(),
            },
        ],
    },
});

type SiteRow = {
    uuid: string;
    domain: string;
    type: string;
    php_version: string | null;
    status: string;
    server?: {
        uuid: string;
        name: string;
        host: string;
    };
};

defineProps<{
    sites: SiteRow[];
}>();

function statusTone(
    status: string,
): 'success' | 'warning' | 'danger' | 'neutral' {
    switch (status) {
        case 'active':
        case 'deployed':
        case 'succeeded':
            return 'success';
        case 'failed':
            return 'danger';
        case 'pending':
        case 'deploying':
        case 'provisioning':
            return 'warning';
        default:
            return 'neutral';
    }
}

function statusLabel(status: string): string {
    if (!status) {
        return 'Unknown';
    }

    return status.charAt(0).toUpperCase() + status.slice(1);
}

function typeLabel(type: string): string {
    switch (type) {
        case 'laravel':
            return 'Laravel';
        case 'php':
            return 'PHP';
        default:
            return type;
    }
}
</script>

<template>
    <Head title="Sites" />

    <div class="flex flex-col gap-6">
        <div
            class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between"
        >
            <div>
                <h1
                    class="text-2xl font-bold tracking-tight text-sl-ink uppercase"
                >
                    Sites
                </h1>
                <p class="mt-1 text-sm text-sl-muted">
                    Domains across your servers — type, PHP version, and status
                    at a glance.
                </p>
            </div>
            <Link
                :href="create()"
                class="inline-flex h-9 items-center rounded-md bg-sl-accent px-4 text-sm font-semibold text-white transition-colors hover:bg-[#a8471a]"
            >
                Create site
            </Link>
        </div>

        <div
            v-if="sites.length === 0"
            class="rounded-md border border-dashed border-sl-border bg-sl-surface px-6 py-12 text-center"
        >
            <p class="text-sm font-medium text-sl-ink">No sites yet</p>
            <p class="mx-auto mt-1 max-w-md text-sm text-sl-muted">
                Create a site on a connected server to provision Nginx and start
                deploying.
            </p>
            <Link
                :href="create()"
                class="mt-4 inline-flex h-9 items-center rounded-md bg-sl-accent px-4 text-sm font-semibold text-white hover:bg-[#a8471a]"
            >
                Create site
            </Link>
        </div>

        <section
            v-else
            class="overflow-hidden rounded-md border border-sl-border bg-sl-surface"
        >
            <div class="overflow-x-auto">
                <table class="w-full min-w-[40rem] text-left text-sm">
                    <thead
                        class="border-b border-sl-border text-[11px] tracking-wide text-sl-muted uppercase"
                    >
                        <tr>
                            <th class="px-4 py-3 font-medium">Domain</th>
                            <th class="px-4 py-3 font-medium">Server</th>
                            <th class="px-4 py-3 font-medium">Type</th>
                            <th class="px-4 py-3 font-medium">PHP</th>
                            <th class="px-4 py-3 font-medium">Status</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-sl-border/80">
                        <tr
                            v-for="site in sites"
                            :key="site.uuid"
                            class="transition-colors hover:bg-sl-background/70"
                        >
                            <td class="px-4 py-3">
                                <Link
                                    :href="show(site)"
                                    class="font-medium text-sl-ink hover:text-sl-accent"
                                >
                                    {{ site.domain }}
                                </Link>
                            </td>
                            <td class="px-4 py-3 font-mono text-xs">
                                {{ site.server?.name ?? '—' }}
                            </td>
                            <td class="px-4 py-3 text-sl-muted">
                                {{ typeLabel(site.type) }}
                            </td>
                            <td class="px-4 py-3 font-mono text-xs">
                                {{ site.php_version ?? '—' }}
                            </td>
                            <td class="px-4 py-3">
                                <StatusDot
                                    :tone="statusTone(site.status)"
                                    :label="statusLabel(site.status)"
                                />
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>
    </div>
</template>
