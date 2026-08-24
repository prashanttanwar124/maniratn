<script setup lang="ts">
import axios from 'axios';
import {
    AlertCircle,
    Banknote,
    Bot,
    Check,
    CheckCircle2,
    Clock,
    Coins,
    CreditCard,
    Edit3,
    ExternalLink,
    FileText,
    Mic,
    MicOff,
    PackagePlus,
    Percent,
    Phone,
    Printer,
    QrCode,
    Receipt,
    RefreshCw,
    Send,
    ShieldCheck,
    Sparkles,
    Tag,
    Trash2,
    TrendingUp,
    User,
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

const setMakingType = (draft: any, type: string) => {
    draft.making_type = type;
    calculateLiveBill(draft);
};

const setPaymentMode = (draft: any, mode: string) => {
    draft.payment_mode = mode;
};

const setPurity = (draft: any, purity: string) => {
    draft.purity = purity;
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

                        <!-- ⚡ Real ERP Action Cards (Luxury Jewel ERP Interface) -->
                        <div v-if="msg.actions && msg.actions.length > 0" class="space-y-3 animate-card-pop">
                            <div
                                v-for="(action, idx) in msg.actions"
                                :key="idx"
                                class="bg-white border border-[#c08f34]/30 shadow-md overflow-hidden transition-all duration-300 hover:shadow-lg hover:border-[#c08f34]/60"
                            >
                                <!-- 1. Rates Inquire Card -->
                                <div v-if="action.tool === 'get_daily_rates'">
                                    <div class="px-3.5 py-2.5 bg-gradient-to-r from-[#142926] via-[#1c3633] to-[#142926] text-white flex items-center justify-between border-b-2 border-b-[#c08f34]">
                                        <div class="flex items-center gap-2">
                                            <div class="w-6 h-6 rounded bg-[#c08f34]/20 border border-[#c08f34]/40 text-[#c08f34] flex items-center justify-center">
                                                <Coins class="w-3.5 h-3.5" />
                                            </div>
                                            <div>
                                                <div class="font-bold text-xs tracking-wide text-white font-serif">Today's Live Bullion Rates</div>
                                                <div class="text-[10px] text-[#c08f34]">Real-time Database Rates</div>
                                            </div>
                                        </div>
                                        <span class="px-2 py-0.5 rounded-full bg-emerald-500/20 border border-emerald-400/30 text-emerald-300 text-[10px] font-bold uppercase">
                                            Active
                                        </span>
                                    </div>
                                    <div v-if="action.result.found === false" class="p-3.5 bg-amber-50 text-xs text-amber-900 space-y-1">
                                        <p class="font-bold flex items-center gap-1.5 text-amber-800">
                                            <span>⚠️</span> Aaj ka rate set nahi hai
                                        </p>
                                        <p class="text-surface-600 text-[11px]">
                                            Mic se bole: <em>"Aaj ka 24k rate 7520 aur silver 89.20 set kar do"</em>
                                        </p>
                                    </div>
                                    <div v-else class="p-3 bg-[#fcfaf6] grid grid-cols-3 gap-2">
                                        <div class="p-2.5 bg-white border border-[#e8dfcf] rounded-sm text-center shadow-2xs">
                                            <p class="text-[10px] font-bold text-surface-500 uppercase tracking-wider">Gold 24K</p>
                                            <p class="text-sm font-bold text-[#c08f34] mt-0.5">
                                                ₹{{ Number(action.result.gold_24k_per_gm).toLocaleString('en-IN') }}<span class="text-[10px] font-normal text-surface-400">/g</span>
                                            </p>
                                        </div>
                                        <div class="p-2.5 bg-white border border-[#e8dfcf] rounded-sm text-center shadow-2xs">
                                            <p class="text-[10px] font-bold text-surface-500 uppercase tracking-wider">Gold 22K (916)</p>
                                            <p class="text-sm font-bold text-[#9b6f1e] mt-0.5">
                                                ₹{{ Number(action.result.gold_22k_per_gm).toLocaleString('en-IN') }}<span class="text-[10px] font-normal text-surface-400">/g</span>
                                            </p>
                                        </div>
                                        <div class="p-2.5 bg-white border border-[#e8dfcf] rounded-sm text-center shadow-2xs">
                                            <p class="text-[10px] font-bold text-surface-500 uppercase tracking-wider">Silver (999)</p>
                                            <p class="text-sm font-bold text-slate-700 mt-0.5">
                                                ₹{{ Number(action.result.silver_per_gm).toLocaleString('en-IN', { minimumFractionDigits: 2 }) }}<span class="text-[10px] font-normal text-surface-400">/g</span>
                                            </p>
                                        </div>
                                    </div>
                                </div>

                                <!-- 1b. Daily Rates (Preview vs Confirmed) -->
                                <div v-else-if="action.tool === 'update_daily_rates'">
                                    <div v-if="action.result.is_preview">
                                        <div class="px-3.5 py-2.5 bg-gradient-to-r from-[#142926] via-[#1c3633] to-[#142926] text-white flex items-center justify-between border-b-2 border-b-[#c08f34]">
                                            <div class="flex items-center gap-2">
                                                <div class="w-6 h-6 rounded bg-[#c08f34]/20 border border-[#c08f34]/40 text-[#c08f34] flex items-center justify-center">
                                                    <Edit3 class="w-3.5 h-3.5" />
                                                </div>
                                                <div>
                                                    <div class="font-bold text-xs tracking-wide text-white font-serif">Daily Rates Update Preview</div>
                                                    <div class="text-[10px] text-[#c08f34]">Review rates before updating database</div>
                                                </div>
                                            </div>
                                            <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full bg-amber-500/20 border border-amber-400/40 text-amber-200 text-[10px] font-bold">
                                                <ShieldCheck class="w-3 h-3" />
                                                Review Required
                                            </span>
                                        </div>
                                        <div class="p-3.5 bg-surface-50/70 space-y-3">
                                            <div class="grid grid-cols-2 gap-2.5">
                                                <div class="bg-white p-2.5 border border-surface-200 shadow-2xs">
                                                    <label class="text-[10px] font-bold text-surface-600 uppercase tracking-wide">Gold 24K Sell (₹/g)</label>
                                                    <div class="relative mt-1">
                                                        <span class="absolute left-2 top-1.5 text-xs font-bold text-[#c08f34]">₹</span>
                                                        <input
                                                            v-model.number="action.result.gold_24k_sell"
                                                            type="number"
                                                            class="w-full pl-6 pr-2 py-1.5 bg-surface-50 border border-surface-300 text-xs font-bold text-[#1c3633] focus:bg-white focus:border-[#c08f34] focus:ring-1 focus:ring-[#c08f34]/30"
                                                        />
                                                    </div>
                                                </div>
                                                <div class="bg-white p-2.5 border border-surface-200 shadow-2xs">
                                                    <label class="text-[10px] font-bold text-surface-600 uppercase tracking-wide">Silver Sell (₹/g)</label>
                                                    <div class="relative mt-1">
                                                        <span class="absolute left-2 top-1.5 text-xs font-bold text-slate-500">₹</span>
                                                        <input
                                                            v-model.number="action.result.silver_sell"
                                                            type="number"
                                                            class="w-full pl-6 pr-2 py-1.5 bg-surface-50 border border-surface-300 text-xs font-bold text-slate-800 focus:bg-white focus:border-[#c08f34] focus:ring-1 focus:ring-[#c08f34]/30"
                                                        />
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="flex items-center gap-2 pt-1">
                                                <button
                                                    type="button"
                                                    :disabled="isConfirming[`rates_${msg.id}`]"
                                                    @click="confirmRatesAction(action, msg.id)"
                                                    class="flex-1 py-2.5 bg-gradient-to-r from-emerald-700 to-emerald-800 hover:from-emerald-600 hover:to-emerald-700 text-white text-xs font-bold flex items-center justify-center gap-1.5 shadow-sm transition-all disabled:opacity-50"
                                                >
                                                    <Check class="w-3.5 h-3.5 text-emerald-200" />
                                                    <span>{{ isConfirming[`rates_${msg.id}`] ? 'Updating Database...' : 'Confirm & Update Live Rates' }}</span>
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
                                    </div>
                                    <div v-else class="p-3 bg-emerald-50/70 border-t border-emerald-300 flex items-center justify-between">
                                        <div class="flex items-center gap-2">
                                            <CheckCircle2 class="w-4 h-4 text-emerald-600" />
                                            <span class="text-xs font-bold text-emerald-900">Rates updated successfully in database!</span>
                                        </div>
                                        <span class="text-xs font-bold text-[#c08f34]">24K: ₹{{ Number(action.result.gold_24k_sell).toLocaleString('en-IN') }}/g</span>
                                    </div>
                                </div>

                                <!-- 2. Product Added (Preview vs Confirmed) -->
                                <div v-else-if="action.tool === 'add_product'">
                                    <div v-if="action.result.is_preview">
                                        <div class="px-3.5 py-2.5 bg-gradient-to-r from-[#142926] via-[#1c3633] to-[#142926] text-white flex items-center justify-between border-b-2 border-b-[#c08f34]">
                                            <div class="flex items-center gap-2">
                                                <div class="w-6 h-6 rounded bg-[#c08f34]/20 border border-[#c08f34]/40 text-[#c08f34] flex items-center justify-center">
                                                    <PackagePlus class="w-3.5 h-3.5" />
                                                </div>
                                                <div>
                                                    <div class="font-bold text-xs tracking-wide text-white font-serif">Add Ornament to Stock Preview</div>
                                                    <div class="text-[10px] text-[#c08f34]">Review specs before saving barcode in ERP</div>
                                                </div>
                                            </div>
                                            <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full bg-amber-500/20 border border-amber-400/40 text-amber-200 text-[10px] font-bold">
                                                <ShieldCheck class="w-3 h-3" />
                                                Review Required
                                            </span>
                                        </div>
                                        <div class="p-3.5 bg-surface-50/70 space-y-3 text-xs">
                                            <div class="grid grid-cols-2 gap-2.5">
                                                <div class="col-span-2">
                                                    <label class="text-[10.5px] font-semibold text-surface-700">Ornament Name *</label>
                                                    <input
                                                        v-model="action.result.name"
                                                        class="w-full mt-1 p-2 bg-white border border-surface-300 text-xs font-semibold text-surface-900 focus:border-[#c08f34] focus:ring-1 focus:ring-[#c08f34]/30"
                                                    />
                                                </div>
                                                <div>
                                                    <label class="text-[10.5px] font-semibold text-surface-700">Net Weight (g) *</label>
                                                    <div class="relative mt-1">
                                                        <input
                                                            v-model.number="action.result.weight"
                                                            type="number"
                                                            step="0.001"
                                                            class="w-full pl-2 pr-7 py-1.5 bg-white border border-surface-300 text-xs font-bold text-[#1c3633] focus:border-[#c08f34] focus:ring-1 focus:ring-[#c08f34]/30"
                                                        />
                                                        <span class="absolute right-2 top-1.5 text-[10.5px] font-bold text-surface-400">g</span>
                                                    </div>
                                                </div>
                                                <div>
                                                    <label class="text-[10.5px] font-semibold text-surface-700">Purity</label>
                                                    <input
                                                        v-model="action.result.purity"
                                                        placeholder="22K, 18K"
                                                        class="w-full mt-1 p-1.5 bg-white border border-surface-300 text-xs font-semibold focus:border-[#c08f34] focus:ring-1 focus:ring-[#c08f34]/30"
                                                    />
                                                </div>
                                                <div>
                                                    <label class="text-[10.5px] font-semibold text-surface-700">Category</label>
                                                    <input
                                                        v-model="action.result.category"
                                                        class="w-full mt-1 p-1.5 bg-white border border-surface-300 text-xs focus:border-[#c08f34] focus:ring-1 focus:ring-[#c08f34]/30"
                                                    />
                                                </div>
                                                <div>
                                                    <label class="text-[10.5px] font-semibold text-surface-700">Making Charge (₹/g)</label>
                                                    <input
                                                        v-model.number="action.result.making_charge_per_gm"
                                                        type="number"
                                                        class="w-full mt-1 p-1.5 bg-white border border-surface-300 text-xs font-semibold focus:border-[#c08f34] focus:ring-1 focus:ring-[#c08f34]/30"
                                                    />
                                                </div>
                                            </div>
                                            <div class="flex items-center gap-2 pt-1">
                                                <button
                                                    type="button"
                                                    :disabled="isConfirming[`prod_${msg.id}`]"
                                                    @click="confirmProductAction(action, msg.id)"
                                                    class="flex-1 py-2.5 bg-gradient-to-r from-emerald-700 to-emerald-800 hover:from-emerald-600 hover:to-emerald-700 text-white text-xs font-bold flex items-center justify-center gap-1.5 shadow-sm transition-all disabled:opacity-50"
                                                >
                                                    <PackagePlus class="w-3.5 h-3.5 text-emerald-200" />
                                                    <span>{{ isConfirming[`prod_${msg.id}`] ? 'Saving to Stock...' : 'Confirm & Save to Stock' }}</span>
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
                                    </div>
                                    <div v-else class="p-3.5 bg-[#fcfaf6] border-t border-[#e8dfcf] space-y-2">
                                        <div class="flex items-center justify-between">
                                            <span class="font-bold text-xs text-[#1c3633]">{{ action.result.name }}</span>
                                            <span class="font-mono text-xs px-2.5 py-0.5 bg-[#1c3633] text-[#c08f34] font-bold tracking-widest">
                                                {{ action.result.barcode }}
                                            </span>
                                        </div>
                                        <div class="grid grid-cols-3 gap-2 text-[11px] text-surface-600 pt-1 border-t border-surface-200">
                                            <div>Weight: <strong class="text-[#1c3633]">{{ action.result.weight }}g</strong></div>
                                            <div>Purity: <strong class="text-[#1c3633]">{{ action.result.purity }}</strong></div>
                                            <div>Making: <strong class="text-[#1c3633]">₹{{ action.result.making_charge_per_gm }}/g</strong></div>
                                        </div>
                                    </div>
                                </div>

                                <!-- 3. Vault Balance Card -->
                                <div v-else-if="action.tool === 'get_vault_balance'">
                                    <div class="px-3.5 py-2 bg-[#1c3633] text-white flex items-center justify-between border-b-2 border-b-[#c08f34]">
                                        <span class="font-serif text-xs font-bold tracking-wide">Showroom Vault Holdings</span>
                                        <span class="text-[10px] text-[#c08f34] uppercase font-mono">Live Safe Balance</span>
                                    </div>
                                    <div class="p-3 bg-[#fcfaf6] grid grid-cols-3 gap-2 text-center">
                                        <div class="p-2.5 bg-white border border-emerald-200 rounded-sm shadow-2xs">
                                            <p class="text-[10px] font-bold text-surface-500 uppercase">Cash In Hand</p>
                                            <p class="text-xs font-bold text-emerald-800 mt-1">{{ action.result.cash_in_hand }}</p>
                                        </div>
                                        <div class="p-2.5 bg-white border border-amber-200 rounded-sm shadow-2xs">
                                            <p class="text-[10px] font-bold text-surface-500 uppercase">Gold Safe</p>
                                            <p class="text-xs font-bold text-[#c08f34] mt-1">{{ action.result.gold_in_vault }}</p>
                                        </div>
                                        <div class="p-2.5 bg-white border border-slate-200 rounded-sm shadow-2xs">
                                            <p class="text-[10px] font-bold text-surface-500 uppercase">Silver Safe</p>
                                            <p class="text-xs font-bold text-slate-700 mt-1">{{ action.result.silver_in_vault }}</p>
                                        </div>
                                    </div>
                                </div>

                                <!-- 4. Estimate Calculation Card -->
                                <div v-else-if="action.tool === 'calculate_estimate'">
                                    <div class="px-3.5 py-2 bg-[#1c3633] text-white flex items-center justify-between border-b-2 border-b-[#c08f34]">
                                        <span class="font-serif text-xs font-bold tracking-wide">Quotation / Estimate</span>
                                        <span class="text-[10px] text-[#c08f34] uppercase font-mono">12% Making • 3% GST</span>
                                    </div>
                                    <div v-if="action.result.found === false" class="p-3.5 bg-amber-50 text-xs text-amber-900 space-y-1">
                                        <p class="font-bold text-amber-800">⚠️ Aaj ka gold rate set nahi hai</p>
                                        <p class="text-[11px] text-surface-600">Rate set karein ya quotation command me rate batayein.</p>
                                    </div>
                                    <div v-else class="p-3.5 bg-[#fcfaf6] space-y-1.5 text-xs">
                                        <div class="flex justify-between text-surface-600 text-[11px]">
                                            <span>Metal Value ({{ action.result.weight }} @ {{ action.result.rate_per_gm }})</span>
                                            <span class="font-medium text-[#1c3633]">{{ action.result.metal_value }}</span>
                                        </div>
                                        <div class="flex justify-between text-surface-600 text-[11px]">
                                            <span>Making Charges</span>
                                            <span class="font-medium text-[#1c3633]">{{ action.result.making_charges }}</span>
                                        </div>
                                        <div class="flex justify-between text-surface-600 text-[11px]">
                                            <span>3% GST</span>
                                            <span class="font-medium text-[#1c3633]">{{ action.result.gst_3_percent }}</span>
                                        </div>
                                        <div class="flex justify-between pt-2 border-t border-surface-200 font-bold text-xs text-[#1c3633]">
                                            <span>Total Quotation</span>
                                            <span class="text-[#c08f34] text-sm font-bold font-serif">{{ action.result.total_estimate }}</span>
                                        </div>
                                    </div>
                                </div>

                                <!-- 5. 💎 LUXURY INVOICE DRAFT & CONFIRMATION (HUMAN-IN-THE-LOOP) -->
                                <div v-if="action.tool === 'create_bill' || action.tool === 'create_invoice'">
                                    <!-- Error State -->
                                    <div v-if="action.result.found === false" class="p-3.5 bg-red-50 border-l-4 border-l-red-500 text-xs text-red-900 space-y-1">
                                        <p class="font-bold flex items-center gap-1.5 text-red-800">
                                            <AlertCircle class="w-4 h-4 text-red-600" />
                                            {{ action.result.message || 'Bill generate nahi ho paya.' }}
                                        </p>
                                        <p class="text-[11px] text-surface-600">Kripya customer details ya valid unsold barcode batayein.</p>
                                    </div>

                                    <!-- 📝 HUMAN-IN-THE-LOOP: LUXURY DRAFT PREVIEW FORM -->
                                    <div v-else-if="action.result.is_preview" class="space-y-0">
                                        <!-- Header Bar -->
                                        <div class="px-3.5 py-2.5 bg-gradient-to-r from-[#142926] via-[#1c3633] to-[#142926] text-white flex items-center justify-between border-b-2 border-b-[#c08f34]">
                                            <div class="flex items-center gap-2">
                                                <div class="w-6 h-6 rounded bg-[#c08f34]/20 border border-[#c08f34]/40 text-[#c08f34] flex items-center justify-center font-bold">
                                                    <Receipt class="w-3.5 h-3.5" />
                                                </div>
                                                <div>
                                                    <div class="font-bold text-xs tracking-wide text-white font-serif">Invoice Draft Preview</div>
                                                    <div class="text-[10px] text-[#c08f34]">Live Calculation • Verify & Edit Before DB Commit</div>
                                                </div>
                                            </div>
                                            <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full bg-amber-500/20 border border-amber-400/40 text-amber-200 text-[10px] font-bold tracking-wide">
                                                <ShieldCheck class="w-3 h-3" />
                                                <span>Review Draft</span>
                                            </span>
                                        </div>

                                        <!-- Interactive Form Body -->
                                        <div class="p-3.5 bg-surface-50/60 space-y-3 text-xs">
                                            <!-- Section 1: Customer Info -->
                                            <div class="bg-white p-2.5 border border-surface-200/90 shadow-2xs space-y-2">
                                                <div class="text-[10px] font-bold text-surface-500 uppercase tracking-wider flex items-center gap-1">
                                                    <User class="w-3 h-3 text-[#c08f34]" />
                                                    Customer Information
                                                </div>
                                                <div class="grid grid-cols-2 gap-2">
                                                    <div>
                                                        <label class="text-[10.5px] font-medium text-surface-700">Customer Name *</label>
                                                        <input
                                                            v-model="action.result.customer_name"
                                                            type="text"
                                                            placeholder="Customer Name"
                                                            class="w-full mt-0.5 px-2 py-1.5 bg-surface-50/80 border border-surface-300 text-xs font-semibold text-surface-900 focus:bg-white focus:border-[#c08f34] focus:ring-1 focus:ring-[#c08f34]/30"
                                                        />
                                                    </div>
                                                    <div>
                                                        <label class="text-[10.5px] font-medium text-surface-700">Mobile Number</label>
                                                        <input
                                                            v-model="action.result.customer_phone"
                                                            type="text"
                                                            placeholder="10-digit Phone"
                                                            class="w-full mt-0.5 px-2 py-1.5 bg-surface-50/80 border border-surface-300 text-xs font-mono text-surface-900 focus:bg-white focus:border-[#c08f34] focus:ring-1 focus:ring-[#c08f34]/30"
                                                        />
                                                    </div>
                                                </div>
                                            </div>

                                            <!-- Section 2: Ornament & Weight -->
                                            <div class="bg-white p-2.5 border border-surface-200/90 shadow-2xs space-y-2">
                                                <div class="text-[10px] font-bold text-surface-500 uppercase tracking-wider flex items-center gap-1">
                                                    <Tag class="w-3 h-3 text-[#c08f34]" />
                                                    Ornament & Metal Specs
                                                </div>
                                                <div class="grid grid-cols-2 gap-2">
                                                    <div>
                                                        <label class="text-[10.5px] font-medium text-surface-700">Item Description *</label>
                                                        <input
                                                            v-model="action.result.item_name"
                                                            type="text"
                                                            class="w-full mt-0.5 px-2 py-1.5 bg-surface-50/80 border border-surface-300 text-xs font-semibold text-surface-900 focus:bg-white focus:border-[#c08f34] focus:ring-1 focus:ring-[#c08f34]/30"
                                                        />
                                                    </div>
                                                    <div>
                                                        <label class="text-[10.5px] font-medium text-surface-700">Stock Barcode</label>
                                                        <input
                                                            v-model="action.result.barcode"
                                                            type="text"
                                                            placeholder="e.g. G00021"
                                                            class="w-full mt-0.5 px-2 py-1.5 bg-surface-50/80 border border-surface-300 text-xs font-mono font-bold uppercase text-[#1c3633] focus:bg-white focus:border-[#c08f34] focus:ring-1 focus:ring-[#c08f34]/30"
                                                        />
                                                    </div>
                                                    <div>
                                                        <label class="text-[10.5px] font-medium text-surface-700">Net Weight *</label>
                                                        <div class="relative mt-0.5">
                                                            <input
                                                                v-model.number="action.result.weight"
                                                                type="number"
                                                                step="0.001"
                                                                @input="calculateLiveBill(action.result)"
                                                                class="w-full pl-2 pr-7 py-1.5 bg-surface-50/80 border border-surface-300 text-xs font-bold text-[#1c3633] focus:bg-white focus:border-[#c08f34] focus:ring-1 focus:ring-[#c08f34]/30"
                                                            />
                                                            <span class="absolute right-2 top-1.5 text-[10.5px] font-bold text-surface-400">g</span>
                                                        </div>
                                                    </div>
                                                    <div>
                                                        <label class="text-[10.5px] font-medium text-surface-700">Purity</label>
                                                        <div class="flex gap-1 mt-0.5">
                                                            <button
                                                                v-for="p in ['22K', '18K', '24K', 'Silver']"
                                                                :key="p"
                                                                type="button"
                                                                @click="setPurity(action.result, p)"
                                                                :class="[
                                                                    'flex-1 py-1 text-[10.5px] font-bold border transition-all',
                                                                    action.result.purity === p
                                                                        ? 'bg-[#1c3633] text-[#c08f34] border-[#1c3633]'
                                                                        : 'bg-surface-50 text-surface-600 border-surface-300 hover:bg-surface-100'
                                                                ]"
                                                            >
                                                                {{ p }}
                                                            </button>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>

                                            <!-- Section 3: Live Rate & Making Charges (Segmented Controls) -->
                                            <div class="bg-white p-2.5 border border-surface-200/90 shadow-2xs space-y-2">
                                                <div class="text-[10px] font-bold text-surface-500 uppercase tracking-wider flex items-center gap-1">
                                                    <Coins class="w-3 h-3 text-[#c08f34]" />
                                                    Pricing & Making Charges
                                                </div>
                                                <div class="grid grid-cols-2 gap-2">
                                                    <div>
                                                        <label class="text-[10.5px] font-medium text-surface-700">Live Rate (₹/g) *</label>
                                                        <div class="relative mt-0.5">
                                                            <span class="absolute left-2 top-1.5 text-xs font-bold text-[#c08f34]">₹</span>
                                                            <input
                                                                v-model.number="action.result.rate_per_gm"
                                                                type="number"
                                                                step="1"
                                                                @input="calculateLiveBill(action.result)"
                                                                class="w-full pl-6 pr-2 py-1.5 bg-surface-50/80 border border-surface-300 text-xs font-bold text-surface-900 focus:bg-white focus:border-[#c08f34] focus:ring-1 focus:ring-[#c08f34]/30"
                                                            />
                                                        </div>
                                                    </div>
                                                    <div>
                                                        <label class="text-[10.5px] font-medium text-surface-700">Discount Amount (₹)</label>
                                                        <div class="relative mt-0.5">
                                                            <span class="absolute left-2 top-1.5 text-xs font-bold text-emerald-700">₹</span>
                                                            <input
                                                                v-model.number="action.result.discount_amount"
                                                                type="number"
                                                                step="1"
                                                                @input="calculateLiveBill(action.result)"
                                                                class="w-full pl-6 pr-2 py-1.5 bg-surface-50/80 border border-surface-300 text-xs font-bold text-emerald-800 focus:bg-white focus:border-[#c08f34] focus:ring-1 focus:ring-[#c08f34]/30"
                                                            />
                                                        </div>
                                                    </div>

                                                    <!-- Making Charge Segmented Switcher -->
                                                    <div class="col-span-2 space-y-1.5 pt-1 border-t border-surface-100">
                                                        <label class="text-[10.5px] font-medium text-surface-700">Making Charge Calculation Method</label>
                                                        <div class="grid grid-cols-3 gap-1.5">
                                                            <button
                                                                type="button"
                                                                @click="setMakingType(action.result, 'percentage')"
                                                                :class="[
                                                                    'py-1.5 px-2 text-[11px] font-bold border transition-all flex items-center justify-center gap-1',
                                                                    action.result.making_type === 'percentage'
                                                                        ? 'bg-[#1c3633] text-white border-[#1c3633] shadow-xs'
                                                                        : 'bg-surface-50 text-surface-600 border-surface-300 hover:bg-surface-100'
                                                                ]"
                                                            >
                                                                <Percent class="w-3 h-3 text-[#c08f34]" />
                                                                <span>% Percentage</span>
                                                            </button>
                                                            <button
                                                                type="button"
                                                                @click="setMakingType(action.result, 'per_gram')"
                                                                :class="[
                                                                    'py-1.5 px-2 text-[11px] font-bold border transition-all flex items-center justify-center gap-1',
                                                                    action.result.making_type === 'per_gram'
                                                                        ? 'bg-[#1c3633] text-white border-[#1c3633] shadow-xs'
                                                                        : 'bg-surface-50 text-surface-600 border-surface-300 hover:bg-surface-100'
                                                                ]"
                                                            >
                                                                <span>₹/g Per Gram</span>
                                                            </button>
                                                            <button
                                                                type="button"
                                                                @click="setMakingType(action.result, 'flat')"
                                                                :class="[
                                                                    'py-1.5 px-2 text-[11px] font-bold border transition-all flex items-center justify-center gap-1',
                                                                    action.result.making_type === 'flat'
                                                                        ? 'bg-[#1c3633] text-white border-[#1c3633] shadow-xs'
                                                                        : 'bg-surface-50 text-surface-600 border-surface-300 hover:bg-surface-100'
                                                                ]"
                                                            >
                                                                <span>₹ Flat Amount</span>
                                                            </button>
                                                        </div>
                                                    </div>

                                                    <div class="col-span-2">
                                                        <label class="text-[10.5px] font-medium text-surface-700">
                                                            Making Value
                                                            <span class="text-surface-400">
                                                                ({{ action.result.making_type === 'percentage' ? '% on metal' : (action.result.making_type === 'per_gram' ? '₹ per gram' : 'flat ₹') }})
                                                            </span>
                                                        </label>
                                                        <div class="relative mt-0.5">
                                                            <input
                                                                v-model.number="action.result.making_value"
                                                                type="number"
                                                                step="0.1"
                                                                @input="calculateLiveBill(action.result)"
                                                                class="w-full px-2 py-1.5 bg-surface-50/80 border border-surface-300 text-xs font-bold text-[#1c3633] focus:bg-white focus:border-[#c08f34] focus:ring-1 focus:ring-[#c08f34]/30"
                                                            />
                                                            <span class="absolute right-2 top-1.5 text-[10.5px] font-bold text-[#c08f34]">
                                                                = ₹{{ Number(action.result.making_charges || 0).toLocaleString('en-IN') }}
                                                            </span>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>

                                            <!-- Section 4: Settlement & Payment Mode Pills -->
                                            <div class="bg-white p-2.5 border border-surface-200/90 shadow-2xs space-y-2">
                                                <div class="text-[10px] font-bold text-surface-500 uppercase tracking-wider flex items-center gap-1">
                                                    <CreditCard class="w-3 h-3 text-[#c08f34]" />
                                                    Settlement & Payment Method
                                                </div>
                                                <div class="flex flex-wrap gap-1">
                                                    <button
                                                        v-for="mode in [
                                                            { id: 'CASH', label: '💵 Cash' },
                                                            { id: 'UPI', label: '📱 UPI' },
                                                            { id: 'CARD', label: '💳 Card' },
                                                            { id: 'BANK_TRANSFER', label: '🏦 Bank' },
                                                            { id: 'UNPAID', label: '⏳ Credit' }
                                                        ]"
                                                        :key="mode.id"
                                                        type="button"
                                                        @click="setPaymentMode(action.result, mode.id)"
                                                        :class="[
                                                            'flex-1 min-w-[55px] py-1.5 px-2 text-[10.5px] font-bold border transition-all text-center',
                                                            action.result.payment_mode === mode.id
                                                                ? 'bg-[#1c3633] text-[#c08f34] border-[#1c3633] shadow-xs'
                                                                : 'bg-surface-50 text-surface-600 border-surface-300 hover:bg-surface-100'
                                                        ]"
                                                    >
                                                        {{ mode.label }}
                                                    </button>
                                                </div>
                                            </div>

                                            <!-- 🧾 LUXURY TAX INVOICE BREAKDOWN TICKET -->
                                            <div class="bg-gradient-to-b from-[#fcfaf6] to-[#f6f2ea] border border-[#e5dcce] p-3 space-y-2 shadow-xs">
                                                <div class="text-[10px] font-bold text-[#1c3633] uppercase tracking-wider flex items-center justify-between border-b border-[#e8dfcf] pb-1.5">
                                                    <span class="font-serif">Invoice Calculation Summary</span>
                                                    <span class="text-[10px] text-surface-500 font-normal">GSTIN Tax Invoice</span>
                                                </div>

                                                <div class="space-y-1 text-xs">
                                                    <div class="flex justify-between text-surface-600">
                                                        <span>Metal Value ({{ action.result.weight || 0 }}g @ ₹{{ action.result.rate_per_gm || 0 }})</span>
                                                        <span class="font-semibold text-surface-900">₹{{ Number(action.result.metal_value || 0).toLocaleString('en-IN') }}</span>
                                                    </div>
                                                    <div class="flex justify-between text-surface-600">
                                                        <span>Making Charges ({{ action.result.making_type === 'percentage' ? (action.result.making_value || 12) + '%' : (action.result.making_type === 'per_gram' ? '₹' + action.result.making_value + '/g' : 'Flat') }})</span>
                                                        <span class="font-semibold text-surface-900">+ ₹{{ Number(action.result.making_charges || 0).toLocaleString('en-IN') }}</span>
                                                    </div>
                                                    <div v-if="action.result.discount_amount > 0" class="flex justify-between text-emerald-700">
                                                        <span>Special Discount</span>
                                                        <span class="font-semibold">- ₹{{ Number(action.result.discount_amount || 0).toLocaleString('en-IN') }}</span>
                                                    </div>
                                                    <div class="flex justify-between text-surface-600 pt-1 border-t border-[#e8dfcf]">
                                                        <span>Taxable Subtotal</span>
                                                        <span class="font-semibold text-surface-900">₹{{ Number(action.result.subtotal || 0).toLocaleString('en-IN') }}</span>
                                                    </div>
                                                    <div class="flex justify-between text-surface-600">
                                                        <span>GST Tax (3% CGST+SGST)</span>
                                                        <span class="font-semibold text-surface-900">+ ₹{{ Number(action.result.gst_3_percent || 0).toLocaleString('en-IN') }}</span>
                                                    </div>
                                                </div>

                                                <!-- Grand Total Payable Bar -->
                                                <div class="p-2.5 bg-[#1c3633] text-white flex items-center justify-between border-l-4 border-l-[#c08f34]">
                                                    <div>
                                                        <div class="text-[9.5px] uppercase tracking-wider text-white/70">Grand Total (Net Payable)</div>
                                                        <div class="text-base font-bold font-serif text-[#c08f34]">
                                                            ₹{{ Number(action.result.grand_total || 0).toLocaleString('en-IN', { minimumFractionDigits: 2 }) }}
                                                        </div>
                                                    </div>
                                                    <span class="px-2 py-0.5 bg-white/10 border border-[#c08f34]/40 text-[#c08f34] text-[11px] font-bold font-mono">
                                                        {{ action.result.payment_mode }}
                                                    </span>
                                                </div>
                                            </div>

                                            <!-- 🚀 Action CTAs -->
                                            <div class="flex items-center gap-2 pt-1">
                                                <button
                                                    type="button"
                                                    :disabled="isConfirming[`bill_${msg.id}`]"
                                                    @click="confirmBillAction(action, msg.id)"
                                                    class="flex-1 py-3 bg-gradient-to-r from-emerald-700 via-emerald-800 to-emerald-700 hover:from-emerald-600 hover:to-emerald-700 text-white text-xs font-bold flex items-center justify-center gap-2 shadow-md transition-all disabled:opacity-50 active:scale-[0.99] cursor-pointer"
                                                >
                                                    <CheckCircle2 class="w-4 h-4 text-emerald-200" />
                                                    <span>{{ isConfirming[`bill_${msg.id}`] ? 'Creating Invoice in Database...' : 'Confirm & Create Invoice in Database' }}</span>
                                                </button>
                                                <button
                                                    type="button"
                                                    @click="discardAction(action)"
                                                    class="px-3.5 py-3 bg-white border border-surface-300 text-surface-700 hover:text-red-700 hover:border-red-300 text-xs font-semibold transition-all cursor-pointer"
                                                >
                                                    Discard
                                                </button>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- ✅ CONFIRMED FINAL REAL INVOICE VOUCHER (Post-Confirmation) -->
                                    <div v-else-if="!action.result.is_discarded" class="space-y-0">
                                        <!-- Invoice Meta Banner -->
                                        <div class="p-3 bg-emerald-700 text-white flex items-center justify-between border-b-2 border-b-[#c08f34]">
                                            <div class="flex items-center gap-2.5">
                                                <div class="w-8 h-8 bg-white/15 border border-white/30 text-white flex items-center justify-center font-bold">
                                                    <Receipt class="w-4 h-4 text-emerald-200" />
                                                </div>
                                                <div>
                                                    <div class="font-bold text-xs font-mono tracking-wide text-white">{{ action.result.invoice_number }}</div>
                                                    <div class="text-[10.5px] text-emerald-100">
                                                        Customer: <strong>{{ action.result.customer_name }}</strong> ({{ action.result.customer_phone || 'Walk-in' }})
                                                    </div>
                                                </div>
                                            </div>
                                            <span class="px-2.5 py-1 bg-white text-emerald-800 text-[10px] font-bold uppercase tracking-wider shadow-xs">
                                                Bill Created
                                            </span>
                                        </div>

                                        <!-- Item Breakdown Grid -->
                                        <div class="p-3 bg-[#fcfaf6] space-y-2.5 text-xs border-b border-[#e8dfcf]">
                                            <div class="grid grid-cols-2 gap-2 text-[11px]">
                                                <div>
                                                    <span class="text-surface-500 text-[10px]">Item & Purity</span>
                                                    <p class="font-semibold text-surface-900">{{ action.result.item_name }} ({{ action.result.purity }})</p>
                                                </div>
                                                <div>
                                                    <span class="text-surface-500 text-[10px]">Weight & Live Rate</span>
                                                    <p class="font-semibold text-surface-900">{{ action.result.weight }}g @ ₹{{ Number(action.result.rate_per_gm || 0).toLocaleString('en-IN') }}/g</p>
                                                </div>
                                                <div>
                                                    <span class="text-surface-500 text-[10px]">Metal Value</span>
                                                    <p class="font-semibold text-surface-900">₹{{ Number(action.result.metal_value || 0).toLocaleString('en-IN') }}</p>
                                                </div>
                                                <div>
                                                    <span class="text-surface-500 text-[10px]">Making Charges</span>
                                                    <p class="font-semibold text-surface-900">₹{{ Number(action.result.making_charges || 0).toLocaleString('en-IN') }}</p>
                                                </div>
                                            </div>

                                            <!-- Grand Total Bar -->
                                            <div class="p-2.5 bg-[#1c3633] text-white flex items-center justify-between border-l-4 border-l-[#c08f34]">
                                                <div>
                                                    <span class="text-[9.5px] uppercase tracking-wider text-white/70">Grand Total (Inc. 3% GST)</span>
                                                    <div class="text-base font-bold font-serif text-[#c08f34]">
                                                        ₹{{ Number(action.result.grand_total || 0).toLocaleString('en-IN', { minimumFractionDigits: 2 }) }}
                                                    </div>
                                                </div>
                                                <span class="px-2 py-0.5 bg-white/10 border border-[#c08f34]/40 text-[#c08f34] text-[11px] font-mono font-bold">
                                                    Paid via {{ action.result.payment_mode }}
                                                </span>
                                            </div>

                                            <!-- Action Buttons: View Invoice & Print Bill PDF -->
                                            <div class="flex items-center gap-2 pt-1">
                                                <a
                                                    :href="action.result.view_url"
                                                    target="_blank"
                                                    class="flex-1 py-2 bg-white border border-surface-300 hover:border-[#1c3633] text-[#1c3633] text-xs font-bold flex items-center justify-center gap-1.5 shadow-2xs transition-all hover:bg-surface-50 text-center"
                                                >
                                                    <FileText class="w-3.5 h-3.5 text-[#c08f34]" />
                                                    <span>View Invoice</span>
                                                    <ExternalLink class="w-3 h-3 text-surface-400" />
                                                </a>
                                                <a
                                                    :href="action.result.print_url"
                                                    target="_blank"
                                                    class="flex-1 py-2 bg-[#1c3633] hover:bg-[#254642] text-white text-xs font-bold flex items-center justify-center gap-1.5 shadow-2xs transition-all text-center"
                                                >
                                                    <Printer class="w-3.5 h-3.5 text-[#c08f34]" />
                                                    <span>Print PDF</span>
                                                </a>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Discarded Note -->
                                    <div v-else class="p-2.5 bg-surface-100 text-surface-500 text-xs italic text-center">
                                        Invoice draft was discarded.
                                    </div>
                                </div>

                                <!-- 6. Real Stock / Inventory Check Card -->
                                <div v-if="action.tool === 'check_stock'" class="space-y-0">
                                    <div class="px-3.5 py-2 bg-[#1c3633] text-white flex items-center justify-between border-b-2 border-b-[#c08f34]">
                                        <span class="font-serif text-xs font-bold tracking-wide">Showroom Inventory Stock</span>
                                        <span class="text-[10px] text-[#c08f34] uppercase font-mono">{{ action.result.total_items }} Items</span>
                                    </div>
                                    <div class="p-3 bg-[#fcfaf6] space-y-2">
                                        <div class="grid grid-cols-2 gap-2 text-xs">
                                            <div class="p-2 bg-white border border-surface-200 rounded-sm">
                                                <span class="text-surface-500 text-[10px]">Total In-Stock Items</span>
                                                <p class="font-bold text-surface-900 text-sm">{{ action.result.total_items }} Items</p>
                                            </div>
                                            <div class="p-2 bg-white border border-surface-200 rounded-sm">
                                                <span class="text-surface-500 text-[10px]">Total Net Weight</span>
                                                <p class="font-bold text-surface-900 text-sm">{{ action.result.total_weight }}</p>
                                            </div>
                                            <div class="p-2 bg-white border border-amber-200 rounded-sm">
                                                <span class="text-surface-500 text-[10px]">Gold Stock</span>
                                                <p class="font-semibold text-amber-800">{{ action.result.gold_count }} items ({{ action.result.gold_weight }})</p>
                                            </div>
                                            <div class="p-2 bg-white border border-slate-200 rounded-sm">
                                                <span class="text-surface-500 text-[10px]">Silver Stock</span>
                                                <p class="font-semibold text-slate-700">{{ action.result.silver_count }} items ({{ action.result.silver_weight }})</p>
                                            </div>
                                        </div>

                                        <div v-if="action.result.items && action.result.items.length > 0" class="space-y-1.5 pt-1">
                                            <div class="text-[10px] font-bold text-surface-600 uppercase tracking-wider">Matching Showcase Stock</div>
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
