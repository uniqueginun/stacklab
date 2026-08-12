<script setup lang="ts">
import { Form, Head, Link } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import InputError from '@/components/InputError.vue';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { index as serversIndex } from '@/routes/servers';
import { create, index, store } from '@/routes/sites';

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Sites',
                href: index(),
            },
            {
                title: 'Create site',
                href: create(),
            },
        ],
    },
});

type ServerOption = {
    uuid: string;
    name: string;
    host: string;
    profile: string | null;
};

const props = defineProps<{
    servers: ServerOption[];
    selectedServerId: string | null;
}>();

const selectedServerId = ref(
    props.selectedServerId &&
        props.servers.some((server) => server.uuid === props.selectedServerId)
        ? props.selectedServerId
        : (props.servers[0]?.uuid ?? ''),
);

const selectedType = ref<'laravel' | 'php'>('laravel');
const webDirectory = ref('public');

watch(selectedType, (type) => {
    if (type === 'laravel' && (webDirectory.value === '' || webDirectory.value === '.')) {
        webDirectory.value = 'public';
    }

    if (type === 'php' && webDirectory.value === 'public') {
        webDirectory.value = '.';
    }
});

const hasServers = computed(() => props.servers.length > 0);
</script>

<template>
    <Head title="Create site" />

    <div class="mx-auto flex w-full max-w-xl flex-col gap-6">
        <div>
            <h1
                class="text-2xl font-bold tracking-tight text-sl-ink uppercase"
            >
                Create site
            </h1>
            <p class="mt-1 text-sm text-sl-muted">
                Nginx config will be written and validated on the selected
                ready server.
            </p>
        </div>

        <div
            v-if="!hasServers"
            class="rounded-md border border-dashed border-sl-border bg-sl-surface px-6 py-10 text-center"
        >
            <p class="text-sm font-medium text-sl-ink">
                No connected servers
            </p>
            <p class="mx-auto mt-1 max-w-md text-sm text-sl-muted">
                Connect and verify a server over SSH before creating a site.
            </p>
            <Link
                :href="serversIndex()"
                class="mt-4 inline-flex h-9 items-center rounded-md bg-sl-accent px-4 text-sm font-semibold text-white hover:bg-[#a8471a]"
            >
                Go to servers
            </Link>
        </div>

        <Form
            v-else
            v-bind="store.form()"
            class="space-y-5 rounded-md border border-sl-border bg-sl-surface p-6"
            #default="{ errors, processing }"
        >
            <div class="grid gap-2">
                <Label
                    for="domain"
                    class="font-mono text-[11px] tracking-[0.14em] text-sl-muted uppercase"
                >
                    Domain
                </Label>
                <Input
                    id="domain"
                    name="domain"
                    required
                    placeholder="api.example.com"
                    class="h-10 border-sl-border bg-white font-mono"
                />
                <InputError :message="errors.domain" />
            </div>

            <div class="grid gap-2">
                <Label
                    for="server_id"
                    class="font-mono text-[11px] tracking-[0.14em] text-sl-muted uppercase"
                >
                    Server
                </Label>
                <select
                    id="server_id"
                    v-model="selectedServerId"
                    name="server_id"
                    required
                    class="h-10 rounded-md border border-sl-border bg-white px-3 text-sm"
                >
                    <option
                        v-for="server in servers"
                        :key="server.uuid"
                        :value="server.uuid"
                    >
                        {{ server.name }} · {{ server.host }}
                    </option>
                </select>
                <InputError :message="errors.server_id" />
            </div>

            <div class="grid gap-4 sm:grid-cols-2">
                <div class="grid gap-2">
                    <Label
                        for="type"
                        class="font-mono text-[11px] tracking-[0.14em] text-sl-muted uppercase"
                    >
                        Type
                    </Label>
                    <select
                        id="type"
                        v-model="selectedType"
                        name="type"
                        required
                        class="h-10 rounded-md border border-sl-border bg-white px-3 text-sm"
                    >
                        <option value="laravel">Laravel</option>
                        <option value="php">PHP</option>
                    </select>
                    <InputError :message="errors.type" />
                </div>
                <div class="grid gap-2">
                    <Label
                        for="php_version"
                        class="font-mono text-[11px] tracking-[0.14em] text-sl-muted uppercase"
                    >
                        PHP version
                    </Label>
                    <select
                        id="php_version"
                        name="php_version"
                        required
                        class="h-10 rounded-md border border-sl-border bg-white px-3 text-sm"
                    >
                        <option value="8.4">8.4</option>
                        <option value="8.3" selected>8.3</option>
                    </select>
                    <InputError :message="errors.php_version" />
                </div>
            </div>

            <div class="grid gap-2">
                <Label
                    for="web_directory"
                    class="font-mono text-[11px] tracking-[0.14em] text-sl-muted uppercase"
                >
                    Web directory
                </Label>
                <Input
                    id="web_directory"
                    v-model="webDirectory"
                    name="web_directory"
                    required
                    class="h-10 border-sl-border bg-white font-mono"
                />
                <p class="text-xs text-sl-muted">
                    Use
                    <span class="font-mono">public</span>
                    for Laravel, or
                    <span class="font-mono">.</span>
                    for the site root.
                </p>
                <InputError :message="errors.web_directory" />
            </div>

            <div class="flex flex-wrap gap-2 pt-2">
                <button
                    type="submit"
                    class="inline-flex h-10 items-center rounded-md bg-sl-accent px-4 text-sm font-semibold text-white hover:bg-[#a8471a] disabled:opacity-60"
                    :disabled="processing"
                >
                    {{ processing ? 'Creating…' : 'Create site' }}
                </button>
                <Link
                    :href="index()"
                    class="inline-flex h-10 items-center rounded-md border border-sl-border bg-sl-surface px-4 text-sm font-medium text-sl-ink hover:border-sl-accent"
                >
                    Cancel
                </Link>
            </div>
        </Form>
    </div>
</template>
