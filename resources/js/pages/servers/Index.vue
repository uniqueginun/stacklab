<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { computed } from 'vue';
import StatusDot from '@/components/StatusDot.vue';
import { create, index, show } from '@/routes/servers';

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Servers',
                href: index(),
            },
        ],
    },
});

type ServerRow = {
    uuid: string;
    name: string;
    host: string;
    ssh_port: string | number;
    provider: string;
    connection_status: string;
};

defineProps<{
    servers: ServerRow[];
}>();

const providerNames = computed(
    () =>
        ({
            hetzner: 'Hetzner',
            digitalocean: 'DigitalOcean',
            custom: 'Custom VPS',
        }) as Record<string, string>,
);

function statusTone(
    status: string,
): 'success' | 'warning' | 'danger' | 'neutral' {
    switch (status) {
        case 'connected':
            return 'success';
        case 'failed':
            return 'danger';
        case 'unverified':
            return 'warning';
        default:
            return 'neutral';
    }
}

function statusLabel(status: string): string {
    switch (status) {
        case 'connected':
            return 'Connected';
        case 'failed':
            return 'Failed';
        case 'unverified':
            return 'Unverified';
        default:
            return status;
    }
}
</script>

<template>
    <Head title="Servers" />

    <div class="flex flex-col gap-6">
        <div
            class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between"
        >
            <div>
                <h1
                    class="text-2xl font-bold tracking-tight text-sl-ink uppercase"
                >
                    Servers
                </h1>
                <p class="mt-1 max-w-xl text-sm text-sl-muted">
                    Connect infrastructure you already own. StackLab manages it
                    over SSH — no rented control plane.
                </p>
            </div>
            <Link
                :href="create()"
                class="inline-flex h-9 items-center rounded-md bg-sl-accent px-4 text-sm font-semibold text-white transition-colors hover:bg-[#a8471a]"
            >
                Add server
            </Link>
        </div>

        <div
            v-if="servers.length === 0"
            class="rounded-md border border-dashed border-sl-border bg-sl-surface px-6 py-12 text-center"
        >
            <p class="text-sm font-medium text-sl-ink">No servers yet</p>
            <p class="mx-auto mt-1 max-w-md text-sm text-sl-muted">
                Add a VPS you already own. StackLab will generate a management
                key and walk you through connecting over SSH.
            </p>
            <Link
                :href="create()"
                class="mt-4 inline-flex h-9 items-center rounded-md bg-sl-accent px-4 text-sm font-semibold text-white transition-colors hover:bg-[#a8471a]"
            >
                Add server
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
                            <th class="px-4 py-3 font-medium">Name</th>
                            <th class="px-4 py-3 font-medium">Host</th>
                            <th class="px-4 py-3 font-medium">Provider</th>
                            <th class="px-4 py-3 font-medium">Status</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-sl-border/80">
                        <tr
                            v-for="server in servers"
                            :key="server.uuid"
                            class="transition-colors hover:bg-sl-background/70"
                        >
                            <td class="px-4 py-3">
                                <Link
                                    :href="show(server)"
                                    class="font-medium text-sl-ink hover:text-sl-accent"
                                >
                                    {{ server.name }}
                                </Link>
                            </td>
                            <td class="px-4 py-3 font-mono text-xs">
                                {{ server.host }}:{{ server.ssh_port }}
                            </td>
                            <td class="px-4 py-3 text-sl-muted">
                                {{
                                    providerNames[server.provider] ??
                                    server.provider
                                }}
                            </td>
                            <td class="px-4 py-3">
                                <StatusDot
                                    :tone="statusTone(server.connection_status)"
                                    :label="
                                        statusLabel(server.connection_status)
                                    "
                                />
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>
    </div>
</template>
