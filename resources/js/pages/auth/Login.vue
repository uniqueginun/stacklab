<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import InputError from '@/components/InputError.vue';
import PasswordInput from '@/components/PasswordInput.vue';
import TextLink from '@/components/TextLink.vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { register } from '@/routes';
import { store } from '@/routes/login';
import { request } from '@/routes/password';

defineOptions({
    layout: {
        title: 'Log in to your panel',
        description: 'Signed sessions stay on your own machine.',
    },
});

defineProps<{
    status?: string;
    canResetPassword: boolean;
}>();
</script>

<template>
    <Head title="Log in" />

    <div
        v-if="status"
        class="mb-4 text-center text-sm font-medium text-sl-accent"
    >
        {{ status }}
    </div>

    <Form
        v-bind="store.form()"
        :reset-on-success="['password']"
        v-slot="{ errors, processing }"
        class="flex flex-col gap-6"
    >
        <div class="grid gap-5">
            <div class="grid gap-2">
                <Label
                    for="email"
                    class="font-mono text-[11px] tracking-[0.16em] text-sl-muted uppercase"
                >
                    Email
                </Label>
                <Input
                    id="email"
                    type="email"
                    name="email"
                    required
                    autofocus
                    :tabindex="1"
                    autocomplete="email"
                    placeholder="edu@stacklab.dev"
                    class="h-11 rounded-md border-sl-border bg-white"
                    :aria-invalid="Boolean(errors.email)"
                />
                <InputError
                    :message="errors.email"
                    class="font-mono text-[11px] tracking-wide uppercase"
                />
            </div>

            <div class="grid gap-2">
                <div class="flex items-center justify-between gap-3">
                    <Label
                        for="password"
                        class="font-mono text-[11px] tracking-[0.16em] text-sl-muted uppercase"
                    >
                        Password
                    </Label>
                    <TextLink
                        v-if="canResetPassword"
                        :href="request()"
                        class="text-xs text-sl-muted no-underline hover:text-sl-accent"
                        :tabindex="5"
                    >
                        Forgot password?
                    </TextLink>
                </div>
                <PasswordInput
                    id="password"
                    name="password"
                    required
                    :tabindex="2"
                    autocomplete="current-password"
                    placeholder="Password"
                    class="h-11 rounded-md border-sl-border bg-white"
                    :aria-invalid="Boolean(errors.password)"
                />
                <InputError
                    :message="errors.password"
                    class="font-mono text-[11px] tracking-wide uppercase"
                />
            </div>

            <Label
                for="remember"
                class="flex items-center gap-3 text-sm text-sl-ink"
            >
                <Checkbox
                    id="remember"
                    name="remember"
                    :tabindex="3"
                    class="border-sl-border data-[state=checked]:border-sl-accent data-[state=checked]:bg-sl-accent"
                />
                <span>Remember this device</span>
            </Label>

            <Button
                type="submit"
                class="mt-1 h-11 w-full rounded-md bg-sl-accent text-white hover:bg-[#a8471a]"
                :tabindex="4"
                :disabled="processing"
                data-test="login-button"
            >
                <Spinner v-if="processing" />
                Log in
            </Button>
        </div>

        <div class="text-center text-sm text-sl-muted">
            No panel yet?
            <TextLink
                :href="register()"
                class="text-sl-ink underline underline-offset-4"
                :tabindex="5"
            >
                Create an account
            </TextLink>
        </div>
    </Form>
</template>
