<script setup lang="ts">
import { Head, Link, setLayoutProps, usePage, usePoll } from '@inertiajs/vue3';
import { ChevronRight, Code2 } from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import SiteDeploymentPanel from '@/components/SiteDeploymentPanel.vue';
import SiteRepositoryPanel from '@/components/SiteRepositoryPanel.vue';
import StatusDot from '@/components/StatusDot.vue';
import { show as serversShow } from '@/routes/servers';
import { index, show } from '@/routes/sites';

type Site = {
    uuid: string;
    domain: string;
    type: string;
    php_version: string | null;
    web_directory: string | null;
    root_path: string | null;
    document_root: string;
    repository: string | null;
    branch: string | null;
    deploy_key_fingerprint: string | null;
    status: string;
    failure_message: string | null;
    deployed_at: string | null;
    created_at: string | null;
    deployment_options: {
        run_composer: boolean;
        run_npm: boolean;
        run_migrations: boolean;
        run_caches: boolean;
        run_queue_restart: boolean;
        run_hook: boolean;
    };
    current_release: {
        uuid: string;
        commit_sha: string;
    } | null;
    server: {
        uuid: string;
        name: string;
        host: string;
    };
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

const props = defineProps<{
    site: Site;
    releases: Release[];
    githubConnected: boolean;
    githubUsername: string | null;
    activeOperation: ActiveOperation;
}>();

const siteTabs = [
    'overview',
    'repository',
    'environment',
    'deployment',
] as const;

type SiteTab = (typeof siteTabs)[number];

const tabs = [
    { key: 'overview' as const, label: 'Overview' },
    { key: 'repository' as const, label: 'Repository' },
    { key: 'environment' as const, label: 'Environment' },
    { key: 'deployment' as const, label: 'Deployment' },
];

function tabFromUrl(url: string): SiteTab {
    const query = url.includes('?') ? url.slice(url.indexOf('?') + 1) : '';
    const value = new URLSearchParams(query).get('tab');

    return (siteTabs as readonly string[]).includes(value ?? '')
        ? (value as SiteTab)
        : 'overview';
}

const tab = ref<SiteTab>(tabFromUrl(usePage().url));

function selectTab(next: SiteTab): void {
    tab.value = next;

    if (typeof window === 'undefined') {
        return;
    }

    const url = new URL(window.location.href);

    if (next === 'overview') {
        url.searchParams.delete('tab');
    } else {
        url.searchParams.set('tab', next);
    }

    window.history.replaceState(
        window.history.state,
        '',
        `${url.pathname}${url.search}${url.hash}`,
    );
}

const { start: startPolling, stop: stopPolling } = usePoll(
    2000,
    {
        only: ['site', 'releases', 'activeOperation'],
    },
    {
        autoStart: false,
    },
);

watch(
    () => props.activeOperation?.status,
    (status) => {
        if (status === 'pending' || status === 'running') {
            startPolling();
            return;
        }

        stopPolling();
    },
    { immediate: true },
);

setLayoutProps({
    breadcrumbs: [
        {
            title: 'Sites',
            href: index(),
        },
        {
            title: props.site.domain,
            href: show(props.site),
        },
    ],
});

const statusTone = computed(
    (): 'success' | 'warning' | 'danger' | 'neutral' => {
        switch (props.site.status) {
            case 'active':
            case 'deployed':
            case 'succeeded':
                return 'success';
            case 'failed':
                return 'danger';
            case 'pending':
            case 'deploying':
                return 'warning';
            default:
                return 'neutral';
        }
    },
);

const statusLabel = computed(() => {
    const status = props.site.status;

    return status
        ? status.charAt(0).toUpperCase() + status.slice(1)
        : 'Unknown';
});

const typeLabel = computed(() => {
    switch (props.site.type) {
        case 'laravel':
            return 'Laravel';
        case 'php':
            return 'PHP';
        default:
            return props.site.type;
    }
});

const shortCommit = (sha: string | null | undefined): string => {
    if (!sha) {
        return '—';
    }

    return sha.slice(0, 7);
};

const latestRelease = computed(() => props.releases[0] ?? null);

const siteDetails = computed((): [string, string][] => [
    ['Domain', props.site.domain],
    ['Server', props.site.server.name],
    ['Root path', props.site.root_path ?? '—'],
    ['Web directory', props.site.web_directory ?? '—'],
    ['Document root', props.site.document_root],
    ['Type', typeLabel.value],
    ['PHP', props.site.php_version ? `${props.site.php_version} (FPM)` : '—'],
    ['Status', statusLabel.value],
]);
</script>

<template>
    <Head :title="site.domain" />

    <div class="flex flex-col gap-6">
        <div class="border-b border-sl-border pb-4">
            <div class="flex flex-wrap items-start gap-3">
                <div
                    class="flex size-11 items-center justify-center rounded-md border border-sl-border bg-sl-surface text-sl-muted"
                >
                    <Code2 class="size-5" />
                </div>
                <div class="min-w-0 flex-1">
                    <div class="flex flex-wrap items-center gap-3">
                        <h1
                            class="text-2xl font-bold tracking-tight text-sl-ink"
                        >
                            {{ site.domain }}
                        </h1>
                        <StatusDot :tone="statusTone" :label="statusLabel" />
                    </div>
                    <div class="mt-3 flex flex-wrap gap-2 text-xs">
                        <Link
                            :href="serversShow(site.server)"
                            class="rounded-md border border-sl-border bg-sl-surface px-2.5 py-1 font-medium text-sky-700 hover:border-sl-accent"
                        >
                            Server: {{ site.server.name }}
                        </Link>
                        <span
                            class="rounded-md border border-sl-border bg-sl-surface px-2.5 py-1 text-sl-muted"
                        >
                            PHP
                            {{
                                site.php_version
                                    ? `${site.php_version} (FPM)`
                                    : '—'
                            }}
                        </span>
                        <span
                            class="rounded-md border border-sl-border bg-sl-surface px-2.5 py-1 text-sl-muted"
                        >
                            {{ typeLabel }}
                        </span>
                    </div>
                </div>
            </div>

            <nav class="mt-5 flex flex-wrap gap-1" aria-label="Site sections">
                <button
                    v-for="item in tabs"
                    :key="item.key"
                    type="button"
                    class="border-b-2 px-3 py-2 text-sm transition-colors"
                    :class="
                        tab === item.key
                            ? 'border-sl-accent font-medium text-sl-ink'
                            : 'border-transparent text-sl-muted hover:text-sl-ink'
                    "
                    @click="selectTab(item.key)"
                >
                    {{ item.label }}
                </button>
            </nav>
        </div>

        <div
            class="grid gap-4 xl:grid-cols-[minmax(0,1.45fr)_minmax(18rem,0.8fr)]"
        >
            <div class="flex flex-col gap-4">
                <section
                    v-if="tab === 'overview'"
                    class="rounded-md border border-sl-border bg-sl-surface p-5"
                >
                    <h2 class="text-sm font-medium text-sl-ink">Overview</h2>
                    <p class="mt-2 text-sm text-sl-muted">
                        Site is
                        {{ statusLabel.toLowerCase() }} on
                        {{ site.server.name }}.
                        <template v-if="latestRelease">
                            Latest release
                            <span class="font-mono text-sl-ink">{{
                                shortCommit(latestRelease.commit_sha)
                            }}</span>
                            <template v-if="latestRelease.commit_message">
                                — {{ latestRelease.commit_message }}
                            </template>
                            .
                        </template>
                        <template v-else> No releases yet. </template>
                    </p>
                    <p
                        v-if="site.failure_message"
                        class="mt-3 rounded-md border border-red-200 bg-red-50 px-3 py-2 font-mono text-xs text-red-700"
                    >
                        {{ site.failure_message }}
                    </p>
                </section>

                <section
                    v-else-if="tab === 'repository'"
                    class="rounded-md border border-sl-border bg-sl-surface p-5"
                >
                    <SiteRepositoryPanel
                        :site="site"
                        :github-connected="githubConnected"
                        :github-username="githubUsername"
                    />
                </section>

                <section
                    v-else-if="tab === 'environment'"
                    class="rounded-md border border-sl-border bg-sl-surface p-5"
                >
                    <h2 class="text-sm font-medium text-sl-ink">Environment</h2>
                    <p class="mt-2 text-sm text-sl-muted">
                        Environment file editing is not wired yet. The site root
                        is
                        <span class="font-mono text-sl-ink">{{
                            site.root_path ?? '—'
                        }}</span
                        >.
                    </p>
                </section>

                <SiteDeploymentPanel
                    v-else
                    :site="site"
                    :releases="releases"
                    :github-connected="githubConnected"
                    :github-username="githubUsername"
                    :active-operation="activeOperation"
                    @open-repository="selectTab('repository')"
                />
            </div>

            <aside class="flex flex-col gap-4">
                <section
                    class="rounded-md border border-sl-border bg-sl-surface"
                >
                    <h2
                        class="border-b border-sl-border px-4 py-3 text-sm font-medium text-sl-ink"
                    >
                        Site details
                    </h2>
                    <dl class="divide-y divide-sl-border/80">
                        <div
                            v-for="[label, value] in siteDetails"
                            :key="label"
                            class="grid grid-cols-[7rem_1fr] gap-2 px-4 py-2.5 text-sm"
                        >
                            <dt class="text-sl-muted">{{ label }}</dt>
                            <dd class="font-mono text-xs break-all text-sl-ink">
                                {{ value }}
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
                        Quick actions
                    </h2>
                    <ul class="divide-y divide-sl-border/80">
                        <li>
                            <a
                                :href="`https://${site.domain}`"
                                target="_blank"
                                rel="noopener noreferrer"
                                class="flex w-full items-center justify-between px-4 py-3 text-left text-sm text-sl-ink hover:bg-sl-background/70"
                            >
                                Visit site
                                <ChevronRight class="size-4 text-sl-muted" />
                            </a>
                        </li>
                        <li>
                            <button
                                type="button"
                                class="flex w-full items-center justify-between px-4 py-3 text-left text-sm text-sl-ink hover:bg-sl-background/70"
                                @click="selectTab('environment')"
                            >
                                Manage environment
                                <ChevronRight class="size-4 text-sl-muted" />
                            </button>
                        </li>
                        <li>
                            <button
                                type="button"
                                class="flex w-full items-center justify-between px-4 py-3 text-left text-sm text-sl-ink hover:bg-sl-background/70"
                                @click="selectTab('deployment')"
                            >
                                View deployments
                                <ChevronRight class="size-4 text-sl-muted" />
                            </button>
                        </li>
                    </ul>
                </section>
            </aside>
        </div>
    </div>
</template>
