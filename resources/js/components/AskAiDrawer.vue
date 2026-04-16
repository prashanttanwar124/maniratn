<script setup>
import Button from 'primevue/button';
import Drawer from 'primevue/drawer';
import Textarea from 'primevue/textarea';
import { computed, nextTick, ref } from 'vue';

defineProps({
    visible: Boolean,
});

const emit = defineEmits(['update:visible']);

const draftMessage = ref('');
const messageList = ref(null);
const pendingChange = ref(null);
const appliedChanges = ref([]);
const messages = ref([
    {
        id: 1,
        role: 'assistant',
        text: 'Tell me what you want to change. I will prepare it first, then you can type "approve the change" to apply it.',
    },
]);

const approvalPattern = /\b(approve|approved|confirm|apply|do it|yes)\b.*\b(change|changes|this|it)\b|\b(approve|confirm|apply)\b/i;

const hasDraftMessage = computed(() => draftMessage.value.trim().length > 0);

const closeDrawer = () => {
    emit('update:visible', false);
};

const scrollToLatestMessage = async () => {
    await nextTick();
    messageList.value?.scrollTo({
        top: messageList.value.scrollHeight,
        behavior: 'smooth',
    });
};

const addMessage = (role, text) => {
    messages.value.push({
        id: Date.now() + messages.value.length,
        role,
        text,
    });

    scrollToLatestMessage();
};

const buildChangeTitle = (message) => {
    const trimmed = message.trim();
    const normalized = trimmed.charAt(0).toUpperCase() + trimmed.slice(1);

    return normalized.length > 80 ? `${normalized.slice(0, 77)}...` : normalized;
};

const prepareChange = (message) => {
    pendingChange.value = {
        id: Date.now(),
        title: buildChangeTitle(message),
        request: message,
        status: 'waiting',
    };

    addMessage('assistant', `I prepared this change: ${pendingChange.value.title}. Type "approve the change" when you want me to apply it.`);
};

const applyPendingChange = () => {
    if (!pendingChange.value) {
        addMessage('assistant', 'There is no pending change to approve yet. Send the change you want first.');
        return;
    }

    appliedChanges.value.unshift({
        ...pendingChange.value,
        status: 'applied',
        appliedAt: new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' }),
    });

    addMessage('assistant', `Done. I applied: ${pendingChange.value.title}.`);
    pendingChange.value = null;
};

const sendMessage = () => {
    const message = draftMessage.value.trim();

    if (!message) {
        return;
    }

    addMessage('user', message);
    draftMessage.value = '';

    if (approvalPattern.test(message)) {
        applyPendingChange();
        return;
    }

    prepareChange(message);
};
</script>

<template>
    <Drawer
        :visible="visible"
        position="right"
        class="!w-full md:!w-[28rem]"
        header="Ask AI"
        @update:visible="emit('update:visible', $event)"
    >
        <div class="flex h-full min-h-0 flex-col">
            <div class="mb-4 border border-surface-200 bg-surface-50 px-4 py-3">
                <p class="text-sm font-medium text-surface-900">AI chat workspace</p>
                <p class="mt-1 text-sm text-surface-500">
                    Ask for a change, review the prepared action, then type approve the change to apply it.
                </p>
            </div>

            <div
                ref="messageList"
                class="min-h-0 flex-1 space-y-3 overflow-y-auto pr-1"
            >
                <div
                    v-for="message in messages"
                    :key="message.id"
                    class="flex"
                    :class="message.role === 'user' ? 'justify-end' : 'justify-start'"
                >
                    <div
                        class="max-w-[84%] px-4 py-3 text-sm leading-relaxed shadow-sm"
                        :class="message.role === 'user'
                            ? 'bg-primary text-primary-contrast'
                            : 'border border-surface-200 bg-white text-surface-700'"
                    >
                        {{ message.text }}
                    </div>
                </div>
            </div>

            <div
                v-if="pendingChange"
                class="mt-4 border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900"
            >
                <div class="flex items-start gap-3">
                    <i class="pi pi-clock mt-0.5 text-amber-600"></i>
                    <div>
                        <p class="font-medium">Waiting for approval</p>
                        <p class="mt-1 text-amber-800">{{ pendingChange.title }}</p>
                    </div>
                </div>
            </div>

            <div
                v-if="appliedChanges.length"
                class="mt-3 border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-900"
            >
                <div class="flex items-start gap-3">
                    <i class="pi pi-check-circle mt-0.5 text-emerald-600"></i>
                    <div>
                        <p class="font-medium">Last applied change</p>
                        <p class="mt-1 text-emerald-800">{{ appliedChanges[0].title }}</p>
                    </div>
                </div>
            </div>

            <div class="mt-4 border-t border-surface-200 pt-4">
                <Textarea
                    v-model="draftMessage"
                    rows="3"
                    autoResize
                    class="w-full"
                    placeholder="Write a change, or type approve the change"
                    @keydown.enter.exact.prevent="sendMessage"
                />
                <div class="mt-3 flex items-center justify-between gap-3">
                    <Button label="Close" severity="secondary" text @click="closeDrawer" />
                    <Button
                        label="Send"
                        icon="pi pi-send"
                        :disabled="!hasDraftMessage"
                        @click="sendMessage"
                    />
                </div>
            </div>
        </div>
    </Drawer>
</template>
