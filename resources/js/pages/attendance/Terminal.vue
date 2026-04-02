<script setup>
import axios from 'axios';
import { Head } from '@inertiajs/vue3';
import { route } from 'ziggy-js';
import Button from 'primevue/button';
import InputText from 'primevue/inputtext';
import Textarea from 'primevue/textarea';
import Tag from 'primevue/tag';
import { computed, ref } from 'vue';
import { formatIndianDate } from '@/utils/indiaTime';

const props = defineProps({
    reasons: Array,
});

const passcode = ref('');
const notes = ref('');
const loading = ref(false);
const activeUser = ref(null);
const availableActions = ref([]);
const currentState = ref('NOT_CHECKED_IN');
const attendance = ref(null);
const message = ref('');
const error = ref('');

const actionButtons = computed(() => availableActions.value || []);
const todayLabel = formatIndianDate(new Date(), { day: 'numeric', month: 'short', year: 'numeric' });

const stateMeta = computed(() => {
    const map = {
        NOT_CHECKED_IN: { label: 'Not Checked In', severity: 'secondary', helper: 'First tap of the day will check staff in.' },
        IN_STORE: { label: 'In Store', severity: 'success', helper: 'Choose whether the staff member is going out or checking out.' },
        OUT_OF_STORE: { label: 'Out Of Store', severity: 'warn', helper: 'Next tap will bring the staff member back in automatically.' },
        CHECKED_OUT: { label: 'Checked Out', severity: 'danger', helper: 'Attendance is closed for today.' },
    };

    return map[currentState.value] || map.NOT_CHECKED_IN;
});

const primaryActions = computed(() => actionButtons.value.filter((action) => action.value === 'CHECK_OUT'));
const outActions = computed(() => actionButtons.value.filter((action) => action.value !== 'CHECK_OUT'));
const latestEventLabel = computed(() => {
    const latestEvent = attendance.value?.latest_event;

    if (!latestEvent) return 'No activity yet';

    const typeLabelMap = {
        CHECK_IN: 'Checked In',
        CHECK_OUT: 'Checked Out',
        BACK_IN: 'Back In',
        OUT: latestEvent.reason ? `Out for ${String(latestEvent.reason).replaceAll('_', ' ').toLowerCase()}` : 'Out of Store',
    };

    return typeLabelMap[latestEvent.type] || latestEvent.type;
});

const terminalHeadline = computed(() => {
    if (!activeUser.value) {
        return 'Enter staff passcode to continue';
    }

    if (currentState.value === 'IN_STORE') {
        return `${activeUser.value.name} is currently in store`;
    }

    if (currentState.value === 'OUT_OF_STORE') {
        return `${activeUser.value.name} is currently out of store`;
    }

    if (currentState.value === 'CHECKED_OUT') {
        return `${activeUser.value.name} is already checked out`;
    }

    return 'Ready for attendance action';
});

const terminalInstruction = computed(() => {
    if (!activeUser.value) {
        return 'Type the staff passcode, then the terminal will show the correct next action automatically.';
    }

    if (currentState.value === 'IN_STORE') {
        return 'Choose why the staff member is leaving the store, or use check out only if the shift is finished.';
    }

    if (currentState.value === 'OUT_OF_STORE') {
        return 'The next passcode already marks this staff member back in automatically.';
    }

    if (currentState.value === 'CHECKED_OUT') {
        return 'Attendance for today is closed for this staff member.';
    }

    return stateMeta.value.helper;
});

const resetTerminal = () => {
    passcode.value = '';
    notes.value = '';
    activeUser.value = null;
    availableActions.value = [];
    currentState.value = 'NOT_CHECKED_IN';
    attendance.value = null;
};

const resetAll = () => {
    resetTerminal();
    error.value = '';
};

const submitPasscode = async () => {
    loading.value = true;
    error.value = '';
    message.value = '';
    notes.value = '';

    try {
        const response = await axios.post(route('attendance-terminal.identify'), {
            passcode: passcode.value,
        });

        activeUser.value = response.data.user;
        attendance.value = response.data.attendance;
        currentState.value = response.data.state;
        message.value = response.data.message;
        availableActions.value = response.data.available_actions || [];

        if (response.data.completed) {
            resetTerminal();
            message.value = response.data.message;
        }
    } catch (err) {
        error.value = err.response?.data?.message || 'Unable to process attendance.';
    } finally {
        loading.value = false;
    }
};

const submitAction = async (action) => {
    if (!activeUser.value) return;

    loading.value = true;
    error.value = '';

    try {
        const response = await axios.post(route('attendance-terminal.act'), {
            user_id: activeUser.value.id,
            action,
            notes: notes.value || null,
        });

        message.value = response.data.message;
        resetTerminal();
    } catch (err) {
        error.value = err.response?.data?.message || 'Unable to record attendance action.';
    } finally {
        loading.value = false;
    }
};
</script>

<template>
    <Head title="Attendance Terminal" />

    <div class="min-h-screen bg-surface-50 px-4 py-5 md:px-6 md:py-6">
        <div class="mx-auto max-w-4xl space-y-5">
            <div class="card mb-0">
                <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-[0.18em] text-primary-500">Attendance Terminal</p>
                        <h1 class="mt-2 text-2xl font-semibold text-surface-900">Staff Attendance</h1>
                        <p class="mt-2 max-w-2xl text-sm text-surface-500">
                            This screen is only for attendance. Staff enter their passcode, then choose the correct action shown on screen.
                        </p>
                    </div>

                    <div class="grid gap-3 sm:grid-cols-3 lg:w-[400px]">
                        <div class="border border-surface-200 bg-surface-0 px-4 py-3">
                            <p class="text-xs uppercase tracking-wide text-surface-500">Today</p>
                            <p class="mt-1 text-sm font-medium text-surface-900">{{ todayLabel }}</p>
                        </div>
                        <div class="border border-surface-200 bg-surface-0 px-4 py-3">
                            <p class="text-xs uppercase tracking-wide text-surface-500">Step 1</p>
                            <p class="mt-1 text-sm font-medium text-surface-900">Enter passcode</p>
                        </div>
                        <div class="border border-surface-200 bg-surface-0 px-4 py-3">
                            <p class="text-xs uppercase tracking-wide text-surface-500">Step 2</p>
                            <p class="mt-1 text-sm font-medium text-surface-900">Tap action</p>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="border-b border-surface-200 pb-4">
                    <div class="border border-primary-100 bg-primary-50 px-4 py-4">
                        <p class="text-xs font-semibold uppercase tracking-[0.16em] text-primary-600">Current Step</p>
                        <h2 class="mt-2 text-xl font-semibold text-surface-900">{{ terminalHeadline }}</h2>
                        <p class="mt-2 text-sm text-surface-600">{{ terminalInstruction }}</p>
                    </div>
                </div>

                <div class="space-y-5 pt-5">
                    <div class="grid gap-4 lg:grid-cols-[minmax(0,1.2fr)_minmax(320px,0.8fr)]">
                        <div class="space-y-5">
                            <div>
                                <label class="mb-2 block text-sm font-medium text-surface-700">Attendance Passcode</label>
                                <InputText
                                    v-model="passcode"
                                    type="password"
                                    class="w-full"
                                    placeholder="Enter staff passcode"
                                    @keyup.enter="submitPasscode"
                                />
                                <p class="mt-3 text-sm text-surface-500">Use the same passcode for check in, going out, coming back in, and check out.</p>
                            </div>

                            <div class="flex flex-wrap gap-2">
                                <Button label="Continue" icon="pi pi-arrow-right" :loading="loading" :disabled="loading || !passcode" @click="submitPasscode" />
                                <Button label="Reset" severity="secondary" outlined :disabled="loading" @click="resetAll" />
                            </div>
                        </div>

                        <div class="grid gap-3 sm:grid-cols-3 lg:grid-cols-1">
                            <div class="border border-surface-200 bg-surface-50 px-4 py-4">
                                <p class="text-xs uppercase tracking-wide text-surface-500">First Tap</p>
                                <p class="mt-2 text-sm text-surface-700">If staff has not entered today, the terminal checks them in automatically.</p>
                            </div>
                            <div class="border border-surface-200 bg-surface-50 px-4 py-4">
                                <p class="text-xs uppercase tracking-wide text-surface-500">When Going Out</p>
                                <p class="mt-2 text-sm text-surface-700">Choose Lunch, Karigar, Bank, Delivery, Personal, or Other.</p>
                            </div>
                            <div class="border border-surface-200 bg-surface-50 px-4 py-4">
                                <p class="text-xs uppercase tracking-wide text-surface-500">When Returning</p>
                                <p class="mt-2 text-sm text-surface-700">Entering the passcode again marks the staff member back in automatically.</p>
                            </div>
                        </div>
                    </div>

                    <div class="space-y-5">
                        <div v-if="message" class="border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">
                            {{ message }}
                        </div>

                        <div v-if="error" class="border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
                            {{ error }}
                        </div>

                        <div v-if="activeUser" class="overflow-hidden border border-surface-200 bg-surface-50">
                            <div class="flex flex-col gap-4 border-b border-surface-200 px-4 py-4 lg:flex-row lg:items-center lg:justify-between">
                                <div class="min-w-0">
                                    <p class="text-xs uppercase tracking-wide text-surface-500">Staff Member</p>
                                    <div class="mt-2 flex flex-wrap items-center gap-3">
                                        <h2 class="text-xl font-semibold text-surface-900">{{ activeUser.name }}</h2>
                                        <Tag :value="stateMeta.label" :severity="stateMeta.severity" />
                                    </div>
                                    <p v-if="activeUser.designation" class="mt-2 text-sm text-surface-500">{{ activeUser.designation }}</p>
                                    <p class="mt-2 text-sm text-surface-700">{{ stateMeta.helper }}</p>
                                </div>

                                <div class="grid gap-3 sm:grid-cols-2">
                                    <div class="border border-surface-200 bg-white px-4 py-3">
                                        <p class="text-xs uppercase tracking-wide text-surface-500">Check In</p>
                                        <p class="mt-2 text-base font-semibold text-surface-900">{{ attendance?.check_in_at || '—' }}</p>
                                    </div>
                                    <div class="border border-surface-200 bg-white px-4 py-3">
                                        <p class="text-xs uppercase tracking-wide text-surface-500">Latest Activity</p>
                                        <p class="mt-2 text-base font-semibold text-surface-900">{{ latestEventLabel }}</p>
                                        <p v-if="attendance?.latest_event?.event_time" class="mt-1 text-xs text-surface-500">{{ attendance.latest_event.event_time }}</p>
                                    </div>
                                </div>
                            </div>

                            <div class="space-y-5 px-4 py-4">
                                <div v-if="outActions.length">
                                    <p class="mb-1 text-base font-semibold text-surface-900">Choose Going Out Reason</p>
                                    <p class="mb-3 text-sm text-surface-500">Tap the reason that best matches why the staff member is leaving the store right now.</p>
                                    <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-3">
                                        <Button
                                            v-for="action in outActions"
                                            :key="action.value"
                                            :label="action.label"
                                            severity="secondary"
                                            outlined
                                            class="justify-start text-left"
                                            :disabled="loading"
                                            @click="submitAction(action.value)"
                                        />
                                    </div>
                                </div>

                                <div v-if="primaryActions.length">
                                    <p class="mb-1 text-base font-semibold text-surface-900">End Of Day</p>
                                    <p class="mb-3 text-sm text-surface-500">Use this only when the shift is finished and the staff member is leaving for the day.</p>
                                    <div class="grid grid-cols-1 gap-3 sm:max-w-sm">
                                        <Button
                                            v-for="action in primaryActions"
                                            :key="action.value"
                                            :label="action.label"
                                            severity="danger"
                                            outlined
                                            :disabled="loading"
                                            @click="submitAction(action.value)"
                                        />
                                    </div>
                                </div>

                                <div v-if="outActions.length || primaryActions.length">
                                    <label class="mb-2 block text-sm font-medium text-surface-700">Optional Note</label>
                                    <Textarea v-model="notes" rows="2" class="w-full" placeholder="Add optional note for this action" />
                                </div>
                            </div>
                        </div>

                        <div v-else class="border border-dashed border-surface-300 bg-surface-50 px-6 py-10 text-center">
                            <p class="text-base font-semibold text-surface-900">Ready for next staff member</p>
                            <p class="mt-2 text-sm text-surface-500">Enter the attendance passcode above. The terminal will guide the next step automatically.</p>
                        </div>

                        <div class="border border-surface-200 bg-white px-4 py-4">
                            <p class="text-xs uppercase tracking-wide text-surface-500">Available Outside Reasons</p>
                            <div class="mt-3 flex flex-wrap gap-2">
                                <span
                                    v-for="reason in props.reasons"
                                    :key="reason.value"
                                    class="border border-surface-200 bg-surface-50 px-3 py-2 text-sm font-medium text-surface-700"
                                >
                                    {{ reason.label }}
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
