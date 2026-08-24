<script setup lang="ts">
import axios from 'axios';
import {
    Bot,
    Clock,
    Coins,
    Mic,
    MicOff,
    PackagePlus,
    RefreshCw,
    Send,
    Sparkles,
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

    const historyPayload = messages.value.slice(-6, -1).map((m) => ({
        role: m.role === 'user' ? 'user' : 'assistant',
        content: m.content,
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

                                <!-- 1b. Rates Updated Card -->
                                <div v-else-if="action.tool === 'update_daily_rates'" class="grid grid-cols-2 gap-3 pt-1">
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

                                <!-- 2. Product Added Real Barcode Card -->
                                <div v-else-if="action.tool === 'add_product'" class="p-3.5 bg-[#fcfaf6] border border-[#e8dfcf] space-y-2.5">
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
