<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import {
    ChevronRight,
    Database,
    Globe,
    Layers,
    Server,
    Terminal,
} from '@lucide/vue';
import StatusDot from '@/components/StatusDot.vue';
import { dashboard } from '@/routes';
import { create as createServer, index as serversIndex } from '@/routes/servers';
import { create as createSite, index as sitesIndex, show as siteShow } from '@/routes/sites';

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Dashboard',
                href: dashboard(),
            },
        ],
    },
});

const stats = [
    {
        label: 'Servers',
        value: '3',
        detail: 'All systems operational',
        icon: Server,
    },
    {
        label: 'Sites',
        value: '8',
        detail: '7 active, 1 staging',
        icon: Globe,
    },
    {
        label: 'Apps',
        value: '16',
        detail: 'Deployed across sites',
        icon: Layers,
    },
    {
        label: 'Databases',
        value: '12',
        detail: 'All healthy',
        icon: Database,
    },
    {
        label: 'Backups',
        value: '42',
        detail: 'Last 24 hours',
        icon: Terminal,
    },
];

const servers = [
    {
        name: 'slab-01',
        os: 'Ubuntu 22.04 LTS',
        ip: '203.0.113.10',
        status: 'Online',
        tone: 'success' as const,
        load: '0.32',
        uptime: '12d 4h',
    },
    {
        name: 'slab-02',
        os: 'Ubuntu 22.04 LTS',
        ip: '203.0.113.11',
        status: 'Online',
        tone: 'success' as const,
        load: '1.14',
        uptime: '8d 21h',
    },
    {
        name: 'slab-staging',
        os: 'Ubuntu 24.04 LTS',
        ip: '203.0.113.20',
        status: 'Online',
        tone: 'success' as const,
        load: '0.08',
        uptime: '3d 2h',
    },
];

const sites = [
    {
        site: 'stacklab.app',
        server: 'slab-01',
        status: 'Active',
        tone: 'success' as const,
        app: 'Laravel 10',
        php: '8.2',
        ssl: "Let's Encrypt",
    },
    {
        site: 'api.stacklab.app',
        server: 'slab-01',
        status: 'Active',
        tone: 'success' as const,
        app: 'Laravel 11',
        php: '8.3',
        ssl: "Let's Encrypt",
    },
    {
        site: 'docs.stacklab.app',
        server: 'slab-02',
        status: 'Staging',
        tone: 'info' as const,
        app: 'Hugo',
        php: '—',
        ssl: "Let's Encrypt",
    },
    {
        site: 'legacy.stacklab.app',
        server: 'slab-02',
        status: 'Disabled',
        tone: 'neutral' as const,
        app: 'Livewire',
        php: '8.1',
        ssl: 'Custom',
    },
];

const operations = [
    {
        time: '2m ago',
        operation: 'Deploy',
        target: 'api.stacklab.app',
        status: 'Deploying',
        tone: 'info' as const,
        details: 'main @ 3f7a2c1 · zero-downtime',
    },
    {
        time: '18m ago',
        operation: 'Backup',
        target: 'slab-01 / mysql',
        status: 'Completed',
        tone: 'success' as const,
        details: 'stacklab_prod · 842 MB',
    },
    {
        time: '1h ago',
        operation: 'SSL Renew',
        target: 'docs.stacklab.app',
        status: 'Completed',
        tone: 'success' as const,
        details: "Let's Encrypt · expires in 89d",
    },
    {
        time: '3h ago',
        operation: 'Deploy',
        target: 'stacklab.app',
        status: 'Failed',
        tone: 'danger' as const,
        details: 'npm run build failed (exit code 1)',
    },
];
</script>

<template>
    <Head title="Dashboard" />

    <div class="flex flex-col gap-6">
        <div
            class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between"
        >
            <div>
                <h1
                    class="text-2xl font-bold tracking-tight text-sl-ink uppercase"
                >
                    Dashboard
                </h1>
                <p class="mt-1 text-sm text-sl-muted">
                    Overview of your infrastructure and recent activity.
                </p>
            </div>
            <div class="flex flex-wrap gap-2">
                <Link
                    :href="createServer()"
                    class="inline-flex h-9 items-center rounded-md bg-sl-accent px-4 text-sm font-semibold text-white transition-colors hover:bg-[#a8471a]"
                >
                    Add server
                </Link>
                <Link
                    :href="createSite()"
                    class="inline-flex h-9 items-center rounded-md border border-sl-ink bg-sl-surface px-4 text-sm font-semibold text-sl-ink transition-colors hover:border-sl-accent hover:text-sl-accent"
                >
                    Create site
                </Link>
            </div>
        </div>

        <section
            class="grid grid-cols-1 divide-y divide-sl-border rounded-md border border-sl-border bg-sl-surface sm:grid-cols-2 sm:divide-x sm:divide-y-0 lg:grid-cols-5"
        >
            <div
                v-for="stat in stats"
                :key="stat.label"
                class="flex items-start gap-3 px-4 py-4"
            >
                <component
                    :is="stat.icon"
                    class="mt-0.5 size-4 shrink-0 text-sl-muted"
                />
                <div>
                    <p class="text-2xl font-semibold tracking-tight text-sl-ink">
                        {{ stat.value }}
                        <span class="text-sm font-medium text-sl-muted">{{
                            stat.label
                        }}</span>
                    </p>
                    <p class="mt-0.5 text-xs text-sl-muted">{{ stat.detail }}</p>
                </div>
            </div>
        </section>

        <div class="grid gap-4 xl:grid-cols-2">
            <section
                class="overflow-hidden rounded-md border border-sl-border bg-sl-surface"
            >
                <div
                    class="flex items-center justify-between border-b border-sl-border px-4 py-3"
                >
                    <h2
                        class="text-xs font-semibold tracking-[0.14em] text-sl-ink uppercase"
                    >
                        Servers
                    </h2>
                    <Link
                        :href="serversIndex()"
                        class="text-xs font-medium text-sl-accent hover:underline"
                    >
                        View all
                    </Link>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full min-w-[36rem] text-left text-sm">
                        <thead
                            class="border-b border-sl-border text-[11px] tracking-wide text-sl-muted uppercase"
                        >
                            <tr>
                                <th class="px-4 py-2 font-medium">Name</th>
                                <th class="px-4 py-2 font-medium">IP Address</th>
                                <th class="px-4 py-2 font-medium">Status</th>
                                <th class="px-4 py-2 font-medium">Load</th>
                                <th class="px-4 py-2 font-medium">Uptime</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-sl-border/80">
                            <tr
                                v-for="server in servers"
                                :key="server.name"
                                class="transition-colors hover:bg-sl-background/70"
                            >
                                <td class="px-4 py-3">
                                    <Link
                                        :href="serversIndex()"
                                        class="font-medium text-sl-ink hover:text-sl-accent"
                                    >
                                        {{ server.name }}
                                    </Link>
                                    <p class="text-xs text-sl-muted">
                                        {{ server.os }}
                                    </p>
                                </td>
                                <td class="px-4 py-3 font-mono text-xs">
                                    {{ server.ip }}
                                </td>
                                <td class="px-4 py-3">
                                    <StatusDot
                                        :tone="server.tone"
                                        :label="server.status"
                                    />
                                </td>
                                <td class="px-4 py-3 font-mono text-xs">
                                    {{ server.load }}
                                </td>
                                <td class="px-4 py-3 font-mono text-xs">
                                    {{ server.uptime }}
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>

            <section
                class="overflow-hidden rounded-md border border-sl-border bg-sl-surface"
            >
                <div
                    class="flex items-center justify-between border-b border-sl-border px-4 py-3"
                >
                    <h2
                        class="text-xs font-semibold tracking-[0.14em] text-sl-ink uppercase"
                    >
                        Sites
                    </h2>
                    <Link
                        :href="sitesIndex()"
                        class="text-xs font-medium text-sl-accent hover:underline"
                    >
                        View all
                    </Link>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full min-w-[40rem] text-left text-sm">
                        <thead
                            class="border-b border-sl-border text-[11px] tracking-wide text-sl-muted uppercase"
                        >
                            <tr>
                                <th class="px-4 py-2 font-medium">Site</th>
                                <th class="px-4 py-2 font-medium">Server</th>
                                <th class="px-4 py-2 font-medium">Status</th>
                                <th class="px-4 py-2 font-medium">App</th>
                                <th class="px-4 py-2 font-medium">PHP</th>
                                <th class="px-4 py-2 font-medium">SSL</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-sl-border/80">
                            <tr
                                v-for="site in sites"
                                :key="site.site"
                                class="transition-colors hover:bg-sl-background/70"
                            >
                                <td class="px-4 py-3">
                                    <Link
                                        :href="siteShow()"
                                        class="font-medium text-sl-ink hover:text-sl-accent"
                                    >
                                        {{ site.site }}
                                    </Link>
                                </td>
                                <td class="px-4 py-3 font-mono text-xs">
                                    {{ site.server }}
                                </td>
                                <td class="px-4 py-3">
                                    <StatusDot
                                        :tone="site.tone"
                                        :label="site.status"
                                    />
                                </td>
                                <td class="px-4 py-3 text-sl-muted">
                                    {{ site.app }}
                                </td>
                                <td class="px-4 py-3 font-mono text-xs">
                                    {{ site.php }}
                                </td>
                                <td class="px-4 py-3 text-xs text-sl-muted">
                                    {{ site.ssl }}
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>
        </div>

        <section
            class="overflow-hidden rounded-md border border-sl-border bg-sl-surface"
        >
            <div
                class="flex items-center justify-between border-b border-sl-border px-4 py-3"
            >
                <h2
                    class="text-xs font-semibold tracking-[0.14em] text-sl-ink uppercase"
                >
                    Recent operations
                </h2>
                <span class="text-xs font-medium text-sl-accent">View all</span>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full min-w-[48rem] text-left text-sm">
                    <thead
                        class="border-b border-sl-border text-[11px] tracking-wide text-sl-muted uppercase"
                    >
                        <tr>
                            <th class="px-4 py-2 font-medium">Time</th>
                            <th class="px-4 py-2 font-medium">Operation</th>
                            <th class="px-4 py-2 font-medium">Target</th>
                            <th class="px-4 py-2 font-medium">Status</th>
                            <th class="px-4 py-2 font-medium">Details</th>
                            <th class="px-4 py-2" />
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-sl-border/80">
                        <tr
                            v-for="op in operations"
                            :key="`${op.time}-${op.target}`"
                            class="transition-colors hover:bg-sl-background/70"
                        >
                            <td class="px-4 py-3 font-mono text-xs text-sl-muted">
                                {{ op.time }}
                            </td>
                            <td class="px-4 py-3 font-medium">
                                {{ op.operation }}
                            </td>
                            <td class="px-4 py-3 font-mono text-xs">
                                {{ op.target }}
                            </td>
                            <td class="px-4 py-3">
                                <StatusDot
                                    :tone="op.tone"
                                    :label="op.status"
                                />
                            </td>
                            <td class="px-4 py-3 font-mono text-xs text-sl-muted">
                                {{ op.details }}
                            </td>
                            <td class="px-4 py-3 text-right">
                                <ChevronRight class="ml-auto size-4 text-sl-muted" />
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>
    </div>
</template>
