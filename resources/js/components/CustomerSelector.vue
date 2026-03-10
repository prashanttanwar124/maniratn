<script setup>
import axios from 'axios';
import { Smartphone } from 'lucide-vue-next';
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
});

// 2. Emits (What we send back to parent)
const emit = defineEmits(['update:modelValue', 'select']);

// 3. State
const filteredCustomers = ref([]);
const selectedObject = ref(null); // PrimeVue needs the full object here

// 4. Search Logic
const searchCustomer = async (event) => {
    if (!event.query.trim()) return;

    try {
        const response = await axios.get(route('customers.search'), {
            params: { query: event.query },
        });
        filteredCustomers.value = response.data;
    } catch (error) {
        console.error('Search failed', error);
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
            placeholder="Type Name or Mobile..."
            class="w-full"
            inputClass="w-full"
            :class="{ 'p-invalid': errorMessage }"
        >
            <template #option="slotProps">
                <div class="flex flex-col border-b pb-1 last:border-0">
                    <span class="text-sm font-bold">{{ slotProps.option.name }}</span>
                    <div class="mt-1 flex items-center gap-2">
                        <Smartphone :size="14" class="text-indigo-500" />

                        <span class="font-mono text-xs tracking-wide text-gray-500">
                            {{ slotProps.option.mobile }}
                        </span>
                    </div>
                </div>
            </template>
        </AutoComplete>

        <small v-if="errorMessage" class="text-xs text-red-500">
            {{ errorMessage }}
        </small>
    </div>
</template>
