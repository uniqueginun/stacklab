<script setup lang="ts">
import { computed, onMounted, onUnmounted, ref } from 'vue';

type OutputLine = {
    kind: 'meta' | 'command' | 'ok' | 'blank';
    text: string;
};

type Scene = {
    meta: string;
    command: string;
    results: string[];
};

const scenes: Scene[] = [
    {
        meta: '01 / CONNECT',
        command: 'stacklab server:connect 203.0.113.42',
        results: [
            '✓ ssh key installed · fingerprint confirmed',
            '✓ passwordless sudo verified',
        ],
    },
    {
        meta: '02 / PROVISION',
        command: 'stacklab provision --stack nginx,php,mysql,redis',
        results: [
            '✓ nginx · php-fpm · mysql · redis',
            '✓ firewall + fail2ban applied',
        ],
    },
    {
        meta: '03 / CREATE SITE',
        command: 'stacklab site:create app.example.com --php 8.4',
        results: [
            '✓ nginx vhost written · cert pending',
            '✓ web root /home/forge/app.example.com',
        ],
    },
    {
        meta: '04 / SOURCE',
        command: 'stacklab site:repo app.example.com github:acme/app',
        results: [
            '✓ deploy key fingerprint SHA256:k9…m2',
            '✓ branch main linked · webhook ready',
        ],
    },
    {
        meta: '05 / DEPLOY',
        command: 'stacklab deploy app.example.com',
        results: [
            '✓ build 1m 12s · zero-downtime release',
            '✓ live · https://app.example.com',
        ],
    },
];

const prefersReducedMotion = () =>
    typeof window !== 'undefined' &&
    window.matchMedia('(prefers-reduced-motion: reduce)').matches;

const lines = ref<OutputLine[]>([]);
const typing = ref('');
const showCaret = ref(true);
const activeMeta = ref('');
let cancelled = false;
let timers: number[] = [];

function wait(ms: number): Promise<void> {
    return new Promise((resolve) => {
        const id = window.setTimeout(() => resolve(), ms);
        timers.push(id);
    });
}

async function typeCommand(command: string): Promise<void> {
    typing.value = '';
    showCaret.value = true;

    for (let i = 0; i < command.length; i++) {
        if (cancelled) {
            return;
        }

        typing.value = command.slice(0, i + 1);
        await wait(18 + Math.floor(Math.random() * 28));
    }

    showCaret.value = false;
    lines.value.push({ kind: 'command', text: `$ ${command}` });
    typing.value = '';
}

async function runScene(scene: Scene): Promise<void> {
    activeMeta.value = scene.meta;
    lines.value.push({ kind: 'meta', text: scene.meta });
    await wait(220);
    await typeCommand(scene.command);

    for (const result of scene.results) {
        if (cancelled) {
            return;
        }

        await wait(160);
        lines.value.push({ kind: 'ok', text: result });
    }

    lines.value.push({ kind: 'blank', text: '' });
    await wait(320);
}

async function play(): Promise<void> {
    if (prefersReducedMotion()) {
        lines.value = scenes.flatMap((scene) => [
            { kind: 'meta' as const, text: scene.meta },
            { kind: 'command' as const, text: `$ ${scene.command}` },
            ...scene.results.map((text) => ({ kind: 'ok' as const, text })),
            { kind: 'blank' as const, text: '' },
        ]);
        showCaret.value = false;
        activeMeta.value = scenes[scenes.length - 1]?.meta ?? '';

        return;
    }

    while (!cancelled) {
        lines.value = [];
        typing.value = '';

        for (const scene of scenes) {
            if (cancelled) {
                return;
            }

            await runScene(scene);
        }

        await wait(1800);
    }
}

const visibleLines = computed(() => lines.value.slice(-14));

onMounted(() => {
    void play();
});

onUnmounted(() => {
    cancelled = true;
    timers.forEach((id) => window.clearTimeout(id));
});
</script>

<template>
    <div
        class="overflow-hidden rounded-xl border border-black/10 bg-[#141414] shadow-[0_18px_50px_rgb(0_0_0_/_0.18)]"
        aria-hidden="true"
    >
        <div
            class="flex items-center gap-2 border-b border-white/10 px-4 py-3"
        >
            <span class="size-2.5 rounded-full bg-[#ff5f57]" />
            <span class="size-2.5 rounded-full bg-[#febc2e]" />
            <span class="size-2.5 rounded-full bg-[#28c840]" />
            <span class="ml-3 font-mono text-[11px] tracking-wide text-white/40">
                stacklab — {{ activeMeta || 'ready' }}
            </span>
        </div>

        <div
            class="min-h-[22rem] space-y-1.5 px-4 py-4 font-mono text-[12px] leading-relaxed sm:min-h-[24rem] sm:text-[13px]"
        >
            <p
                v-for="(line, index) in visibleLines"
                :key="`${index}-${line.kind}-${line.text}`"
                :class="{
                    'text-[#c05621]': line.kind === 'meta',
                    'text-white/90': line.kind === 'command',
                    'text-emerald-400/90': line.kind === 'ok',
                    'h-3': line.kind === 'blank',
                }"
            >
                {{ line.text }}
            </p>

            <p v-if="typing || showCaret" class="text-white/90">
                <span class="text-white/45">$</span>
                {{ ' ' }}{{ typing }}<span v-if="showCaret" class="sl-caret" />
            </p>
        </div>
    </div>
</template>
