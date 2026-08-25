<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import axios from 'axios';
import { Bot, Calculator, Clock, Coins, Mic, MicOff, PackagePlus, Receipt, RefreshCw, Send, Sparkles, TrendingUp, Volume2, VolumeX, Wallet, X } from 'lucide-vue-next';
import Drawer from 'primevue/drawer';
import Textarea from 'primevue/textarea';
import { computed, nextTick, onMounted, ref, watch } from 'vue';

// Subcomponents
import CustomerKhataCard from './ai/CustomerKhataCard.vue';
import DailyRatesCard from './ai/DailyRatesCard.vue';
import InvoiceDraftCard from './ai/InvoiceDraftCard.vue';
import InvoiceHistoryCard from './ai/InvoiceHistoryCard.vue';
import ProductDraftCard from './ai/ProductDraftCard.vue';
import SalesSummaryCard from './ai/SalesSummaryCard.vue';
import StockCheckCard from './ai/StockCheckCard.vue';
import VaultBalanceCard from './ai/VaultBalanceCard.vue';

interface ActionItem {
    tool: string;
    args: Record<string, any>;
    result: Record<string, any>;
}

interface Message {
    id: string;
    role: 'user' | 'assistant';
    content: string;
    actions?: ActionItem[];
    audio?: string | null;
    timestamp: string;
}

const props = defineProps<{
    visible: boolean;
}>();

const emit = defineEmits(['update:visible']);
const page = usePage();
const isVoiceGloballyEnabled = computed(() => Boolean((page.props.aiSettings as any)?.voice_enabled ?? true));

const messages = ref<Message[]>([
    {
        id: 'welcome',
        role: 'assistant',
        content:
            'Namaste! Main Karat AI Voice Copilot hoon. Aap live market bhav pooch sakte hain, naya stock add karwa sakte hain, ya quotation / bill bana sakte hain. Mic button daba kar boliye ya type kijiye.',
        timestamp: new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' }),
    },
]);

const hasMoreHistory = ref(false);
const isLoadingHistory = ref(false);
const oldestMessageId = ref<string | null>(null);
const historyLoaded = ref(false);

const inputPrompt = ref('');
const isLoading = ref(false);
const isListening = ref(false);
const isSpeaking = ref(false);
const recognitionSupported = ref(true);
const speechError = ref('');
const chatError = ref('');
const showResetConfirm = ref(false);
const autoVoiceOutput = ref(true);
const selectedVoice = ref('Aoede');
const messageContainer = ref<HTMLElement | null>(null);
const composerRef = ref<any>(null);

const isStarterConversation = computed(() => messages.value.length === 1 && messages.value[0]?.id.startsWith('welcome'));

const quickSuggestions = [
    { label: 'Aaj ka 22K & Silver bhav?', icon: TrendingUp, prompt: 'Aaj 22K gold aur silver ka bhav kya hai?' },
    { label: '15g Chain Bill banao', icon: Receipt, prompt: '15g 22K Gold Chain ka bill bana do' },
    { label: '14.5g 22K Gold Chain add karo', icon: PackagePlus, prompt: '14.5 gram ki 22K gold chain add kar do' },
    { label: '15g 22K Chain quotation estimate', icon: Sparkles, prompt: '15 gram 22K chain ka total estimate kitna banega?' },
    { label: 'Vault cash & gold balance', icon: Wallet, prompt: 'Vault me abhi kitna cash aur sona hai?' },
];

let recognition: any = null;
let currentAudio: HTMLAudioElement | null = null;

const initSpeech = () => {
    if (typeof window !== 'undefined') {
        const SpeechRecognition = (window as any).SpeechRecognition || (window as any).webkitSpeechRecognition;
        if (SpeechRecognition) {
            recognition = new SpeechRecognition();
            recognition.continuous = false;
            recognition.interimResults = false;
            recognition.lang = 'hi-IN';

            recognition.onstart = () => {
                speechError.value = '';
                isListening.value = true;
            };

            recognition.onresult = (event: any) => {
                const transcript = event.results[0][0].transcript;
                inputPrompt.value = transcript;
                isListening.value = false;
                sendMessage();
            };

            recognition.onerror = (err: any) => {
                console.warn('Speech recognition error:', err);
                speechError.value =
                    err.error === 'not-allowed' ? 'Mic permission blocked hai. Browser settings me microphone allow karein.' : 'Awaaz samajh nahi aayi. Dobara boliye ya command type karein.';
                isListening.value = false;
            };

            recognition.onend = () => {
                isListening.value = false;
            };
        } else {
            recognitionSupported.value = false;
        }
    }
};

const toggleListening = () => {
    if (!recognition) {
        speechError.value = 'Is browser me voice input available nahi hai. Aap command type kar sakte hain.';
        return;
    }

    if (isListening.value) {
        recognition.stop();
        isListening.value = false;
    } else {
        stopAudio();
        speechError.value = '';
        try {
            recognition.start();
        } catch (e) {
            console.error(e);
        }
    }
};

const playAudio = (audioDataUri: string) => {
    if (!audioDataUri) return;

    stopAudio();

    currentAudio = new Audio(audioDataUri);
    isSpeaking.value = true;

    currentAudio.onplay = () => {
        isSpeaking.value = true;
    };

    currentAudio.onended = () => {
        isSpeaking.value = false;
        currentAudio = null;
    };

    currentAudio.onerror = (e) => {
        console.error('Audio playback error', e);
        isSpeaking.value = false;
        currentAudio = null;
    };

    currentAudio.play().catch((err) => {
        console.warn('Audio play error:', err);
        isSpeaking.value = false;
    });
};

const stopAudio = () => {
    if (currentAudio) {
        currentAudio.pause();
        currentAudio.currentTime = 0;
        currentAudio = null;
    }
    isSpeaking.value = false;
};

const scrollToBottom = () => {
    nextTick(() => {
        if (messageContainer.value) {
            messageContainer.value.scrollTop = messageContainer.value.scrollHeight;
        }
    });
};

const sendMessage = async (customText?: string) => {
    const textToSend = customText || inputPrompt.value.trim();
    if (!textToSend || isLoading.value) return;

    stopAudio();
    speechError.value = '';
    chatError.value = '';

    const userMessage: Message = {
        id: Date.now().toString(),
        role: 'user',
        content: textToSend,
        timestamp: new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' }),
    };

    messages.value.push(userMessage);
    inputPrompt.value = '';
    isLoading.value = true;
    scrollToBottom();

    const historyPayload = messages.value
        .slice(-8, -1)
        .filter((m) => m.content && m.content.trim() !== '')
        .map((m) => ({
            role: m.role === 'user' ? 'user' : 'assistant',
            content: m.content.trim(),
        }));

    try {
        const response = await axios.post('/api/ai/copilot/chat', {
            message: textToSend,
            history: historyPayload,
            voice: selectedVoice.value,
            include_audio: isVoiceGloballyEnabled.value && autoVoiceOutput.value,
        });

        const replyText = response.data.reply || 'Action executed successfully.';
        const actions = response.data.actions || [];
        const audioUri = response.data.audio || null;

        const assistantMessage: Message = {
            id: (Date.now() + 1).toString(),
            role: 'assistant',
            content: replyText,
            actions: actions,
            audio: audioUri,
            timestamp: new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' }),
        };

        messages.value.push(assistantMessage);
        scrollToBottom();

        if (autoVoiceOutput.value && isVoiceGloballyEnabled.value && audioUri) {
            playAudio(audioUri);
        }
    } catch {
        messages.value.push({
            id: (Date.now() + 1).toString(),
            role: 'assistant',
            content: 'Error: AI Hub se connect nahi ho paya. Kripya check karein ki AI server chalu hai.',
            timestamp: new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' }),
        });
    } finally {
        isLoading.value = false;
        scrollToBottom();
    }
};

const isConfirming = ref<Record<string, boolean>>({});

const calculateLiveBill = (draft: any) => {
    const weight = Math.round(parseFloat(draft.weight || 0) * 1000) / 1000;
    const rate = Math.round(parseFloat(draft.rate_per_gm || 0) * 100) / 100;
    draft.rate_per_gm = rate;
    const metalVal = Math.round(weight * rate * 100) / 100;
    draft.metal_value = metalVal;

    let making = 0;
    if (draft.making_type === 'flat') {
        making = parseFloat(draft.making_value || 0);
    } else if (draft.making_type === 'per_gram') {
        making = Math.round(weight * parseFloat(draft.making_value || 0) * 100) / 100;
    } else {
        making = Math.round(metalVal * (parseFloat(draft.making_value || 12) / 100) * 100) / 100;
    }
    draft.making_charges = making;

    const discount = parseFloat(draft.discount_amount || 0);
    const subtotal = Math.max(0, metalVal + making - discount);
    draft.subtotal = Math.round(subtotal * 100) / 100;

    const gst = Math.round(subtotal * 0.03 * 100) / 100;
    draft.gst_3_percent = gst;

    const grandTotal = Math.round((subtotal + gst) * 100) / 100;
    draft.grand_total = grandTotal;
    if (!draft.payment_amount || draft.payment_amount === draft.prev_grand_total) {
        draft.payment_amount = grandTotal;
    }
    draft.prev_grand_total = grandTotal;
};

const confirmBillAction = async (action: ActionItem, msgId: string) => {
    const key = `bill_${msgId}`;
    isConfirming.value[key] = true;
    try {
        const payload = { ...action.result, message_id: msgId };
        const res = await axios.post('/api/ai/copilot/confirm-bill', payload);
        if (res.data && res.data.success) {
            action.result = {
                ...action.result,
                ...res.data,
                is_preview: false,
                found: true,
                status: 'INVOICE_GENERATED_REAL_DB',
            };
            const targetMsg = messages.value.find((m) => m.id === msgId);
            if (targetMsg) {
                targetMsg.content = `Done! Customer ${res.data.customer_name} ke liye Bill #${res.data.invoice_number} successfully create ho gaya hai.`;
            }
            if (isVoiceGloballyEnabled.value && autoVoiceOutput.value) {
                speakText(`Done! Bill number ${res.data.invoice_number} successfully create ho gaya hai.`);
            }
        }
    } catch (err: any) {
        alert(err.response?.data?.message || 'Error creating invoice in database.');
    } finally {
        isConfirming.value[key] = false;
    }
};

const confirmProductAction = async (action: ActionItem, msgId: string) => {
    const key = `prod_${msgId}`;
    isConfirming.value[key] = true;
    try {
        const payload = { ...action.result, message_id: msgId };
        const res = await axios.post('/api/ai/copilot/confirm-product', payload);
        if (res.data && res.data.success) {
            action.result = {
                ...action.result,
                ...res.data,
                is_preview: false,
                status: 'IN_STOCK_REAL_DB',
            };
            const targetMsg = messages.value.find((m) => m.id === msgId);
            if (targetMsg) {
                targetMsg.content = `Done! Product stock me save ho gayi, Barcode ${res.data.barcode}.`;
            }
            if (isVoiceGloballyEnabled.value && autoVoiceOutput.value) {
                speakText(`Done! Product stock me save ho gayi, Barcode ${res.data.barcode}.`);
            }
        }
    } catch (err: any) {
        alert(err.response?.data?.message || 'Error adding product.');
    } finally {
        isConfirming.value[key] = false;
    }
};

const confirmRatesAction = async (action: ActionItem, msgId: string) => {
    const key = `rates_${msgId}`;
    isConfirming.value[key] = true;
    try {
        const payload = { ...action.result, message_id: msgId };
        const res = await axios.post('/api/ai/copilot/confirm-rates', payload);
        if (res.data && res.data.success) {
            action.result = {
                ...action.result,
                ...res.data,
                is_preview: false,
                status: 'UPDATED_IN_DATABASE',
            };
            const targetMsg = messages.value.find((m) => m.id === msgId);
            if (targetMsg) {
                targetMsg.content = `Done! Aaj ke live rates database me update ho gaye.`;
            }
            if (isVoiceGloballyEnabled.value && autoVoiceOutput.value) {
                speakText(`Done! Aaj ke live rates update ho gaye.`);
            }
        }
    } catch (err: any) {
        alert(err.response?.data?.message || 'Error updating rates.');
    } finally {
        isConfirming.value[key] = false;
    }
};

const discardAction = (action: ActionItem, msgId?: string) => {
    action.result.is_preview = false;
    action.result.is_discarded = true;
    if (msgId) {
        const targetMsg = messages.value.find((m) => m.id === msgId);
        if (targetMsg) {
            targetMsg.content = 'Action draft discard kar diya gaya.';
        }
    }
};

const fetchChatHistory = async (isLoadMore = false) => {
    if (isLoadingHistory.value) return;
    isLoadingHistory.value = true;

    try {
        const params: any = { limit: 10 };
        if (isLoadMore && oldestMessageId.value) {
            params.before_id = oldestMessageId.value;
        }

        const res = await axios.get('/api/ai/copilot/history', { params });
        if (res.data && Array.isArray(res.data.messages)) {
            const fetched = res.data.messages;

            if (isLoadMore) {
                const prevScrollHeight = messageContainer.value?.scrollHeight || 0;
                messages.value = [...fetched, ...messages.value];
                nextTick(() => {
                    if (messageContainer.value) {
                        messageContainer.value.scrollTop = messageContainer.value.scrollHeight - prevScrollHeight;
                    }
                });
            } else {
                if (fetched.length > 0) {
                    messages.value = fetched;
                    scrollToBottom();
                }
            }

            hasMoreHistory.value = Boolean(res.data.has_more);
            if (res.data.oldest_id) {
                oldestMessageId.value = String(res.data.oldest_id);
            }
        }
    } catch (e) {
        console.warn('Could not load chat history:', e);
    } finally {
        isLoadingHistory.value = false;
        historyLoaded.value = true;
    }
};

const resetChat = async () => {
    stopAudio();
    chatError.value = '';
    try {
        await axios.delete('/api/ai/copilot/history');
    } catch (e) {
        console.warn(e);
        chatError.value = 'Chat history clear nahi ho paayi. Connection check karke dobara try karein.';
        showResetConfirm.value = false;
        return;
    }
    messages.value = [
        {
            id: 'welcome_reset',
            role: 'assistant',
            content: 'Chat session reset kar diya gaya hai. Main aapki kya sahayata karoon?',
            timestamp: new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' }),
        },
    ];
    hasMoreHistory.value = false;
    oldestMessageId.value = null;
    showResetConfirm.value = false;
};

const speakText = async (text: string) => {
    try {
        const res = await axios.post('/api/ai/copilot/speak', {
            text: text,
            voice: selectedVoice.value,
        });
        if (res.data && res.data.audio) {
            playAudio(res.data.audio);
        }
    } catch (e) {
        console.warn(e);
    }
};

watch(
    () => props.visible,
    (newVal) => {
        if (newVal) {
            scrollToBottom();
            nextTick(() => {
                const input = composerRef.value?.$el ?? composerRef.value;
                input?.focus?.();
            });
            if (!historyLoaded.value) {
                fetchChatHistory(false);
            }
        } else {
            stopAudio();
            showResetConfirm.value = false;
            if (isListening.value && recognition) {
                recognition.stop();
                isListening.value = false;
            }
        }
    },
);

onMounted(() => {
    initSpeech();
});
</script>

<template>
    <Drawer
        :visible="props.visible"
        position="right"
        class="!w-full !border-l !border-surface-200 !p-0 font-sans !shadow-2xl sm:!w-[560px] xl:!w-[600px]"
        :modal="false"
        :dismissable="true"
        :show-close-icon="false"
        :pt="{
            root: { class: '!p-0 !border-0 !rounded-none !bg-white' },
            header: { class: '!hidden !p-0' },
            content: { class: '!p-0 !overflow-hidden flex flex-col h-full' },
        }"
        @update:visible="emit('update:visible', $event)"
    >
        <template #container>
            <div class="relative flex h-full w-full flex-col bg-white font-sans text-surface-800" style="font-family: 'Poppins', sans-serif !important">
                <div class="z-10 flex w-full shrink-0 items-center justify-between border-b border-b-[#c08f34] bg-[#1c3633] px-4 py-3.5 text-white sm:px-5">
                    <div class="flex min-w-0 items-center gap-3">
                        <div class="flex h-10 w-10 shrink-0 items-center justify-center border border-white/10 bg-white/10">
                            <Bot class="h-5 w-5 text-[#e1b65f]" />
                        </div>
                        <div class="min-w-0">
                            <div class="flex items-center gap-2">
                                <span class="truncate text-sm font-semibold tracking-wide text-white">Karat AI</span>
                                <span
                                    class="hidden items-center gap-1 border border-emerald-400/30 bg-emerald-400/10 px-1.5 py-0.5 text-[9px] font-semibold tracking-wide text-emerald-200 uppercase min-[390px]:inline-flex"
                                >
                                    <span class="h-1.5 w-1.5 bg-emerald-400"></span>
                                    ERP live
                                </span>
                            </div>
                            <p class="truncate text-[11px] text-white/65">Billing, stock, rates aur vault assistant</p>
                        </div>
                    </div>

                    <div class="flex shrink-0 items-center gap-1.5">
                        <button
                            v-if="isVoiceGloballyEnabled"
                            type="button"
                            class="inline-flex h-9 items-center gap-1.5 border px-2.5 text-[11px] font-medium transition-colors"
                            :class="autoVoiceOutput ? 'border-[#c08f34]/70 bg-[#c08f34] text-[#142926]' : 'border-white/15 bg-white/5 text-white/70 hover:bg-white/10'"
                            :aria-pressed="autoVoiceOutput"
                            :aria-label="autoVoiceOutput ? 'Mute AI voice replies' : 'Enable AI voice replies'"
                            @click="
                                autoVoiceOutput = !autoVoiceOutput;
                                if (!autoVoiceOutput) stopAudio();
                            "
                        >
                            <Volume2 v-if="autoVoiceOutput" class="h-3.5 w-3.5" />
                            <VolumeX v-else class="h-3.5 w-3.5" />
                            <span class="hidden sm:inline">{{ autoVoiceOutput ? 'Voice on' : 'Muted' }}</span>
                        </button>

                        <button
                            type="button"
                            class="flex h-9 w-9 items-center justify-center border border-white/15 bg-white/5 text-white/75 transition-colors hover:bg-white/10 hover:text-white"
                            aria-label="Clear chat history"
                            title="Clear chat history"
                            @click="showResetConfirm = true"
                        >
                            <RefreshCw class="h-3.5 w-3.5" />
                        </button>

                        <button
                            type="button"
                            class="flex h-9 w-9 items-center justify-center border border-white/15 bg-white/5 text-white/75 transition-colors hover:border-red-300/40 hover:bg-red-500/20 hover:text-white"
                            aria-label="Close Karat AI"
                            title="Close Karat AI"
                            @click="
                                emit('update:visible', false);
                                stopAudio();
                            "
                        >
                            <X class="h-4 w-4" />
                        </button>
                    </div>
                </div>

                <div v-if="showResetConfirm" class="flex shrink-0 items-center justify-between gap-3 border-b border-amber-200 bg-amber-50 px-4 py-2.5 sm:px-5" role="alert">
                    <p class="text-[10.5px] leading-4 font-medium text-amber-900">Poori chat history clear karni hai?</p>
                    <div class="flex shrink-0 items-center gap-1.5">
                        <button
                            type="button"
                            class="border border-transparent px-2.5 py-1.5 text-[10.5px] font-medium text-surface-600 hover:border-surface-300 hover:bg-white"
                            @click="showResetConfirm = false"
                        >
                            Cancel
                        </button>
                        <button type="button" class="border border-[#1c3633] bg-[#1c3633] px-2.5 py-1.5 text-[10.5px] font-semibold text-white hover:bg-[#254642]" @click="resetChat">
                            Clear chat
                        </button>
                    </div>
                </div>

                <div class="flex shrink-0 items-center justify-between gap-3 border-b border-surface-200 bg-white px-4 py-2 sm:px-5">
                    <p class="text-[10.5px] leading-4 text-surface-500">Live ERP data connected · Save hone se pehle aap review karenge</p>
                    <span class="shrink-0 border border-emerald-200 bg-emerald-50 px-2 py-1 text-[9.5px] font-semibold text-emerald-700">Secure</span>
                </div>

                <div class="flex h-full min-h-0 flex-1 flex-col overflow-hidden bg-[#f7f9f8]">
                    <div ref="messageContainer" class="min-h-0 flex-1 space-y-5 overflow-y-auto px-3 py-5 sm:px-5" aria-live="polite" aria-label="Conversation">
                        <div v-if="isLoadingHistory && !historyLoaded" class="flex h-full items-center justify-center">
                            <div class="flex items-center gap-2 text-xs font-medium text-surface-500">
                                <RefreshCw class="h-4 w-4 animate-spin text-[#c08f34]" />
                                Pichli conversation load ho rahi hai...
                            </div>
                        </div>

                        <div v-if="historyLoaded && isStarterConversation" class="mx-auto flex min-h-full max-w-lg flex-col justify-center py-4">
                            <div class="mb-6 text-center">
                                <div class="mx-auto mb-3 flex h-12 w-12 items-center justify-center border border-amber-200 bg-amber-50">
                                    <Sparkles class="h-5 w-5 text-[#b07b24]" />
                                </div>
                                <h3 class="text-lg font-semibold text-[#1c3633]">Aaj kya kaam karna hai?</h3>
                                <p class="mx-auto mt-1.5 max-w-sm text-xs leading-5 text-surface-500">Hindi ya English mein poochiye. Koi bhi database change confirm karne ke baad hi save hoga.</p>
                            </div>
                            <p class="mb-2 text-[10px] font-semibold tracking-[0.14em] text-surface-400 uppercase">Popular tasks</p>
                            <div class="grid grid-cols-1 gap-2 min-[420px]:grid-cols-2">
                                <button
                                    v-for="(item, idx) in quickSuggestions"
                                    :key="idx"
                                    type="button"
                                    :disabled="isLoading"
                                    class="group flex min-h-14 items-center gap-3 border border-surface-200 bg-white px-3 py-2.5 text-left shadow-xs transition-colors hover:border-[#c08f34] hover:bg-amber-50/40 disabled:opacity-50"
                                    @click="sendMessage(item.prompt)"
                                >
                                    <span class="flex h-8 w-8 shrink-0 items-center justify-center bg-[#1c3633] text-[#e1b65f]">
                                        <component :is="item.icon" class="h-4 w-4" />
                                    </span>
                                    <span class="text-[11.5px] leading-4 font-medium text-surface-700 group-hover:text-[#1c3633]">{{ item.label }}</span>
                                </button>
                            </div>
                        </div>

                        <div v-if="!isStarterConversation && hasMoreHistory" class="flex justify-center pb-2">
                            <button
                                type="button"
                                :disabled="isLoadingHistory"
                                class="flex items-center gap-2 border border-surface-200 bg-white px-3 py-1.5 text-[10.5px] font-medium text-surface-600 shadow-xs transition-colors hover:border-[#c08f34] hover:text-[#1c3633] disabled:opacity-50"
                                @click="fetchChatHistory(true)"
                            >
                                <RefreshCw v-if="isLoadingHistory" class="h-3 w-3 animate-spin text-[#c08f34]" />
                                <Clock v-else class="h-3 w-3 text-[#c08f34]" />
                                <span>{{ isLoadingHistory ? 'Loading...' : 'Earlier chats' }}</span>
                            </button>
                        </div>

                        <!-- Chat Messages List -->
                        <div
                            v-for="msg in messages"
                            :key="msg.id"
                            v-show="!isStarterConversation"
                            :class="['flex max-w-full gap-2.5', msg.role === 'user' ? 'ml-auto max-w-[88%] flex-row-reverse' : 'mr-auto max-w-[96%]']"
                        >
                            <div
                                :class="[
                                    'mt-0.5 flex h-8 w-8 shrink-0 items-center justify-center border text-[9px] font-semibold',
                                    msg.role === 'user' ? 'border-[#1c3633] bg-[#1c3633] text-white' : 'border-amber-200 bg-amber-50 text-[#b07b24]',
                                ]"
                            >
                                <Sparkles v-if="msg.role === 'assistant'" class="h-3.5 w-3.5" />
                                <span v-else>Aap</span>
                            </div>

                            <div class="min-w-0 flex-1 space-y-2.5">
                                <div
                                    :class="[
                                        'border px-3.5 py-3 text-[12.5px] leading-5 shadow-xs',
                                        msg.role === 'user' ? 'border-[#1c3633] bg-[#1c3633] text-white' : 'border-surface-200 bg-white text-surface-800',
                                    ]"
                                >
                                    <div class="mb-2 flex items-center gap-2" :class="msg.role === 'user' ? 'justify-end' : 'justify-start'">
                                        <span :class="['text-[10px] font-semibold', msg.role === 'user' ? 'text-white/75' : 'text-surface-500']">
                                            {{ msg.role === 'user' ? 'Aap' : 'Karat AI' }}
                                        </span>
                                        <span :class="['text-[9.5px]', msg.role === 'user' ? 'text-white/50' : 'text-surface-400']">{{ msg.timestamp }}</span>
                                    </div>
                                    <p class="whitespace-pre-wrap select-text">{{ msg.content }}</p>

                                    <div v-if="msg.role === 'assistant' && msg.audio && isVoiceGloballyEnabled" class="mt-2.5 border-t border-surface-100 pt-2">
                                        <button
                                            type="button"
                                            class="inline-flex items-center gap-1.5 border border-amber-200 bg-amber-50 px-2.5 py-1 text-[10.5px] font-medium text-amber-800 transition-colors hover:bg-amber-100"
                                            @click="playAudio(msg.audio)"
                                        >
                                            <Volume2 class="h-3 w-3" />
                                            <span>{{ isSpeaking ? 'Voice chal rahi hai' : 'Voice mein sunein' }}</span>
                                        </button>
                                    </div>
                                </div>

                                <!-- ⚡ Dedicated Sub-Component Action Cards -->
                                <div v-if="msg.actions && msg.actions.length > 0" class="space-y-2">
                                    <template v-for="(action, idx) in msg.actions" :key="idx">
                                        <!-- 1. Invoice / Bill Draft Card -->
                                        <InvoiceDraftCard
                                            v-if="action.tool === 'create_bill' || action.tool === 'create_invoice'"
                                            :action="action"
                                            :msg-id="msg.id"
                                            :is-confirming="Boolean(isConfirming[`bill_${msg.id}`])"
                                            @confirm="confirmBillAction"
                                            @discard="discardAction"
                                            @recalculate="calculateLiveBill"
                                        />

                                        <!-- 2. Product Add Draft Card -->
                                        <ProductDraftCard
                                            v-else-if="action.tool === 'add_product'"
                                            :action="action"
                                            :msg-id="msg.id"
                                            :is-confirming="Boolean(isConfirming[`prod_${msg.id}`])"
                                            @confirm="confirmProductAction"
                                            @discard="discardAction"
                                        />

                                        <!-- 3. Daily Rates Draft Card -->
                                        <DailyRatesCard
                                            v-else-if="action.tool === 'update_daily_rates'"
                                            :action="action"
                                            :msg-id="msg.id"
                                            :is-confirming="Boolean(isConfirming[`rates_${msg.id}`])"
                                            @confirm="confirmRatesAction"
                                            @discard="discardAction"
                                        />

                                        <!-- 4. Stock Inventory Check Card -->
                                        <StockCheckCard v-else-if="action.tool === 'check_stock'" :action="action" />

                                        <!-- 5. Vault Balance Holdings Card -->
                                        <VaultBalanceCard v-else-if="action.tool === 'get_vault_balance'" :action="action" />

                                        <!-- 6. Daily Rate Inquire Card (Sleek Compact Light Luxury) -->
                                        <div
                                            v-else-if="action.tool === 'get_daily_rates'"
                                            class="my-3 overflow-hidden border border-l-[3px] border-surface-300 border-l-[#c08f34] bg-white font-sans shadow-[0_4px_14px_rgba(15,23,42,0.06)]"
                                        >
                                            <div
                                                class="flex items-center justify-between gap-3 border-b border-surface-200 bg-[#f8f6f0] px-3.5 py-2.5"
                                            >
                                                <div class="flex items-center gap-2.5">
                                                    <span class="flex h-7 w-7 shrink-0 items-center justify-center bg-[#1c3633] text-[#e5c278]">
                                                        <Coins class="h-3.5 w-3.5" />
                                                    </span>
                                                    <div class="flex flex-col justify-center">
                                                        <p class="!m-0 !p-0 !text-xs font-semibold tracking-wide text-surface-900 !leading-tight">Today's Live Bullion Rates</p>
                                                        <p class="!m-0 !p-0 !text-[10px] font-normal text-surface-500 !leading-tight">Real-time database rates</p>
                                                    </div>
                                                </div>
                                                <span class="inline-flex items-center gap-1 border border-emerald-200 bg-emerald-50 px-2 py-0.5 text-[10px] font-medium text-emerald-800 uppercase">
                                                    <span class="h-1.5 w-1.5 bg-emerald-500"></span>
                                                    Live
                                                </span>
                                            </div>
                                            <div
                                                class="grid grid-cols-1 divide-y divide-surface-200 bg-white text-left min-[400px]:grid-cols-3 min-[400px]:divide-x min-[400px]:divide-y-0 min-[400px]:text-center"
                                            >
                                                <div class="bg-surface-50 p-2.5">
                                                    <p class="text-[10px] font-semibold text-amber-800 uppercase">Gold 24K</p>
                                                    <p class="mt-1 font-mono text-sm font-bold text-[#9b6f1e]">₹{{ Number(action.result.gold_24k_per_gm || 7520).toLocaleString('en-IN') }}/g</p>
                                                </div>
                                                <div class="bg-surface-50 p-2.5">
                                                    <p class="text-[10px] font-semibold text-amber-800 uppercase">Gold 22K</p>
                                                    <p class="mt-1 font-mono text-sm font-bold text-surface-900">
                                                        ₹{{ Number(action.result.gold_22k_per_gm || 6888.32).toLocaleString('en-IN', { minimumFractionDigits: 2 }) }}/g
                                                    </p>
                                                </div>
                                                <div class="bg-surface-50 p-2.5">
                                                    <p class="text-[10px] font-semibold text-slate-700 uppercase">Silver (999)</p>
                                                    <p class="mt-1 font-mono text-sm font-bold text-surface-900">
                                                        ₹{{ Number(action.result.silver_per_gm || 89.2).toLocaleString('en-IN', { minimumFractionDigits: 2 }) }}/g
                                                    </p>
                                                </div>
                                            </div>
                                        </div>

                                        <!-- 7. Estimate Quotation Card (Sleek Compact Light Luxury) -->
                                        <div
                                            v-else-if="action.tool === 'calculate_estimate'"
                                            class="my-3 overflow-hidden border border-l-[3px] border-surface-300 border-l-[#c08f34] bg-white font-sans shadow-[0_4px_14px_rgba(15,23,42,0.06)]"
                                        >
                                            <div
                                                class="flex items-center justify-between gap-3 border-b border-surface-200 bg-[#f8f6f0] px-3.5 py-2.5"
                                            >
                                                <div class="flex items-center gap-2.5">
                                                    <span class="flex h-7 w-7 shrink-0 items-center justify-center bg-[#1c3633] text-[#e5c278]">
                                                        <Calculator class="h-3.5 w-3.5" />
                                                    </span>
                                                    <div class="flex flex-col justify-center">
                                                        <p class="!m-0 !p-0 !text-xs font-semibold tracking-wide text-surface-900 !leading-tight">
                                                            {{ action.result.item_name ? (action.result.item_name + (action.result.barcode ? ' (' + action.result.barcode + ')' : '')) : 'Price Estimate Quotation' }}
                                                        </p>
                                                        <p class="!m-0 !p-0 !text-[10px] font-normal text-surface-500 !leading-tight">
                                                            {{ action.result.purity || '22K (916 Hallmark)' }} · {{ action.result.weight }}
                                                        </p>
                                                    </div>
                                                </div>
                                                <span class="inline-flex items-center gap-1 border border-amber-300 bg-amber-50 px-2 py-0.5 text-[9.5px] font-semibold tracking-wide text-amber-900 uppercase">
                                                    Estimate
                                                </span>
                                            </div>

                                            <div class="grid grid-cols-2 divide-x divide-y divide-surface-200 bg-white text-left text-xs min-[460px]:grid-cols-4 min-[460px]:divide-y-0">
                                                <div class="p-2.5">
                                                    <span class="block text-[10px] font-medium text-surface-400">Rate / g</span>
                                                    <span class="font-mono font-semibold text-surface-800">{{ action.result.rate_per_gm }}</span>
                                                </div>
                                                <div class="p-2.5">
                                                    <span class="block text-[10px] font-medium text-surface-400">Metal Value</span>
                                                    <span class="font-mono font-semibold text-surface-800">{{ action.result.metal_value }}</span>
                                                </div>
                                                <div class="p-2.5">
                                                    <span class="block text-[10px] font-medium text-surface-400">Making</span>
                                                    <span class="font-mono font-semibold text-surface-800">{{ action.result.making_charges }}</span>
                                                </div>
                                                <div class="bg-amber-50/50 p-2.5">
                                                    <span class="block text-[10px] font-medium text-amber-800">Total (+3% GST)</span>
                                                    <span class="font-mono text-sm font-bold text-[#9b6f1e]">{{ action.result.total_estimate }}</span>
                                                </div>
                                            </div>
                                        </div>

                                        <!-- 8. Customer Khata / Ledger Card -->
                                        <CustomerKhataCard
                                            v-else-if="action.tool === 'get_customer_khata' || action.tool === 'customer_khata'"
                                            :action="action"
                                        />

                                        <!-- 9. Sales Summary & Counter Report Card -->
                                        <SalesSummaryCard
                                            v-else-if="action.tool === 'get_sales_summary' || action.tool === 'daily_sales_report'"
                                            :action="action"
                                        />

                                        <!-- 10. Previous Invoices / Purchase History Card -->
                                        <InvoiceHistoryCard
                                            v-else-if="action.tool === 'search_invoices' || action.tool === 'get_customer_invoices'"
                                            :action="action"
                                        />
                                    </template>
                                </div>
                            </div>
                        </div>

                        <div v-if="isLoading" class="flex items-center gap-2.5">
                            <div class="flex h-8 w-8 shrink-0 items-center justify-center border border-amber-200 bg-amber-50">
                                <Sparkles class="h-3.5 w-3.5 animate-pulse text-[#b07b24]" />
                            </div>
                            <div class="flex items-center gap-2 border border-surface-200 bg-white px-3.5 py-3 shadow-xs">
                                <span class="h-1.5 w-1.5 animate-bounce bg-[#c08f34]" />
                                <span class="h-1.5 w-1.5 animate-bounce bg-[#c08f34] [animation-delay:0.15s]" />
                                <span class="h-1.5 w-1.5 animate-bounce bg-[#c08f34] [animation-delay:0.3s]" />
                                <span class="ml-1 text-[11.5px] font-medium text-surface-500">
                                    {{ autoVoiceOutput ? 'Jawab aur voice taiyar ho rahe hain...' : 'ERP command process ho rahi hai...' }}
                                </span>
                            </div>
                        </div>
                    </div>

                    <!-- 🎙️ 3. Bottom Command Area: Enterprise Sharp Pinned Footer -->
                    <div class="z-10 shrink-0 space-y-2.5 border-t border-surface-200 bg-white px-3 py-3 sm:px-5">
                        <!-- Quick Suggestion Chips (Sharp Rectangular Buttons) -->
                        <div v-if="!isStarterConversation" class="no-scrollbar flex items-center gap-1.5 overflow-x-auto pb-0.5">
                            <button
                                v-for="(item, idx) in quickSuggestions.slice(0, 3)"
                                :key="idx"
                                type="button"
                                :disabled="isLoading"
                                class="flex shrink-0 items-center gap-1.5 border border-surface-200 bg-surface-50 px-2.5 py-1.5 text-[10.5px] font-medium text-surface-600 transition-colors hover:border-[#c08f34] hover:bg-amber-50 hover:text-[#1c3633] disabled:opacity-50"
                                @click="sendMessage(item.prompt)"
                            >
                                <component :is="item.icon" class="h-3 w-3 text-[#b07b24]" />
                                <span>{{ item.label }}</span>
                            </button>
                        </div>

                        <div v-if="isListening" class="flex items-center justify-between border border-red-200 bg-red-50 px-3 py-2" role="status">
                            <div class="flex items-center gap-2 text-[11px] font-medium text-red-700">
                                <span class="relative flex h-2 w-2">
                                    <span class="absolute inline-flex h-full w-full animate-ping bg-red-400 opacity-75"></span>
                                    <span class="relative inline-flex h-2 w-2 bg-red-600"></span>
                                </span>
                                <span>Sun raha hoon... Hindi ya English mein boliye</span>
                            </div>
                            <button type="button" class="ml-3 text-[11px] font-semibold text-red-700 underline-offset-2 hover:underline" @click="toggleListening">Stop</button>
                        </div>

                        <div v-if="speechError || chatError" class="border border-amber-200 bg-amber-50 px-3 py-2 text-[10.5px] leading-4 text-amber-800" role="alert">
                            {{ speechError || chatError }}
                        </div>

                        <div class="border border-surface-300 bg-white p-2 shadow-xs transition-colors focus-within:border-[#c08f34] focus-within:ring-2 focus-within:ring-amber-100">
                            <Textarea
                                ref="composerRef"
                                v-model="inputPrompt"
                                :auto-resize="true"
                                :rows="1"
                                :disabled="isLoading"
                                placeholder="Jaise: 12g 22K ring ka estimate banao..."
                                class="karat-composer !max-h-28 !min-h-10 !w-full !resize-none !border-0 !bg-transparent !px-2 !py-2 text-xs !shadow-none focus:!ring-0"
                                aria-label="Ask Karat AI"
                                @keydown.enter.exact.prevent="sendMessage()"
                            />

                            <div class="mt-1 flex items-center justify-between gap-2 border-t border-surface-100 pt-2">
                                <div class="flex items-center gap-2">
                                    <button
                                        type="button"
                                        :disabled="!recognitionSupported || isLoading"
                                        :class="[
                                            'flex h-8 items-center gap-1.5 border px-2.5 text-[10.5px] font-medium transition-colors disabled:cursor-not-allowed disabled:opacity-40',
                                            isListening ? 'border-red-600 bg-red-600 text-white' : 'border-surface-200 bg-surface-50 text-surface-600 hover:border-[#c08f34] hover:text-[#1c3633]',
                                        ]"
                                        :aria-label="isListening ? 'Stop listening' : 'Start voice input'"
                                        @click="toggleListening"
                                    >
                                        <MicOff v-if="isListening" class="h-3.5 w-3.5" />
                                        <Mic v-else class="h-3.5 w-3.5" />
                                        <span>{{ isListening ? 'Stop' : 'Boliye' }}</span>
                                    </button>
                                    <span class="hidden text-[9.5px] text-surface-400 sm:inline">Enter send · Shift+Enter new line</span>
                                </div>

                                <button
                                    type="button"
                                    :disabled="!inputPrompt.trim() || isLoading"
                                    class="flex h-8 items-center gap-1.5 border border-[#1c3633] bg-[#1c3633] px-3 text-[10.5px] font-semibold text-white transition-colors hover:bg-[#254642] disabled:cursor-not-allowed disabled:opacity-35"
                                    aria-label="Send message"
                                    @click="sendMessage()"
                                >
                                    <span>Send</span>
                                    <Send class="h-3.5 w-3.5 text-[#e1b65f]" />
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </template>
    </Drawer>
</template>

<style scoped>
.no-scrollbar::-webkit-scrollbar {
    display: none;
}
.no-scrollbar {
    -ms-overflow-style: none;
    scrollbar-width: none;
}

:deep(.p-drawer-content) {
    padding: 0 !important;
}
:deep(.karat-composer:focus),
:deep(.karat-composer:focus-visible) {
    box-shadow: none !important;
    outline: none !important;
}
</style>
