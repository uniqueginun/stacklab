<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { dashboard } from '@/routes';
</script>

<template>
    <Link
        :href="dashboard()"
        class="flex items-center gap-2 px-1 py-0.5 text-left focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-sl-focus"
    >
        <span
            class="flex size-5 shrink-0 items-center justify-center rounded-[3px] bg-sl-accent text-[10px] font-bold text-white"
            aria-hidden="true"
        >
            S
        </span>
        <span class="text-base font-bold tracking-tight">
            <span class="text-sl-ink">STACK</span
            ><span class="text-sl-accent">LAB</span>
        </span>
    </Link>
</template>
