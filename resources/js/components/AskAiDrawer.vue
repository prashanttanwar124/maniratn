<script setup lang="ts">
import axios from 'axios';
import {
    AlertCircle,
    Bot,
    Check,
    CheckCircle2,
    Clock,
    Coins,
    Edit3,
    ExternalLink,
    FileText,
    Mic,
    MicOff,
    PackagePlus,
    Printer,
    Receipt,
    RefreshCw,
    Send,
    Sparkles,
    Trash2,
    TrendingUp,
    Volume2,
    VolumeX,
    Wallet,
    X,
    Zap,
} from 'lucide-vue-next';
import Drawer from 'primevue/drawer';
import { usePage } from '@inertiajs/vue3';
import { computed, nextTick, onMounted, ref, watch } from 'vue';

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
        content: 'Namaste! Main Karat AI Voice Copilot hoon. Aap live market bhav pooch sakte hain, naya stock/product add karwa sakte hain, ya quotation estimate bana sakte hain. Mic button daba kar boliye ya type kijiye.',
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
    { label: '15g Chain Bill banao', icon: Receipt, prompt: 'Customer Rahul Sharma (phone: 9876543210) ke liye 15g 22K Gold Chain ka bill bana do' },
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
    const weight = parseFloat(draft.weight || 0);
    const rate = parseFloat(draft.rate_per_gm || 0);
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
        const payload = { ...action.result };
        const res = await axios.post('/api/ai/copilot/confirm-bill', payload);
        if (res.data && res.data.success) {
            action.result = {
                ...action.result,
                ...res.data,
                is_preview: false,
                found: true,
                status: 'INVOICE_GENERATED_REAL_DB',
            };
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
        const payload = { ...action.result };
        const res = await axios.post('/api/ai/copilot/confirm-product', payload);
        if (res.data && res.data.success) {
            action.result = {
                ...action.result,
                ...res.data,
                is_preview: false,
                status: 'IN_STOCK_REAL_DB',
            };
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
        const payload = { ...action.result };
        const res = await axios.post('/api/ai/copilot/confirm-rates', payload);
        if (res.data && res.data.success) {
            action.result = {
                ...action.result,
                ...res.data,
                is_preview: false,
                status: 'UPDATED_IN_DATABASE',
            };
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

const discardAction = (action: ActionItem) => {
    action.result.is_preview = false;
    action.result.is_discarded = true;
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
                // Prepend older messages while maintaining scroll position
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
                oldestMessageId.value = res.data.oldest_id;
            }
        }
    } catch (err) {
        console.warn('Could not fetch chat history from AI Hub', err);
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
        console.warn('Could not clear history on server', e);
    }
    messages.value = [
        {
            id: Date.now().toString(),
            role: 'assistant',
            content: 'Chat history reset ho gayi hai. Naye sawal ya ERP command ke liye mic button dabayein.',
            timestamp: new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' }),
        },
    ];
    hasMoreHistory.value = false;
    oldestMessageId.value = null;
};

watch(
    () => props.visible,
    (val) => {
        if (val && !historyLoaded.value) {
            fetchChatHistory(false);
        }
    },
    { immediate: true }
);

onMounted(() => {
    initSpeech();
    fetchChatHistory(false);
});
</script>

<template>
    <Drawer
        :visible="visible"
        position="right"
        class="!w-full sm:!w-[30rem] md:!w-[34rem] !p-0 !bg-[#f8faf9] !border-l !border-surface-200 shadow-2xl !overflow-hidden"
        :pt="{
            root: { class: '!flex !flex-col !h-full !overflow-hidden' },
            header: { class: '!hidden' },
            content: { class: '!p-0 !m-0 !flex !flex-col !flex-1 !min-h-0 !overflow-hidden !bg-[#f8faf9]' }
        }"
        @update:visible="emit('update:visible', $event); stopAudio();"
    >
        <!-- 💎 1. Enterprise Sharp Header matching KaratSetu ERP Theme -->
        <div class="flex items-center justify-between px-5 py-4 bg-white border-b border-surface-200 shrink-0 z-20">
            <!-- Left: Brand Logo & Status -->
            <div class="flex items-center gap-3">
                <div class="w-9 h-9 bg-[#1c3633] flex items-center justify-center">
                    <Sparkles class="w-4.5 h-4.5 text-[#c08f34]" />
                </div>
                <div>
                    <div class="flex items-center gap-2 leading-none">
                        <span class="text-base font-bold tracking-tight text-[#1c3633]">
                            Karat<span class="text-[#c08f34]">AI</span>
                        </span>
                        <span class="px-2 py-0.5 bg-[#f4ece1] border border-[#e8d5b5] text-[#9b6f1e] text-[11px] font-bold uppercase tracking-wider">
                            Copilot
                        </span>
                    </div>
                    <div class="flex items-center gap-2 mt-1">
                        <span class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></span>
                        <span class="text-xs font-medium text-surface-500">
                            {{ isVoiceGloballyEnabled ? 'Live Voice & ERP Database Connected' : 'ERP Database Connected' }}
                        </span>
                    </div>
                </div>
            </div>

            <!-- Right: Action Buttons (Sharp Square Icons) -->
            <div class="flex items-center gap-1.5">
                <!-- Voice Mute/Unmute Square Button (Only shown if enabled in Settings) -->
                <button
                    v-if="isVoiceGloballyEnabled"
                    type="button"
                    :class="[
                        'h-8 px-3 flex items-center gap-2 text-xs font-semibold transition-all cursor-pointer border',
                        autoVoiceOutput
                            ? 'bg-emerald-50 text-emerald-800 border-emerald-300 hover:bg-emerald-100'
                            : 'bg-surface-100 text-surface-500 border-surface-200 hover:bg-surface-200',
                    ]"
                    :title="autoVoiceOutput ? 'Studio HD Voice Enabled (Click to Mute)' : 'Voice Muted (Click to Enable)'"
                    @click="autoVoiceOutput = !autoVoiceOutput; if (!autoVoiceOutput) stopAudio();"
                >
                    <Volume2 v-if="autoVoiceOutput" class="w-3.5 h-3.5 text-emerald-700" />
                    <VolumeX v-else class="w-3.5 h-3.5 text-surface-400" />
                    <span>{{ autoVoiceOutput ? 'Voice ON' : 'Muted' }}</span>
                </button>

                <!-- Reset Chat -->
                <button
                    type="button"
                    class="w-8 h-8 flex items-center justify-center border border-surface-200 bg-white hover:bg-surface-100 text-surface-600 hover:text-[#1c3633] transition-colors cursor-pointer"
                    title="Reset Chat"
                    @click="resetChat"
                >
                    <RefreshCw class="w-3.5 h-3.5" />
                </button>

                <!-- Close Button -->
                <button
                    type="button"
                    class="w-8 h-8 flex items-center justify-center border border-surface-200 bg-white hover:bg-red-50 text-surface-500 hover:text-red-600 transition-colors cursor-pointer"
                    title="Close Copilot (ESC)"
                    @click="emit('update:visible', false); stopAudio();"
                >
                    <X class="w-4 h-4" />
                </button>
            </div>
        </div>

        <!-- 💬 2. Main Body: Structured Chat Area -->
        <div class="flex flex-col flex-1 h-full min-h-0 overflow-hidden bg-[#f8faf9]">
            <!-- Live Chat Scroll Area -->
            <div ref="messageContainer" class="flex-1 min-h-0 overflow-y-auto p-5 space-y-4">
                <!-- 📜 Load Earlier Chats (10 more) Button -->
                <div v-if="hasMoreHistory" class="flex justify-center pb-2">
                    <button
                        type="button"
                        :disabled="isLoadingHistory"
                        class="px-3.5 py-1.5 bg-white border border-surface-300 hover:border-[#1c3633] text-surface-700 hover:text-[#1c3633] text-[11px] font-semibold flex items-center gap-2 shadow-xs transition-all cursor-pointer disabled:opacity-50"
                        @click="fetchChatHistory(true)"
                    >
                        <RefreshCw v-if="isLoadingHistory" class="w-3 h-3 animate-spin text-[#c08f34]" />
                        <Clock v-else class="w-3 h-3 text-[#c08f34]" />
                        <span>{{ isLoadingHistory ? 'Loading earlier chats...' : 'Load Earlier Chats (+10)' }}</span>
                    </button>
                </div>

                <div
                    v-for="msg in messages"
                    :key="msg.id"
                    :class="[
                        'flex gap-3 max-w-full transition-all duration-300',
                        msg.role === 'user' ? 'ml-auto flex-row-reverse max-w-[85%] animate-msg-user' : 'mr-auto max-w-[95%] animate-msg-ai',
                    ]"
                >
                    <!-- Role Avatar (Sharp Square with Subtle Glow) -->
                    <div
                        :class="[
                            'w-8 h-8 flex items-center justify-center shrink-0 text-xs font-bold mt-0.5 transition-transform hover:scale-105',
                            msg.role === 'user'
                                ? 'bg-[#1c3633] text-white shadow-xs'
                                : 'bg-white border border-[#c08f34] text-[#c08f34] shadow-xs',
                        ]"
                    >
                        <Sparkles v-if="msg.role === 'assistant'" class="w-4 h-4 text-[#c08f34] animate-pulse" />
                        <span v-else>You</span>
                    </div>

                    <!-- Message Bubble & Action Cards -->
                    <div class="space-y-3 flex-1 min-w-0">
                        <!-- Message Bubble (Sharp Rectangular Borders, Readable 14px Font) -->
                        <div
                            :class="[
                                'p-4 text-sm leading-relaxed border relative shadow-xs transition-all duration-200',
                                msg.role === 'user'
                                    ? 'bg-[#1c3633] border-[#1c3633] text-white'
                                    : 'bg-white border-surface-200 text-surface-900 hover:border-[#c08f34]/30',
                            ]"
                        >
                            <p class="whitespace-pre-wrap font-normal text-[13.5px] leading-relaxed">{{ msg.content }}</p>

                            <!-- Bubble Footer -->
                            <div class="flex items-center justify-between mt-3 pt-2 border-t" :class="msg.role === 'user' ? 'border-white/10' : 'border-surface-100'">
                                <button
                                    v-if="msg.role === 'assistant' && msg.audio && isVoiceGloballyEnabled"
                                    type="button"
                                    class="inline-flex items-center gap-1.5 px-3 py-1 bg-amber-50 hover:bg-amber-100 text-[#9b6f1e] text-xs font-semibold border border-amber-200 cursor-pointer transition-all active:scale-95 hover:shadow-xs"
                                    @click="playAudio(msg.audio)"
                                >
                                    <div v-if="isSpeaking" class="flex items-end gap-0.5 h-3 w-3 py-0.5">
                                        <span class="w-0.5 bg-[#c08f34] h-full animate-eq-1"></span>
                                        <span class="w-0.5 bg-[#c08f34] h-full animate-eq-2"></span>
                                        <span class="w-0.5 bg-[#c08f34] h-full animate-eq-3"></span>
                                    </div>
                                    <Volume2 v-else class="w-3.5 h-3.5 text-[#c08f34]" />
                                    <span>{{ isSpeaking ? 'Playing HD Audio...' : 'Play HD Voice' }}</span>
                                </button>
                                <span v-else />

                                <span :class="['text-xs font-mono', msg.role === 'user' ? 'text-white/60' : 'text-surface-400']">
                                    {{ msg.timestamp }}
                                </span>
                            </div>
                        </div>

                        <!-- ⚡ Real ERP Action Cards (Sharp Structured Enterprise Tables with Pop Entrance) -->
                        <div v-if="msg.actions && msg.actions.length > 0" class="space-y-3 animate-card-pop">
                            <div
                                v-for="(action, idx) in msg.actions"
                                :key="idx"
                                class="p-4 bg-white border border-[#c08f34]/50 shadow-xs hover:border-[#c08f34] hover:shadow-md transition-all duration-200 space-y-3"
                            >
                                <!-- Action Header -->
                                <div class="flex items-center justify-between pb-2 border-b border-surface-200">
                                    <div class="flex items-center gap-2">
                                        <Zap class="w-4 h-4 text-[#c08f34] animate-pulse" />
                                        <span class="text-xs font-bold uppercase tracking-wider text-[#1c3633]">
                                            ERP Action: {{ action.tool.replace(/_/g, ' ') }}
                                        </span>
                                    </div>
                                    <span class="px-2 py-0.5 bg-emerald-50 border border-emerald-200 text-emerald-800 text-[10px] font-bold uppercase">
                                        Real Database
                                    </span>
                                </div>

                                <!-- 1. Rates Card -->
                                <div v-if="action.tool === 'get_daily_rates'">
                                    <div v-if="action.result.found === false" class="p-3 bg-amber-50/80 border border-amber-300/80 text-xs text-amber-900 space-y-1">
                                        <p class="font-bold flex items-center gap-1.5 text-amber-800">
                                            <span>⚠️</span> Aaj ka rate abhi tak set nahi hai
                                        </p>
                                        <p class="text-surface-600 text-[12px]">
                                            Aap mic se bole: <em>"Aaj ka 24k rate 7450 aur silver 89 set kar do"</em>
                                        </p>
                                    </div>
                                    <div v-else class="grid grid-cols-2 gap-3 pt-1">
                                        <div class="p-3 bg-[#fcfaf6] border border-[#e8dfcf] text-center">
                                            <p class="text-xs font-medium text-surface-500 uppercase tracking-wider">Gold 24K Sell</p>
                                            <p class="text-base font-bold text-[#c08f34] mt-1">
                                                ₹{{ Number(action.result.gold_24k_per_gm).toLocaleString('en-IN') }}/g
                                            </p>
                                        </div>
                                        <div v-if="action.result.gold_22k_per_gm" class="p-3 bg-[#fcfaf6] border border-[#e8dfcf] text-center">
                                            <p class="text-xs font-medium text-surface-500 uppercase tracking-wider">Gold 22K (916)</p>
                                            <p class="text-base font-bold text-[#9b6f1e] mt-1">
                                                ₹{{ Number(action.result.gold_22k_per_gm).toLocaleString('en-IN') }}/g
                                            </p>
                                        </div>
                                    </div>
                                </div>

                                <!-- 1b. Daily Rates Card (Preview vs Confirmed) -->
                                <div v-else-if="action.tool === 'update_daily_rates'">
                                    <!-- Editable Preview Form -->
                                    <div v-if="action.result.is_preview" class="p-3 bg-amber-50/70 border border-amber-300 space-y-2.5">
                                        <div class="flex items-center justify-between">
                                            <span class="text-xs font-bold text-amber-950 flex items-center gap-1.5">
                                                <Edit3 class="w-3.5 h-3.5 text-[#c08f34]" />
                                                Review Daily Rates Before Updating
                                            </span>
                                            <span class="px-2 py-0.5 bg-amber-200 text-amber-900 text-[10px] font-bold uppercase">
                                                Confirmation Required
                                            </span>
                                        </div>
                                        <div class="grid grid-cols-2 gap-2 text-xs">
                                            <div>
                                                <label class="text-[10.5px] text-surface-600 font-medium">Gold 24K Sell (₹/g)</label>
                                                <input
                                                    v-model.number="action.result.gold_24k_sell"
                                                    type="number"
                                                    class="w-full mt-0.5 p-1.5 bg-white border border-surface-300 text-xs font-bold text-[#1c3633]"
                                                />
                                            </div>
                                            <div>
                                                <label class="text-[10.5px] text-surface-600 font-medium">Silver Sell (₹/g)</label>
                                                <input
                                                    v-model.number="action.result.silver_sell"
                                                    type="number"
                                                    class="w-full mt-0.5 p-1.5 bg-white border border-surface-300 text-xs font-bold text-surface-800"
                                                />
                                            </div>
                                        </div>
                                        <div class="flex items-center gap-2 pt-1">
                                            <button
                                                type="button"
                                                :disabled="isConfirming[`rates_${message.id}`]"
                                                @click="confirmRatesAction(action, message.id)"
                                                class="flex-1 py-2 bg-emerald-700 hover:bg-emerald-800 text-white text-xs font-bold flex items-center justify-center gap-1.5 shadow-xs transition-all disabled:opacity-50"
                                            >
                                                <Check class="w-3.5 h-3.5" />
                                                <span>{{ isConfirming[`rates_${message.id}`] ? 'Updating...' : 'Confirm & Update Live Rates' }}</span>
                                            </button>
                                            <button
                                                type="button"
                                                @click="discardAction(action)"
                                                class="px-3 py-2 bg-white border border-surface-300 text-surface-700 hover:text-red-700 text-xs font-medium"
                                            >
                                                Discard
                                            </button>
                                        </div>
                                    </div>

                                    <!-- Confirmed Rates Display -->
                                    <div v-else class="grid grid-cols-2 gap-3 pt-1">
                                        <div class="p-3 bg-[#fcfaf6] border border-emerald-300 text-center">
                                            <p class="text-xs font-medium text-emerald-800 uppercase tracking-wider">Set Gold 24K</p>
                                            <p class="text-base font-bold text-[#c08f34] mt-1">
                                                ₹{{ Number(action.result.gold_24k_sell).toLocaleString('en-IN') }}/g
                                            </p>
                                        </div>
                                        <div class="p-3 bg-[#fcfaf6] border border-emerald-300 text-center">
                                            <p class="text-xs font-medium text-emerald-800 uppercase tracking-wider">Set Silver</p>
                                            <p class="text-base font-bold text-surface-700 mt-1">
                                                ₹{{ Number(action.result.silver_sell).toLocaleString('en-IN') }}/g
                                            </p>
                                        </div>
                                    </div>
                                </div>

                                <!-- 2. Product Added (Preview vs Confirmed) -->
                                <div v-else-if="action.tool === 'add_product'">
                                    <!-- Editable Preview Form -->
                                    <div v-if="action.result.is_preview" class="p-3 bg-amber-50/70 border border-amber-300 space-y-2.5">
                                        <div class="flex items-center justify-between">
                                            <span class="text-xs font-bold text-amber-950 flex items-center gap-1.5">
                                                <Edit3 class="w-3.5 h-3.5 text-[#c08f34]" />
                                                Review Ornament Before Saving to Stock
                                            </span>
                                            <span class="px-2 py-0.5 bg-amber-200 text-amber-900 text-[10px] font-bold uppercase">
                                                Confirmation Required
                                            </span>
                                        </div>
                                        <div class="grid grid-cols-2 gap-2 text-xs">
                                            <div class="col-span-2">
                                                <label class="text-[10.5px] text-surface-600 font-medium">Ornament Name</label>
                                                <input
                                                    v-model="action.result.name"
                                                    class="w-full mt-0.5 p-1.5 bg-white border border-surface-300 text-xs font-semibold text-surface-900"
                                                />
                                            </div>
                                            <div>
                                                <label class="text-[10.5px] text-surface-600 font-medium">Gross/Net Weight (g)</label>
                                                <input
                                                    v-model.number="action.result.weight"
                                                    type="number"
                                                    step="0.001"
                                                    class="w-full mt-0.5 p-1.5 bg-white border border-surface-300 text-xs font-bold text-[#1c3633]"
                                                />
                                            </div>
                                            <div>
                                                <label class="text-[10.5px] text-surface-600 font-medium">Purity (e.g. 22K, 18K)</label>
                                                <input
                                                    v-model="action.result.purity"
                                                    class="w-full mt-0.5 p-1.5 bg-white border border-surface-300 text-xs font-semibold"
                                                />
                                            </div>
                                            <div>
                                                <label class="text-[10.5px] text-surface-600 font-medium">Category</label>
                                                <input
                                                    v-model="action.result.category"
                                                    class="w-full mt-0.5 p-1.5 bg-white border border-surface-300 text-xs"
                                                />
                                            </div>
                                            <div>
                                                <label class="text-[10.5px] text-surface-600 font-medium">Making Charge (₹/g)</label>
                                                <input
                                                    v-model.number="action.result.making_charge_per_gm"
                                                    type="number"
                                                    class="w-full mt-0.5 p-1.5 bg-white border border-surface-300 text-xs font-semibold"
                                                />
                                            </div>
                                        </div>
                                        <div class="flex items-center gap-2 pt-1">
                                            <button
                                                type="button"
                                                :disabled="isConfirming[`prod_${message.id}`]"
                                                @click="confirmProductAction(action, message.id)"
                                                class="flex-1 py-2 bg-emerald-700 hover:bg-emerald-800 text-white text-xs font-bold flex items-center justify-center gap-1.5 shadow-xs transition-all disabled:opacity-50"
                                            >
                                                <PackagePlus class="w-3.5 h-3.5" />
                                                <span>{{ isConfirming[`prod_${message.id}`] ? 'Saving...' : 'Confirm & Save to Stock' }}</span>
                                            </button>
                                            <button
                                                type="button"
                                                @click="discardAction(action)"
                                                class="px-3 py-2 bg-white border border-surface-300 text-surface-700 hover:text-red-700 text-xs font-medium"
                                            >
                                                Discard
                                            </button>
                                        </div>
                                    </div>

                                    <!-- Confirmed Product Barcode Card -->
                                    <div v-else class="p-3.5 bg-[#fcfaf6] border border-[#e8dfcf] space-y-2.5">
                                        <div class="flex items-center justify-between">
                                            <span class="font-bold text-sm text-[#1c3633]">{{ action.result.name }}</span>
                                            <span class="font-mono text-xs px-3 py-1 bg-[#1c3633] text-[#c08f34] font-bold tracking-widest">
                                                {{ action.result.barcode }}
                                            </span>
                                        </div>
                                        <div class="grid grid-cols-3 gap-2 text-xs text-surface-600 border-t border-surface-200 pt-2">
                                            <div>Weight: <strong class="text-[#1c3633]">{{ action.result.weight }}</strong></div>
                                            <div>Purity: <strong class="text-[#1c3633]">{{ action.result.purity }}</strong></div>
                                            <div>Making: <strong class="text-[#1c3633]">{{ action.result.making_charge_per_gm }}</strong></div>
                                        </div>
                                    </div>
                                </div>

                                <!-- 3. Vault Balance Card -->
                                <div v-else-if="action.tool === 'get_vault_balance'" class="grid grid-cols-3 gap-2">
                                    <div class="p-2.5 bg-[#fcfaf6] border border-[#e8dfcf] text-center">
                                        <p class="text-[10px] font-medium text-surface-500 uppercase">Cash In Hand</p>
                                        <p class="text-sm font-bold text-emerald-800 mt-1">{{ action.result.cash_in_hand }}</p>
                                    </div>
                                    <div class="p-2.5 bg-[#fcfaf6] border border-[#e8dfcf] text-center">
                                        <p class="text-[10px] font-medium text-surface-500 uppercase">Gold Safe</p>
                                        <p class="text-sm font-bold text-[#c08f34] mt-1">{{ action.result.gold_in_vault }}</p>
                                    </div>
                                    <div class="p-2.5 bg-[#fcfaf6] border border-[#e8dfcf] text-center">
                                        <p class="text-[10px] font-medium text-surface-500 uppercase">Silver Safe</p>
                                        <p class="text-sm font-bold text-surface-600 mt-1">{{ action.result.silver_in_vault }}</p>
                                    </div>
                                </div>

                                <!-- 4. Estimate Calculation Card -->
                                <div v-else-if="action.tool === 'calculate_estimate'">
                                    <div v-if="action.result.found === false" class="p-3.5 bg-amber-50/80 border border-amber-300/80 text-xs text-amber-900 space-y-1">
                                        <p class="font-bold flex items-center gap-1.5 text-amber-800">
                                            <span>⚠️</span> Aaj ka gold rate set nahi hai
                                        </p>
                                        <p class="text-surface-600 text-[12px]">
                                            Quotation nikalne ke liye pehle aaj ka bhav update karein (jaise: <em>"Aaj ka 24k rate 7450 set karo"</em>) ya command me rate batayein (jaise: <em>"15g chain 7100 bhav se estimate banao"</em>).
                                        </p>
                                    </div>
                                    <div v-else class="p-3.5 bg-[#fcfaf6] border border-[#e8dfcf] space-y-2 text-xs">
                                        <div class="flex justify-between text-surface-600">
                                            <span>Metal Value ({{ action.result.weight }} @ {{ action.result.rate_per_gm }})</span>
                                            <span class="font-medium text-[#1c3633]">{{ action.result.metal_value }}</span>
                                        </div>
                                        <div class="flex justify-between text-surface-600">
                                            <span>Making Charges</span>
                                            <span class="font-medium text-[#1c3633]">{{ action.result.making_charges }}</span>
                                        </div>
                                        <div class="flex justify-between text-surface-600">
                                            <span>GST (3%)</span>
                                            <span class="font-medium text-[#1c3633]">{{ action.result.gst_3_percent }}</span>
                                        </div>
                                        <div class="flex justify-between pt-2.5 border-t border-surface-200 font-bold text-sm text-[#1c3633]">
                                            <span>Total Estimated Quotation</span>
                                            <span class="text-[#c08f34] text-base font-bold">{{ action.result.total_estimate }}</span>
                                        </div>
                                    </div>
                                </div>

                                <!-- 5. Bill / Invoice (Interactive Preview Draft vs Confirmed Invoice) -->
                                <div v-if="action.tool === 'create_bill' || action.tool === 'create_invoice'">
                                    <!-- Error Alert -->
                                    <div v-if="action.result.found === false" class="p-3 bg-red-50 border border-red-300 text-xs text-red-900">
                                        <p class="font-bold">⚠️ {{ action.result.message || 'Bill generate nahi ho paya.' }}</p>
                                    </div>

                                    <!-- 📝 HUMAN-IN-THE-LOOP: EDITABLE INVOICE DRAFT PREVIEW -->
                                    <div v-else-if="action.result.is_preview" class="p-3.5 bg-amber-50/70 border-2 border-amber-300 shadow-sm space-y-3">
                                        <!-- Draft Header -->
                                        <div class="flex items-center justify-between pb-2 border-b border-amber-200">
                                            <div class="flex items-center gap-2">
                                                <div class="w-7 h-7 bg-[#1c3633] text-[#c08f34] flex items-center justify-center font-bold">
                                                    <Edit3 class="w-4 h-4" />
                                                </div>
                                                <div>
                                                    <div class="font-bold text-xs text-amber-950">Invoice Draft Preview</div>
                                                    <div class="text-[10.5px] text-amber-800">Review & edit fields below before saving to DB</div>
                                                </div>
                                            </div>
                                            <span class="px-2 py-0.5 bg-amber-200 border border-amber-300 text-amber-900 text-[10px] font-bold uppercase tracking-wider">
                                                Approval Required
                                            </span>
                                        </div>

                                        <!-- Interactive Form Grid (Live 2-way data binding) -->
                                        <div class="grid grid-cols-2 gap-2 text-xs">
                                            <!-- Customer Details -->
                                            <div>
                                                <label class="text-[10.5px] font-semibold text-surface-700">Customer Name *</label>
                                                <input
                                                    v-model="action.result.customer_name"
                                                    type="text"
                                                    placeholder="Customer Name"
                                                    class="w-full mt-0.5 p-1.5 bg-white border border-surface-300 text-xs font-semibold text-surface-900 focus:border-[#1c3633] focus:ring-0"
                                                />
                                            </div>
                                            <div>
                                                <label class="text-[10.5px] font-semibold text-surface-700">Customer Mobile</label>
                                                <input
                                                    v-model="action.result.customer_phone"
                                                    type="text"
                                                    placeholder="Mobile Number"
                                                    class="w-full mt-0.5 p-1.5 bg-white border border-surface-300 text-xs font-mono text-surface-900 focus:border-[#1c3633] focus:ring-0"
                                                />
                                            </div>

                                            <!-- Item & Barcode -->
                                            <div>
                                                <label class="text-[10.5px] font-semibold text-surface-700">Item Description *</label>
                                                <input
                                                    v-model="action.result.item_name"
                                                    type="text"
                                                    placeholder="Item Name"
                                                    class="w-full mt-0.5 p-1.5 bg-white border border-surface-300 text-xs font-semibold text-surface-900 focus:border-[#1c3633] focus:ring-0"
                                                />
                                            </div>
                                            <div>
                                                <label class="text-[10.5px] font-semibold text-surface-700">Stock Barcode</label>
                                                <input
                                                    v-model="action.result.barcode"
                                                    type="text"
                                                    placeholder="Barcode (Optional)"
                                                    class="w-full mt-0.5 p-1.5 bg-white border border-surface-300 text-xs font-mono font-bold uppercase text-surface-900 focus:border-[#1c3633] focus:ring-0"
                                                />
                                            </div>

                                            <!-- Weight & Purity -->
                                            <div>
                                                <label class="text-[10.5px] font-semibold text-surface-700">Net Weight (g) *</label>
                                                <input
                                                    v-model.number="action.result.weight"
                                                    type="number"
                                                    step="0.001"
                                                    @input="calculateLiveBill(action.result)"
                                                    class="w-full mt-0.5 p-1.5 bg-white border border-surface-300 text-xs font-bold text-[#1c3633] focus:border-[#1c3633] focus:ring-0"
                                                />
                                            </div>
                                            <div>
                                                <label class="text-[10.5px] font-semibold text-surface-700">Purity</label>
                                                <input
                                                    v-model="action.result.purity"
                                                    type="text"
                                                    placeholder="22K, 18K, Silver"
                                                    class="w-full mt-0.5 p-1.5 bg-white border border-surface-300 text-xs font-semibold focus:border-[#1c3633] focus:ring-0"
                                                />
                                            </div>

                                            <!-- Rate & Discount -->
                                            <div>
                                                <label class="text-[10.5px] font-semibold text-surface-700">Rate / gm (₹) *</label>
                                                <input
                                                    v-model.number="action.result.rate_per_gm"
                                                    type="number"
                                                    step="1"
                                                    @input="calculateLiveBill(action.result)"
                                                    class="w-full mt-0.5 p-1.5 bg-white border border-surface-300 text-xs font-bold text-surface-900 focus:border-[#1c3633] focus:ring-0"
                                                />
                                            </div>
                                            <div>
                                                <label class="text-[10.5px] font-semibold text-surface-700">Discount Amount (₹)</label>
                                                <input
                                                    v-model.number="action.result.discount_amount"
                                                    type="number"
                                                    step="1"
                                                    @input="calculateLiveBill(action.result)"
                                                    class="w-full mt-0.5 p-1.5 bg-white border border-surface-300 text-xs font-semibold text-emerald-800 focus:border-[#1c3633] focus:ring-0"
                                                />
                                            </div>

                                            <!-- Making Type & Value -->
                                            <div>
                                                <label class="text-[10.5px] font-semibold text-surface-700">Making Charge Type</label>
                                                <select
                                                    v-model="action.result.making_type"
                                                    @change="calculateLiveBill(action.result)"
                                                    class="w-full mt-0.5 p-1.5 bg-white border border-surface-300 text-xs font-semibold focus:border-[#1c3633] focus:ring-0"
                                                >
                                                    <option value="percentage">% (Percentage on Metal)</option>
                                                    <option value="per_gram">₹/g (Per Gram)</option>
                                                    <option value="flat">₹ Flat (Lump-Sum)</option>
                                                </select>
                                            </div>
                                            <div>
                                                <label class="text-[10.5px] font-semibold text-surface-700">
                                                    Making Value {{ action.result.making_type === 'percentage' ? '(%)' : '(₹)' }}
                                                </label>
                                                <input
                                                    v-model.number="action.result.making_value"
                                                    type="number"
                                                    step="0.1"
                                                    @input="calculateLiveBill(action.result)"
                                                    class="w-full mt-0.5 p-1.5 bg-white border border-surface-300 text-xs font-bold text-[#1c3633] focus:border-[#1c3633] focus:ring-0"
                                                />
                                            </div>

                                            <!-- Payment Mode & Paid Amount -->
                                            <div>
                                                <label class="text-[10.5px] font-semibold text-surface-700">Payment Mode</label>
                                                <select
                                                    v-model="action.result.payment_mode"
                                                    class="w-full mt-0.5 p-1.5 bg-white border border-surface-300 text-xs font-semibold focus:border-[#1c3633] focus:ring-0"
                                                >
                                                    <option value="CASH">CASH</option>
                                                    <option value="UPI">UPI / QR</option>
                                                    <option value="BANK_TRANSFER">BANK TRANSFER</option>
                                                    <option value="CARD">CARD (DEBIT/CREDIT)</option>
                                                    <option value="UNPAID">CREDIT / UNPAID</option>
                                                </select>
                                            </div>
                                            <div>
                                                <label class="text-[10.5px] font-semibold text-surface-700">Amount Received (₹)</label>
                                                <input
                                                    v-model.number="action.result.payment_amount"
                                                    type="number"
                                                    step="1"
                                                    class="w-full mt-0.5 p-1.5 bg-white border border-surface-300 text-xs font-bold text-emerald-800 focus:border-[#1c3633] focus:ring-0"
                                                />
                                            </div>
                                        </div>

                                        <!-- Live Real-Time Calculation Bar -->
                                        <div class="p-2.5 bg-white border border-amber-200 grid grid-cols-3 gap-2 text-xs">
                                            <div>
                                                <span class="text-[10px] text-surface-500">Metal Value</span>
                                                <p class="font-bold text-surface-900">₹{{ Number(action.result.metal_value || 0).toLocaleString('en-IN') }}</p>
                                            </div>
                                            <div>
                                                <span class="text-[10px] text-surface-500">Making Charges</span>
                                                <p class="font-bold text-surface-900">₹{{ Number(action.result.making_charges || 0).toLocaleString('en-IN') }}</p>
                                            </div>
                                            <div>
                                                <span class="text-[10px] text-surface-500">3% GST</span>
                                                <p class="font-bold text-surface-900">₹{{ Number(action.result.gst_3_percent || 0).toLocaleString('en-IN') }}</p>
                                            </div>
                                            <div class="col-span-3 pt-2 border-t border-surface-200 flex items-center justify-between">
                                                <span class="font-bold text-surface-800 text-xs uppercase tracking-wider">Grand Total (Inc. GST)</span>
                                                <span class="text-base font-bold font-serif text-[#c08f34]">
                                                    ₹{{ Number(action.result.grand_total || 0).toLocaleString('en-IN', { minimumFractionDigits: 2 }) }}
                                                </span>
                                            </div>
                                        </div>

                                        <!-- Action Buttons: Confirm vs Discard -->
                                        <div class="flex items-center gap-2 pt-1">
                                            <button
                                                type="button"
                                                :disabled="isConfirming[`bill_${message.id}`]"
                                                @click="confirmBillAction(action, message.id)"
                                                class="flex-1 py-2.5 bg-emerald-700 hover:bg-emerald-800 text-white text-xs font-bold flex items-center justify-center gap-1.5 shadow-sm transition-all disabled:opacity-50"
                                            >
                                                <CheckCircle2 class="w-4 h-4 text-emerald-200" />
                                                <span>{{ isConfirming[`bill_${message.id}`] ? 'Creating Invoice...' : 'Confirm & Create Invoice in Database' }}</span>
                                            </button>
                                            <button
                                                type="button"
                                                @click="discardAction(action)"
                                                class="px-3.5 py-2.5 bg-white border border-surface-300 text-surface-700 hover:text-red-700 hover:border-red-300 text-xs font-semibold transition-all"
                                            >
                                                Discard
                                            </button>
                                        </div>
                                    </div>

                                    <!-- ✅ CONFIRMED FINAL REAL INVOICE CARD (Shown After Confirmation) -->
                                    <div v-else-if="!action.result.is_discarded" class="space-y-2.5">
                                        <!-- Invoice Meta Banner -->
                                        <div class="p-3 bg-emerald-50/90 border border-emerald-300 flex items-center justify-between">
                                            <div class="flex items-center gap-2.5">
                                                <div class="w-8 h-8 bg-emerald-600 text-white flex items-center justify-center font-bold">
                                                    <Receipt class="w-4 h-4" />
                                                </div>
                                                <div>
                                                    <div class="font-bold text-xs text-emerald-950 font-mono tracking-wide">{{ action.result.invoice_number }}</div>
                                                    <div class="text-[11px] text-emerald-800">
                                                        Customer: <strong>{{ action.result.customer_name }}</strong> ({{ action.result.customer_phone }})
                                                    </div>
                                                </div>
                                            </div>
                                            <span class="px-2 py-0.5 bg-emerald-600 text-white text-[10px] font-bold uppercase tracking-wider">
                                                Bill Created
                                            </span>
                                        </div>

                                        <!-- Item Breakdown Grid -->
                                        <div class="grid grid-cols-2 gap-2 text-xs border border-surface-200 p-2.5 bg-surface-50">
                                            <div>
                                                <span class="text-surface-500 text-[10.5px]">Item & Purity</span>
                                                <p class="font-semibold text-surface-800">{{ action.result.item_name }} ({{ action.result.purity }})</p>
                                            </div>
                                            <div>
                                                <span class="text-surface-500 text-[10.5px]">Weight & Live Rate</span>
                                                <p class="font-semibold text-surface-800">{{ action.result.weight }} @ {{ action.result.rate_per_gm }}/g</p>
                                            </div>
                                            <div>
                                                <span class="text-surface-500 text-[10.5px]">Metal Value</span>
                                                <p class="font-semibold text-surface-800">{{ action.result.metal_value }}</p>
                                            </div>
                                            <div>
                                                <span class="text-surface-500 text-[10.5px]">Making Charges</span>
                                                <p class="font-semibold text-surface-800">{{ action.result.making_charges }}</p>
                                            </div>
                                            <div>
                                                <span class="text-surface-500 text-[10.5px]">Subtotal (Pre-GST)</span>
                                                <p class="font-semibold text-surface-800">{{ action.result.subtotal }}</p>
                                            </div>
                                            <div>
                                                <span class="text-surface-500 text-[10.5px]">3% GST Amount</span>
                                                <p class="font-semibold text-surface-800">{{ action.result.gst_3_percent }}</p>
                                            </div>
                                        </div>

                                        <!-- Total & Payment Mode Bar -->
                                        <div class="p-3 bg-[#1c3633] text-white flex items-center justify-between border-l-4 border-l-[#c08f34]">
                                            <div>
                                                <span class="text-[10px] uppercase tracking-wider text-white/70">Grand Total Bill Amount</span>
                                                <div class="text-lg font-bold font-serif text-[#c08f34]">{{ action.result.grand_total }}</div>
                                            </div>
                                            <span class="px-2.5 py-1 bg-white/10 border border-[#c08f34]/40 text-[#c08f34] text-xs font-bold font-mono">
                                                Paid via {{ action.result.payment_mode }}
                                            </span>
                                        </div>

                                        <!-- Action Buttons: View Invoice & Print Bill PDF -->
                                        <div class="flex items-center gap-2 pt-1">
                                            <a
                                                :href="action.result.view_url"
                                                target="_blank"
                                                class="flex-1 py-2 bg-white border border-surface-300 hover:border-[#1c3633] text-[#1c3633] text-xs font-bold flex items-center justify-center gap-1.5 shadow-xs transition-all hover:bg-surface-50 text-center"
                                            >
                                                <FileText class="w-3.5 h-3.5 text-[#c08f34]" />
                                                <span>View Invoice</span>
                                                <ExternalLink class="w-3 h-3 text-surface-400" />
                                            </a>
                                            <a
                                                :href="action.result.print_url"
                                                target="_blank"
                                                class="flex-1 py-2 bg-[#1c3633] hover:bg-[#254642] text-white text-xs font-bold flex items-center justify-center gap-1.5 shadow-xs transition-all text-center"
                                            >
                                                <Printer class="w-3.5 h-3.5 text-[#c08f34]" />
                                                <span>Print PDF</span>
                                            </a>
                                        </div>
                                    </div>

                                    <!-- Discarded Note -->
                                    <div v-else class="p-2.5 bg-surface-100 text-surface-500 text-xs italic text-center">
                                        Invoice draft was discarded.
                                    </div>
                                </div>

                                <!-- 6. Real Stock / Inventory Check Card -->
                                <div v-if="action.tool === 'check_stock'" class="space-y-2.5">
                                    <div class="grid grid-cols-2 gap-2 text-xs border border-surface-200 p-2.5 bg-surface-50">
                                        <div>
                                            <span class="text-surface-500 text-[10.5px]">Total In-Stock Items</span>
                                            <p class="font-bold text-surface-900 text-sm">{{ action.result.total_items }} Items</p>
                                        </div>
                                        <div>
                                            <span class="text-surface-500 text-[10.5px]">Total Net Weight</span>
                                            <p class="font-bold text-surface-900 text-sm">{{ action.result.total_weight }}</p>
                                        </div>
                                        <div>
                                            <span class="text-surface-500 text-[10.5px]">Gold Stock</span>
                                            <p class="font-semibold text-amber-800">{{ action.result.gold_count }} items ({{ action.result.gold_weight }})</p>
                                        </div>
                                        <div>
                                            <span class="text-surface-500 text-[10.5px]">Silver Stock</span>
                                            <p class="font-semibold text-slate-700">{{ action.result.silver_count }} items ({{ action.result.silver_weight }})</p>
                                        </div>
                                    </div>

                                    <div v-if="action.result.items && action.result.items.length > 0" class="space-y-1.5 pt-1">
                                        <div class="text-[11px] font-bold text-surface-600 uppercase tracking-wider">Matching Showcase Stock</div>
                                        <div class="space-y-1 max-h-36 overflow-y-auto">
                                            <div
                                                v-for="(it, i) in action.result.items"
                                                :key="i"
                                                class="p-2 bg-white border border-surface-200 flex items-center justify-between text-xs hover:border-[#c08f34]"
                                            >
                                                <div>
                                                    <div class="font-semibold text-surface-900">{{ it.name }} ({{ it.purity }})</div>
                                                    <div class="text-[10.5px] font-mono text-surface-500">{{ it.barcode }} • {{ it.category }}</div>
                                                </div>
                                                <div class="text-right">
                                                    <div class="font-bold text-[#1c3633]">{{ it.weight }}</div>
                                                    <div class="text-[10px] text-surface-400">Making: {{ it.making }}</div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Thinking Wave -->
                <div v-if="isLoading" class="flex items-center gap-3">
                    <div class="w-8 h-8 bg-white border border-[#c08f34] flex items-center justify-center shrink-0">
                        <Sparkles class="w-4 h-4 text-[#c08f34] animate-spin" />
                    </div>
                    <div class="px-4 py-3 bg-white border border-surface-200 flex items-center gap-2">
                        <span class="w-2 h-2 rounded-full bg-[#c08f34] animate-bounce" />
                        <span class="w-2 h-2 rounded-full bg-[#c08f34] animate-bounce [animation-delay:0.2s]" />
                        <span class="w-2 h-2 rounded-full bg-[#c08f34] animate-bounce [animation-delay:0.4s]" />
                        <span class="text-xs text-surface-600 font-medium ml-1">
                            {{ autoVoiceOutput ? 'Thinking & Generating HD Voice...' : 'Processing ERP Command...' }}
                        </span>
                    </div>
                </div>
            </div>

            <!-- 🎙️ 3. Bottom Command Area: Sharp Enterprise Pinned Footer -->
            <div class="shrink-0 border-t border-surface-200 bg-white p-4 space-y-3 z-10">
                <!-- Quick Suggestion Chips (Sharp Rectangular Buttons, Readable Text) -->
                <div class="flex items-center gap-2 overflow-x-auto pb-1 no-scrollbar">
                    <button
                        v-for="(item, idx) in quickSuggestions"
                        :key="idx"
                        type="button"
                        class="shrink-0 px-3 py-1.5 text-xs font-medium bg-[#f8faf9] hover:bg-[#edf4f2] border border-surface-200 hover:border-[#1c3633] text-[#1c3633] transition-all cursor-pointer flex items-center gap-1.5"
                        @click="sendMessage(item.prompt)"
                    >
                        <component :is="item.icon" class="w-3.5 h-3.5 text-[#c08f34]" />
                        <span>{{ item.label }}</span>
                    </button>
                </div>

                <!-- Listening Active Banner -->
                <div v-if="isListening" class="px-4 py-2.5 bg-red-50 border border-red-200 flex items-center justify-between animate-pulse">
                    <div class="flex items-center gap-2.5 text-xs text-red-700 font-semibold">
                        <span class="w-2.5 h-2.5 rounded-full bg-red-600 animate-ping"></span>
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
                            'w-11 h-11 flex items-center justify-center shrink-0 transition-all cursor-pointer',
                            isListening
                                ? 'bg-red-600 text-white animate-pulse'
                                : 'bg-[#1c3633] hover:bg-[#254642] text-[#c08f34]',
                        ]"
                        :title="isListening ? 'Stop Listening' : 'Click to Speak (Voice Command)'"
                        @click="toggleListening"
                    >
                        <MicOff v-if="isListening" class="w-4.5 h-4.5" />
                        <Mic v-else class="w-4.5 h-4.5" />
                    </button>

                    <!-- Text Input -->
                    <input
                        v-model="inputPrompt"
                        type="text"
                        placeholder="Ask Karat AI or speak via mic..."
                        class="flex-1 h-11 px-3.5 text-sm bg-[#f8faf9] border border-surface-200 text-surface-900 placeholder:text-surface-400 focus:outline-none focus:border-[#1c3633] transition-colors"
                        @keydown.enter="sendMessage()"
                    />

                    <!-- Send Button -->
                    <button
                        type="button"
                        :disabled="!inputPrompt.trim() || isLoading"
                        class="w-11 h-11 flex items-center justify-center bg-[#1c3633] hover:bg-[#254642] text-[#c08f34] disabled:opacity-30 disabled:hover:bg-[#1c3633] transition-all cursor-pointer shrink-0"
                        @click="sendMessage()"
                    >
                        <Send class="w-4.5 h-4.5" />
                    </button>
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
    display: flex !important;
    flex-direction: column !important;
    flex: 1 1 0% !important;
    min-height: 0 !important;
    overflow: hidden !important;
    background-color: #f8faf9 !important;
}

:deep(.p-drawer-header) {
    display: none !important;
}

/* 💎 Smooth Question & Answer Animations */
.animate-msg-user {
    animation: msgUserSlideIn 0.28s cubic-bezier(0.16, 1, 0.3, 1) forwards;
}

.animate-msg-ai {
    animation: msgAiSlideIn 0.32s cubic-bezier(0.16, 1, 0.3, 1) forwards;
}

.animate-card-pop {
    animation: cardPopIn 0.36s cubic-bezier(0.16, 1, 0.3, 1) 0.08s both;
}

@keyframes msgUserSlideIn {
    0% {
        opacity: 0;
        transform: translateX(18px) scale(0.97);
    }
    100% {
        opacity: 1;
        transform: translateX(0) scale(1);
    }
}

@keyframes msgAiSlideIn {
    0% {
        opacity: 0;
        transform: translateX(-18px) translateY(4px) scale(0.97);
    }
    100% {
        opacity: 1;
        transform: translateX(0) translateY(0) scale(1);
    }
}

@keyframes cardPopIn {
    0% {
        opacity: 0;
        transform: translateY(14px) scale(0.96);
    }
    60% {
        transform: translateY(-2px) scale(1.01);
    }
    100% {
        opacity: 1;
        transform: translateY(0) scale(1);
    }
}

/* 🎙️ Equalizer Dancing Bars for HD Audio Playback */
.animate-eq-1 {
    animation: eqBounce1 0.75s ease-in-out infinite alternate;
}
.animate-eq-2 {
    animation: eqBounce2 0.6s ease-in-out infinite alternate 0.15s;
}
.animate-eq-3 {
    animation: eqBounce3 0.85s ease-in-out infinite alternate 0.3s;
}

@keyframes eqBounce1 {
    0% { height: 25%; }
    100% { height: 100%; }
}
@keyframes eqBounce2 {
    0% { height: 40%; }
    100% { height: 90%; }
}
@keyframes eqBounce3 {
    0% { height: 15%; }
    100% { height: 100%; }
}
</style>
