<script setup>
import axios from 'axios';
import { Search, Smartphone, UserRound } from 'lucide-vue-next';
import AutoComplete from 'primevue/autocomplete';
import { ref, watch } from 'vue';

// 1. Props (What the parent sends in)
const props = defineProps({
    modelValue: {
        // This handles v-model="form.customer_id"
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
const selectedObject = ref(null); // PrimeVue needs the full object here
const isSearching = ref(false);

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
    // event.value is the selected User Object {id: 1, name: '...'}
    emit('update:modelValue', event.value.id); // Send ID to Form
    emit('select', event.value); // Send full object just in case
};

// 6. Handle Clearing/Resetting
const onClear = () => {
    emit('update:modelValue', null);
    selectedObject.value = null;
};

// 7. Watch for External Resets (e.g. form.reset())
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
        if (newVal?.id && String(newVal.id) === String(props.modelValue)) {
            selectedObject.value = newVal;
        }
    },
    { immediate: true },
);
</script>

<template>
    <div class="flex flex-col gap-1">
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
            class="w-full"
            inputClass="w-full !py-3"
            :class="{ 'p-invalid': errorMessage }"
            :placeholder="placeholder"
            :loading="isSearching"
        >
            <template #dropdownicon>
                <Search :size="15" />
            </template>

            <template #value="slotProps">
                <div v-if="slotProps.value" class="flex min-w-0 items-center gap-3">
                    <div class="flex h-9 w-9 shrink-0 items-center justify-center border border-surface-200 bg-surface-50 text-surface-600">
                        <UserRound :size="16" />
                    </div>
                    <div class="min-w-0">
                        <p class="truncate text-sm font-medium text-surface-900">{{ slotProps.value.name }}</p>
                        <p class="truncate text-xs text-surface-500">{{ slotProps.value.mobile }}</p>
                    </div>
                </div>
            </template>

            <template #option="slotProps">
                <div class="flex items-center gap-3 border-b border-surface-100 py-2 last:border-0">
                    <div class="flex h-9 w-9 shrink-0 items-center justify-center border border-surface-200 bg-surface-50 text-surface-600">
                        <UserRound :size="16" />
                    </div>
                    <div class="min-w-0 flex-1">
                        <span class="block truncate text-sm font-semibold text-surface-900">{{ slotProps.option.name }}</span>
                        <div class="mt-1 flex items-center gap-2">
                            <Smartphone :size="14" class="text-surface-500" />
                            <span class="font-mono text-xs tracking-wide text-surface-500">
                                {{ slotProps.option.mobile }}
                            </span>
                        </div>
                    </div>
                </div>
            </template>

            <template #empty>
                <div class="px-3 py-4 text-sm text-surface-500">
                    Start typing a customer name or mobile number.
                </div>
            </template>

            <template #footer>
                <div class="border-t border-surface-200 px-3 py-2 text-xs text-surface-500">
                    Search by customer name or mobile number.
                </div>
            </template>
        </AutoComplete>

        <small v-if="helperText && !errorMessage" class="text-xs text-surface-500">
            {{ helperText }}
        </small>

        <small v-if="errorMessage" class="text-xs text-red-500">
            {{ errorMessage }}
        </small>
    </div>
</template>
