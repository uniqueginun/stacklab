<script setup lang="ts">
import { computed } from 'vue';
import { cn } from '@/lib/utils';

const props = defineProps<{
    tone?: 'success' | 'warning' | 'danger' | 'info' | 'neutral';
    label?: string;
}>();

const color = computed(() => {
    switch (props.tone) {
        case 'success':
            return 'bg-emerald-500';
        case 'warning':
            return 'bg-sl-accent';
        case 'danger':
            return 'bg-red-500';
        case 'info':
            return 'bg-sky-500';
        default:
            return 'bg-neutral-400';
    }
});
</script>

<template>
    <span class="inline-flex items-center gap-1.5">
        <span :class="cn('size-1.5 shrink-0 rounded-full', color)" />
        <span v-if="label" class="text-sm text-sl-ink">{{ label }}</span>
    </span>
</template>
