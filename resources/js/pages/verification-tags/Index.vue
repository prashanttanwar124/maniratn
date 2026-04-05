<script setup>
import AppLayout from '@/layouts/AppLayout.vue';
import { useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

import Button from 'primevue/button';
import Dialog from 'primevue/dialog';
import InputText from 'primevue/inputtext';
import Select from 'primevue/select';
import Tag from 'primevue/tag';
import Textarea from 'primevue/textarea';

const props = defineProps({
    publicBaseUrl: String,
    eligibleItems: Array,
    tags: Array,
});

const showCreateDialog = ref(false);
const copiedToken = ref(null);

const createForm = useForm({
    invoice_item_id: null,
    notes: '',
});

const activeTagsCount = computed(() => props.tags?.filter((tag) => tag.is_active).length || 0);
const lockedTagsCount = computed(() => props.tags?.filter((tag) => tag.status === 'LOCKED').length || 0);
const pendingTagsCount = computed(() => props.tags?.filter((tag) => tag.status === 'PENDING').length || 0);

const statusSeverity = (status) => {
    if (status === 'LOCKED') return 'success';
    if (status === 'WRITTEN') return 'info';
    if (status === 'DISABLED') return 'contrast';
    return 'warn';
};

const openCreateDialog = () => {
    createForm.reset();
    createForm.clearErrors();
    showCreateDialog.value = true;
};

const createTag = () => {
    createForm.post(route('verification-tags.store'), {
        onSuccess: () => {
            showCreateDialog.value = false;
        },
    });
};

const markWritten = (tagId) => {
    useForm({}).patch(route('verification-tags.written', tagId), {
        preserveScroll: true,
    });
};

const lockTag = (tagId) => {
    useForm({}).patch(route('verification-tags.lock', tagId), {
        preserveScroll: true,
    });
};

const deactivateTag = (tagId) => {
    useForm({}).patch(route('verification-tags.deactivate', tagId), {
        preserveScroll: true,
    });
};

const copyToClipboard = async (value, token) => {
    if (!value) return;

    try {
        await navigator.clipboard.writeText(value);
        copiedToken.value = token;
        window.setTimeout(() => {
            if (copiedToken.value === token) copiedToken.value = null;
        }, 1800);
    } catch {
        copiedToken.value = null;
    }
};

const openWriter = (tagId) => {
    const writerUrl = route('verification-tags.writer', tagId);
    window.open(writerUrl, '_blank', 'noopener');
};
</script>

<template>
    <AppLayout>
        <div class="space-y-6">
            <div class="border border-surface-200 bg-white px-5 py-5">
                <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                    <div>
                        <h2 class="text-2xl font-semibold tracking-tight text-surface-900">Verification Tags</h2>
                        <p class="mt-1 text-sm text-surface-500">Create public verification links for sold items, then track whether the NFC tag has been written and locked.</p>
                    </div>

                    <div class="flex items-center gap-2">
                        <Button label="Create Tag" icon="pi pi-plus" @click="openCreateDialog" />
                    </div>
                </div>
            </div>

            <div class="grid grid-cols-1 gap-4 md:grid-cols-4">
                <div class="border border-surface-200 bg-white p-4">
                    <p class="text-xs uppercase tracking-wide text-surface-500">Public Base URL</p>
                    <p class="mt-2 break-all text-sm font-medium text-surface-900">{{ publicBaseUrl || 'Not set' }}</p>
                </div>
                <div class="border border-surface-200 bg-white p-4">
                    <p class="text-xs uppercase tracking-wide text-surface-500">Active Tags</p>
                    <p class="mt-2 text-2xl font-semibold text-surface-900">{{ activeTagsCount }}</p>
                </div>
                <div class="border border-surface-200 bg-white p-4">
                    <p class="text-xs uppercase tracking-wide text-surface-500">Pending Setup</p>
                    <p class="mt-2 text-2xl font-semibold text-amber-600">{{ pendingTagsCount }}</p>
                </div>
                <div class="border border-surface-200 bg-white p-4">
                    <p class="text-xs uppercase tracking-wide text-surface-500">Locked Tags</p>
                    <p class="mt-2 text-2xl font-semibold text-emerald-600">{{ lockedTagsCount }}</p>
                </div>
            </div>

            <div class="border border-surface-200 bg-white">
                <div class="flex flex-col gap-2 border-b border-surface-200 px-5 py-4 md:flex-row md:items-center md:justify-between">
                    <div>
                        <h3 class="text-base font-semibold text-surface-900">Tag Register</h3>
                        <p class="mt-1 text-sm text-surface-500">Each tag gets a neutral `tag_...` token and a public verification URL. Use the desktop writer page with your PC NFC reader/writer to write and then lock the tag.</p>
                    </div>
                </div>

                <div v-if="!tags?.length" class="px-5 py-10 text-center text-sm text-surface-500">
                    No verification tags created yet.
                </div>

                <div v-else class="divide-y divide-surface-200">
                    <div v-for="tag in tags" :key="tag.id" class="grid gap-4 px-5 py-4 lg:grid-cols-[1.3fr_1fr_0.9fr_auto] lg:items-center">
                        <div class="space-y-2">
                            <div class="flex flex-wrap items-center gap-2">
                                <p class="text-sm font-semibold text-surface-900">{{ tag.token }}</p>
                                <Tag :value="tag.status" :severity="statusSeverity(tag.status)" />
                                <Tag v-if="!tag.is_active" value="Inactive" severity="contrast" />
                            </div>

                            <div class="text-sm text-surface-600">
                                <div><span class="font-medium text-surface-800">Item:</span> {{ tag.item_name || 'Sold item' }}</div>
                                <div><span class="font-medium text-surface-800">Invoice:</span> {{ tag.invoice_number || '—' }}</div>
                                <div><span class="font-medium text-surface-800">Customer:</span> {{ tag.customer_name || 'Walk-in' }}</div>
                            </div>
                        </div>

                        <div class="space-y-2">
                            <p class="text-xs uppercase tracking-wide text-surface-500">Public Verify URL</p>
                            <div class="flex items-start gap-2">
                                <InputText :model-value="tag.public_url" readonly class="w-full" />
                                <Button icon="pi pi-copy" outlined @click="copyToClipboard(tag.public_url, tag.token)" />
                            </div>
                            <small class="block text-xs text-emerald-600" v-if="copiedToken === tag.token">Copied</small>
                        </div>

                        <div class="space-y-1 text-sm text-surface-600">
                            <div><span class="font-medium text-surface-800">Written:</span> {{ tag.written_at || 'Not marked' }}</div>
                            <div><span class="font-medium text-surface-800">Locked:</span> {{ tag.locked_at || 'Not marked' }}</div>
                            <div><span class="font-medium text-surface-800">Verified:</span> {{ tag.verified_count }}</div>
                        </div>

                        <div class="flex flex-wrap gap-2 lg:justify-end">
                            <Button
                                v-if="tag.is_active"
                                label="Open Desktop Writer"
                                icon="pi pi-external-link"
                                size="small"
                                @click="openWriter(tag.id)"
                            />
                            <Button
                                v-if="tag.status === 'PENDING' && tag.is_active"
                                label="Mark Written"
                                icon="pi pi-pencil"
                                outlined
                                size="small"
                                @click="markWritten(tag.id)"
                            />
                            <Button
                                v-if="tag.status !== 'LOCKED' && tag.is_active"
                                label="Lock"
                                icon="pi pi-lock"
                                severity="success"
                                outlined
                                size="small"
                                @click="lockTag(tag.id)"
                            />
                            <Button
                                v-if="tag.is_active"
                                label="Deactivate"
                                icon="pi pi-ban"
                                severity="danger"
                                outlined
                                size="small"
                                @click="deactivateTag(tag.id)"
                            />
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <Dialog v-model:visible="showCreateDialog" header="Create Verification Tag" modal class="w-full max-w-2xl">
            <div class="space-y-4 pt-2">
                <div class="border border-surface-200 bg-surface-50 px-4 py-3 text-sm text-surface-700">
                    The selected sold item will receive a new `tag_...` token and a public verification URL under
                    <span class="font-semibold text-surface-900">{{ publicBaseUrl }}</span>.
                </div>

                <div>
                    <label class="mb-2 block text-sm font-medium text-surface-700">Sold Item</label>
                    <Select
                        v-model="createForm.invoice_item_id"
                        :options="eligibleItems"
                        optionLabel="label"
                        optionValue="id"
                        filter
                        class="w-full"
                        placeholder="Select a sold invoice item"
                    />
                    <small v-if="createForm.errors.invoice_item_id" class="mt-1 block text-red-600">{{ createForm.errors.invoice_item_id }}</small>
                </div>

                <div>
                    <label class="mb-2 block text-sm font-medium text-surface-700">Notes</label>
                    <Textarea v-model="createForm.notes" rows="3" class="w-full" placeholder="Optional tag batch note, packaging note, or NFC card reference..." />
                    <small v-if="createForm.errors.notes" class="mt-1 block text-red-600">{{ createForm.errors.notes }}</small>
                </div>

                <div class="flex justify-end gap-2 pt-2">
                    <Button label="Cancel" text @click="showCreateDialog = false" />
                    <Button label="Create Tag" icon="pi pi-plus" @click="createTag" :loading="createForm.processing" />
                </div>
            </div>
        </Dialog>
    </AppLayout>
</template>
