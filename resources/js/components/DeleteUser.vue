<script setup lang="ts">
import ProfileController from '@/actions/App/Http/Controllers/Settings/ProfileController';
import { Form } from '@inertiajs/vue3';
import { ref, useTemplateRef } from 'vue';

// Components
import HeadingSmall from '@/components/HeadingSmall.vue';
import InputError from '@/components/InputError.vue';

import Button from 'primevue/button';
import Dialog from 'primevue/dialog';
import InputText from 'primevue/inputtext';

const passwordInput = useTemplateRef('passwordInput');
const visible = ref(false);
</script>

<template>
    <div class="space-y-6">
        <HeadingSmall title="Delete account" description="Delete your account and all of its resources" />
        <div class="space-y-4 rounded-lg border border-red-100 bg-red-50 p-4 dark:border-red-200/10 dark:bg-red-700/10">
            <div class="relative space-y-0.5 text-red-600 dark:text-red-100">
                <p class="font-medium">Warning</p>
                <p class="text-sm">Please proceed with caution, this cannot be undone.</p>
            </div>
            <Button severity="danger" @click="visible = true" data-test="delete-user-button">Delete account</Button>

            <Dialog v-model:visible="visible" modal header="Are you sure you want to delete your account?" :style="{ width: '28rem' }">
                <Form
                    v-bind="ProfileController.destroy.form()"
                    reset-on-success
                    @error="() => passwordInput?.$el?.focus()"
                    :options="{ preserveScroll: true }"
                    class="space-y-6"
                    v-slot="{ errors, processing, reset, clearErrors }"
                >
                    <p class="text-sm text-surface-500">
                        Once your account is deleted, all of its resources and data will also be permanently deleted. Please enter your password to confirm you would like to permanently delete your
                        account.
                    </p>

                    <div class="grid gap-2">
                        <label for="password" class="sr-only">Password</label>
                        <InputText id="password" type="password" name="password" ref="passwordInput" placeholder="Password" class="w-full" />
                        <InputError :message="errors.password" />
                    </div>

                    <div class="flex justify-end gap-2">
                        <Button
                            severity="secondary"
                            label="Cancel"
                            @click="
                                () => {
                                    clearErrors();
                                    reset();
                                    visible = false;
                                }
                            "
                        />
                        <Button type="submit" severity="danger" label="Delete account" :disabled="processing" :loading="processing" data-test="confirm-delete-user-button" />
                    </div>
                </Form>
            </Dialog>
        </div>
    </div>
</template>
