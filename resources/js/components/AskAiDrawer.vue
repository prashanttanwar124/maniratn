<script setup lang="ts">
import { useProductDraftTray } from '@/composables/useProductDraftTray';
import { router, usePage } from '@inertiajs/vue3';
import axios from 'axios';
import { AlertCircle, AlertTriangle, BarChart3, Bot, Calculator, CheckCircle2, Clock, Coins, ExternalLink, FileText, Info, Layers3, Mic, MicOff, PackagePlus, Receipt, RefreshCw, Send, Sparkles, TrendingUp, UserRound, Volume2, VolumeX, Wallet, X, Zap } from 'lucide-vue-next';
import Drawer from 'primevue/drawer';
import Textarea from 'primevue/textarea';
import { computed, nextTick, onMounted, ref, watch } from 'vue';

// Subcomponents
import CustomerKhataCard from './ai/CustomerKhataCard.vue';
import DailyRatesCard from './ai/DailyRatesCard.vue';
import InvoiceHistoryCard from './ai/InvoiceHistoryCard.vue';
import OldGoldCard from './ai/OldGoldCard.vue';
import SalesSummaryCard from './ai/SalesSummaryCard.vue';
import StockCheckCard from './ai/StockCheckCard.vue';
import TaskCard from './ai/TaskCard.vue';
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
    duration?: string;
}

const props = defineProps<{
    visible: boolean;
}>();

const emit = defineEmits(['update:visible']);
const page = usePage();
const { open: openProductDraftTray } = useProductDraftTray();

const isVoiceGloballyEnabled = computed(() => {
    return Boolean((page.props as any).business?.ai_voice_enabled);
});

const inputPrompt = ref('');
const messages = ref<Message[]>([]);
const isLoading = ref(false);
const isLoadingHistory = ref(false);
const historyLoaded = ref(false);
const hasMoreHistory = ref(false);
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
    { label: 'Barcode se bill banao', icon: Receipt, prompt: 'G00001 ka bill Ramesh Sharma 9876543210 ke naam se cash me bana do' },
    { label: '5.5g 22K Gold Ring add karo', icon: PackagePlus, prompt: '5.5g 22K Gold Ring stock mein add kar do' },
    { label: 'Old Gold 12g 18K estimate', icon: Coins, prompt: '12 gram old gold 18k purity ka estimate nikalo' },
    { label: 'Customer khata balance', icon: UserRound, prompt: 'Ramesh ka khata balance aur pending udhar batao' },
    { label: 'Aaj ki counter sale summary', icon: BarChart3, prompt: 'Aaj ki total counter sale report dikhao' },
    { label: 'Vault cash & gold balance', icon: Wallet, prompt: 'Vault me abhi kitna cash aur sona hai?' },
];

const applyQuickPrompt = (prompt: string) => {
    inputPrompt.value = prompt;
    nextTick(() => {
        if (composerRef.value) {
            const el = (composerRef.value.$el as HTMLElement | HTMLTextAreaElement) || composerRef.value;
            const textarea = el instanceof HTMLTextAreaElement ? el : (el.querySelector ? el.querySelector('textarea') : null);
            if (textarea) {
                textarea.focus();
                textarea.setSelectionRange(textarea.value.length, textarea.value.length);
            }
        }
    });
};

const getIndianTime = (): string => {
    try {
        return new Intl.DateTimeFormat('en-IN', {
            timeZone: 'Asia/Kolkata',
            hour: '2-digit',
            minute: '2-digit',
            hour12: true,
        }).format(new Date()).toUpperCase();
    } catch {
        return new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
    }
};

const formatMarkdown = (text: string | null | undefined, isUser = false): string => {
    if (!text) return '';
    // 1. Escape HTML special characters to prevent XSS
    let escaped = text
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');

    const boldClass = isUser ? 'font-bold text-[#1c3633]' : 'font-bold text-surface-900';
    const codeClass = 'bg-amber-50 border border-amber-200/70 font-mono text-[11.5px] px-1.5 py-0.5 text-[#1c3633] font-semibold';

    // 2. Bold: **text** or __text__
    escaped = escaped.replace(/\*\*(.*?)\*\*/g, `<strong class="${boldClass}">$1</strong>`);
    escaped = escaped.replace(/__(.*?)__/g, `<strong class="${boldClass}">$1</strong>`);

    // 3. Italic: *text* or _text_
    escaped = escaped.replace(/(?<!\*)\*(?!\*)(.*?)(?<!\*)\*(?!\*)/g, '<em class="italic opacity-90">$1</em>');

    // 4. Inline code: `code`
    escaped = escaped.replace(/`([^`]+)`/g, `<code class="${codeClass}">$1</code>`);

    return escaped;
};

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

const completedPreviewStatuses = new Set(['IN_STOCK_REAL_DB', 'UPDATED_IN_DATABASE', 'SUPERSEDED']);
const isPendingPreview = (action: ActionItem) =>
    action.result?.is_preview === true
    && !action.result?.is_discarded
    && !action.result?.is_superseded
    && !completedPreviewStatuses.has(action.result?.status);

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
        timestamp: getIndianTime(),
    };

    messages.value.push(userMessage);
    inputPrompt.value = '';
    isLoading.value = true;
    scrollToBottom();

    // Automatically mark all previous unconfirmed drafts as superseded
    messages.value.forEach((m) => {
        if (m.role === 'assistant' && m.actions) {
            m.actions.forEach((a) => {
                if (isPendingPreview(a)) {
                    a.result.is_superseded = true;
                }
            });
        }
    });

    const historyPayload = messages.value
        .slice(-8, -1)
        .filter((m) => m.content && m.content.trim() !== '')
        .map((m) => ({
            role: m.role === 'user' ? 'user' : 'assistant',
            content: m.content.trim(),
        }));

    const startTime = performance.now();
    try {
        const response = await axios.post('/api/ai/copilot/chat', {
            message: textToSend,
            history: historyPayload,
            voice: selectedVoice.value,
            include_audio: isVoiceGloballyEnabled.value && autoVoiceOutput.value,
        });

        const elapsedSeconds = ((performance.now() - startTime) / 1000).toFixed(1);
        const durationText = `${elapsedSeconds}s`;

        const replyText = response.data.reply || 'Action executed successfully.';
        const actions = response.data.actions || [];
        const audioUri = response.data.audio || null;

        const assistantMessage: Message = {
            id: response.data.message_id ? String(response.data.message_id) : (Date.now() + 1).toString(),
            role: 'assistant',
            content: replyText,
            actions: actions,
            audio: audioUri,
            timestamp: getIndianTime(),
            duration: durationText,
        };

        messages.value.push(assistantMessage);
        scrollToBottom();

        if (actions.some((action: ActionItem) => action.tool === 'add_product' && action.result?.is_preview !== false)) {
            openProductDrafts(assistantMessage);
        }

        if (autoVoiceOutput.value && isVoiceGloballyEnabled.value && audioUri) {
            playAudio(audioUri);
        }
    } catch {
        const elapsedSeconds = ((performance.now() - startTime) / 1000).toFixed(1);
        messages.value.push({
            id: (Date.now() + 1).toString(),
            role: 'assistant',
            content: 'Error: AI Hub se connect nahi ho paya. Kripya check karein ki AI server chalu hai.',
            timestamp: getIndianTime(),
            duration: `${elapsedSeconds}s`,
        });
    } finally {
        isLoading.value = false;
        scrollToBottom();
    }
};

const isConfirming = ref<Record<string, boolean>>({});

const latestActionableMsgId = computed(() => {
    for (let i = messages.value.length - 1; i >= 0; i--) {
        const m = messages.value[i];
        if (m.role === 'assistant' && m.actions && m.actions.length > 0) {
            const hasPendingDraft = m.actions.some(isPendingPreview);
            if (hasPendingDraft) {
                return m.id;
            }
        }
    }
    return null;
});

interface ToastNotice {
    id: string;
    type: 'error' | 'warning' | 'success' | 'info';
    title: string;
    message: string;
}
const activeToasts = ref<ToastNotice[]>([]);

const showToast = (message: string, title = 'Notification', type: 'error' | 'warning' | 'success' | 'info' = 'error') => {
    const id = 'toast_' + Date.now() + '_' + Math.random().toString(36).substring(2, 7);
    activeToasts.value.push({ id, type, title, message });
    setTimeout(() => {
        dismissToast(id);
    }, 5500);
};

const dismissToast = (id: string) => {
    activeToasts.value = activeToasts.value.filter((t) => t.id !== id);
};

const productActionsFor = (message: Message) => (message.actions || []).filter((action) => action.tool === 'add_product');

const pendingProductActionsFor = (message: Message) => productActionsFor(message).filter((action) => (
    action.result?.is_preview !== false
    && !action.result?.is_discarded
    && !action.result?.is_superseded
    && action.result?.status !== 'IN_STOCK_REAL_DB'
));

const savedProductCountFor = (message: Message) => productActionsFor(message).reduce(
    (total, action) => total + (action.result?.status === 'IN_STOCK_REAL_DB' ? Number(action.result?.quantity || 1) : 0),
    0,
);

const openProductDrafts = (message: Message) => {
    const actions = pendingProductActionsFor(message);
    if (actions.length === 0) return;
    openProductDraftTray(message.id, actions);
    emit('update:visible', false);
};

const openInvoiceDraft = (url: string) => {
    emit('update:visible', false);
    router.visit(url);
};

const confirmRatesAction = async (action: ActionItem, msgId: string) => {
    const key = `rates_${msgId}`;
    isConfirming.value[key] = true;
    if (action.result) action.result.error_message = null;
    try {
        const payload = { ...action.result, message_id: msgId };
        const res = await axios.post('/api/ai/copilot/confirm-rates', payload);
        if (res.data && res.data.success) {
            action.result = {
                ...action.result,
                ...res.data,
                is_preview: false,
                error_message: null,
                status: 'UPDATED_IN_DATABASE',
            };
            const targetMsg = messages.value.find((m) => m.id === msgId);
            if (targetMsg) {
                targetMsg.content = `Done! Aaj ke live rates database me update ho gaye.`;
            }
            showToast('Live bullion rates database me update ho gaye.', 'Rates Updated', 'success');
            if (isVoiceGloballyEnabled.value && autoVoiceOutput.value) {
                speakText(`Done! Aaj ke live rates update ho gaye.`);
            }
        }
    } catch (err: any) {
        const errorMsg = err.response?.data?.message || 'Error updating rates.';
        if (action.result) action.result.error_message = errorMsg;
        showToast(errorMsg, 'Rates Update Failed', 'error');
        if (isVoiceGloballyEnabled.value && autoVoiceOutput.value) {
            speakText(errorMsg);
        }
    } finally {
        isConfirming.value[key] = false;
    }
};

const discardAction = async (action: ActionItem, msgId?: string) => {
    action.result.is_preview = false;
    action.result.is_discarded = true;
    if (msgId) {
        const targetMsg = messages.value.find((m) => m.id === msgId);
        if (targetMsg) {
            targetMsg.content = 'Action draft discard kar diya gaya.';
        }
        try {
            await axios.post('/api/ai/copilot/discard-action', {
                message_id: msgId,
                action_tool: action.tool,
            });
        } catch (e) {
            console.warn('Failed to sync discard to AI Hub:', e);
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
                const existingIds = new Set(messages.value.map((m) => m.id));
                const newMessages = fetched.filter((m: any) => !existingIds.has(m.id));
                if (newMessages.length > 0) {
                    messages.value = [...newMessages, ...messages.value];
                    nextTick(() => {
                        if (messageContainer.value) {
                            messageContainer.value.scrollTo({ top: 0, behavior: 'smooth' });
                        }
                    });
                }
            } else {
                if (fetched.length > 0) {
                    messages.value = fetched;
                    scrollToBottom();
                }
            }

            hasMoreHistory.value = Boolean(res.data.has_more && fetched.length > 0);
            if (res.data.oldest_id) {
                oldestMessageId.value = String(res.data.oldest_id);
            }
        } else {
            hasMoreHistory.value = false;
        }
    } catch (e) {
        console.warn('Could not load chat history:', e);
        hasMoreHistory.value = false;
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
            timestamp: getIndianTime(),
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
        :modal="true"
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
                        <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg border border-white/10 bg-white/10">
                            <Bot class="h-5 w-5 text-[#e1b65f]" />
                        </div>
                        <div class="min-w-0">
                            <div class="flex items-center gap-2">
                                <span class="truncate text-sm font-semibold tracking-wide text-white">Karat AI</span>
                                <span
                                    class="hidden items-center gap-1 rounded-full border border-emerald-400/30 bg-emerald-400/10 px-2 py-0.5 text-[9px] font-semibold tracking-wide text-emerald-200 uppercase min-[390px]:inline-flex"
                                >
                                    <span class="h-1.5 w-1.5 rounded-full bg-emerald-400"></span>
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
                            class="inline-flex h-9 items-center gap-1.5 rounded-md border px-2.5 text-[11px] font-medium transition-colors"
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
                            class="flex h-8 w-8 items-center justify-center rounded-md border border-white/15 bg-white/10 text-white/80 transition-colors hover:bg-white/20 hover:text-white"
                            aria-label="Clear chat history"
                            title="Clear chat history"
                            @click="showResetConfirm = true"
                        >
                            <RefreshCw class="h-3.5 w-3.5" />
                        </button>

                        <button
                            type="button"
                            class="flex h-8 w-8 items-center justify-center rounded-md border border-white/15 bg-white/10 text-white/80 transition-colors hover:border-red-300/40 hover:bg-red-500/20 hover:text-white"
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

                <!-- 🔔 Luxury Floating Toast Notifications -->
                <TransitionGroup
                    enter-active-class="transform transition ease-out duration-300"
                    enter-from-class="-translate-y-2 opacity-0"
                    enter-to-class="translate-y-0 opacity-100"
                    leave-active-class="transition ease-in duration-200"
                    leave-from-class="opacity-100 scale-100"
                    leave-to-class="opacity-0 scale-95"
                    tag="div"
                    class="absolute top-18 left-3 right-3 z-50 flex flex-col gap-2 pointer-events-none sm:left-5 sm:right-5"
                >
                    <div
                        v-for="toast in activeToasts"
                        :key="toast.id"
                        :class="[
                            'pointer-events-auto flex items-start gap-3 p-3.5 shadow-2xl border border-l-4 font-sans backdrop-blur-md transition-all',
                            toast.type === 'error' ? 'bg-slate-900/95 border-rose-700/80 border-l-rose-500 text-rose-100 shadow-rose-950/40' :
                            toast.type === 'warning' ? 'bg-slate-900/95 border-amber-700/80 border-l-amber-500 text-amber-100 shadow-amber-950/40' :
                            toast.type === 'success' ? 'bg-slate-900/95 border-emerald-700/80 border-l-emerald-500 text-emerald-100 shadow-emerald-950/40' :
                            'bg-slate-900/95 border-slate-700 border-l-cyan-500 text-slate-100'
                        ]"
                    >
                        <div class="shrink-0 mt-0.5">
                            <AlertCircle v-if="toast.type === 'error'" class="w-4 h-4 text-rose-400" />
                            <AlertTriangle v-else-if="toast.type === 'warning'" class="w-4 h-4 text-amber-400" />
                            <CheckCircle2 v-else-if="toast.type === 'success'" class="w-4 h-4 text-emerald-400" />
                            <Info v-else class="w-4 h-4 text-cyan-400" />
                        </div>
                        <div class="flex-1 min-w-0">
                            <p v-if="toast.title" class="text-xs font-bold tracking-wide text-white">{{ toast.title }}</p>
                            <p class="text-xs leading-relaxed text-slate-200 mt-0.5 break-words font-medium">{{ toast.message }}</p>
                        </div>
                        <button
                            type="button"
                            @click="dismissToast(toast.id)"
                            class="shrink-0 text-slate-400 hover:text-white transition-colors p-1 -mr-1 -mt-1"
                        >
                            <X class="w-3.5 h-3.5" />
                        </button>
                    </div>
                </TransitionGroup>

                <div v-if="showResetConfirm" class="flex shrink-0 items-center justify-between gap-3 border-b border-amber-200 bg-amber-50 px-4 py-2.5 sm:px-5" role="alert">
                    <p class="text-[10.5px] leading-4 font-medium text-amber-900">Poori chat history clear karni hai?</p>
                    <div class="flex shrink-0 items-center gap-1.5">
                        <button
                            type="button"
                            class="rounded-md border border-transparent px-2.5 py-1.5 text-[10.5px] font-medium text-surface-600 hover:border-surface-300 hover:bg-white"
                            @click="showResetConfirm = false"
                        >
                            Cancel
                        </button>
                        <button type="button" class="rounded-md border border-[#1c3633] bg-[#1c3633] px-2.5 py-1.5 text-[10.5px] font-semibold text-white hover:bg-[#254642]" @click="resetChat">
                            Clear chat
                        </button>
                    </div>
                </div>

                <div class="flex shrink-0 items-center justify-between gap-3 border-b border-surface-200 bg-white px-4 py-2 sm:px-5">
                    <p class="text-[10.5px] leading-4 text-surface-500">Live ERP data connected · Save hone se pehle aap review karenge</p>
                    <span class="ai-status-pill shrink-0 border border-emerald-300 bg-emerald-50 text-emerald-800">
                        <span class="h-1.5 w-1.5 rounded-full bg-emerald-500"></span>
                        Secure
                    </span>
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
                                    class="group flex min-h-14 items-center justify-between gap-3 border border-surface-200 bg-white px-3 py-2.5 text-left shadow-xs transition-colors hover:border-[#c08f34] hover:bg-amber-50/40 disabled:opacity-50"
                                    @click="applyQuickPrompt(item.prompt)"
                                >
                                    <div class="flex items-center gap-2.5 min-w-0">
                                        <span class="flex h-8 w-8 shrink-0 items-center justify-center bg-[#1c3633] text-[#e1b65f]">
                                            <component :is="item.icon" class="h-4 w-4" />
                                        </span>
                                        <span class="text-[11.5px] leading-4 font-medium text-surface-700 group-hover:text-[#1c3633] truncate">{{ item.label }}</span>
                                    </div>
                                    <span class="text-[9.5px] font-medium text-surface-400 group-hover:text-[#c08f34] shrink-0">Click to edit →</span>
                                </button>
                            </div>
                        </div>

                        <div v-if="!isStarterConversation && hasMoreHistory" class="flex justify-center pb-2">
                            <button
                                type="button"
                                :disabled="isLoadingHistory"
                                class="flex items-center gap-2 rounded-full border border-surface-200 bg-white px-4 py-1.5 text-[11px] font-medium text-surface-700 shadow-xs transition-colors hover:border-[#c08f34] hover:bg-amber-50/60 hover:text-[#1c3633] disabled:opacity-50"
                                @click="fetchChatHistory(true)"
                            >
                                <RefreshCw v-if="isLoadingHistory" class="h-3.5 w-3.5 animate-spin text-[#c08f34]" />
                                <Clock v-else class="h-3.5 w-3.5 text-[#c08f34]" />
                                <span>{{ isLoadingHistory ? 'Pichli chats load ho rahi hain...' : 'Pichli chats dekhein' }}</span>
                            </button>
                        </div>

                        <!-- Chat Messages List -->
                        <div
                            v-for="msg in messages"
                            :key="msg.id"
                            v-show="!isStarterConversation"
                            :class="['w-full my-2 flex', msg.role === 'user' ? 'justify-end' : 'justify-start']"
                        >
                            <!-- 👤 User Message: Luxury Emerald Curved Speech Bubble with Right Avatar -->
                            <div v-if="msg.role === 'user'" class="ml-auto flex max-w-[88%] items-start justify-end gap-2.5">
                                <div class="flex flex-col items-end min-w-0">
                                    <div class="rounded-2xl rounded-tr-xs border border-[#2b4c47] bg-gradient-to-br from-[#1c3633] to-[#142825] px-4 py-2.5 text-[13px] leading-relaxed text-slate-50 shadow-md">
                                        <p class="whitespace-pre-wrap select-text font-normal leading-relaxed text-slate-50">{{ msg.content }}</p>
                                    </div>
                                    <span class="mt-1 pr-1 text-[10px] font-mono font-medium text-surface-400">{{ msg.timestamp }}</span>
                                </div>
                                <div class="mt-1 flex h-8 w-8 shrink-0 items-center justify-center rounded-xl border border-[#2b4c47] bg-[#1c3633] text-[#e5c278] shadow-xs">
                                    <UserRound class="h-4 w-4 text-[#e5c278]" />
                                </div>
                            </div>

                            <!-- ✨ Karat AI Message: Luxury Full-Width Card with Left Avatar -->
                            <div v-else class="w-full flex items-start gap-2.5">
                                <div class="mt-1 flex h-8 w-8 shrink-0 items-center justify-center rounded-xl border border-amber-300/80 bg-gradient-to-br from-amber-50 to-amber-100/60 text-[#b07b24] shadow-xs">
                                    <Sparkles class="h-4 w-4 text-[#b07b24]" />
                                </div>

                                <div class="min-w-0 flex-1 w-full space-y-2">
                                    <div class="w-full rounded-2xl rounded-tl-xs border border-surface-200/90 bg-white px-4 py-3 text-[13px] leading-relaxed text-surface-800 shadow-[0_2px_12px_rgba(15,23,42,0.05)]">
                                        <div class="mb-2 flex items-center justify-between gap-2 border-b border-surface-100 pb-1.5">
                                            <div class="flex items-center gap-1.5">
                                                <span class="text-[11.5px] font-bold tracking-wide text-[#1c3633]">Karat AI</span>
                                                <span class="inline-flex items-center rounded-full bg-amber-50 px-1.5 py-0.2 text-[9px] font-semibold text-amber-800 border border-amber-200/70">Showroom Copilot</span>
                                            </div>
                                            <div class="flex items-center gap-2">
                                                <span
                                                    v-if="msg.duration"
                                                    class="inline-flex items-center gap-1 rounded-full bg-amber-50/80 px-2 py-0.5 text-[9.5px] font-medium text-amber-800 border border-amber-200/80"
                                                    title="Search & AI response time"
                                                >
                                                    <Zap class="h-2.5 w-2.5 text-amber-600 fill-amber-500" />
                                                    <span>{{ msg.duration }}</span>
                                                </span>
                                                <span class="text-[10px] font-mono text-surface-400">{{ msg.timestamp }}</span>
                                            </div>
                                        </div>

                                        <p class="whitespace-pre-wrap select-text leading-relaxed font-normal text-surface-800" v-html="formatMarkdown(msg.content)"></p>

                                        <div v-if="msg.audio && isVoiceGloballyEnabled" class="mt-3 border-t border-surface-100 pt-2">
                                            <button
                                                type="button"
                                                class="inline-flex items-center gap-1.5 rounded-full border border-amber-300/80 bg-amber-50 px-3 py-1 text-[11px] font-medium text-amber-900 shadow-2xs transition-colors hover:bg-amber-100"
                                                @click="playAudio(msg.audio)"
                                            >
                                                <Volume2 class="h-3.5 w-3.5 text-[#b07b24]" />
                                                <span>{{ isSpeaking ? 'Voice chal rahi hai' : 'Voice mein sunein' }}</span>
                                            </button>
                                        </div>
                                    </div>

                                    <!-- ⚡ Dedicated Sub-Component Action Cards (Full 100% Width) -->
                                    <div v-if="msg.actions && msg.actions.length > 0" class="w-full space-y-2">
                                        <template v-for="(action, idx) in msg.actions" :key="idx">
                                        <!-- 1. Billing uses the regular invoice draft screen with a luxury Nora action card -->
                                        <section
                                            v-if="['create_bill', 'create_invoice', 'create_bill_draft'].includes(action.tool) && action.result?.draft_url"
                                            class="erp-ai-card my-3 overflow-hidden rounded-xl border border-l-[3px] border-surface-300 border-l-[#c08f34] bg-white font-sans shadow-xs"
                                            style="font-family: 'Poppins', sans-serif !important"
                                        >
                                            <div class="flex items-center justify-between gap-3 border-b border-surface-200 bg-[#f8f6f0] px-3.5 py-2.5">
                                                <div class="flex items-center gap-2.5 min-w-0">
                                                    <span class="flex h-7 w-7 shrink-0 items-center justify-center rounded-lg bg-[#1c3633] text-[#e5c278] shadow-2xs">
                                                        <FileText class="h-3.5 w-3.5" />
                                                    </span>
                                                    <div class="flex flex-col justify-center min-w-0">
                                                        <p class="!m-0 !p-0 !text-xs font-semibold tracking-wide text-surface-900 !leading-tight truncate">
                                                            {{ action.result.customer_name || 'Customer' }} &bull; {{ action.result.item_count || 1 }} Item{{ (action.result.item_count || 1) > 1 ? 's' : '' }}
                                                        </p>
                                                        <p class="!m-0 !p-0 !text-[10.5px] font-normal text-surface-500 !leading-tight mt-0.5 truncate">
                                                            {{ action.result.item_name || 'Stock item' }} ({{ action.result.barcode }}) &bull; Est: <strong class="text-[#1c3633]">₹{{ Number(action.result.grand_total || 0).toLocaleString('en-IN') }}</strong>
                                                        </p>
                                                    </div>
                                                </div>
                                                <span class="ai-status-pill inline-flex items-center gap-1 rounded-md border border-amber-300 bg-amber-50 px-2 py-0.5 text-[10px] font-semibold text-amber-800 shrink-0">
                                                    <Clock class="h-3 w-3 text-amber-600" /> Draft Ready
                                                </span>
                                            </div>
                                            <a
                                                :href="action.result.draft_url"
                                                class="flex w-full items-center justify-center gap-2 border-t border-amber-200 bg-[#1c3633] hover:bg-[#254642] px-3 py-2.5 text-xs font-semibold text-white transition-colors cursor-pointer"
                                                @click.prevent="openInvoiceDraft(action.result.draft_url)"
                                            >
                                                <ExternalLink class="h-3.5 w-3.5 text-[#e5c278]" />
                                                <span>Open Invoice Draft</span>
                                            </a>
                                        </section>

                                        <!-- 2. Product drafts open globally without leaving the current ERP page -->
                                        <section
                                            v-else-if="action.tool === 'add_product' && idx === msg.actions.findIndex((item) => item.tool === 'add_product')"
                                            class="erp-ai-card my-3 overflow-hidden border border-l-[3px] border-surface-300 border-l-[#c08f34] bg-white font-sans shadow-[0_4px_14px_rgba(15,23,42,0.06)]"
                                            style="font-family: 'Poppins', sans-serif !important"
                                        >
                                            <div class="flex items-center justify-between gap-3 border-b border-surface-200 bg-[#f8f6f0] px-3.5 py-2.5">
                                                <div class="flex items-center gap-2.5 min-w-0">
                                                    <span class="flex h-7 w-7 shrink-0 items-center justify-center bg-[#1c3633] text-[#e5c278]">
                                                        <PackagePlus class="h-3.5 w-3.5" />
                                                    </span>
                                                    <div class="flex flex-col justify-center min-w-0">
                                                        <p class="!m-0 !p-0 !text-xs font-semibold tracking-wide text-surface-900 !leading-tight truncate">
                                                            {{ pendingProductActionsFor(msg).length > 0 ? `${pendingProductActionsFor(msg).length} product draft${pendingProductActionsFor(msg).length > 1 ? 's' : ''} ready` : 'Product stock updated' }}
                                                        </p>
                                                        <p class="!m-0 !p-0 !text-[10.5px] font-normal text-surface-500 !leading-tight mt-0.5">
                                                            {{ pendingProductActionsFor(msg).length > 0 ? 'Current page chhode bina details review aur save karein.' : `${savedProductCountFor(msg)} item(s) stock mein save ho gaye.` }}
                                                        </p>
                                                    </div>
                                                </div>
                                                <div class="flex items-center gap-2 shrink-0">
                                                    <span v-if="pendingProductActionsFor(msg).length === 0" class="ai-status-pill border border-emerald-300 bg-emerald-50 text-emerald-800">
                                                        <CheckCircle2 class="h-3 w-3 text-emerald-600" /> Saved
                                                    </span>
                                                    <a
                                                        href="/products"
                                                        class="ai-action-link"
                                                        title="Open Products Page"
                                                    >
                                                        <ExternalLink class="h-3 w-3 text-[#b07b24]" />
                                                        Catalog
                                                    </a>
                                                </div>
                                            </div>
                                            <button
                                                v-if="pendingProductActionsFor(msg).length > 0"
                                                type="button"
                                                class="flex w-full items-center justify-center gap-2 border-t border-amber-200 bg-[#1c3633] px-3 py-2.5 text-xs font-semibold text-white transition-colors hover:bg-[#254641]"
                                                @click="openProductDrafts(msg)"
                                            >
                                                <Layers3 class="h-3.5 w-3.5 text-[#e5c278]" />
                                                Review in Product Draft Tray
                                            </button>
                                        </section>

                                        <!-- 3. Daily Rates Draft Card -->
                                        <DailyRatesCard
                                            v-else-if="action.tool === 'update_daily_rates'"
                                            :action="action"
                                            :msg-id="msg.id + (msg.actions.length > 1 ? ('_' + idx) : '')"
                                            :is-confirming="Boolean(isConfirming[`rates_${msg.id}_${idx}`] || isConfirming[`rates_${msg.id}`])"
                                            :is-superseded="latestActionableMsgId !== msg.id"
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
                                            class="my-3 overflow-hidden rounded-lg border border-surface-300 border-l-[3px] border-l-[#c08f34] bg-white font-sans shadow-[0_4px_14px_rgba(15,23,42,0.06)]"
                                        >
                                            <div
                                                class="flex items-center justify-between gap-3 border-b border-surface-200 bg-[#f8f6f0] px-3.5 py-2.5"
                                            >
                                                <div class="flex items-center gap-2.5">
                                                    <span class="flex h-7 w-7 shrink-0 items-center justify-center rounded-md bg-[#1c3633] text-[#e5c278]">
                                                        <Coins class="h-3.5 w-3.5" />
                                                    </span>
                                                    <div class="flex flex-col justify-center">
                                                        <p class="!m-0 !p-0 !text-xs font-semibold tracking-wide text-surface-900 !leading-tight">Today's Live Bullion Rates</p>
                                                        <p class="!m-0 !p-0 !text-[10px] font-normal text-surface-500 !leading-tight">Real-time database rates</p>
                                                    </div>
                                                </div>
                                                <span
                                                    v-if="action.result?.gold_24k_per_gm > 0"
                                                    class="ai-status-pill inline-flex items-center gap-1 border border-emerald-300 bg-emerald-50 text-[10px] font-medium text-emerald-800 uppercase"
                                                >
                                                    <span class="h-1.5 w-1.5 rounded-full bg-emerald-500"></span>
                                                    Live
                                                </span>
                                                <span
                                                    v-else
                                                    class="ai-status-pill inline-flex items-center gap-1 border border-amber-300 bg-amber-50 text-[10px] font-medium text-amber-800 uppercase"
                                                >
                                                    <span class="h-1.5 w-1.5 rounded-full bg-amber-500"></span>
                                                    Not Set Today
                                                </span>
                                            </div>
                                            <div
                                                v-if="action.result?.gold_24k_per_gm > 0"
                                                class="grid grid-cols-1 divide-y divide-surface-200 bg-white text-left min-[400px]:grid-cols-3 min-[400px]:divide-x min-[400px]:divide-y-0 min-[400px]:text-center"
                                            >
                                                <div class="bg-surface-50 p-2.5">
                                                    <p class="text-[10px] font-semibold text-amber-800 uppercase">Gold 24K</p>
                                                    <p class="mt-1 font-mono text-sm font-bold text-[#9b6f1e]">₹{{ Number(action.result.gold_24k_per_gm).toLocaleString('en-IN') }}/g</p>
                                                </div>
                                                <div class="bg-surface-50 p-2.5">
                                                    <p class="text-[10px] font-semibold text-amber-800 uppercase">Gold 22K</p>
                                                    <p class="mt-1 font-mono text-sm font-bold text-surface-900">
                                                        ₹{{ Number(action.result.gold_22k_per_gm || (action.result.gold_24k_per_gm * 0.916)).toLocaleString('en-IN', { minimumFractionDigits: 2 }) }}/g
                                                    </p>
                                                </div>
                                                <div class="bg-surface-50 p-2.5">
                                                    <p class="text-[10px] font-semibold text-slate-700 uppercase">Silver (999)</p>
                                                    <p class="mt-1 font-mono text-sm font-bold text-surface-900">
                                                        ₹{{ Number(action.result.silver_per_gm || 0).toLocaleString('en-IN', { minimumFractionDigits: 2 }) }}/g
                                                    </p>
                                                </div>
                                            </div>
                                            <div v-else class="p-3 text-center text-xs text-amber-800 bg-amber-50/70">
                                                Aaj ke market rates update nahi hain. Kripya rates update karein.
                                            </div>
                                        </div>

                                        <!-- 7a. Old Gold Valuation / Buyback Card -->
                                        <OldGoldCard
                                            v-else-if="action.tool === 'calculate_old_gold' || action.tool === 'old_gold_estimate' || action.result?.is_old_gold"
                                            :action="action"
                                        />

                                        <!-- 7b. Estimate Quotation Card (New Finished Jewellery) -->
                                        <div
                                            v-else-if="action.tool === 'calculate_estimate'"
                                            class="my-3 overflow-hidden rounded-lg border border-surface-300 border-l-[3px] border-l-[#c08f34] bg-white font-sans shadow-[0_4px_14px_rgba(15,23,42,0.06)]"
                                        >
                                            <div
                                                class="flex items-center justify-between gap-3 border-b border-surface-200 bg-[#f8f6f0] px-3.5 py-2.5"
                                            >
                                                <div class="flex items-center gap-2.5">
                                                    <span class="flex h-7 w-7 shrink-0 items-center justify-center rounded-md bg-[#1c3633] text-[#e5c278]">
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
                                                <span class="ai-status-pill inline-flex items-center gap-1 border border-amber-300 bg-amber-50 text-[9.5px] font-semibold tracking-wide text-amber-900 uppercase">
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

                                        <!-- 11. Tasks / Showroom Todo Card -->
                                        <TaskCard
                                            v-else-if="action.tool === 'get_tasks' || action.tool === 'create_task' || action.tool === 'tasks'"
                                            :action="action"
                                        />
                                    </template>
                                    </div>
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

                    <!-- 🎙️ 3. Bottom Command Area: Enterprise Luxury Pinned Footer -->
                    <div class="z-10 shrink-0 space-y-2.5 border-t border-surface-200 bg-white px-3.5 py-3 sm:px-5">
                        <!-- Quick Suggestion Chips (Rounded Luxury Capsule Pills - Click to edit before submit) -->
                        <div v-if="!isStarterConversation" class="no-scrollbar flex items-center gap-2 overflow-x-auto py-1 px-0.5">
                            <button
                                v-for="(item, idx) in quickSuggestions"
                                :key="idx"
                                type="button"
                                :disabled="isLoading"
                                class="erp-chip-action flex shrink-0 items-center gap-1.5 border border-surface-200 bg-[#f8f6f0] px-3.5 py-1.5 text-xs font-medium text-surface-700 transition-colors hover:border-[#c08f34] hover:bg-amber-50 hover:text-[#1c3633] disabled:opacity-50"
                                @click="applyQuickPrompt(item.prompt)"
                            >
                                <component :is="item.icon" class="h-3.5 w-3.5 text-[#b07b24]" />
                                <span>{{ item.label }}</span>
                            </button>
                        </div>

                        <div v-if="isListening" class="erp-alert-row flex items-center justify-between border border-red-200 bg-red-50 px-3 py-2 rounded-lg" role="status">
                            <div class="flex items-center gap-2 text-xs font-medium text-red-700">
                                <span class="relative flex h-2 w-2">
                                    <span class="absolute inline-flex h-full w-full animate-ping bg-red-400 opacity-75"></span>
                                    <span class="relative inline-flex h-2 w-2 bg-red-600"></span>
                                </span>
                                <span>Sun raha hoon... Hindi ya English mein boliye</span>
                            </div>
                            <button type="button" class="ml-3 text-xs font-semibold text-red-700 underline-offset-2 hover:underline" @click="toggleListening">Stop</button>
                        </div>

                        <div v-if="speechError || chatError" class="erp-alert-row border border-amber-200 bg-amber-50 px-3 py-2 text-xs leading-5 text-amber-800 rounded-lg" role="alert">
                            {{ speechError || chatError }}
                        </div>

                        <div class="erp-composer border border-surface-300 bg-white p-2.5 shadow-xs transition-colors rounded-xl focus-within:border-[#c08f34] focus-within:ring-2 focus-within:ring-[#c08f34]/15">
                            <Textarea
                                ref="composerRef"
                                v-model="inputPrompt"
                                :auto-resize="true"
                                :rows="1"
                                :disabled="isLoading"
                                placeholder="Jaise: 12g 22K ring ka estimate banao ya stock check karo..."
                                class="karat-composer !max-h-36 !min-h-12 !w-full !resize-none !border-0 !bg-transparent !px-2.5 !py-2 !text-[15px] sm:!text-[16px] !leading-relaxed font-normal text-surface-900 !shadow-none focus:!ring-0 placeholder:!text-slate-500 placeholder:!text-[14.5px] sm:placeholder:!text-[15px] placeholder:!font-normal"
                                aria-label="Ask Karat AI"
                                @keydown.enter.exact.prevent="sendMessage()"
                            />

                            <div class="mt-1.5 flex items-center justify-between gap-2 border-t border-surface-100 pt-2 px-1">
                                <div class="flex items-center gap-2">
                                    <button
                                        type="button"
                                        :disabled="!recognitionSupported || isLoading"
                                        :class="[
                                            'erp-action-button flex h-8 items-center gap-1.5 border px-3 text-xs font-medium transition-colors disabled:cursor-not-allowed disabled:opacity-40 rounded-md',
                                            isListening
                                                ? 'border-red-600 bg-red-600 text-white shadow-xs animate-pulse'
                                                : 'border-surface-200 bg-surface-50 text-surface-700 hover:border-[#c08f34] hover:bg-amber-50',
                                        ]"
                                        :title="recognitionSupported ? 'Voice typing shuru karein' : 'Voice is browser me supported nahi hai'"
                                        @click="toggleListening"
                                    >
                                        <Mic v-if="!isListening" class="h-3.5 w-3.5 text-[#b07b24]" />
                                        <MicOff v-else class="h-3.5 w-3.5 text-white" />
                                        <span>{{ isListening ? 'Listening...' : 'Voice Input' }}</span>
                                    </button>
                                    
                                    <span v-if="isVoiceGloballyEnabled" class="text-[11px] font-medium text-surface-400">Aoede Studio</span>
                                </div>

                                <button
                                    type="button"
                                    :disabled="!inputPrompt.trim() || isLoading"
                                    class="erp-action-button flex h-8 items-center gap-1.5 border border-[#1c3633] bg-[#1c3633] px-4 text-xs font-semibold text-white transition-colors hover:bg-[#254642] disabled:cursor-not-allowed disabled:opacity-40 rounded-md"
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
:deep(.karat-composer),
:deep(textarea.karat-composer),
:deep(.p-textarea.karat-composer) {
    font-size: 15.5px !important;
    line-height: 1.6 !important;
    font-weight: 400 !important;
    color: #0f172a !important;
}
:deep(.karat-composer::placeholder),
:deep(textarea.karat-composer::placeholder),
:deep(.karat-composer textarea::placeholder),
:deep(.p-textarea.karat-composer::placeholder) {
    color: #64748b !important;
    font-size: 14.5px !important;
    line-height: 1.6 !important;
    opacity: 0.95 !important;
    font-weight: 400 !important;
}
</style>
