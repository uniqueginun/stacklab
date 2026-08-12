<script setup lang="ts">
import { Form, Head, setLayoutProps, usePage, usePoll } from '@inertiajs/vue3';
import {
    Check,
    Copy,
    LoaderCircle,
    Square,
} from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import InputError from '@/components/InputError.vue';
import StatusDot from '@/components/StatusDot.vue';
import {
    confirm as sshConfirm,
    connect as sshConnect,
    verify as sshVerify,
} from '@/routes/servers/ssh';
import { retry as retryOperation } from '@/routes/servers/operations';
import { index, provision, show } from '@/routes/servers';

type ConnectionStatus =
    | 'unverified'
    | 'pending_confirmation'
    | 'connected'
    | 'failed';

type OperationStatus = 'pending' | 'running' | 'succeeded' | 'failed';

type OperationStepStatus = 'pending' | 'running' | 'succeeded' | 'failed';

type OperationStep = {
    id: number;
    position: number;
    name: string;
    recipe: string;
    status: OperationStepStatus;
    output: string | null;
    started_at: string | null;
    finished_at: string | null;
};

type Operation = {
    uuid: string;
    type: string;
    status: OperationStatus;
    profile: string | null;
    php_version: string | null;
    failure_message: string | null;
    started_at: string | null;
    finished_at: string | null;
    steps: OperationStep[];
};

type ProvisioningProfileOption = {
    key: string;
    label: string;
    requires_php: boolean;
};

const props = defineProps<{
    server: {
        uuid: string;
        name: string;
        host: string;
        ssh_port: string | number;
        ssh_user: string;
        provider: string;
        connection_status: ConnectionStatus;
        ssh_public_key: string | null;
        host_key_fingerprint: string | null;
        verified_at: string | null;
        has_management_key: boolean;
        can_connect: boolean;
        profile: string | null;
    };
    discoveredFingerprint: string | null;
    operation: Operation | null;
    provisioningProfiles: ProvisioningProfileOption[];
    phpVersions: string[];
    defaultPhpVersion: string;
}>();

const page = usePage();
const copied = ref(false);
const activeTab = ref<'operations' | 'sites' | 'databases' | 'security'>(
    'operations',
);
const selectedProfile = ref(props.provisioningProfiles[0]?.key ?? 'app');
const selectedPhpVersion = ref(props.defaultPhpVersion);

const { start: startPolling, stop: stopPolling } = usePoll(
    2000,
    {
        only: ['operation'],
    },
    {
        autoStart: false,
    },
);

watch(
    () => props.operation?.status,
    (status) => {
        if (status === 'pending' || status === 'running') {
            startPolling();
            return;
        }

        stopPolling();
    },
    { immediate: true },
);

const providerLabel = computed(() => {
    const labels: Record<string, string> = {
        hetzner: 'Hetzner',
        digitalocean: 'DigitalOcean',
        custom: 'Custom VPS',
    };

    return labels[props.server.provider] ?? props.server.provider;
});

const statusTone = computed((): 'success' | 'warning' | 'danger' | 'neutral' => {
    switch (props.server.connection_status) {
        case 'connected':
            return 'success';
        case 'failed':
            return 'danger';
        case 'pending_confirmation':
            return 'warning';
        default:
            return props.server.has_management_key ? 'warning' : 'neutral';
    }
});

const statusLabel = computed(() => {
    switch (props.server.connection_status) {
        case 'connected':
            return 'Connected';
        case 'failed':
            return 'Failed';
        case 'pending_confirmation':
            return 'Confirm fingerprint';
        default:
            return props.server.has_management_key
                ? 'Awaiting verification'
                : 'Unverified';
    }
});

const fingerprint = computed(
    () =>
        props.discoveredFingerprint ??
        (props.server.connection_status === 'connected'
            ? props.server.host_key_fingerprint
            : null),
);

const shortFingerprint = computed(() => {
    const value = fingerprint.value;

    if (!value) {
        return null;
    }

    if (value.length <= 22) {
        return value;
    }

    return `${value.slice(0, 14)}…${value.slice(-3)}`;
});

const sshPhase = computed(() => {
    if (props.server.connection_status === 'connected') {
        return 'connected' as const;
    }

    if (props.server.connection_status === 'failed') {
        return 'failed' as const;
    }

    if (props.discoveredFingerprint) {
        return 'confirm' as const;
    }

    if (props.server.has_management_key) {
        return 'verify' as const;
    }

    return 'connect' as const;
});

const selectedProfileOption = computed(() =>
    props.provisioningProfiles.find(
        (profile) => profile.key === selectedProfile.value,
    ),
);

const requiresPhp = computed(
    () => selectedProfileOption.value?.requires_php ?? false,
);

const operationIsActive = computed(() => {
    const status = props.operation?.status;

    return status === 'pending' || status === 'running';
});

const operationSucceeded = computed(
    () => props.operation?.status === 'succeeded',
);

const showReprovisionForm = ref(false);

watch(
    () => props.operation?.uuid,
    () => {
        showReprovisionForm.value = false;
    },
);

const showProvisioningForm = computed(
    () =>
        props.server.connection_status === 'connected' &&
        !operationIsActive.value &&
        (!operationSucceeded.value || showReprovisionForm.value),
);

const showProvisioningProgress = computed(
    () =>
        props.operation !== null &&
        props.operation.status !== 'succeeded',
);

const canStartProvisioning = computed(
    () =>
        props.server.connection_status === 'connected' && !operationIsActive.value,
);

const provisionSteps = computed(() => {
    if (!props.operation) {
        return [];
    }

    return props.operation.steps.map((step) => {
        const state =
            step.status === 'succeeded'
                ? ('done' as const)
                : step.status === 'running'
                  ? ('running' as const)
                  : step.status === 'failed'
                    ? ('failed' as const)
                    : ('pending' as const);

        return {
            label: step.name,
            detail: stepDetail(step),
            state,
            output: step.output,
        };
    });
});

const completedStepCount = computed(
    () =>
        props.operation?.steps.filter((step) => step.status === 'succeeded')
            .length ?? 0,
);

const totalStepCount = computed(() => props.operation?.steps.length ?? 0);

const progressPercent = computed(() => {
    if (!props.operation || totalStepCount.value === 0) {
        return 0;
    }

    if (props.operation.status === 'succeeded') {
        return 100;
    }

    const runningBonus =
        props.operation.steps.some((step) => step.status === 'running') ? 0.35 : 0;

    return Math.min(
        99,
        Math.round(
            ((completedStepCount.value + runningBonus) / totalStepCount.value) *
                100,
        ),
    );
});

const currentStep = computed(() => {
    if (!props.operation) {
        return null;
    }

    return (
        props.operation.steps.find((step) => step.status === 'running') ??
        props.operation.steps.find((step) => step.status === 'failed') ??
        [...props.operation.steps]
            .reverse()
            .find((step) => step.status === 'succeeded') ??
        props.operation.steps[0] ??
        null
    );
});

const logLines = computed(() => {
    if (!props.operation) {
        return [];
    }

    return props.operation.steps
        .filter((step) => filled(step.output))
        .flatMap((step) => {
            const lines = (step.output ?? '')
                .split(/\r?\n/)
                .map((line) => line.trimEnd())
                .filter((line) => line !== '');

            if (lines.length === 0) {
                return [];
            }

            return [`── ${step.name} ──`, ...lines, ''];
        });
});

const operationStatusLabel = computed(() => {
    switch (props.operation?.status) {
        case 'pending':
            return 'Queued';
        case 'running':
            return `Running · step ${completedStepCount.value + 1}/${totalStepCount.value}`;
        case 'succeeded':
            return 'Succeeded';
        case 'failed':
            return 'Failed';
        default:
            return 'Idle';
    }
});

const provisionError = computed(() => {
    const errors = page.props.errors as Record<string, string> | undefined;

    return (
        errors?.profile ??
        errors?.php_version ??
        errors?.server ??
        props.operation?.failure_message ??
        null
    );
});

const lifecycle = computed(() => {
    const keyDone = props.server.has_management_key;
    const fingerprintReady = Boolean(fingerprint.value);
    const connected = props.server.connection_status === 'connected';
    const provisioning = operationIsActive.value;
    const provisioned =
        props.operation?.status === 'succeeded' || Boolean(props.server.profile);

    return [
        {
            label: 'Management key',
            detail: keyDone ? 'Added' : 'Pending',
            state: keyDone
                ? ('done' as const)
                : ('current' as const),
        },
        {
            label: 'Fingerprint',
            detail: fingerprintReady
                ? `Verified · ${shortFingerprint.value}`
                : keyDone
                  ? 'Awaiting discovery'
                  : 'Pending',
            state: fingerprintReady
                ? ('done' as const)
                : keyDone
                  ? ('current' as const)
                  : ('pending' as const),
        },
        {
            label: 'SSH connection',
            detail: connected
                ? 'Succeeded'
                : fingerprintReady
                  ? 'Confirm to finish'
                  : 'Pending',
            state: connected
                ? ('done' as const)
                : fingerprintReady
                  ? ('current' as const)
                  : ('pending' as const),
        },
        {
            label: 'Provision',
            detail: provisioned
                ? props.operation?.profile
                    ? `Succeeded · ${props.operation.profile}`
                    : 'Succeeded'
                : provisioning
                  ? operationStatusLabel.value
                  : connected
                    ? 'Ready to start'
                    : 'Pending',
            state: provisioned
                ? ('done' as const)
                : provisioning || connected
                  ? ('current' as const)
                  : ('pending' as const),
        },
        {
            label: 'Ready',
            detail: provisioned ? 'Server ready' : 'Pending',
            state: provisioned ? ('done' as const) : ('pending' as const),
        },
    ];
});

function filled(value: string | null | undefined): value is string {
    return typeof value === 'string' && value.trim() !== '';
}

function stepDetail(step: OperationStep): string {
    if (step.status === 'running') {
        return 'running';
    }

    if (step.status === 'pending') {
        return 'pending';
    }

    if (step.status === 'failed') {
        return 'failed';
    }

    if (step.started_at && step.finished_at) {
        const ms =
            new Date(step.finished_at).getTime() -
            new Date(step.started_at).getTime();

        if (Number.isFinite(ms) && ms >= 0) {
            const seconds = Math.max(1, Math.round(ms / 1000));

            if (seconds < 60) {
                return `${seconds}s`;
            }

            const minutes = Math.floor(seconds / 60);
            const remainder = seconds % 60;

            return `${minutes}m ${remainder.toString().padStart(2, '0')}s`;
        }
    }

    return 'done';
}

function scrollToProvisioning(): void {
    if (operationSucceeded.value) {
        showReprovisionForm.value = true;
    }

    document.getElementById('provisioning-panel')?.scrollIntoView({
        behavior: 'smooth',
        block: 'start',
    });
}

const sidebarDetails: [string, string][] = [
    ['Hostname', 'web-01'],
    ['Host', '192.168.56.181:22'],
    ['Provider', 'Custom VPS'],
    ['Plan', '4 vCPU / 8 GB'],
    ['Region', 'fra1'],
    ['OS image', 'debian-12-x64'],
    ['SSH user', 'othman'],
    ['Fingerprint', 'SHA256:9f2c…a01'],
    ['Created', '2026-03-18 10:12'],
    ['Lifecycle', 'Provisioning'],
];

const network: [string, string][] = [
    ['IPv4', '192.168.56.181'],
    ['IPv6', '—'],
    ['Private IPv4', '—'],
    ['Firewall ports', '22, 80, 443'],
];

const health: [string, string][] = [
    ['Agent', 'awaiting install'],
    ['Load', '0.42 · 0.31 · 0.19'],
    ['Disk', '12.4 / 80 GB'],
    ['Uptime', '1h 06m'],
];

const tabs = [
    { key: 'operations' as const, label: 'Operations' },
    { key: 'sites' as const, label: 'Sites' },
    { key: 'databases' as const, label: 'Databases' },
    { key: 'security' as const, label: 'Security' },
];

const sshError = computed(() => {
    const errors = page.props.errors as Record<string, string> | undefined;

    return errors?.message ?? errors?.connection ?? null;
});

setLayoutProps({
    breadcrumbs: [
        {
            title: 'Servers',
            href: index(),
        },
        {
            title: props.server.name,
            href: show(props.server),
        },
    ],
});

async function copyPublicKey(): Promise<void> {
    if (!props.server.ssh_public_key) {
        return;
    }

    await navigator.clipboard.writeText(props.server.ssh_public_key);
    copied.value = true;
    window.setTimeout(() => {
        copied.value = false;
    }, 1600);
}
</script>

<template>
    <Head :title="server.name" />

    <div class="flex flex-col gap-6">
        <div
            class="flex flex-col gap-4 border-b border-sl-border pb-6 lg:flex-row lg:items-start lg:justify-between"
        >
            <div>
                <p
                    class="font-mono text-[11px] tracking-[0.16em] text-sl-muted uppercase"
                >
                    Server detail
                </p>
                <div class="mt-1 flex flex-wrap items-center gap-3">
                    <h1
                        class="font-mono text-3xl font-bold tracking-tight text-sl-ink uppercase"
                    >
                        {{ server.name }}
                    </h1>
                    <StatusDot :tone="statusTone" :label="statusLabel" />
                </div>
                <p class="mt-2 font-mono text-sm text-sl-muted">
                    {{ server.ssh_user }}@{{ server.host }}:{{ server.ssh_port }}
                    · debian 12 · {{ providerLabel.toLowerCase() }}
                </p>
            </div>

            <div class="flex flex-wrap items-center gap-2">
                <button
                    type="button"
                    class="inline-flex h-9 items-center rounded-md border border-sl-ink/80 bg-sl-surface px-3 text-sm font-medium text-sl-ink"
                >
                    Open shell
                </button>
                <button
                    type="button"
                    class="inline-flex h-9 items-center rounded-md border border-sl-ink/80 bg-sl-surface px-3 text-sm font-medium text-sl-ink"
                >
                    Re-run checks
                </button>
                <button
                    type="button"
                    class="inline-flex h-9 items-center rounded-md bg-sl-accent px-3 text-sm font-semibold text-white hover:bg-[#a8471a] disabled:opacity-60"
                    :disabled="!canStartProvisioning"
                    @click="scrollToProvisioning"
                >
                    {{
                        operationIsActive
                            ? 'Provisioning…'
                            : props.operation?.status === 'succeeded'
                              ? 'Re-provision'
                              : 'Provision now'
                    }}
                </button>
            </div>
        </div>

        <section
            class="rounded-md border border-sl-border bg-sl-surface px-4 py-5"
        >
            <ol class="grid gap-4 sm:grid-cols-5">
                <li
                    v-for="(step, index) in lifecycle"
                    :key="step.label"
                    class="flex flex-col gap-2"
                >
                    <div class="flex items-center gap-2">
                        <span
                            class="flex size-7 items-center justify-center rounded-full text-xs font-semibold"
                            :class="{
                                'bg-emerald-500 text-white':
                                    step.state === 'done',
                                'bg-sl-accent text-white':
                                    step.state === 'current',
                                'border border-sl-border bg-sl-background text-sl-muted':
                                    step.state === 'pending',
                            }"
                        >
                            <Check
                                v-if="step.state === 'done'"
                                class="size-3.5"
                            />
                            <template v-else>{{ index + 1 }}</template>
                        </span>
                        <p class="text-sm font-medium text-sl-ink">
                            {{ step.label }}
                        </p>
                    </div>
                    <p class="pl-9 font-mono text-xs text-sl-muted">
                        {{ step.detail }}
                    </p>
                </li>
            </ol>
        </section>

        <div
            class="grid gap-4 xl:grid-cols-[minmax(0,1.55fr)_minmax(18rem,0.75fr)]"
        >
            <div class="flex flex-col gap-4">
                <div class="flex flex-wrap gap-1 border-b border-sl-border">
                    <button
                        v-for="tab in tabs"
                        :key="tab.key"
                        type="button"
                        class="px-3 py-2 font-mono text-[11px] tracking-[0.14em] uppercase transition-colors"
                        :class="
                            activeTab === tab.key
                                ? 'bg-sl-ink text-white'
                                : 'text-sl-muted hover:text-sl-ink'
                        "
                        @click="activeTab = tab.key"
                    >
                        {{ tab.label }}
                    </button>
                </div>

                <template v-if="activeTab === 'operations'">
                    <section
                        id="provisioning-panel"
                        class="rounded-md border border-sl-border bg-sl-surface"
                    >
                        <div
                            class="flex flex-wrap items-start justify-between gap-3 border-b border-sl-border px-4 py-3"
                        >
                            <div class="space-y-1">
                                <div class="flex items-center gap-2">
                                    <Square
                                        class="size-3.5 fill-sl-accent text-sl-accent"
                                    />
                                    <h2 class="text-sm font-medium text-sl-ink">
                                        Server provisioning
                                    </h2>
                                </div>
                                <p class="font-mono text-xs text-sl-muted">
                                    <template v-if="operation">
                                        {{ operation.uuid.slice(0, 8) }}
                                        <template v-if="operation.profile">
                                            · {{ operation.profile }}
                                        </template>
                                        <template v-if="operation.php_version">
                                            · php {{ operation.php_version }}
                                        </template>
                                        <template v-if="operation.started_at">
                                            · started {{ operation.started_at }}
                                        </template>
                                    </template>
                                    <template v-else>
                                        Choose a profile to provision this
                                        server
                                    </template>
                                </p>
                            </div>
                            <div
                                v-if="operation"
                                class="flex items-center gap-3"
                            >
                                <span
                                    class="inline-flex items-center gap-1.5 rounded-md border px-2 py-1 font-mono text-[11px] tracking-wide uppercase"
                                    :class="{
                                        'border-sl-accent/30 bg-sl-accent/10 text-sl-accent':
                                            operationIsActive,
                                        'border-emerald-200 bg-emerald-50 text-emerald-700':
                                            operation.status === 'succeeded',
                                        'border-red-200 bg-red-50 text-red-700':
                                            operation.status === 'failed',
                                    }"
                                >
                                    <span
                                        class="size-1.5 rounded-full"
                                        :class="{
                                            'animate-pulse bg-sl-accent':
                                                operationIsActive,
                                            'bg-emerald-500':
                                                operation.status === 'succeeded',
                                            'bg-red-500':
                                                operation.status === 'failed',
                                        }"
                                    />
                                    {{ operationStatusLabel }}
                                </span>
                                <Form
                                    v-if="operation.status === 'failed'"
                                    v-bind="
                                        retryOperation.form([server, operation])
                                    "
                                    #default="{ processing }"
                                >
                                    <button
                                        type="submit"
                                        class="text-xs font-medium text-sl-accent hover:text-[#a8471a] disabled:opacity-60"
                                        :disabled="processing"
                                    >
                                        {{
                                            processing
                                                ? 'Retrying…'
                                                : 'Retry failed step'
                                        }}
                                    </button>
                                </Form>
                            </div>
                        </div>

                        <div
                            v-if="showProvisioningForm"
                            class="space-y-4 border-b border-sl-border px-4 py-4"
                        >
                            <InputError :message="provisionError ?? undefined" />

                            <p
                                v-if="operationSucceeded"
                                class="text-sm text-sl-muted"
                            >
                                This starts a
                                <span class="font-medium text-sl-ink"
                                    >new</span
                                >
                                provisioning operation with a fresh set of
                                steps.
                            </p>

                            <Form
                                v-bind="provision.form(server)"
                                class="space-y-4"
                                #default="{ processing }"
                            >
                                <div
                                    class="grid gap-4 sm:grid-cols-2"
                                >
                                    <label class="space-y-1.5">
                                        <span
                                            class="font-mono text-[11px] tracking-[0.12em] text-sl-muted uppercase"
                                        >
                                            Profile
                                        </span>
                                        <select
                                            v-model="selectedProfile"
                                            name="profile"
                                            class="h-9 w-full rounded-md border border-sl-border bg-sl-background px-3 text-sm text-sl-ink"
                                        >
                                            <option
                                                v-for="profile in provisioningProfiles"
                                                :key="profile.key"
                                                :value="profile.key"
                                            >
                                                {{ profile.label }}
                                            </option>
                                        </select>
                                    </label>

                                    <label
                                        v-if="requiresPhp"
                                        class="space-y-1.5"
                                    >
                                        <span
                                            class="font-mono text-[11px] tracking-[0.12em] text-sl-muted uppercase"
                                        >
                                            PHP version
                                        </span>
                                        <select
                                            v-model="selectedPhpVersion"
                                            name="php_version"
                                            class="h-9 w-full rounded-md border border-sl-border bg-sl-background px-3 text-sm text-sl-ink"
                                        >
                                            <option
                                                v-for="version in phpVersions"
                                                :key="version"
                                                :value="version"
                                            >
                                                {{ version }}
                                            </option>
                                        </select>
                                    </label>
                                </div>

                                <div class="flex flex-wrap items-center gap-3">
                                    <button
                                        type="submit"
                                        class="inline-flex h-9 items-center rounded-md bg-sl-accent px-3 text-sm font-semibold text-white hover:bg-[#a8471a] disabled:opacity-60"
                                        :disabled="processing"
                                    >
                                        {{
                                            processing
                                                ? 'Starting…'
                                                : operationSucceeded
                                                  ? 'Start new provisioning'
                                                  : operation
                                                    ? 'Start provisioning again'
                                                    : 'Start provisioning'
                                        }}
                                    </button>
                                    <button
                                        v-if="operationSucceeded"
                                        type="button"
                                        class="text-xs font-medium text-sl-muted hover:text-sl-ink"
                                        @click="showReprovisionForm = false"
                                    >
                                        Cancel
                                    </button>
                                </div>
                            </Form>
                        </div>

                        <div
                            v-else-if="operationSucceeded"
                            class="px-4 py-4 text-sm text-sl-muted"
                        >
                            Provisioning completed
                            <template v-if="operation?.profile">
                                with the
                                <span class="font-mono text-sl-ink">{{
                                    operation.profile
                                }}</span>
                                profile
                            </template>
                            .
                            <button
                                type="button"
                                class="ml-1 font-medium text-sl-accent hover:text-[#a8471a]"
                                @click="showReprovisionForm = true"
                            >
                                Re-provision
                            </button>
                        </div>

                        <template v-if="showProvisioningProgress">
                            <div class="space-y-3 px-4 py-3">
                                <div
                                    class="flex items-center justify-between gap-3 font-mono text-xs text-sl-muted"
                                >
                                    <span
                                        >{{ progressPercent }}% ·
                                        {{ completedStepCount }}/{{
                                            totalStepCount
                                        }}
                                        steps</span
                                    >
                                </div>
                                <div
                                    class="h-1.5 overflow-hidden rounded-full bg-sl-background"
                                >
                                    <div
                                        class="h-full rounded-full bg-sl-accent transition-[width] duration-500"
                                        :style="{
                                            width: `${progressPercent}%`,
                                        }"
                                    />
                                </div>
                            </div>

                            <ol
                                class="divide-y divide-sl-border/80 border-t border-sl-border"
                            >
                                <li
                                    v-for="(step, index) in provisionSteps"
                                    :key="`${step.label}-${index}`"
                                    class="flex items-center gap-3 px-4 py-3"
                                >
                                    <span
                                        class="flex size-6 shrink-0 items-center justify-center rounded-full text-[11px] font-semibold"
                                        :class="{
                                            'bg-emerald-500 text-white':
                                                step.state === 'done',
                                            'bg-sl-accent text-white':
                                                step.state === 'running',
                                            'bg-red-500 text-white':
                                                step.state === 'failed',
                                            'border border-sl-border text-sl-muted':
                                                step.state === 'pending',
                                        }"
                                    >
                                        <Check
                                            v-if="step.state === 'done'"
                                            class="size-3.5"
                                        />
                                        <LoaderCircle
                                            v-else-if="step.state === 'running'"
                                            class="size-3.5 animate-spin"
                                        />
                                        <template v-else>{{
                                            index + 1
                                        }}</template>
                                    </span>
                                    <p
                                        class="min-w-0 flex-1 text-sm font-medium text-sl-ink"
                                    >
                                        {{ step.label }}
                                    </p>
                                    <p class="font-mono text-xs text-sl-muted">
                                        {{ step.detail }}
                                    </p>
                                </li>
                            </ol>

                            <div class="border-t border-sl-border">
                                <div
                                    class="flex flex-wrap items-center justify-between gap-2 border-b border-neutral-800 bg-[#171717] px-4 py-2"
                                >
                                    <p
                                        class="font-mono text-[11px] tracking-[0.12em] text-neutral-400 uppercase"
                                    >
                                        Output
                                        <template v-if="currentStep">
                                            · step {{ currentStep.position }} —
                                            {{ currentStep.name }}
                                        </template>
                                    </p>
                                </div>
                                <div
                                    class="max-h-72 space-y-1 overflow-y-auto bg-[#171717] px-4 py-4 font-mono text-[12px] leading-relaxed text-neutral-200"
                                >
                                    <template v-if="logLines.length > 0">
                                        <p
                                            v-for="(line, index) in logLines"
                                            :key="`${index}-${line}`"
                                            :class="
                                                line.startsWith('──')
                                                    ? 'text-neutral-500'
                                                    : 'text-emerald-300/90'
                                            "
                                        >
                                            {{ line || '\u00a0' }}
                                        </p>
                                    </template>
                                    <p
                                        v-else-if="operationIsActive"
                                        class="text-neutral-500"
                                    >
                                        Waiting for step output…
                                    </p>
                                    <p v-else class="text-neutral-500">
                                        No output yet.
                                    </p>
                                </div>
                            </div>
                        </template>

                        <div
                            v-else-if="server.connection_status !== 'connected'"
                            class="px-4 py-6 text-sm text-sl-muted"
                        >
                            Complete the SSH connection check before
                            provisioning.
                        </div>
                    </section>

                    <!-- SSH connection (wired) -->
                    <section
                        v-if="sshPhase === 'connected'"
                        class="rounded-md border border-sl-border bg-sl-surface"
                    >
                        <div
                            class="flex flex-wrap items-start justify-between gap-3 px-4 py-3"
                        >
                            <div class="space-y-1">
                                <div class="flex items-center gap-2">
                                    <span
                                        class="size-2.5 rounded-full bg-emerald-500"
                                    />
                                    <h2 class="text-sm font-medium text-sl-ink">
                                        SSH connection check
                                    </h2>
                                </div>
                                <p class="font-mono text-xs text-sl-muted">
                                    verified
                                    <template v-if="server.verified_at">
                                        · {{ server.verified_at }}
                                    </template>
                                    · 3 steps
                                </p>
                            </div>
                            <span
                                class="inline-flex items-center gap-1.5 rounded-md border border-emerald-200 bg-emerald-50 px-2 py-1 font-mono text-[11px] tracking-wide text-emerald-700 uppercase"
                            >
                                <span
                                    class="size-1.5 rounded-full bg-emerald-500"
                                />
                                Succeeded
                            </span>
                        </div>
                        <div
                            class="flex flex-wrap gap-x-4 gap-y-2 border-t border-sl-border px-4 py-3 font-mono text-xs text-sl-ink"
                        >
                            <span class="text-emerald-600">✓ key accepted</span>
                            <span class="text-emerald-600"
                                >✓ host fingerprint matched</span
                            >
                            <span class="text-emerald-600"
                                >✓ sudo without password</span
                            >
                        </div>
                    </section>

                    <section
                        v-else
                        class="rounded-md border border-sl-border bg-sl-surface"
                    >
                        <div
                            class="flex flex-wrap items-start justify-between gap-3 border-b border-sl-border px-4 py-3"
                        >
                            <div class="space-y-1">
                                <div class="flex items-center gap-2">
                                    <span
                                        class="size-2.5 rounded-full"
                                        :class="
                                            sshPhase === 'failed'
                                                ? 'bg-red-500'
                                                : 'bg-sl-accent'
                                        "
                                    />
                                    <h2 class="text-sm font-medium text-sl-ink">
                                        SSH connection check
                                    </h2>
                                </div>
                                <p class="font-mono text-xs text-sl-muted">
                                    <template v-if="sshPhase === 'connect'">
                                        Step 1/3 · generate management key
                                    </template>
                                    <template v-else-if="sshPhase === 'verify'">
                                        Step 2/3 · discover host fingerprint
                                    </template>
                                    <template v-else-if="sshPhase === 'confirm'">
                                        Step 3/3 · trust fingerprint & verify
                                        login
                                    </template>
                                    <template v-else>
                                        failed · retry connection
                                    </template>
                                </p>
                            </div>
                            <span
                                class="inline-flex items-center gap-1.5 rounded-md border px-2 py-1 font-mono text-[11px] tracking-wide uppercase"
                                :class="
                                    sshPhase === 'failed'
                                        ? 'border-red-200 bg-red-50 text-red-700'
                                        : 'border-sl-accent/30 bg-sl-accent/10 text-sl-accent'
                                "
                            >
                                <span
                                    class="size-1.5 rounded-full"
                                    :class="
                                        sshPhase === 'failed'
                                            ? 'bg-red-500'
                                            : 'bg-sl-accent'
                                    "
                                />
                                {{
                                    sshPhase === 'failed'
                                        ? 'Failed'
                                        : 'In progress'
                                }}
                            </span>
                        </div>

                        <div class="space-y-4 px-4 py-4">
                            <InputError :message="sshError ?? undefined" />

                            <div
                                v-if="server.has_management_key && server.ssh_public_key"
                                class="space-y-2"
                            >
                                <p class="text-sm text-sl-muted">
                                    Install this management public key in
                                    <span class="font-mono text-sl-ink"
                                        >~/.ssh/authorized_keys</span
                                    >
                                    for
                                    <span class="font-mono text-sl-ink">{{
                                        server.ssh_user
                                    }}</span>
                                    and enable passwordless sudo before trusting
                                    the connection.
                                </p>
                                <div
                                    class="relative rounded-md border border-sl-border bg-sl-background p-3"
                                >
                                    <pre
                                        class="overflow-x-auto pr-10 font-mono text-[12px] break-all whitespace-pre-wrap text-sl-ink"
                                        >{{ server.ssh_public_key }}</pre
                                    >
                                    <button
                                        type="button"
                                        class="absolute top-2 right-2 inline-flex size-8 items-center justify-center rounded-md border border-sl-border bg-sl-surface text-sl-muted hover:text-sl-ink"
                                        :title="copied ? 'Copied' : 'Copy'"
                                        @click="copyPublicKey"
                                    >
                                        <Check
                                            v-if="copied"
                                            class="size-3.5 text-emerald-600"
                                        />
                                        <Copy v-else class="size-3.5" />
                                    </button>
                                </div>
                            </div>

                            <template
                                v-if="
                                    sshPhase === 'connect' ||
                                    (sshPhase === 'failed' &&
                                        !server.has_management_key)
                                "
                            >
                                <p class="text-sm text-sl-muted">
                                    StackLab needs a management keypair on this
                                    server record. Generate it, then install the
                                    public key on the host for
                                    <span class="font-mono text-sl-ink">{{
                                        server.ssh_user
                                    }}</span>
                                    with passwordless sudo.
                                </p>
                                <Form
                                    v-bind="sshConnect.form(server)"
                                    #default="{ processing }"
                                >
                                    <button
                                        type="submit"
                                        class="inline-flex h-9 items-center rounded-md bg-sl-accent px-3 text-sm font-semibold text-white hover:bg-[#a8471a] disabled:opacity-60"
                                        :disabled="processing"
                                    >
                                        {{
                                            processing
                                                ? 'Generating…'
                                                : 'Generate management key'
                                        }}
                                    </button>
                                </Form>
                            </template>

                            <template
                                v-else-if="
                                    sshPhase === 'verify' ||
                                    (sshPhase === 'failed' &&
                                        server.has_management_key &&
                                        !discoveredFingerprint)
                                "
                            >
                                <Form
                                    v-bind="sshVerify.form(server)"
                                    #default="{ processing }"
                                >
                                    <button
                                        type="submit"
                                        class="inline-flex h-9 items-center rounded-md bg-sl-accent px-3 text-sm font-semibold text-white hover:bg-[#a8471a] disabled:opacity-60"
                                        :disabled="processing"
                                    >
                                        {{
                                            processing
                                                ? 'Discovering…'
                                                : 'Verify connection'
                                        }}
                                    </button>
                                </Form>
                            </template>

                            <template v-else-if="sshPhase === 'confirm'">
                                <div class="space-y-2">
                                    <p class="text-sm text-sl-muted">
                                        Compare this fingerprint on the VM
                                        (e.g.
                                        <span class="font-mono text-sl-ink"
                                            >ssh-keygen -lf
                                            /etc/ssh/ssh_host_ed25519_key.pub</span
                                        >). Trust it only if it matches.
                                    </p>
                                    <div
                                        class="rounded-md border border-sl-border bg-sl-background px-3 py-3 font-mono text-sm text-sl-ink"
                                    >
                                        {{ discoveredFingerprint }}
                                    </div>
                                </div>
                                <div class="flex flex-wrap items-center gap-3">
                                    <Form
                                        v-bind="sshConfirm.form(server)"
                                        #default="{ processing }"
                                    >
                                        <button
                                            type="submit"
                                            class="inline-flex h-9 items-center rounded-md bg-sl-accent px-3 text-sm font-semibold text-white hover:bg-[#a8471a] disabled:opacity-60"
                                            :disabled="processing"
                                        >
                                            {{
                                                processing
                                                    ? 'Verifying…'
                                                    : 'Trust and verify SSH'
                                            }}
                                        </button>
                                    </Form>
                                    <Form
                                        v-bind="sshVerify.form(server)"
                                        #default="{ processing: rediscovering }"
                                    >
                                        <button
                                            type="submit"
                                            class="text-xs font-medium text-sl-muted hover:text-sl-ink disabled:opacity-60"
                                            :disabled="rediscovering"
                                        >
                                            Rediscover fingerprint
                                        </button>
                                    </Form>
                                </div>
                            </template>

                            <template v-else-if="sshPhase === 'failed'">
                                <p
                                    class="rounded-md border border-red-200 bg-red-50 px-3 py-2 font-mono text-xs text-red-700"
                                >
                                    {{
                                        sshError ??
                                        'SSH verification failed. Retry after fixing host access.'
                                    }}
                                </p>
                                <Form
                                    v-bind="
                                        server.has_management_key
                                            ? sshVerify.form(server)
                                            : sshConnect.form(server)
                                    "
                                    #default="{ processing }"
                                >
                                    <button
                                        type="submit"
                                        class="inline-flex h-9 items-center rounded-md border border-sl-ink/80 bg-sl-surface px-3 text-sm font-medium text-sl-ink disabled:opacity-60"
                                        :disabled="processing"
                                    >
                                        {{ processing ? 'Retrying…' : 'Retry' }}
                                    </button>
                                </Form>
                            </template>
                        </div>
                    </section>
                </template>

                <section
                    v-else
                    class="rounded-md border border-dashed border-sl-border bg-sl-surface px-4 py-10 text-center"
                >
                    <p
                        class="font-mono text-[11px] tracking-[0.14em] text-sl-muted uppercase"
                    >
                        {{ activeTab }}
                    </p>
                    <p class="mt-2 text-sm text-sl-muted">
                        Placeholder — this tab is not wired yet.
                    </p>
                </section>
            </div>

            <aside class="flex flex-col gap-4">
                <section
                    class="rounded-md border border-sl-border bg-sl-surface"
                >
                    <h2
                        class="border-b border-sl-border px-4 py-3 text-sm font-medium text-sl-ink"
                    >
                        Server details
                    </h2>
                    <dl class="divide-y divide-sl-border/80">
                        <div
                            v-for="[label, value] in sidebarDetails"
                            :key="label"
                            class="flex items-start justify-between gap-3 px-4 py-2.5 text-sm"
                        >
                            <dt class="text-sl-muted">{{ label }}</dt>
                            <dd
                                class="text-right font-mono text-xs break-all text-sl-ink"
                                :class="{
                                    'text-sl-accent': label === 'Lifecycle',
                                }"
                            >
                                <template v-if="label === 'Hostname'">{{
                                    server.name
                                }}</template>
                                <template v-else-if="label === 'Host'"
                                    >{{ server.host }}:{{
                                        server.ssh_port
                                    }}</template
                                >
                                <template v-else-if="label === 'Provider'">{{
                                    providerLabel
                                }}</template>
                                <template v-else-if="label === 'SSH user'">{{
                                    server.ssh_user
                                }}</template>
                                <template v-else-if="label === 'Fingerprint'">{{
                                    shortFingerprint ?? '—'
                                }}</template>
                                <template v-else-if="label === 'Lifecycle'">{{
                                    statusLabel
                                }}</template>
                                <template v-else>{{ value }}</template>
                            </dd>
                        </div>
                    </dl>
                </section>

                <section
                    class="rounded-md border border-sl-border bg-sl-surface"
                >
                    <h2
                        class="border-b border-sl-border px-4 py-3 text-sm font-medium text-sl-ink"
                    >
                        Network
                    </h2>
                    <dl class="divide-y divide-sl-border/80">
                        <div
                            v-for="[label, value] in network"
                            :key="label"
                            class="flex items-start justify-between gap-3 px-4 py-2.5 text-sm"
                        >
                            <dt class="text-sl-muted">{{ label }}</dt>
                            <dd class="text-right font-mono text-xs text-sl-ink">
                                <template v-if="label === 'IPv4'">{{
                                    server.host
                                }}</template>
                                <template v-else>{{ value }}</template>
                            </dd>
                        </div>
                    </dl>
                </section>

                <section
                    class="rounded-md border border-sl-border bg-sl-surface"
                >
                    <h2
                        class="border-b border-sl-border px-4 py-3 text-sm font-medium text-sl-ink"
                    >
                        Health
                    </h2>
                    <dl class="divide-y divide-sl-border/80">
                        <div
                            v-for="[label, value] in health"
                            :key="label"
                            class="flex items-start justify-between gap-3 px-4 py-2.5 text-sm"
                        >
                            <dt class="text-sl-muted">{{ label }}</dt>
                            <dd class="text-right font-mono text-xs text-sl-ink">
                                {{ value }}
                            </dd>
                        </div>
                    </dl>
                </section>

                <div class="rounded-md border border-red-200 bg-sl-surface p-4">
                    <p
                        class="mb-3 font-mono text-[11px] tracking-[0.14em] text-red-600 uppercase"
                    >
                        Danger zone
                    </p>
                    <button
                        type="button"
                        class="inline-flex h-9 w-full items-center justify-center rounded-md border border-red-500 px-3 text-sm font-medium text-red-600"
                    >
                        Destroy server
                    </button>
                    <p class="mt-2 text-center text-xs text-sl-muted">
                        This action cannot be undone.
                    </p>
                </div>
            </aside>
        </div>
    </div>
</template>
