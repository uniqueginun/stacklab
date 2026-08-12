<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import StackLabTerminal from '@/components/StackLabTerminal.vue';
import { dashboard, login, register } from '@/routes';

const steps = [
    {
        label: '01 / CONNECT',
        title: 'One SSH key, one machine, no middleman.',
        body: 'Install the management key, confirm the host fingerprint, and verify passwordless sudo. Your VPS stays yours.',
    },
    {
        label: '02 / PROVISION',
        title: 'The stack lands hot and configured.',
        body: 'Nginx, PHP, MySQL, Redis — provisioned once with calm, repeatable steps instead of a rented control plane.',
    },
    {
        label: '03 / CREATE SITE',
        title: 'A domain becomes a ready site.',
        body: 'Point a hostname at the server, pick a PHP version, and StackLab writes the Nginx config you can trust.',
    },
    {
        label: '04 / SOURCE',
        title: 'Wire GitHub without sharing the keys.',
        body: 'Attach a repository and branch. StackLab shows the deploy-key fingerprint — never the private key.',
    },
    {
        label: '05 / DEPLOY',
        title: 'Push, and the release is already live.',
        body: 'Trigger a deploy, watch the steps, and keep zero-downtime releases in one precise panel.',
    },
];
</script>

<template>
    <Head title="Self-hosted server control" />

    <div class="sl-brand min-h-svh font-sans text-sl-ink">
        <!-- Hero -->
        <section class="sl-grid-bg relative">
            <div
                class="mx-auto flex w-full max-w-6xl items-center justify-between px-6 pt-6 sm:px-8"
            >
                <p
                    class="font-mono text-[11px] tracking-[0.18em] text-sl-muted uppercase"
                >
                    Self-hosted · v1.4
                </p>
                <nav class="flex items-center gap-4 text-sm">
                    <Link
                        v-if="$page.props.auth.user"
                        :href="dashboard()"
                        class="font-mono text-[11px] tracking-[0.16em] text-sl-ink uppercase transition-colors hover:text-sl-accent"
                    >
                        Dashboard
                    </Link>
                    <Link
                        v-else
                        :href="login()"
                        class="font-mono text-[11px] tracking-[0.16em] text-sl-ink uppercase transition-colors hover:text-sl-accent"
                    >
                        Log in
                    </Link>
                </nav>
            </div>

            <div
                class="mx-auto grid w-full max-w-6xl items-center gap-10 px-6 py-12 sm:px-8 lg:grid-cols-[minmax(0,1fr)_minmax(0,1.05fr)] lg:gap-12 lg:py-16"
            >
                <div class="max-w-xl">
                    <p
                        class="font-sans text-5xl font-bold tracking-tight sm:text-6xl md:text-7xl"
                    >
                        <span class="text-sl-ink">STACK</span
                        ><span class="text-sl-accent">LAB</span>
                    </p>
                    <h1
                        class="mt-6 text-3xl font-semibold tracking-tight text-sl-ink sm:text-4xl"
                    >
                        Your server, under your own control panel.
                    </h1>
                    <p
                        class="mt-4 max-w-md text-base leading-relaxed text-sl-muted"
                    >
                        Connect a VPS over SSH, provision the stack once, then
                        run sites, deploys, and databases from one calm
                        interface you host yourself.
                    </p>
                    <div class="mt-8 flex flex-wrap items-center gap-3">
                        <Link
                            v-if="$page.props.auth.user"
                            :href="dashboard()"
                            class="inline-flex items-center justify-center rounded-md bg-sl-accent px-5 py-2.5 text-sm font-semibold text-white transition-colors hover:bg-[#a8471a] focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-sl-focus"
                        >
                            Open dashboard
                        </Link>
                        <template v-else>
                            <Link
                                :href="register()"
                                class="inline-flex items-center justify-center rounded-md bg-sl-accent px-5 py-2.5 text-sm font-semibold text-white transition-colors hover:bg-[#a8471a] focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-sl-focus"
                            >
                                Get started
                            </Link>
                            <Link
                                :href="login()"
                                class="inline-flex items-center justify-center rounded-md border border-sl-ink/80 bg-transparent px-5 py-2.5 text-sm font-semibold text-sl-ink transition-colors hover:border-sl-accent hover:text-sl-accent focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-sl-focus"
                            >
                                Log in
                            </Link>
                        </template>
                    </div>
                </div>

                <div class="w-full">
                    <StackLabTerminal />
                </div>
            </div>
        </section>

        <!-- Steps — tight to hero, no empty band -->
        <section class="bg-sl-surface">
            <div
                class="mx-auto flex w-full max-w-6xl flex-col gap-12 px-6 py-14 sm:px-8 sm:py-16"
            >
                <article
                    v-for="step in steps"
                    :key="step.label"
                    class="grid gap-3 border-t border-sl-border/80 pt-10 first:border-t-0 first:pt-0 md:grid-cols-[10rem_minmax(0,1fr)] md:gap-10"
                >
                    <p
                        class="font-mono text-[11px] tracking-[0.18em] text-sl-accent uppercase"
                    >
                        {{ step.label }}
                    </p>
                    <div class="max-w-2xl">
                        <h2
                            class="text-2xl font-semibold tracking-tight text-sl-ink sm:text-[1.75rem]"
                        >
                            {{ step.title }}
                        </h2>
                        <p class="mt-3 text-base leading-relaxed text-sl-muted">
                            {{ step.body }}
                        </p>
                    </div>
                </article>
            </div>
        </section>

        <footer
            class="border-t border-sl-border/80 bg-sl-surface px-6 py-8 sm:px-8"
        >
            <div
                class="mx-auto flex w-full max-w-6xl flex-col gap-4 sm:flex-row sm:items-center sm:justify-between"
            >
                <p class="text-lg font-bold tracking-tight">
                    <span class="text-sl-ink">STACK</span
                    ><span class="text-sl-accent">LAB</span>
                </p>
                <div
                    class="flex flex-wrap gap-x-6 gap-y-2 font-mono text-[11px] tracking-[0.16em] text-sl-muted uppercase"
                >
                    <Link
                        :href="login()"
                        class="transition-colors hover:text-sl-accent"
                    >
                        Log in
                    </Link>
                    <Link
                        :href="register()"
                        class="transition-colors hover:text-sl-accent"
                    >
                        Register
                    </Link>
                    <Link
                        :href="register()"
                        class="transition-colors hover:text-sl-accent"
                    >
                        Get started
                    </Link>
                </div>
            </div>
        </footer>
    </div>
</template>
