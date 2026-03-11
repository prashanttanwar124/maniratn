<script setup lang="ts">
import ProfileController from '@/actions/App/Http/Controllers/Settings/ProfileController';
import { edit } from '@/routes/profile';
import { send } from '@/routes/verification';
import { Form, Head, Link, usePage } from '@inertiajs/vue3';

import DeleteUser from '@/components/DeleteUser.vue';
import HeadingSmall from '@/components/HeadingSmall.vue';
import InputError from '@/components/InputError.vue';

import AppLayout from '@/layouts/AppLayout.vue';
import SettingsLayout from '@/layouts/settings/Layout.vue';
import { type BreadcrumbItem } from '@/types';
import Button from 'primevue/button';
import InputText from 'primevue/inputtext';

interface Props {
    mustVerifyEmail: boolean;
    status?: string;
}

defineProps<Props>();

const breadcrumbItems: BreadcrumbItem[] = [
    {
        title: 'Profile settings',
        href: edit().url,
    },
];

const page = usePage();
const user = page.props.auth.user;
const role = page.props.auth.role || 'user';
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbItems">
        <Head title="Profile settings" />

        <SettingsLayout>
            <div class="grid grid-cols-1 gap-6 xl:grid-cols-[minmax(0,1fr)_320px]">
                <div class="border border-surface-200 bg-white p-5">
                    <HeadingSmall title="Profile Information" description="Update your account name and email address used across the ERP." />

                    <Form v-bind="ProfileController.update.form()" class="mt-6 space-y-6" v-slot="{ errors, processing, recentlySuccessful }">
                        <div class="grid gap-2">
                            <label for="name" class="text-sm font-medium text-surface-700">Full name</label>
                            <InputText id="name" class="mt-1 w-full" name="name" :default-value="user.name" autocomplete="name" placeholder="Enter full name" />
                            <InputError class="mt-1" :message="errors.name" />
                        </div>

                        <div class="grid gap-2">
                            <label for="email" class="text-sm font-medium text-surface-700">Email address</label>
                            <InputText
                                id="email"
                                type="email"
                                class="mt-1 w-full"
                                name="email"
                                :default-value="user.email"
                                autocomplete="username"
                                placeholder="Enter email address"
                            />
                            <InputError class="mt-1" :message="errors.email" />
                        </div>

                        <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                            <div class="border border-surface-200 bg-surface-50 px-4 py-3">
                                <p class="text-xs font-semibold uppercase tracking-[0.16em] text-surface-500">Current role</p>
                                <p class="mt-1 text-sm font-medium text-surface-900">{{ String(role).charAt(0).toUpperCase() + String(role).slice(1) }}</p>
                            </div>

                            <div class="border border-surface-200 bg-surface-50 px-4 py-3">
                                <p class="text-xs font-semibold uppercase tracking-[0.16em] text-surface-500">Account status</p>
                                <p class="mt-1 text-sm font-medium text-surface-900">{{ user.email_verified_at ? 'Verified and active' : 'Active, email pending verification' }}</p>
                            </div>
                        </div>

                        <div v-if="mustVerifyEmail && !user.email_verified_at" class="border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">
                            <p>
                                Your email address is not verified.
                                <Link
                                    :href="send()"
                                    as="button"
                                    class="font-medium underline decoration-amber-400 underline-offset-4 transition-colors hover:decoration-current"
                                >
                                    Resend verification email
                                </Link>
                            </p>

                            <div v-if="status === 'verification-link-sent'" class="mt-2 font-medium text-green-700">
                                A new verification link has been sent to your email address.
                            </div>
                        </div>

                        <div class="flex items-center gap-4 border-t border-surface-200 pt-4">
                            <Button :disabled="processing" data-test="update-profile-button" label="Save Profile" />

                            <Transition enter-active-class="transition ease-in-out" enter-from-class="opacity-0" leave-active-class="transition ease-in-out" leave-to-class="opacity-0">
                                <p v-show="recentlySuccessful" class="text-sm text-green-700">Profile updated.</p>
                            </Transition>
                        </div>
                    </Form>
                </div>

                <div class="space-y-6">
                    <div class="border border-surface-200 bg-white p-5">
                        <HeadingSmall title="Account Snapshot" description="Basic identity details for the current login." />

                        <div class="mt-6 space-y-4">
                            <div class="border border-surface-200 bg-surface-50 px-4 py-3">
                                <p class="text-xs font-semibold uppercase tracking-[0.16em] text-surface-500">Name</p>
                                <p class="mt-1 text-sm font-medium text-surface-900">{{ user.name }}</p>
                            </div>

                            <div class="border border-surface-200 bg-surface-50 px-4 py-3">
                                <p class="text-xs font-semibold uppercase tracking-[0.16em] text-surface-500">Email</p>
                                <p class="mt-1 text-sm font-medium text-surface-900">{{ user.email }}</p>
                            </div>

                            <div class="border border-surface-200 bg-surface-50 px-4 py-3">
                                <p class="text-xs font-semibold uppercase tracking-[0.16em] text-surface-500">Verification</p>
                                <p class="mt-1 text-sm font-medium text-surface-900">
                                    {{ user.email_verified_at ? 'Verified' : 'Pending verification' }}
                                </p>
                            </div>

                            <div class="border border-surface-200 bg-surface-50 px-4 py-3">
                                <p class="text-xs font-semibold uppercase tracking-[0.16em] text-surface-500">Role</p>
                                <p class="mt-1 text-sm font-medium text-surface-900">{{ String(role).charAt(0).toUpperCase() + String(role).slice(1) }}</p>
                            </div>

                            <div class="border border-surface-200 bg-surface-50 px-4 py-3">
                                <p class="text-xs font-semibold uppercase tracking-[0.16em] text-surface-500">User ID</p>
                                <p class="mt-1 text-sm font-medium text-surface-900">#{{ user.id }}</p>
                            </div>
                        </div>
                    </div>

                    <div class="border border-surface-200 bg-white p-5">
                        <HeadingSmall title="Quick Actions" description="Move to related account settings." />

                        <div class="mt-6 flex flex-col gap-3">
                            <Link href="/settings/password" class="border border-surface-200 px-4 py-3 text-sm font-medium text-surface-900 transition hover:bg-surface-50">
                                Update password
                            </Link>
                            <Link href="/settings/two-factor" class="border border-surface-200 px-4 py-3 text-sm font-medium text-surface-900 transition hover:bg-surface-50">
                                Manage two-factor authentication
                            </Link>
                        </div>
                    </div>
                    <DeleteUser />
                </div>
            </div>
        </SettingsLayout>
    </AppLayout>
</template>
