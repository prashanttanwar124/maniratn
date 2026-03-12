<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import TextLink from '@/components/TextLink.vue';
import { Spinner } from '@/components/ui/spinner';
import AuthBase from '@/layouts/AuthLayout.vue';
import { store } from '@/routes/login';
import { request } from '@/routes/password';
import { Form, Head } from '@inertiajs/vue3';
import Button from 'primevue/button';
import Checkbox from 'primevue/checkbox';
import InputText from 'primevue/inputtext';
import Password from 'primevue/password';

defineProps<{
    status?: string;
    canResetPassword: boolean;
    canRegister: boolean;
}>();
</script>

<template>
    <AuthBase title="Secure Login" description="Sign in to access billing, ledger, orders, and daily operations.">
        <Head title="Log in" />

        <div class="border border-surface-200 bg-white">
            <div class="border-b border-surface-200 bg-surface-50 px-6 py-4">
                <div class="flex items-center justify-between gap-4">
                    <div>
                        <p class="text-xs font-semibold tracking-[0.16em] text-surface-500 uppercase">JewelFlow ERP</p>
                        <h2 class="mt-1 text-lg font-semibold text-surface-900">Account Access</h2>
                    </div>
                </div>
            </div>

            <div class="space-y-5 px-6 py-6">
                <div v-if="status" class="border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-700">
                    {{ status }}
                </div>

                <Form v-bind="store.form()" :reset-on-success="['password']" v-slot="{ errors, processing }" class="space-y-5">
                    <div class="grid gap-5">
                        <div class="grid gap-2">
                            <Label for="email">Email address</Label>
                            <InputText
                                id="email"
                                type="email"
                                name="email"
                                required
                                autofocus
                                :tabindex="1"
                                autocomplete="email"
                                placeholder="Enter your work email"
                                class="w-full"
                                :invalid="Boolean(errors.email)"
                            />
                            <InputError :message="errors.email" />
                        </div>

                        <div class="grid gap-2">
                            <div class="flex items-center justify-between">
                                <Label for="password">Password</Label>
                                <TextLink v-if="canResetPassword" :href="request()" class="text-sm" :tabindex="4">Forgot password?</TextLink>
                            </div>
                            <Password
                                id="password"
                                name="password"
                                :feedback="false"
                                toggleMask
                                required
                                :tabindex="2"
                                autocomplete="current-password"
                                placeholder="Enter your password"
                                inputClass="w-full"
                                class="w-full"
                                :invalid="Boolean(errors.password)"
                            />
                            <InputError :message="errors.password" />
                        </div>

                        <div class="flex items-center justify-between border border-surface-200 bg-surface-50 px-4 py-3">
                            <Label for="remember" class="flex items-center gap-3 text-sm font-medium text-surface-700">
                                <Checkbox id="remember" name="remember" binary :tabindex="3" />
                                <span>Keep me signed in on this device</span>
                            </Label>
                        </div>
                    </div>

                    <Button type="submit" class="w-full" size="large" :tabindex="5" :disabled="processing" data-test="login-button">
                        <Spinner v-if="processing" />
                        Log in to JewelFlow
                    </Button>

                    <div class="border-t border-surface-200 pt-4 text-center text-xs leading-5 text-surface-500">
                        Access is controlled by admin-created accounts. Public self-registration is disabled.
                    </div>
                </Form>
            </div>
        </div>
    </AuthBase>
</template>
