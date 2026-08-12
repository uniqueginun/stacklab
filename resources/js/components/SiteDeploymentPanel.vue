<script setup lang="ts">
import { Link, useForm } from '@inertiajs/vue3';
import { Check, LoaderCircle } from '@lucide/vue';
import { computed } from 'vue';
import { redirect as githubRedirect } from '@/actions/App/Http/Controllers/GitHubConnectionController';
import { store } from '@/actions/App/Http/Controllers/SiteDeployController';
import InputError from '@/components/InputError.vue';
import { Checkbox } from '@/components/ui/checkbox';
import { Label } from '@/components/ui/label';
import { show } from '@/routes/sites';

type DeploymentOptions = {
    run_composer: boolean;
    run_npm: boolean;
    run_migrations: boolean;
    run_caches: boolean;
    run_queue_restart: boolean;
    run_hook: boolean;
};

type SiteSummary = {
    uuid: string;
    domain: string;
    type: string;
    repository: string | null;
    branch: string | null;
    status: string;
    deployed_at: string | null;
    deployment_options: DeploymentOptions;
    current_release: {
        uuid: string;
        commit_sha: string;
    } | null;
};

type Release = {
    uuid: string;
    commit_sha: string | null;
    commit_message: string | null;
    status: string;
    failure_message: string | null;
    deployed_at: string | null;
    created_at: string | null;
    is_current: boolean;
    can_rollback: boolean;
};

type ActiveOperation = {
    uuid: string;
    type: string;
    status: string;
    failure_message: string | null;
} | null;

const laravelOptions = [
    {
        key: 'run_composer',
        label: 'Install Composer dependencies',
        hint: 'composer install --no-dev --optimize-autoloader',
    },
    {
        key: 'run_npm',
        label: 'Install NPM dependencies and build assets',
        hint: 'npm ci && npm run build',
    },
    {
        key: 'run_migrations',
        label: 'Run database migrations',
        hint: 'php artisan migrate --force',
    },
    {
        key: 'run_caches',
        label: 'Cache config, routes, and views',
        hint: 'php artisan config:cache, route:cache, view:cache',
    },
    {
        key: 'run_queue_restart',
        label: 'Restart queue workers',
        hint: 'php artisan queue:restart',
    },
    {
        key: 'run_hook',
        label: 'Run deploy.sh hook',
        hint: 'Execute an executable deploy.sh in the release if present',
    },
] as const;

const props = defineProps<{
    site: SiteSummary;
    releases: Release[];
    githubConnected: boolean;
    githubUsername: string | null;
    activeOperation: ActiveOperation;
}>();

const emit = defineEmits<{
    'open-repository': [];
}>();

const form = useForm({
    run_composer: props.site.deployment_options.run_composer,
    run_npm: props.site.deployment_options.run_npm,
    run_migrations: props.site.deployment_options.run_migrations,
    run_caches: props.site.deployment_options.run_caches,
    run_queue_restart: props.site.deployment_options.run_queue_restart,
    run_hook: props.site.deployment_options.run_hook,
});

const connectGitHub = computed(() =>
    githubRedirect({
        query: {
            return: show.url(props.site, { query: { tab: 'deployment' } }),
        },
    }),
);

const isLaravel = computed(() => props.site.type === 'laravel');
const operationInProgress = computed(
    () =>
        props.activeOperation?.status === 'pending' ||
        props.activeOperation?.status === 'running',
);
const canStartDeploy = computed(
    () =>
        props.githubConnected &&
        Boolean(props.site.repository) &&
        props.site.status === 'active' &&
        !operationInProgress.value,
);

const operationLabel = computed(() => {
    if (!props.activeOperation) {
        return null;
    }

    const type =
        props.activeOperation.type === 'rollback' ? 'Rollback' : 'Deploy';

    return `${type} · ${props.activeOperation.status}`;
});

const shortCommit = (sha: string | null | undefined): string => {
    if (!sha) {
        return '—';
    }

    return sha.slice(0, 7);
};

const formatTimestamp = (value: string | null): string => {
    if (!value) {
        return '—';
    }

    try {
        return new Date(value).toLocaleString();
    } catch {
        return value;
    }
};

function setDeployOption(
    key: keyof DeploymentOptions,
    value: boolean | 'indeterminate',
): void {
    if (value === 'indeterminate') {
        return;
    }

    form[key] = value;
}

function submit(): void {
    form.post(store.url(props.site), {
        preserveScroll: true,
    });
}
</script>

<template>
    <div class="flex flex-col gap-4">
        <section class="rounded-md border border-sl-border bg-sl-surface p-4">
            <div
                class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between"
            >
                <div>
                    <p class="text-sm font-medium text-sl-ink">
                        {{ site.repository ?? site.domain }}
                    </p>
                    <p class="mt-1 font-mono text-xs text-sl-muted">
                        <template v-if="site.branch">
                            branch {{ site.branch }}
                        </template>
                        <template v-if="site.current_release">
                            ·
                            {{ shortCommit(site.current_release.commit_sha) }}
                        </template>
                        <template v-if="site.deployed_at">
                            · {{ formatTimestamp(site.deployed_at) }}
                        </template>
                        <template
                            v-if="
                                !site.branch &&
                                !site.current_release &&
                                !site.deployed_at
                            "
                        >
                            Waiting for first deploy
                        </template>
                    </p>
                </div>
                <button
                    type="button"
                    class="inline-flex h-9 items-center justify-center rounded-md bg-sl-accent px-4 text-sm font-semibold text-white hover:bg-[#a8471a] disabled:opacity-60"
                    :disabled="!canStartDeploy || form.processing"
                    @click="submit"
                >
                    {{ form.processing ? 'Starting…' : 'Deploy release' }}
                </button>
            </div>

            <InputError class="mt-3" :message="form.errors.site" />

            <p
                v-if="!githubConnected"
                class="mt-3 text-sm text-sl-muted"
            >
                <Link
                    :href="connectGitHub"
                    class="font-medium text-sl-accent hover:underline"
                >
                    Connect GitHub
                </Link>
                to deploy from the selected branch.
            </p>
            <p
                v-else-if="!site.repository"
                class="mt-3 text-sm text-sl-muted"
            >
                <button
                    type="button"
                    class="font-medium text-sl-accent hover:underline"
                    @click="emit('open-repository')"
                >
                    Attach a repository
                </button>
                before deploying.
            </p>
            <p
                v-else-if="site.status !== 'active'"
                class="mt-3 text-sm text-sl-muted"
            >
                Wait until the site Nginx configuration is active before
                deploying.
            </p>
            <p
                v-else-if="operationInProgress"
                class="mt-3 text-sm text-sl-muted"
            >
                A deployment is already running on this server.
            </p>
        </section>

        <section
            v-if="isLaravel && githubConnected && site.repository"
            class="rounded-md border border-sl-border bg-sl-surface p-4"
        >
            <h2 class="text-sm font-medium text-sl-ink">Deploy options</h2>
            <p class="mt-1 text-sm text-sl-muted">
                These commands run during the next Laravel deploy.
            </p>

            <div class="mt-4 flex flex-col gap-3">
                <Label
                    v-for="option in laravelOptions"
                    :key="option.key"
                    :for="option.key"
                    class="flex items-start gap-3 text-sm font-normal text-sl-ink"
                >
                    <Checkbox
                        :id="option.key"
                        :checked="form[option.key]"
                        :disabled="!canStartDeploy || form.processing"
                        class="mt-0.5 border-sl-border data-[state=checked]:border-sl-accent data-[state=checked]:bg-sl-accent"
                        @update:checked="
                            (value) => setDeployOption(option.key, value)
                        "
                    />
                    <span class="min-w-0">
                        <span class="block">{{ option.label }}</span>
                        <span class="mt-0.5 block font-mono text-xs text-sl-muted">
                            {{ option.hint }}
                        </span>
                    </span>
                </Label>
            </div>
        </section>

        <section
            v-if="activeOperation"
            class="rounded-md border border-sl-border bg-sl-surface px-4 py-3"
        >
            <div class="flex items-center gap-2">
                <LoaderCircle
                    v-if="operationInProgress"
                    class="size-4 animate-spin text-sl-accent"
                />
                <span
                    class="font-mono text-xs tracking-wide text-sl-accent uppercase"
                >
                    {{ operationLabel }}
                </span>
            </div>
            <p
                v-if="activeOperation.failure_message"
                class="mt-2 font-mono text-xs text-red-700"
            >
                {{ activeOperation.failure_message }}
            </p>
        </section>

        <section
            class="overflow-hidden rounded-md border border-sl-border bg-sl-surface"
        >
            <div
                class="border-b border-sl-border px-4 py-3 text-xs font-semibold tracking-[0.14em] text-sl-ink uppercase"
            >
                Recent releases
            </div>
            <div
                v-if="releases.length === 0"
                class="px-4 py-8 text-center text-sm text-sl-muted"
            >
                No releases yet.
            </div>
            <div v-else class="overflow-x-auto">
                <table class="w-full min-w-[40rem] text-left text-sm">
                    <thead
                        class="border-b border-sl-border text-[11px] tracking-wide text-sl-muted uppercase"
                    >
                        <tr>
                            <th class="px-4 py-2 font-medium">ID</th>
                            <th class="px-4 py-2 font-medium">Commit</th>
                            <th class="px-4 py-2 font-medium">Message</th>
                            <th class="px-4 py-2 font-medium">Deployed</th>
                            <th class="px-4 py-2 font-medium">Status</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-sl-border/80">
                        <tr
                            v-for="release in releases"
                            :key="release.uuid"
                        >
                            <td class="px-4 py-3 font-mono text-xs">
                                {{ release.uuid.slice(0, 8) }}
                                <span
                                    v-if="release.is_current"
                                    class="ml-2 text-[10px] tracking-wide text-sl-accent uppercase"
                                >
                                    current
                                </span>
                            </td>
                            <td class="px-4 py-3 font-mono text-xs">
                                {{ shortCommit(release.commit_sha) }}
                            </td>
                            <td class="px-4 py-3">
                                {{ release.commit_message ?? '—' }}
                            </td>
                            <td class="px-4 py-3 text-xs text-sl-muted">
                                {{ formatTimestamp(release.deployed_at) }}
                            </td>
                            <td class="px-4 py-3">
                                <span
                                    class="inline-flex items-center gap-1.5 text-sm"
                                    :class="
                                        release.status === 'succeeded' ||
                                        release.status === 'active'
                                            ? 'text-emerald-700'
                                            : release.status === 'failed'
                                              ? 'text-red-700'
                                              : 'text-sl-muted'
                                    "
                                >
                                    <Check
                                        v-if="
                                            release.status === 'succeeded' ||
                                            release.status === 'active'
                                        "
                                        class="size-3.5"
                                    />
                                    {{ release.status }}
                                </span>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>
    </div>
</template>
