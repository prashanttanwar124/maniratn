<script setup lang="ts">
import PasswordController from '@/actions/App/Http/Controllers/Settings/PasswordController';
import InputError from '@/components/InputError.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import SettingsLayout from '@/layouts/settings/Layout.vue';
import { edit } from '@/routes/user-password';
import { Form, Head } from '@inertiajs/vue3';

import HeadingSmall from '@/components/HeadingSmall.vue';

import { type BreadcrumbItem } from '@/types';
import Button from 'primevue/button';
import InputText from 'primevue/inputtext';

const breadcrumbItems: BreadcrumbItem[] = [
    {
        title: 'Password settings',
        href: edit().url,
    },
];
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbItems">
        <Head title="Password settings" />

        <SettingsLayout>
            <div class="grid grid-cols-1 gap-6 xl:grid-cols-[minmax(0,1fr)_320px]">
                <div class="border border-surface-200 bg-white p-5">
                    <HeadingSmall title="Update Password" description="Use a strong password to keep your account secure." />

                    <Form
                        v-bind="PasswordController.update.form()"
                        :options="{
                            preserveScroll: true,
                        }"
                        reset-on-success
                        :reset-on-error="['password', 'password_confirmation', 'current_password']"
                        class="mt-6 space-y-6"
                        v-slot="{ errors, processing, recentlySuccessful }"
                    >
                        <div class="grid gap-2">
                            <label for="current_password" class="text-sm font-medium text-surface-700">Current password</label>
                            <InputText
                                id="current_password"
                                name="current_password"
                                type="password"
                                class="mt-1 w-full"
                                autocomplete="current-password"
                                placeholder="Enter current password"
                            />
                            <InputError :message="errors.current_password" />
                        </div>

                        <div class="grid gap-2">
                            <label for="password" class="text-sm font-medium text-surface-700">New password</label>
                            <InputText id="password" name="password" type="password" class="mt-1 w-full" autocomplete="new-password" placeholder="Enter new password" />
                            <InputError :message="errors.password" />
                        </div>

                        <div class="grid gap-2">
                            <label for="password_confirmation" class="text-sm font-medium text-surface-700">Confirm password</label>
                            <InputText
                                id="password_confirmation"
                                name="password_confirmation"
                                type="password"
                                class="mt-1 w-full"
                                autocomplete="new-password"
                                placeholder="Confirm new password"
                            />
                            <InputError :message="errors.password_confirmation" />
                        </div>

                        <div class="flex items-center gap-4 border-t border-surface-200 pt-4">
                            <Button :disabled="processing" data-test="update-password-button" label="Save Password" />

                            <Transition enter-active-class="transition ease-in-out" enter-from-class="opacity-0" leave-active-class="transition ease-in-out" leave-to-class="opacity-0">
                                <p v-show="recentlySuccessful" class="text-sm text-green-700">Password updated.</p>
                            </Transition>
                        </div>
                    </Form>
                </div>

                <div class="border border-surface-200 bg-white p-5">
                    <HeadingSmall title="Password Guidance" description="Use a strong secret that is difficult to guess." />

                    <div class="mt-6 space-y-4">
                        <div class="border border-surface-200 bg-surface-50 px-4 py-3">
                            <p class="text-xs font-semibold uppercase tracking-[0.16em] text-surface-500">Recommended</p>
                            <p class="mt-1 text-sm text-surface-900">Use at least 12 characters with a mix of upper case, lower case, numbers, and symbols.</p>
                        </div>

                        <div class="border border-surface-200 bg-surface-50 px-4 py-3">
                            <p class="text-xs font-semibold uppercase tracking-[0.16em] text-surface-500">Avoid</p>
                            <p class="mt-1 text-sm text-surface-900">Do not reuse old passwords or simple names, dates, and mobile numbers.</p>
                        </div>
                    </div>
                </div>
            </div>
        </SettingsLayout>
    </AppLayout>
</template>
