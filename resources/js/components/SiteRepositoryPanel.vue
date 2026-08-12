<script setup lang="ts">
import { Link, useForm, useHttp } from '@inertiajs/vue3';
import { Check } from '@lucide/vue';
import { computed, onMounted, ref, watch } from 'vue';
import { redirect as githubRedirect } from '@/actions/App/Http/Controllers/GitHubConnectionController';
import {
    edit,
    update,
} from '@/actions/App/Http/Controllers/SiteRepositoryController';
import InputError from '@/components/InputError.vue';
import { Label } from '@/components/ui/label';
import { show } from '@/routes/sites';

type SiteSummary = {
    uuid: string;
    repository: string | null;
    branch: string | null;
    deploy_key_fingerprint: string | null;
    status: string;
};

type GitHubRepo = {
    id: number;
    full_name: string;
    private: boolean;
    default_branch: string;
};

type GitHubBranch = {
    name: string;
};

type CatalogResponse = {
    githubConnected: boolean;
    repository: string | null;
    branch: string | null;
    repositories: GitHubRepo[];
    branches: GitHubBranch[];
};

const props = defineProps<{
    site: SiteSummary;
    githubConnected: boolean;
    githubUsername: string | null;
}>();

const repositories = ref<GitHubRepo[]>([]);
const branches = ref<GitHubBranch[]>([]);
const catalog = useHttp();
const form = useForm({
    repository: props.site.repository ?? '',
    branch: props.site.branch ?? '',
});

const connectGitHub = computed(() =>
    githubRedirect({
        query: {
            return: show.url(props.site, { query: { tab: 'repository' } }),
        },
    }),
);

const canAttach = computed(() => props.site.status === 'active');
const isBusy = computed(() => form.processing || catalog.processing);

function catalogRequest(repository: string) {
    return edit(props.site, {
        query: repository !== '' ? { repository } : {},
    });
}

function applyBranches(preserveBranch: boolean): void {
    const names = branches.value.map((branch) => branch.name);

    if (preserveBranch && form.branch !== '' && names.includes(form.branch)) {
        return;
    }

    const selected = repositories.value.find(
        (repo) => repo.full_name === form.repository,
    );

    if (selected && names.includes(selected.default_branch)) {
        form.branch = selected.default_branch;

        return;
    }

    form.branch = names[0] ?? '';
}

async function loadCatalog(preserveBranch: boolean): Promise<void> {
    if (!props.githubConnected) {
        return;
    }

    try {
        const data = (await catalog.submit(
            catalogRequest(form.repository),
        )) as CatalogResponse;

        repositories.value = data.repositories ?? [];
        branches.value = data.branches ?? [];
        applyBranches(preserveBranch);
    } catch {
        repositories.value = [];
        branches.value = [];
    }
}

watch(
    () => form.repository,
    async (repository, previous) => {
        if (previous === undefined || repository === previous) {
            return;
        }

        form.branch = '';
        await loadCatalog(false);
    },
);

onMounted(() => {
    void loadCatalog(true);
});

function submit(): void {
    form.put(update.url(props.site), {
        preserveScroll: true,
    });
}
</script>

<template>
    <div>
        <h2 class="text-sm font-medium text-sl-ink">Repository</h2>

        <template v-if="site.repository">
            <p class="mt-3 font-mono text-xs text-sl-ink">
                {{ site.repository }}
            </p>
            <p class="mt-1 text-sm text-sl-muted">
                Branch: {{ site.branch ?? '—' }}
            </p>
            <p
                v-if="site.deploy_key_fingerprint"
                class="mt-4 font-mono text-xs text-sl-muted"
            >
                Deploy key fingerprint ·
                {{ site.deploy_key_fingerprint }}
            </p>
        </template>
        <p
            v-else
            class="mt-2 text-sm text-sl-muted"
        >
            Connect a version control provider to attach a repository. GitLab
            and Bitbucket will be available later.
        </p>

        <div
            class="mt-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-3"
            role="group"
            aria-label="Version control providers"
        >
            <component
                :is="githubConnected ? 'div' : Link"
                v-bind="githubConnected ? {} : { href: connectGitHub }"
                class="relative rounded-md border bg-white p-4 transition-colors duration-150"
                :class="
                    githubConnected
                        ? 'border-sl-accent ring-2 ring-sl-accent/20'
                        : 'cursor-pointer border-sl-border hover:border-sl-ink/30'
                "
                :aria-label="
                    githubConnected
                        ? `GitHub connected${githubUsername ? ` as ${githubUsername}` : ''}`
                        : 'Connect GitHub'
                "
            >
                <span class="flex items-start gap-3">
                    <span
                        class="mt-0.5 flex size-4 shrink-0 items-center justify-center rounded-[4px] border"
                        :class="
                            githubConnected
                                ? 'border-sl-accent bg-sl-accent text-white'
                                : 'border-sl-border bg-white'
                        "
                        aria-hidden="true"
                    >
                        <Check
                            v-if="githubConnected"
                            class="size-3"
                        />
                    </span>

                    <span
                        class="flex size-9 shrink-0 items-center justify-center rounded-md border border-sl-border bg-sl-background text-sl-ink"
                        aria-hidden="true"
                    >
                        <svg
                            class="size-5"
                            viewBox="0 0 24 24"
                            fill="currentColor"
                        >
                            <path
                                d="M12 .297c-6.63 0-12 5.373-12 12 0 5.303 3.438 9.8 8.205 11.385.6.113.82-.258.82-.577 0-.285-.01-1.04-.015-2.04-3.338.724-4.042-1.61-4.042-1.61C4.422 18.07 3.633 17.7 3.633 17.7c-1.087-.744.084-.729.084-.729 1.205.084 1.838 1.236 1.838 1.236 1.07 1.835 2.809 1.305 3.495.998.108-.776.417-1.305.76-1.605-2.665-.3-5.466-1.332-5.466-5.93 0-1.31.465-2.38 1.235-3.22-.135-.303-.54-1.523.105-3.176 0 0 1.005-.322 3.3 1.23.96-.267 1.98-.399 3-.405 1.02.006 2.04.138 3 .405 2.28-1.552 3.285-1.23 3.285-1.23.645 1.653.24 2.873.12 3.176.765.84 1.23 1.91 1.23 3.22 0 4.61-2.805 5.625-5.475 5.92.42.36.81 1.096.81 2.22 0 1.606-.015 2.896-.015 3.286 0 .315.21.69.825.57C20.565 22.092 24 17.592 24 12.297c0-6.627-5.373-12-12-12"
                            />
                        </svg>
                    </span>

                    <span class="min-w-0">
                        <span class="block text-sm font-semibold text-sl-ink">
                            GitHub
                        </span>
                        <span class="mt-0.5 block text-xs text-sl-muted">
                            <template v-if="githubConnected">
                                Connected
                                <template v-if="githubUsername">
                                    as {{ githubUsername }}
                                </template>
                            </template>
                            <template v-else>
                                Connect to select repositories and branches.
                            </template>
                        </span>
                    </span>
                </span>
            </component>
        </div>

        <form
            v-if="githubConnected"
            class="mt-6 flex flex-col gap-4"
            @submit.prevent="submit"
        >
            <div
                v-if="catalog.processing && repositories.length === 0"
                class="flex flex-col gap-3"
            >
                <div class="h-10 animate-pulse rounded-md bg-sl-border/60" />
                <div class="h-10 animate-pulse rounded-md bg-sl-border/60" />
            </div>

            <template v-else>
                <div class="grid gap-2">
                    <Label
                        for="repository"
                        class="font-mono text-[11px] tracking-[0.14em] text-sl-muted uppercase"
                    >
                        Repository
                    </Label>
                    <select
                        id="repository"
                        v-model="form.repository"
                        name="repository"
                        required
                        :disabled="isBusy || !canAttach"
                        class="h-10 rounded-md border border-sl-border bg-white px-3 text-sm"
                    >
                        <option value="" disabled>Select a repository</option>
                        <option
                            v-for="repo in repositories"
                            :key="repo.id"
                            :value="repo.full_name"
                        >
                            {{ repo.full_name }}{{ repo.private ? ' (private)' : '' }}
                        </option>
                    </select>
                    <InputError :message="form.errors.repository" />
                </div>

                <div class="grid gap-2">
                    <Label
                        for="branch"
                        class="font-mono text-[11px] tracking-[0.14em] text-sl-muted uppercase"
                    >
                        Branch
                    </Label>
                    <select
                        id="branch"
                        v-model="form.branch"
                        name="branch"
                        required
                        :disabled="
                            isBusy || !canAttach || form.repository === ''
                        "
                        class="h-10 rounded-md border border-sl-border bg-white px-3 text-sm"
                    >
                        <option value="" disabled>Select a branch</option>
                        <option
                            v-for="item in branches"
                            :key="item.name"
                            :value="item.name"
                        >
                            {{ item.name }}
                        </option>
                    </select>
                    <InputError :message="form.errors.branch" />
                </div>
            </template>

            <p
                v-if="!canAttach"
                class="text-sm text-sl-muted"
            >
                Wait until the site Nginx configuration is active before
                attaching a repository.
            </p>

            <button
                type="submit"
                class="inline-flex h-10 w-fit items-center rounded-md bg-sl-accent px-4 text-sm font-semibold text-white hover:bg-[#a8471a] disabled:opacity-60"
                :disabled="
                    isBusy ||
                    !canAttach ||
                    form.repository === '' ||
                    form.branch === ''
                "
            >
                {{
                    form.processing
                        ? 'Saving…'
                        : site.repository
                          ? 'Update repository'
                          : 'Attach repository'
                }}
            </button>
        </form>
    </div>
</template>
