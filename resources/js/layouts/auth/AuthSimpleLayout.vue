<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { home } from '@/routes';

defineProps<{
    title?: string;
    description?: string;
}>();
</script>

<template>
    <div
        class="sl-brand sl-grid-bg relative flex min-h-svh flex-col items-center justify-center overflow-hidden px-6 py-10"
    >
        <div
            class="pointer-events-none absolute -top-24 right-0 size-[28rem] rounded-full bg-[radial-gradient(circle,rgb(192_86_33_/_0.12),transparent_65%)]"
        />

        <div class="relative w-full max-w-sm">
            <div class="flex flex-col gap-8">
                <div class="flex flex-col items-center gap-5 text-center">
                    <Link
                        :href="home()"
                        class="text-3xl font-bold tracking-tight focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-sl-focus sm:text-4xl"
                    >
                        <span class="text-sl-ink">STACK</span
                        ><span class="text-sl-accent">LAB</span>
                        <span class="sr-only">{{ title }}</span>
                    </Link>

                    <div class="space-y-2">
                        <h1
                            class="text-xl font-medium tracking-tight text-sl-ink"
                        >
                            {{ title }}
                        </h1>
                        <p
                            v-if="description"
                            class="text-sm leading-relaxed text-sl-muted"
                        >
                            {{ description }}
                        </p>
                    </div>
                </div>

                <slot />
            </div>
        </div>
    </div>
</template>
