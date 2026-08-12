<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import InputError from '@/components/InputError.vue';
import PasswordInput from '@/components/PasswordInput.vue';
import TextLink from '@/components/TextLink.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { login } from '@/routes';
import { store } from '@/routes/register';

defineProps<{
    passwordRules: string;
}>();

defineOptions({
    layout: {
        title: 'Create your account',
        description: 'The first account becomes the panel owner.',
    },
});
</script>

<template>
    <Head title="Register" />

    <Form
        v-bind="store.form()"
        :reset-on-success="['password', 'password_confirmation']"
        v-slot="{ errors, processing }"
        class="flex flex-col gap-6"
    >
        <div class="grid gap-5">
            <div class="grid gap-2">
                <Label
                    for="name"
                    class="font-mono text-[11px] tracking-[0.16em] text-sl-muted uppercase"
                >
                    Name
                </Label>
                <Input
                    id="name"
                    type="text"
                    required
                    autofocus
                    :tabindex="1"
                    autocomplete="name"
                    name="name"
                    placeholder="Ada Lovelace"
                    class="h-11 rounded-md border-sl-border bg-white"
                />
                <InputError
                    :message="errors.name"
                    class="font-mono text-[11px] tracking-wide uppercase"
                />
            </div>

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
                    required
                    :tabindex="2"
                    autocomplete="email"
                    name="email"
                    placeholder="ada@stacklab.dev"
                    class="h-11 rounded-md border-sl-border bg-white"
                />
                <InputError
                    :message="errors.email"
                    class="font-mono text-[11px] tracking-wide uppercase"
                />
            </div>

            <div class="grid gap-2">
                <Label
                    for="password"
                    class="font-mono text-[11px] tracking-[0.16em] text-sl-muted uppercase"
                >
                    Password
                </Label>
                <PasswordInput
                    id="password"
                    required
                    :tabindex="3"
                    autocomplete="new-password"
                    name="password"
                    placeholder="Password"
                    :passwordrules="passwordRules"
                    class="h-11 rounded-md border-sl-border bg-white"
                />
                <p class="text-xs text-sl-muted">
                    At least 12 characters, including a number.
                </p>
                <InputError
                    :message="errors.password"
                    class="font-mono text-[11px] tracking-wide uppercase"
                />
            </div>

            <div class="grid gap-2">
                <Label
                    for="password_confirmation"
                    class="font-mono text-[11px] tracking-[0.16em] text-sl-muted uppercase"
                >
                    Confirm password
                </Label>
                <PasswordInput
                    id="password_confirmation"
                    required
                    :tabindex="4"
                    autocomplete="new-password"
                    name="password_confirmation"
                    placeholder="Confirm password"
                    :passwordrules="passwordRules"
                    class="h-11 rounded-md border-sl-border bg-white"
                />
                <InputError
                    :message="errors.password_confirmation"
                    class="font-mono text-[11px] tracking-wide uppercase"
                />
            </div>

            <Button
                type="submit"
                class="mt-1 h-11 w-full rounded-md bg-sl-accent text-white hover:bg-[#a8471a]"
                tabindex="5"
                :disabled="processing"
                data-test="register-user-button"
            >
                <Spinner v-if="processing" />
                Create account
            </Button>
        </div>

        <div class="text-center text-sm text-sl-muted">
            Already have a panel?
            <TextLink
                :href="login()"
                class="text-sl-ink underline underline-offset-4"
                :tabindex="6"
            >
                Log in
            </TextLink>
        </div>
    </Form>
</template>
