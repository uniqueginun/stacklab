<script setup lang="ts">
import { Form, Head, Link } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import InputError from '@/components/InputError.vue';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { create, index, store } from '@/routes/servers';

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Servers',
                href: index(),
            },
            {
                title: 'Add server',
                href: create(),
            },
        ],
    },
});

const props = defineProps<{
    providers: Record<string, { name: string }>;
}>();

const selectedProvider = ref(Object.keys(props.providers)[0] ?? 'custom');

const providerMeta: Record<
    string,
    {
        description: string;
        accent: string;
        mark: 'hetzner' | 'digitalocean' | 'custom';
    }
> = {
    hetzner: {
        description: 'Cloud & dedicated hosts',
        accent: 'text-[#D50C2D]',
        mark: 'hetzner',
    },
    digitalocean: {
        description: 'Droplets & VPCs',
        accent: 'text-[#0080FF]',
        mark: 'digitalocean',
    },
    custom: {
        description: 'Any SSH-reachable VPS',
        accent: 'text-sl-accent',
        mark: 'custom',
    },
};

const providerOptions = computed(() =>
    Object.entries(props.providers).map(([key, provider]) => ({
        key,
        name: provider.name,
        description: providerMeta[key]?.description ?? 'Connect over SSH',
        accent: providerMeta[key]?.accent ?? 'text-sl-ink',
        mark: providerMeta[key]?.mark ?? 'custom',
    })),
);
</script>

<template>
    <Head title="Add server" />

    <div class="mx-auto flex w-full max-w-2xl flex-col gap-6">
        <div>
            <h1
                class="text-2xl font-bold tracking-tight text-sl-ink uppercase"
            >
                Add server
            </h1>
            <p class="mt-1 text-sm text-sl-muted">
                Choose where the machine lives, then enter SSH connection
                details. Next, StackLab generates a management key to install on
                the host.
            </p>
        </div>

        <Form
            v-bind="store.form()"
            class="space-y-6 rounded-md border border-sl-border bg-sl-surface p-6"
            v-slot="{ errors, processing }"
        >
            <input type="hidden" name="provider" :value="selectedProvider" />

            <fieldset class="space-y-3">
                <legend
                    class="font-mono text-[11px] tracking-[0.14em] text-sl-muted uppercase"
                >
                    Provider
                </legend>

                <div
                    class="grid gap-3 sm:grid-cols-3"
                    role="radiogroup"
                    aria-label="Provider"
                >
                    <label
                        v-for="provider in providerOptions"
                        :key="provider.key"
                        class="relative cursor-pointer rounded-md border bg-white p-4 transition-colors duration-150"
                        :class="
                            selectedProvider === provider.key
                                ? 'border-sl-accent ring-2 ring-sl-accent/20'
                                : 'border-sl-border hover:border-sl-ink/30'
                        "
                    >
                        <input
                            v-model="selectedProvider"
                            type="radio"
                            name="provider_choice"
                            class="sr-only"
                            :value="provider.key"
                        />

                        <span class="flex items-start gap-3">
                            <span
                                class="flex size-9 shrink-0 items-center justify-center rounded-md border border-sl-border bg-sl-background"
                                :class="provider.accent"
                                aria-hidden="true"
                            >
                                <svg
                                    v-if="provider.mark === 'hetzner'"
                                    class="size-5"
                                    viewBox="0 0 24 24"
                                    fill="currentColor"
                                >
                                    <path
                                        d="M3 3h7.5v7.5H3V3zm10.5 0H21v7.5h-7.5V3zM3 13.5h7.5V21H3v-7.5zm10.5 0H21V21h-7.5v-7.5z"
                                    />
                                </svg>
                                <svg
                                    v-else-if="provider.mark === 'digitalocean'"
                                    class="size-5"
                                    viewBox="0 0 24 24"
                                    fill="currentColor"
                                >
                                    <path
                                        d="M12 2a10 10 0 0 0-7.07 17.07l1.41-1.41A8 8 0 1 1 12 20v2a10 10 0 0 0 0-20zm0 14a4 4 0 1 0 0-8 4 4 0 0 0 0 8z"
                                    />
                                </svg>
                                <svg
                                    v-else
                                    class="size-5"
                                    viewBox="0 0 24 24"
                                    fill="none"
                                    stroke="currentColor"
                                    stroke-width="2"
                                >
                                    <rect
                                        x="3"
                                        y="4"
                                        width="18"
                                        height="16"
                                        rx="2"
                                    />
                                    <path d="M7 8h10M7 12h6M7 16h4" />
                                </svg>
                            </span>

                            <span class="min-w-0">
                                <span
                                    class="block text-sm font-semibold text-sl-ink"
                                >
                                    {{ provider.name }}
                                </span>
                                <span
                                    class="mt-0.5 block text-xs text-sl-muted"
                                >
                                    {{ provider.description }}
                                </span>
                            </span>
                        </span>

                        <span
                            class="absolute top-3 right-3 size-2.5 rounded-full border"
                            :class="
                                selectedProvider === provider.key
                                    ? 'border-sl-accent bg-sl-accent'
                                    : 'border-sl-border bg-white'
                            "
                            aria-hidden="true"
                        />
                    </label>
                </div>
                <InputError :message="errors.provider" />
            </fieldset>

            <div class="grid gap-2">
                <Label
                    for="name"
                    class="font-mono text-[11px] tracking-[0.14em] text-sl-muted uppercase"
                >
                    Name
                </Label>
                <Input
                    id="name"
                    name="name"
                    required
                    class="h-10 border-sl-border bg-white"
                />
                <InputError :message="errors.name" />
            </div>

            <div class="grid gap-4 sm:grid-cols-[1fr_7rem]">
                <div class="grid gap-2">
                    <Label
                        for="host"
                        class="font-mono text-[11px] tracking-[0.14em] text-sl-muted uppercase"
                    >
                        Host
                    </Label>
                    <Input
                        id="host"
                        name="host"
                        required
                        class="h-10 border-sl-border bg-white font-mono"
                    />
                    <InputError :message="errors.host" />
                </div>
                <div class="grid gap-2">
                    <Label
                        for="ssh_port"
                        class="font-mono text-[11px] tracking-[0.14em] text-sl-muted uppercase"
                    >
                        Port
                    </Label>
                    <Input
                        id="ssh_port"
                        name="ssh_port"
                        type="number"
                        min="1"
                        max="65535"
                        :default-value="22"
                        required
                        class="h-10 border-sl-border bg-white font-mono"
                    />
                    <InputError :message="errors.ssh_port" />
                </div>
            </div>

            <div class="grid gap-2">
                <Label
                    for="ssh_user"
                    class="font-mono text-[11px] tracking-[0.14em] text-sl-muted uppercase"
                >
                    SSH user
                </Label>
                <Input
                    id="ssh_user"
                    name="ssh_user"
                    default-value="root"
                    required
                    class="h-10 border-sl-border bg-white font-mono"
                />
                <InputError :message="errors.ssh_user" />
            </div>

            <div class="flex flex-wrap gap-2 pt-2">
                <button
                    type="submit"
                    class="inline-flex h-10 items-center rounded-md bg-sl-accent px-4 text-sm font-semibold text-white hover:bg-[#a8471a] disabled:opacity-60"
                    :disabled="processing"
                >
                    Continue
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
