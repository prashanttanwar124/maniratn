<script setup lang="ts">
import axios from 'axios';
import {
    Bot,
    Coins,
    Mic,
    MicOff,
    PackagePlus,
    Receipt,
    RefreshCw,
    Send,
    Sparkles,
    TrendingUp,
    Volume2,
    VolumeX,
    Wallet,
    X,
    Clock,
} from 'lucide-vue-next';
import Drawer from 'primevue/drawer';
import InputText from 'primevue/inputtext';
import { usePage } from '@inertiajs/vue3';
import { computed, nextTick, onMounted, ref, watch } from 'vue';

// Subcomponents
import InvoiceDraftCard from './ai/InvoiceDraftCard.vue';
import ProductDraftCard from './ai/ProductDraftCard.vue';
import DailyRatesCard from './ai/DailyRatesCard.vue';
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
        content: 'Namaste! Main Karat AI Voice Copilot hoon. Aap live market bhav pooch sakte hain, naya stock add karwa sakte hain, ya quotation / bill bana sakte hain. Mic button daba kar boliye ya type kijiye.',
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
const autoVoiceOutput = ref(true);
const selectedVoice = ref('Aoede');
const messageContainer = ref<HTMLElement | null>(null);

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
                isListening.value = false;
            };

            recognition.onend = () => {
                isListening.value = false;
            };
        }
    }
};

const toggleListening = () => {
    if (!recognition) {
        alert('Aapke browser me speech recognition support nahi hai. Kripya Google Chrome use karein.');
        return;
    }

    if (isListening.value) {
        recognition.stop();
        isListening.value = false;
    } else {
        stopAudio();
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
    } catch (error: any) {
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
            const targetMsg = messages.value.find(m => m.id === msgId);
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
            const targetMsg = messages.value.find(m => m.id === msgId);
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
            const targetMsg = messages.value.find(m => m.id === msgId);
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
        const targetMsg = messages.value.find(m => m.id === msgId);
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
    try {
        await axios.delete('/api/ai/copilot/history');
    } catch (e) {
        console.warn(e);
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
            if (!historyLoaded.value) {
                fetchChatHistory(false);
            }
        } else {
            stopAudio();
            if (isListening.value && recognition) {
                recognition.stop();
                isListening.value = false;
            }
        }
    }
);

onMounted(() => {
    initSpeech();
});
</script>

<template>
    <Drawer
        :visible="props.visible"
        position="right"
        class="!w-full sm:!w-[520px] md:!w-[560px] !p-0 !border-l !border-surface-200 !shadow-2xl font-sans rounded-none"
        :modal="false"
        :dismissable="true"
        :show-close-icon="false"
        @update:visible="emit('update:visible', $event)"
    >
        <div class="flex flex-col h-full bg-white text-surface-800 relative select-none rounded-none">
            <!-- 🏛️ 1. Enterprise Top Header (Sharp Rectangular) -->
            <div class="px-5 py-3.5 bg-gradient-to-r from-[#142926] via-[#1c3633] to-[#142926] text-white flex items-center justify-between border-b-2 border-b-[#c08f34] shrink-0 z-10 rounded-none">
                <!-- Left: Branding & Status -->
                <div class="flex items-center gap-3">
                    <div class="w-9 h-9 bg-gradient-to-br from-[#c08f34] to-[#9b6f1e] text-[#142926] flex items-center justify-center font-bold font-serif shadow-xs rounded-none">
                        <Bot class="w-5 h-5 text-white" />
                    </div>
                    <div>
                        <div class="flex items-center gap-2">
                            <span class="font-serif font-bold text-sm tracking-wider text-white uppercase">Karat AI Copilot</span>
                            <span class="px-1.5 py-0.2 bg-emerald-500/20 border border-emerald-400/40 text-emerald-300 text-[10px] font-mono font-semibold rounded-none">
                                ERP Live
                            </span>
                        </div>
                        <p class="text-[11px] text-[#c08f34] flex items-center gap-1.5 font-medium">
                            <span class="w-1.5 h-1.5 rounded-none bg-emerald-400 animate-ping"></span>
                            Voice & POS Operations Online
                        </p>
                    </div>
                </div>

                <!-- Right: Controls -->
                <div class="flex items-center gap-2">
                    <!-- Voice Toggle -->
                    <button
                        v-if="isVoiceGloballyEnabled"
                        type="button"
                        class="px-2.5 py-1.5 text-xs font-semibold border transition-colors flex items-center gap-1.5 cursor-pointer rounded-none"
                        :class="autoVoiceOutput ? 'bg-[#c08f34] text-[#142926] border-[#c08f34]' : 'bg-white/10 text-white/70 border-white/20 hover:bg-white/20'"
                        :title="autoVoiceOutput ? 'Voice is Enabled (Click to Mute)' : 'Voice is Muted (Click to Enable)'"
                        @click="autoVoiceOutput = !autoVoiceOutput; if(!autoVoiceOutput) stopAudio();"
                    >
                        <Volume2 v-if="autoVoiceOutput" class="w-3.5 h-3.5" />
                        <VolumeX v-else class="w-3.5 h-3.5 text-white/50" />
                        <span>{{ autoVoiceOutput ? 'Voice ON' : 'Muted' }}</span>
                    </button>

                    <!-- Reset Chat -->
                    <button
                        type="button"
                        class="w-8 h-8 flex items-center justify-center border border-white/20 bg-white/10 hover:bg-white/20 text-white transition-colors cursor-pointer rounded-none"
                        title="Reset Chat History"
                        @click="resetChat"
                    >
                        <RefreshCw class="w-3.5 h-3.5" />
                    </button>

                    <!-- Close Button -->
                    <button
                        type="button"
                        class="w-8 h-8 flex items-center justify-center border border-white/20 bg-white/10 hover:bg-red-700 text-white transition-colors cursor-pointer rounded-none"
                        title="Close Copilot (ESC)"
                        @click="emit('update:visible', false); stopAudio();"
                    >
                        <X class="w-4 h-4" />
                    </button>
                </div>
            </div>

            <!-- 💬 2. Main Body: Structured Chat Area -->
            <div class="flex flex-col flex-1 h-full min-h-0 overflow-hidden bg-[#f8faf9] rounded-none">
                <!-- Live Chat Scroll Area -->
                <div ref="messageContainer" class="flex-1 min-h-0 overflow-y-auto p-4 space-y-4 rounded-none">
                    <!-- 📜 Load Earlier Chats Button -->
                    <div v-if="hasMoreHistory" class="flex justify-center pb-2">
                        <button
                            type="button"
                            :disabled="isLoadingHistory"
                            class="px-3 py-1.5 bg-white border border-slate-300 hover:border-[#1c3633] text-slate-700 hover:text-[#1c3633] text-[11px] font-bold flex items-center gap-2 shadow-xs transition-all cursor-pointer disabled:opacity-50 rounded-none"
                            @click="fetchChatHistory(true)"
                        >
                            <RefreshCw v-if="isLoadingHistory" class="w-3 h-3 animate-spin text-[#c08f34]" />
                            <Clock v-else class="w-3 h-3 text-[#c08f34]" />
                            <span>{{ isLoadingHistory ? 'Loading earlier chats...' : 'Load Earlier Chats (+10)' }}</span>
                        </button>
                    </div>

                    <!-- Chat Messages List -->
                    <div
                        v-for="msg in messages"
                        :key="msg.id"
                        :class="[
                            'flex gap-2.5 max-w-full transition-all duration-300',
                            msg.role === 'user' ? 'ml-auto flex-row-reverse max-w-[85%]' : 'mr-auto max-w-[95%]',
                        ]"
                    >
                        <!-- Role Avatar (Sharp Square) -->
                        <div
                            :class="[
                                'w-7 h-7 flex items-center justify-center shrink-0 text-xs font-bold mt-0.5 rounded-none',
                                msg.role === 'user'
                                    ? 'bg-[#1c3633] text-white shadow-xs'
                                    : 'bg-white border border-[#c08f34] text-[#c08f34] shadow-xs',
                            ]"
                        >
                            <Sparkles v-if="msg.role === 'assistant'" class="w-3.5 h-3.5 text-[#c08f34]" />
                            <span v-else>You</span>
                        </div>

                        <!-- Message Bubble & Action Cards -->
                        <div class="space-y-2.5 flex-1 min-w-0">
                            <!-- Text Bubble (Sharp Rectangular) -->
                            <div
                                :class="[
                                    'p-3.5 text-xs leading-relaxed border shadow-xs rounded-none transition-all',
                                    msg.role === 'user'
                                        ? 'bg-[#1c3633] border-[#1c3633] text-white'
                                        : 'bg-white border-slate-200 text-slate-900',
                                ]"
                            >
                                <p class="whitespace-pre-wrap font-normal text-[13px] leading-relaxed">{{ msg.content }}</p>

                                <!-- Bubble Footer -->
                                <div class="flex items-center justify-between mt-2.5 pt-2 border-t" :class="msg.role === 'user' ? 'border-white/10' : 'border-slate-100'">
                                    <button
                                        v-if="msg.role === 'assistant' && msg.audio && isVoiceGloballyEnabled"
                                        type="button"
                                        class="inline-flex items-center gap-1 px-2.5 py-0.5 bg-amber-50 hover:bg-amber-100 text-[#9b6f1e] text-[11px] font-bold border border-amber-200 cursor-pointer rounded-none transition-all"
                                        @click="playAudio(msg.audio)"
                                    >
                                        <Volume2 class="w-3 h-3 text-[#c08f34]" />
                                        <span>{{ isSpeaking ? 'Playing Voice...' : 'Play HD Voice' }}</span>
                                    </button>
                                    <span v-else />

                                    <span :class="['text-[10px] font-mono', msg.role === 'user' ? 'text-white/60' : 'text-slate-400']">
                                        {{ msg.timestamp }}
                                    </span>
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
                                    <StockCheckCard
                                        v-else-if="action.tool === 'check_stock'"
                                        :action="action"
                                    />

                                    <!-- 5. Vault Balance Holdings Card -->
                                    <VaultBalanceCard
                                        v-else-if="action.tool === 'get_vault_balance'"
                                        :action="action"
                                    />

                                    <!-- 6. Daily Rate Inquire Card -->
                                    <div
                                        v-else-if="action.tool === 'get_daily_rates'"
                                        class="border border-slate-300 bg-white shadow-xs rounded-none overflow-hidden my-2"
                                    >
                                        <div class="px-3 py-2 bg-[#1c3633] text-white flex items-center justify-between border-b-2 border-b-[#c08f34] rounded-none">
                                            <div class="flex items-center gap-1.5">
                                                <Coins class="w-3.5 h-3.5 text-[#c08f34]" />
                                                <span class="font-serif text-xs font-bold tracking-wide uppercase">Today's Live Bullion Rates</span>
                                            </div>
                                            <span class="px-1.5 py-0.5 bg-emerald-500/20 border border-emerald-400/30 text-emerald-300 text-[10px] font-bold uppercase rounded-none">
                                                Live Active
                                            </span>
                                        </div>
                                        <div class="p-2.5 bg-slate-50 grid grid-cols-3 gap-2 text-center">
                                            <div class="p-2 bg-white border border-slate-200 rounded-none shadow-2xs">
                                                <p class="text-[10px] font-bold text-slate-500 uppercase">Gold 24K</p>
                                                <p class="text-xs font-bold text-[#c08f34] mt-0.5 font-mono">
                                                    ₹{{ Number(action.result.gold_24k_per_gm || 7450).toLocaleString('en-IN') }}/g
                                                </p>
                                            </div>
                                            <div class="p-2 bg-white border border-slate-200 rounded-none shadow-2xs">
                                                <p class="text-[10px] font-bold text-slate-500 uppercase">Gold 22K</p>
                                                <p class="text-xs font-bold text-slate-800 mt-0.5 font-mono">
                                                    ₹{{ Number(action.result.gold_22k_per_gm || 6830).toLocaleString('en-IN') }}/g
                                                </p>
                                            </div>
                                            <div class="p-2 bg-white border border-slate-200 rounded-none shadow-2xs">
                                                <p class="text-[10px] font-bold text-slate-500 uppercase">Silver (999)</p>
                                                <p class="text-xs font-bold text-slate-700 mt-0.5 font-mono">
                                                    ₹{{ Number(action.result.silver_per_gm || 88.50).toLocaleString('en-IN', { minimumFractionDigits: 2 }) }}/g
                                                </p>
                                            </div>
                                        </div>
                                    </div>
                                </template>
                            </div>
                        </div>
                    </div>

                    <!-- Loading / Thinking Indicator -->
                    <div v-if="isLoading" class="flex items-center gap-2.5">
                        <div class="w-7 h-7 bg-white border border-[#c08f34] flex items-center justify-center shrink-0 rounded-none">
                            <Sparkles class="w-3.5 h-3.5 text-[#c08f34] animate-spin" />
                        </div>
                        <div class="px-3.5 py-2.5 bg-white border border-slate-200 flex items-center gap-2 rounded-none shadow-xs">
                            <span class="w-1.5 h-1.5 bg-[#c08f34] animate-bounce" />
                            <span class="w-1.5 h-1.5 bg-[#c08f34] animate-bounce [animation-delay:0.2s]" />
                            <span class="w-1.5 h-1.5 bg-[#c08f34] animate-bounce [animation-delay:0.4s]" />
                            <span class="text-xs text-slate-600 font-medium ml-1">
                                {{ autoVoiceOutput ? 'Thinking & Generating Studio Voice...' : 'Processing ERP Command...' }}
                            </span>
                        </div>
                    </div>
                </div>

                <!-- 🎙️ 3. Bottom Command Area: Enterprise Sharp Pinned Footer -->
                <div class="shrink-0 border-t border-slate-200 bg-white p-3.5 space-y-2.5 z-10 rounded-none">
                    <!-- Quick Suggestion Chips (Sharp Rectangular Buttons) -->
                    <div class="flex items-center gap-1.5 overflow-x-auto pb-1 no-scrollbar">
                        <button
                            v-for="(item, idx) in quickSuggestions"
                            :key="idx"
                            type="button"
                            class="shrink-0 px-2.5 py-1 text-xs font-semibold bg-slate-50 hover:bg-slate-100 border border-slate-300 hover:border-[#1c3633] text-[#1c3633] transition-all cursor-pointer flex items-center gap-1.5 rounded-none"
                            @click="sendMessage(item.prompt)"
                        >
                            <component :is="item.icon" class="w-3 h-3 text-[#c08f34]" />
                            <span>{{ item.label }}</span>
                        </button>
                    </div>

                    <!-- Listening Active Banner (Sharp Rectangular) -->
                    <div v-if="isListening" class="px-3 py-2 bg-red-50 border border-red-300 flex items-center justify-between animate-pulse rounded-none">
                        <div class="flex items-center gap-2 text-xs text-red-700 font-bold">
                            <span class="w-2 h-2 bg-red-600 animate-ping"></span>
                            <span>Listening... Boliye (e.g. "14 gram gold chain add karo")</span>
                        </div>
                        <button type="button" class="text-xs text-red-700 font-bold hover:underline cursor-pointer" @click="toggleListening">
                            Stop
                        </button>
                    </div>

                    <!-- Input & Mic Bar (Sharp Clean Rectangular) -->
                    <div class="flex items-center gap-2">
                        <!-- Mic Button -->
                        <button
                            type="button"
                            :class="[
                                'w-10 h-10 flex items-center justify-center shrink-0 transition-all cursor-pointer rounded-none border',
                                isListening
                                    ? 'bg-red-600 text-white border-red-600 animate-pulse'
                                    : 'bg-[#1c3633] hover:bg-[#254642] text-[#c08f34] border-[#1c3633]',
                            ]"
                            :title="isListening ? 'Stop Listening' : 'Click to Speak (Voice Command)'"
                            @click="toggleListening"
                        >
                            <MicOff v-if="isListening" class="w-4 h-4" />
                            <Mic v-else class="w-4 h-4" />
                        </button>

                        <!-- Text Input (Sakai Theme InputText, Rounded None) -->
                        <InputText
                            v-model="inputPrompt"
                            placeholder="Ask Karat AI or speak via mic..."
                            class="flex-1 !h-10 text-xs bg-white rounded-none"
                            @keydown.enter="sendMessage()"
                        />

                        <!-- Send Button -->
                        <button
                            type="button"
                            :disabled="!inputPrompt.trim() || isLoading"
                            class="w-10 h-10 flex items-center justify-center bg-[#1c3633] hover:bg-[#254642] text-[#c08f34] disabled:opacity-30 disabled:hover:bg-[#1c3633] transition-all cursor-pointer shrink-0 rounded-none border border-[#1c3633]"
                            @click="sendMessage()"
                        >
                            <Send class="w-4 h-4" />
                        </button>
                    </div>
                </div>
            </div>
        </div>
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
:deep(.p-inputtext) {
    border-radius: 0px !important;
}
</style>
