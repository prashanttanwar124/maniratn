<script setup>
import axios from 'axios';
import { Plus, Search, Smartphone, UserRound } from 'lucide-vue-next';
import AutoComplete from 'primevue/autocomplete';
import Button from 'primevue/button';
import Dialog from 'primevue/dialog';
import InputText from 'primevue/inputtext';
import { ref, watch } from 'vue';

// 1. Props (What the parent sends in)
const props = defineProps({
    modelValue: {
        type: [String, Number, null],
        default: null,
    },
    errorMessage: {
        type: String,
        default: '',
    },
    placeholder: {
        type: String,
        default: 'Search customer by name or mobile...',
    },
    helperText: {
        type: String,
        default: '',
    },
    selectedOption: {
        type: Object,
        default: null,
    },
});

// 2. Emits (What we send back to parent)
const emit = defineEmits(['update:modelValue', 'select']);

// 3. State
const filteredCustomers = ref([]);
const selectedObject = ref(null);
const isSearching = ref(false);

// Quick-add dialog state
const showDialog = ref(false);
const isSaving = ref(false);
const newCustomer = ref({ name: '', mobile: '' });
const saveErrors = ref({});

// 4. Search Logic
const searchCustomer = async (event) => {
    if (!event.query.trim()) return;

    try {
        isSearching.value = true;
        const response = await axios.get(route('customers.search'), {
            params: { query: event.query },
        });
        filteredCustomers.value = response.data;
    } catch (error) {
        console.error('Search failed', error);
    } finally {
        isSearching.value = false;
    }
};

// 5. Handle Selection
const onSelect = (event) => {
    emit('update:modelValue', event.value.id);
    emit('select', event.value);
};

// 6. Handle Clearing/Resetting
const onClear = () => {
    emit('update:modelValue', null);
    emit('select', null);
    selectedObject.value = null;
};

// 7. Quick-add customer
const openQuickAdd = () => {
    newCustomer.value = { name: '', mobile: '' };
    saveErrors.value = {};
    showDialog.value = true;
};

const saveQuickCustomer = async () => {
    saveErrors.value = {};
    isSaving.value = true;

    try {
        const response = await axios.post(route('customers.quick-store'), newCustomer.value);
        const created = response.data;

        // Auto-select the newly created customer
        selectedObject.value = created;
        emit('update:modelValue', created.id);
        emit('select', created);

        showDialog.value = false;
    } catch (error) {
        if (error.response?.status === 422) {
            saveErrors.value = error.response.data.errors || {};
        } else {
            saveErrors.value = { general: ['Something went wrong. Please try again.'] };
        }
    } finally {
        isSaving.value = false;
    }
};

// 8. Watch for External Resets
watch(
    () => props.modelValue,
    (newVal) => {
        if (!newVal) {
            selectedObject.value = null;
        }
    },
);

watch(
    () => props.selectedOption,
    (newVal) => {
        selectedObject.value = newVal || null;
    },
    { immediate: true },
);
</script>

<template>
    <div class="flex flex-col gap-1">
        <div class="flex items-center gap-2">
            <AutoComplete
                v-model="selectedObject"
                :suggestions="filteredCustomers"
                @complete="searchCustomer"
                @item-select="onSelect"
                @clear="onClear"
                optionLabel="name"
                forceSelection
                dropdown
                dropdownMode="blank"
                placeholder="Search customer by name or mobile..."
                class="customer-selector w-full"
                inputClass="customer-selector-input w-full"
                panelClass="customer-selector-panel"
                :class="{ 'p-invalid': errorMessage }"
                :placeholder="placeholder"
                :loading="isSearching"
            >
                <template #dropdownicon>
                    <Search :size="15" />
                </template>

                <template #value="slotProps">
                    <div v-if="slotProps.value" class="flex min-w-0 items-center gap-2">
                        <UserRound :size="14" class="shrink-0 text-surface-500" />
                        <span class="truncate text-sm font-medium text-surface-900">{{ slotProps.value.name }}</span>
                        <span v-if="slotProps.value.mobile" class="shrink-0 text-xs text-surface-400">{{ slotProps.value.mobile }}</span>
                    </div>
                </template>

                <template #option="slotProps">
                    <div class="flex items-center gap-2.5">
                        <UserRound :size="14" class="shrink-0 text-surface-500" />
                        <div class="min-w-0 flex-1">
                            <span class="block truncate text-sm font-medium text-surface-900">{{ slotProps.option.name }}</span>
                            <span class="flex items-center gap-1.5 text-xs text-surface-500">
                                <Smartphone :size="11" />
                                {{ slotProps.option.mobile }}
                            </span>
                        </div>
                    </div>
                </template>

                <template #empty>
                    <div class="px-3 py-4 text-center text-sm text-surface-500">
                        <p>No customers found.</p>
                        <button type="button" class="mt-2 inline-flex items-center gap-1.5 text-sm font-medium text-primary hover:underline" @click="openQuickAdd">
                            <Plus :size="14" />
                            Add new customer
                        </button>
                    </div>
                </template>

                <template #footer>
                    <div class="flex items-center justify-between border-t border-surface-200 px-3 py-2">
                        <span class="text-xs text-surface-500">Search by name or mobile</span>
                        <button type="button" class="inline-flex items-center gap-1 text-xs font-medium text-primary hover:underline" @click="openQuickAdd">
                            <Plus :size="12" />
                            New Customer
                        </button>
                    </div>
                </template>
            </AutoComplete>

            <Button
                type="button"
                icon="pi pi-user-plus"
                severity="secondary"
                outlined
                @click="openQuickAdd"
                v-tooltip.top="'Quick add customer'"
                class="shrink-0"
            />
        </div>

        <small v-if="helperText && !errorMessage" class="text-xs text-surface-500">
            {{ helperText }}
        </small>

        <small v-if="errorMessage" class="text-xs text-red-500">
            {{ errorMessage }}
        </small>

        <!-- Quick Add Customer Dialog -->
        <Dialog
            v-model:visible="showDialog"
            header="Quick Add Customer"
            modal
            :style="{ width: '28rem' }"
            :closable="!isSaving"
            :closeOnEscape="!isSaving"
        >
            <div class="flex flex-col gap-4 pt-2">
                <small v-if="saveErrors.general" class="text-xs text-red-500">{{ saveErrors.general[0] }}</small>

                <div class="flex flex-col gap-1.5">
                    <label class="text-sm font-medium text-surface-700">
                        Name <span class="text-red-500">*</span>
                    </label>
                    <InputText
                        v-model="newCustomer.name"
                        placeholder="Customer name"
                        :class="{ 'p-invalid': saveErrors.name }"
                        @keydown.enter="saveQuickCustomer"
                    />
                    <small v-if="saveErrors.name" class="text-xs text-red-500">{{ saveErrors.name[0] }}</small>
                </div>

                <div class="flex flex-col gap-1.5">
                    <label class="text-sm font-medium text-surface-700">
                        Mobile <span class="text-red-500">*</span>
                    </label>
                    <InputText
                        v-model="newCustomer.mobile"
                        placeholder="Mobile number"
                        :class="{ 'p-invalid': saveErrors.mobile }"
                        @keydown.enter="saveQuickCustomer"
                    />
                    <small v-if="saveErrors.mobile" class="text-xs text-red-500">{{ saveErrors.mobile[0] }}</small>
                </div>
            </div>

            <template #footer>
                <div class="flex items-center justify-end gap-2">
                    <Button label="Cancel" severity="secondary" text @click="showDialog = false" :disabled="isSaving" />
                    <Button label="Add & Select" icon="pi pi-check" @click="saveQuickCustomer" :loading="isSaving" />
                </div>
            </template>
        </Dialog>
    </div>
</template>

<style scoped>
:deep(.customer-selector .p-autocomplete-input) {
    padding-top: 0.5rem;
    padding-bottom: 0.5rem;
}

:deep(.customer-selector .p-autocomplete-dropdown) {
    width: 2.5rem;
}

:deep(.customer-selector-panel .p-autocomplete-list) {
    padding: 0.4rem;
}

:deep(.customer-selector-panel .p-autocomplete-option) {
    border-bottom: 1px solid var(--p-content-border-color);
    padding: 0.45rem 0.6rem;
}

:deep(.customer-selector-panel .p-autocomplete-option:last-child) {
    border-bottom: 0;
}
</style>
