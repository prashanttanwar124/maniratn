<script setup>
import { logout } from '@/routes';
import { Link, router, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import { useLayout } from '@/layout/composables/layout';
import { formatIndianDate } from '@/utils/indiaTime';

const { toggleMenu } = useLayout();
const emit = defineEmits(['openAskAi']);
const page = usePage();
const pageUser = computed(() => page.props.auth?.user);
const role = computed(() => page.props.auth?.role || 'user');
const dayStatus = computed(() => page.props.dayStatus ?? { is_open: true });
const currentPath = computed(() => String(page.url || '/').split('?')[0]);

const rates = computed(() => page.props.rates ?? { gold_sell: 0, silver_sell: 0 });

const currentDate = computed(() =>
    formatIndianDate(new Date(), {
        day: '2-digit',
        month: 'short',
        year: 'numeric',
    }),
);

const initials = computed(() => {
    const name = pageUser.value?.name || 'User';
    return name
        .split(' ')
        .filter(Boolean)
        .slice(0, 2)
        .map((part) => part[0]?.toUpperCase())
        .join('');
});

const roleLabel = computed(() => {
    const value = String(role.value || 'user');
    return value.charAt(0).toUpperCase() + value.slice(1);
});

const workspaceLabel = computed(() => {
    const path = currentPath.value;

    if (path === '/dashboard') return 'Dashboard';
    if (path.startsWith('/invoices')) return 'Sales & Billing';
    if (path.startsWith('/orders')) return 'Orders';
    if (path.startsWith('/customers')) return 'Customers';
    if (path.startsWith('/products')) return 'Products';
    if (path.startsWith('/purities')) return 'Purities';
    if (path.startsWith('/suppliers')) return 'Suppliers';
    if (path.startsWith('/karigars')) return 'Karigars';
    if (path.startsWith('/expenses')) return 'Expenses';
    if (path.startsWith('/mortgages')) return 'Mortgages';
    if (path.startsWith('/users')) return 'User Management';
    if (path.includes('/ledger/')) return 'Ledger';
    if (path.startsWith('/settings')) return 'Settings';

    return 'Workspace';
});

const submitLogout = () => {
    router.post(logout.url());
};
</script>

<template>
    <div class="layout-topbar">
        <div class="layout-topbar-start">
            <button class="layout-menu-button layout-topbar-action" @click="toggleMenu" aria-label="Toggle sidebar" title="Toggle Navigation">
                <i class="pi pi-bars"></i>
            </button>

            <a href="/dashboard" class="layout-topbar-brand flex items-center gap-3 group">
                <img src="/logo-mark.png" alt="KaratSetu" class="h-10 w-auto object-contain flex-shrink-0 transition-transform group-hover:scale-105" />
                <span class="layout-topbar-brand-copy flex flex-col justify-center">
                    <span class="text-[1.3rem] font-bold leading-none tracking-tight text-[#1c3633]">
                        Karat<span class="text-[#c08f34]">Setu</span>
                    </span>
                    <span class="mt-1 text-[0.66rem] font-bold uppercase tracking-[0.2em] text-[#1c3633]/60 leading-none hidden sm:block">
                        Jewellery ERP
                    </span>
                </span>
            </a>
        </div>

        <div class="layout-topbar-center">
            <div class="layout-topbar-workspace">
                <span class="layout-topbar-workspace-label">Current</span>
                <span class="layout-topbar-workspace-value">{{ workspaceLabel }}</span>
            </div>

            <!-- Live Rates Ticker -->
            <div v-if="rates?.gold_sell > 0" class="hidden xl:inline-flex items-center gap-2.5 px-3 py-1 bg-amber-50/80 border border-amber-200 text-xs text-amber-950">
                <div class="flex items-center gap-1.5 font-bold">
                    <span class="h-1.5 w-1.5 rounded-full bg-amber-500 animate-pulse"></span>
                    <span class="text-[9px] uppercase tracking-wider text-amber-700 font-bold">24K Gold</span>
                    <span>₹{{ Number(rates.gold_sell).toLocaleString('en-IN') }}/g</span>
                </div>
                <span class="text-amber-300">|</span>
                <div class="flex items-center gap-1 text-surface-600">
                    <span class="text-[9px] uppercase tracking-wider text-surface-500 font-bold">Silver</span>
                    <span class="font-semibold text-surface-800">₹{{ Number(rates.silver_sell).toLocaleString('en-IN') }}/g</span>
                </div>
            </div>

            <div class="layout-topbar-date hidden lg:inline-flex">
                <i class="pi pi-calendar text-xs text-surface-500"></i>
                <span>{{ currentDate }}</span>
            </div>
        </div>

        <div class="layout-topbar-actions">
            <button type="button" class="layout-topbar-utility layout-topbar-ai" title="Ask AI" @click="emit('openAskAi')">
                <i class="pi pi-sparkles text-[#c08f34]"></i>
                <span class="hidden md:inline">Ask AI</span>
            </button>

            <div class="layout-topbar-status" :class="dayStatus.is_open ? 'layout-topbar-status-open' : 'layout-topbar-status-closed'">
                <span class="layout-topbar-status-dot"></span>
                <span>{{ dayStatus.is_open ? 'Day Open' : 'Day Closed' }}</span>
            </div>

            <div class="layout-topbar-user">
                <div class="layout-topbar-user-badge">{{ initials }}</div>
                <div class="layout-topbar-user-meta">
                    <span class="layout-topbar-user-name">{{ pageUser?.name || 'User' }}</span>
                    <span class="layout-topbar-user-role">{{ roleLabel }}</span>
                </div>
            </div>

            <Link href="/settings/profile" class="layout-topbar-utility" title="Profile">
                <i class="pi pi-user"></i>
                <span class="hidden md:inline">Profile</span>
            </Link>

            <button type="button" class="layout-topbar-utility layout-topbar-logout" title="Log out" @click="submitLogout">
                <i class="pi pi-sign-out"></i>
                <span class="hidden md:inline">Logout</span>
            </button>
        </div>
    </div>
</template>
