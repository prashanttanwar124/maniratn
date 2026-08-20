<script setup>
import { logout } from '@/routes';
import { Link, router, usePage } from '@inertiajs/vue3';
import { computed, ref, onMounted, onUnmounted, watch, nextTick } from 'vue';
import { useLayout } from '@/layout/composables/layout';
import { formatIndianDate } from '@/utils/indiaTime';

const { toggleMenu } = useLayout();
const emit = defineEmits(['openAskAi']);
const page = usePage();

const pageUser = computed(() => page.props.auth?.user);
const role = computed(() => page.props.auth?.role || 'user');
const dayStatus = computed(() => page.props.dayStatus ?? { is_open: true });
const currentPath = computed(() => String(page.url || '/').split('?')[0]);
const rates = computed(() => page.props.rates ?? { gold_sell: 0, gold_buy: 0, silver_sell: 0 });

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

// Dropdowns State
const isUserMenuOpen = ref(false);
const isQuickCreateOpen = ref(false);
const isRatesModalOpen = ref(false);

// Rate Update Form
const rateForm = ref({
    gold_sell: rates.value.gold_sell || '',
    gold_buy: rates.value.gold_buy || '',
    silver_sell: rates.value.silver_sell || '',
});
const rateUpdating = ref(false);

watch(rates, (newRates) => {
    rateForm.value = {
        gold_sell: newRates.gold_sell || '',
        gold_buy: newRates.gold_buy || '',
        silver_sell: newRates.silver_sell || '',
    };
}, { immediate: true });

const openRatesModal = () => {
    rateForm.value = {
        gold_sell: rates.value.gold_sell || '',
        gold_buy: rates.value.gold_buy || '',
        silver_sell: rates.value.silver_sell || '',
    };
    isRatesModalOpen.value = true;
};

const saveRates = () => {
    rateUpdating.value = true;
    router.post('/dashboard/update-rates', rateForm.value, {
        preserveScroll: true,
        onSuccess: () => {
            isRatesModalOpen.value = false;
        },
        onFinish: () => {
            rateUpdating.value = false;
        },
    });
};

// Spotlight Omnisearch State
const isSpotlightOpen = ref(false);
const searchQuery = ref('');
const searchInputRef = ref(null);
const searchResults = ref({ customers: [], invoices: [], products: [] });
const isSearching = ref(false);
let searchTimeout = null;

const openSpotlight = () => {
    isSpotlightOpen.value = true;
    searchQuery.value = '';
    searchResults.value = { customers: [], invoices: [], products: [] };
    nextTick(() => {
        searchInputRef.value?.focus();
    });
};

const closeSpotlight = () => {
    isSpotlightOpen.value = false;
};

const performSearch = () => {
    if (searchTimeout) clearTimeout(searchTimeout);
    const q = searchQuery.value.trim();
    if (!q) {
        searchResults.value = { customers: [], invoices: [], products: [] };
        isSearching.value = false;
        return;
    }

    isSearching.value = true;
    searchTimeout = setTimeout(async () => {
        try {
            const res = await fetch(`/api/omnisearch?q=${encodeURIComponent(q)}`);
            if (res.ok) {
                searchResults.value = await res.json();
            }
        } catch (e) {
            console.error(e);
        } finally {
            isSearching.value = false;
        }
    }, 200);
};

// Quick Navigation Links for Spotlight
const quickLinks = [
    { title: 'New Invoice (F2)', url: '/invoices/create', icon: 'pi pi-file-edit', desc: 'Create retail jewellery tax bill' },
    { title: 'Customers Directory', url: '/customers', icon: 'pi pi-users', desc: 'Manage customer ledger & KYC' },
    { title: 'Product Inventory', url: '/products', icon: 'pi pi-box', desc: 'Gold & silver stock catalog' },
    { title: 'Karigars & Jobwork', url: '/karigars', icon: 'pi pi-wrench', desc: 'Artisans & manufacturing orders' },
    { title: 'Suppliers & Purchases', url: '/suppliers', icon: 'pi pi-truck', desc: 'Bullion vendors & purchases' },
    { title: 'Daily Expenses', url: '/expenses', icon: 'pi pi-wallet', desc: 'Petty cash & operational expenses' },
];

const handleKeyDown = (e) => {
    const activeEl = document.activeElement;
    const isEditing = activeEl && ['INPUT', 'TEXTAREA', 'SELECT'].includes(activeEl.tagName);

    // ⌘K or Ctrl+K for Spotlight Search
    if ((e.metaKey || e.ctrlKey) && e.key.toLowerCase() === 'k') {
        e.preventDefault();
        if (isSpotlightOpen.value) {
            closeSpotlight();
        } else {
            openSpotlight();
        }
        return;
    }

    // F2 for New Invoice - only when NOT actively typing in an input
    if (e.key === 'F2' && !isEditing) {
        e.preventDefault();
        router.visit('/invoices/create');
        return;
    }

    // ESC to close modals/menus
    if (e.key === 'Escape') {
        isSpotlightOpen.value = false;
        isRatesModalOpen.value = false;
        isUserMenuOpen.value = false;
        isQuickCreateOpen.value = false;
    }
};

const closeAllDropdowns = (e) => {
    if (!e.target.closest('.user-menu-wrapper')) isUserMenuOpen.value = false;
    if (!e.target.closest('.quick-create-wrapper')) isQuickCreateOpen.value = false;
};

onMounted(() => {
    window.addEventListener('keydown', handleKeyDown);
    window.addEventListener('click', closeAllDropdowns);
});

onUnmounted(() => {
    if (searchTimeout) clearTimeout(searchTimeout);
    window.removeEventListener('keydown', handleKeyDown);
    window.removeEventListener('click', closeAllDropdowns);
});

const submitLogout = () => {
    router.post(logout.url());
};
</script>

<template>
    <div class="layout-topbar">
        <!-- 1. LEFT: Brand & Navigation Toggle -->
        <div class="layout-topbar-start">
            <button class="layout-menu-button layout-topbar-action" @click="toggleMenu" aria-label="Toggle sidebar" title="Toggle Navigation Menu">
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

        <!-- 2. CENTER: Spotlight Search & Live Bullion Rates -->
        <div class="layout-topbar-center">
            <!-- ⚡ Spotlight Search Bar Trigger -->
            <button
                type="button"
                @click="openSpotlight"
                class="hidden md:inline-flex items-center gap-2.5 px-3.5 py-1.5 bg-[#f6f8f8] hover:bg-[#ebf0ee] border border-surface-200 hover:border-[#1c3633]/30 transition-all text-xs text-surface-600 rounded-none w-64 lg:w-80 group cursor-pointer"
                title="Press ⌘K or Ctrl+K to Search"
            >
                <i class="pi pi-search text-xs text-surface-400 group-hover:text-[#1c3633]"></i>
                <span class="flex-1 text-left truncate text-surface-500 font-medium">Search bill, customer, tag...</span>
                <kbd class="px-1.5 py-0.5 bg-white border border-surface-200 text-[10px] font-mono text-surface-500 font-semibold shadow-xs">⌘K</kbd>
            </button>

            <!-- 🪙 Live Rates Ticker (Clickable to Quick-Update) -->
            <button
                type="button"
                @click="openRatesModal"
                class="hidden xl:inline-flex items-center gap-2.5 px-3 py-1.5 bg-amber-50/90 hover:bg-amber-100/90 border border-amber-200/80 transition-all text-xs text-amber-950 cursor-pointer group"
                title="Click to Update Today's Gold/Silver Rates"
            >
                <div class="flex items-center gap-1.5 font-bold">
                    <span class="h-2 w-2 rounded-full bg-amber-500 animate-pulse"></span>
                    <span class="text-[9px] uppercase tracking-wider text-amber-700 font-bold">24K Gold</span>
                    <span>₹{{ rates?.gold_sell ? Number(rates.gold_sell).toLocaleString('en-IN') : 'Set Rate' }}/g</span>
                </div>
                <span class="text-amber-300">|</span>
                <div class="flex items-center gap-1 text-surface-600">
                    <span class="text-[9px] uppercase tracking-wider text-surface-500 font-bold">Silver</span>
                    <span class="font-semibold text-surface-800">₹{{ rates?.silver_sell ? Number(rates.silver_sell).toLocaleString('en-IN') : '0' }}/g</span>
                </div>
                <i class="pi pi-pencil text-[10px] text-amber-600 opacity-60 group-hover:opacity-100 transition-opacity ml-0.5"></i>
            </button>
        </div>

        <!-- 3. RIGHT: Actions, Quick Create, AI, Status & User Menu -->
        <div class="layout-topbar-actions">
            <!-- ➕ Quick Create Button with Dropdown -->
            <div class="relative quick-create-wrapper">
                <div class="inline-flex items-center shadow-xs">
                    <Link
                        href="/invoices/create"
                        class="inline-flex items-center gap-1.5 h-10 px-3.5 bg-[#c08f34] hover:bg-[#a67a26] text-white text-xs font-bold transition-all cursor-pointer border-r border-[#a67a26]"
                        title="Create New Jewellery Bill (Shortcut: F2)"
                    >
                        <i class="pi pi-plus text-xs text-white"></i>
                        <span class="hidden sm:inline font-semibold">New Bill</span>
                        <kbd class="hidden lg:inline px-1 py-0.2 bg-black/20 text-[9px] text-white/90 font-mono font-normal">F2</kbd>
                    </Link>
                    <button
                        type="button"
                        @click.stop="isQuickCreateOpen = !isQuickCreateOpen"
                        class="h-10 px-2 bg-[#b58428] hover:bg-[#996e1d] text-white transition-all cursor-pointer"
                        title="More Quick Actions"
                    >
                        <i class="pi pi-chevron-down text-[10px]"></i>
                    </button>
                </div>

                <!-- Quick Create Dropdown Menu -->
                <div
                    v-if="isQuickCreateOpen"
                    class="absolute right-0 mt-1 w-48 bg-white border border-surface-200 shadow-xl z-50 py-1.5 divide-y divide-surface-100 animate-in fade-in zoom-in-95 duration-100"
                >
                    <div class="py-1">
                        <Link href="/invoices/create" class="flex items-center gap-2.5 px-3.5 py-2 text-xs font-medium text-[#1c3633] hover:bg-[#f2f6f5]">
                            <i class="pi pi-receipt text-[#c08f34]"></i>
                            <span>New Tax Invoice</span>
                        </Link>
                        <Link href="/customers" class="flex items-center gap-2.5 px-3.5 py-2 text-xs font-medium text-surface-700 hover:bg-[#f2f6f5]">
                            <i class="pi pi-user-plus text-surface-500"></i>
                            <span>Add Customer</span>
                        </Link>
                        <Link href="/orders" class="flex items-center gap-2.5 px-3.5 py-2 text-xs font-medium text-surface-700 hover:bg-[#f2f6f5]">
                            <i class="pi pi-shopping-bag text-surface-500"></i>
                            <span>New Custom Order</span>
                        </Link>
                        <Link href="/expenses" class="flex items-center gap-2.5 px-3.5 py-2 text-xs font-medium text-surface-700 hover:bg-[#f2f6f5]">
                            <i class="pi pi-wallet text-surface-500"></i>
                            <span>Record Expense</span>
                        </Link>
                    </div>
                </div>
            </div>

            <!-- ✨ Karat AI Copilot -->
            <button
                type="button"
                class="layout-topbar-utility layout-topbar-ai"
                title="Karat AI Assistant"
                @click="emit('openAskAi')"
            >
                <i class="pi pi-sparkles text-[#c08f34]"></i>
                <span class="hidden md:inline font-bold">Ask AI</span>
            </button>

            <!-- 🟢 Day Status Indicator Pill -->
            <div
                class="layout-topbar-status"
                :class="dayStatus.is_open ? 'layout-topbar-status-open' : 'layout-topbar-status-closed'"
                :title="dayStatus.is_open ? 'Store Register is Open' : 'Store Register is Closed'"
            >
                <span class="layout-topbar-status-dot" :class="dayStatus.is_open ? 'animate-pulse' : ''"></span>
                <span class="hidden sm:inline">{{ dayStatus.is_open ? 'Day Open' : 'Day Closed' }}</span>
            </div>

            <!-- 👤 Executive User Profile Dropdown -->
            <div class="relative user-menu-wrapper">
                <button
                    type="button"
                    @click.stop="isUserMenuOpen = !isUserMenuOpen"
                    class="layout-topbar-user cursor-pointer group"
                    title="Account & Settings"
                >
                    <div class="layout-topbar-user-badge">{{ initials }}</div>
                    <div class="layout-topbar-user-meta hidden sm:flex">
                        <span class="layout-topbar-user-name">{{ pageUser?.name || 'User' }}</span>
                        <span class="layout-topbar-user-role">{{ roleLabel }}</span>
                    </div>
                    <i class="pi pi-chevron-down text-[10px] text-surface-400 group-hover:text-[#1c3633] transition-transform" :class="isUserMenuOpen ? 'rotate-180' : ''"></i>
                </button>

                <!-- User Dropdown Menu -->
                <div
                    v-if="isUserMenuOpen"
                    class="absolute right-0 mt-1 w-56 bg-white border border-surface-200 shadow-xl z-50 py-2 divide-y divide-surface-100 animate-in fade-in zoom-in-95 duration-100"
                >
                    <div class="px-4 py-2">
                        <p class="text-xs font-bold text-[#1c3633]">{{ pageUser?.name }}</p>
                        <p class="text-[11px] text-surface-500 truncate">{{ pageUser?.email }}</p>
                        <span class="inline-block mt-1 px-1.5 py-0.5 bg-surface-100 text-[10px] font-semibold text-surface-700 uppercase tracking-wider">{{ roleLabel }}</span>
                    </div>

                    <div class="py-1">
                        <Link href="/settings/profile" class="flex items-center gap-2.5 px-4 py-2 text-xs text-surface-700 hover:bg-[#f2f6f5]">
                            <i class="pi pi-user text-surface-500"></i>
                            <span>My Profile</span>
                        </Link>
                        <Link href="/settings" class="flex items-center gap-2.5 px-4 py-2 text-xs text-surface-700 hover:bg-[#f2f6f5]">
                            <i class="pi pi-cog text-surface-500"></i>
                            <span>Store Settings</span>
                        </Link>
                    </div>

                    <div class="py-1">
                        <button
                            type="button"
                            @click="submitLogout"
                            class="w-full flex items-center gap-2.5 px-4 py-2 text-xs text-red-600 hover:bg-red-50 text-left cursor-pointer font-medium"
                        >
                            <i class="pi pi-sign-out text-red-500"></i>
                            <span>Log Out</span>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- ⚡ SPOTLIGHT OMNISEARCH MODAL (⌘K) -->
    <div
        v-if="isSpotlightOpen"
        class="fixed inset-0 z-[9999] flex items-start justify-center pt-16 sm:pt-24 px-4 bg-black/50 backdrop-blur-xs animate-in fade-in duration-150"
        @click.self="closeSpotlight"
    >
        <div class="w-full max-w-2xl bg-white border border-surface-300 shadow-2xl overflow-hidden flex flex-col max-h-[80vh]">
            <!-- Search Header Input -->
            <div class="flex items-center gap-3 px-4 py-3.5 border-b border-surface-200 bg-white">
                <i class="pi pi-search text-base text-[#c08f34]"></i>
                <input
                    ref="searchInputRef"
                    v-model="searchQuery"
                    @input="performSearch"
                    type="text"
                    placeholder="Search invoices, customer phone, product barcode (e.g. G0001)..."
                    class="flex-1 text-sm bg-transparent border-none outline-hidden text-[#1c3633] placeholder:text-surface-400 font-medium"
                />
                <button
                    type="button"
                    @click="closeSpotlight"
                    class="px-2 py-1 text-[11px] font-mono text-surface-500 hover:text-surface-900 bg-surface-100 border border-surface-200"
                >
                    ESC
                </button>
            </div>

            <!-- Search Results Body -->
            <div class="flex-1 overflow-y-auto p-4 space-y-4 max-h-96">
                <!-- Searching Spinner -->
                <div v-if="isSearching" class="flex items-center justify-center py-8 text-surface-400 text-xs gap-2">
                    <i class="pi pi-spin pi-spinner"></i>
                    <span>Searching KaratSetu database...</span>
                </div>

                <!-- Live Results -->
                <template v-else-if="searchQuery.trim()">
                    <!-- Customers -->
                    <div v-if="searchResults.customers?.length" class="space-y-1">
                        <span class="text-[10px] font-bold uppercase tracking-wider text-surface-400 px-2">Customers</span>
                        <div class="divide-y divide-surface-100">
                            <a
                                v-for="c in searchResults.customers"
                                :key="c.id"
                                :href="`/customers/${c.id}`"
                                class="flex items-center justify-between p-2.5 hover:bg-[#f4f7f6] transition-colors group"
                            >
                                <div class="flex items-center gap-2.5">
                                    <div class="h-7 w-7 rounded-full bg-[#1c3633]/10 text-[#1c3633] flex items-center justify-center text-xs font-bold">
                                        {{ c.name.charAt(0) }}
                                    </div>
                                    <div>
                                        <p class="text-xs font-bold text-[#1c3633] group-hover:text-[#c08f34]">{{ c.name }}</p>
                                        <p class="text-[11px] text-surface-500">{{ c.phone }} {{ c.city ? '• ' + c.city : '' }}</p>
                                    </div>
                                </div>
                                <i class="pi pi-arrow-right text-xs text-surface-300 group-hover:text-[#1c3633]"></i>
                            </a>
                        </div>
                    </div>

                    <!-- Invoices -->
                    <div v-if="searchResults.invoices?.length" class="space-y-1">
                        <span class="text-[10px] font-bold uppercase tracking-wider text-surface-400 px-2">Invoices & Bills</span>
                        <div class="divide-y divide-surface-100">
                            <a
                                v-for="inv in searchResults.invoices"
                                :key="inv.id"
                                :href="`/invoices/${inv.id}/print`"
                                class="flex items-center justify-between p-2.5 hover:bg-[#f4f7f6] transition-colors group"
                            >
                                <div class="flex items-center gap-2.5">
                                    <div class="h-7 w-7 rounded-full bg-amber-50 text-amber-800 flex items-center justify-center text-xs font-bold">
                                        <i class="pi pi-receipt text-xs"></i>
                                    </div>
                                    <div>
                                        <p class="text-xs font-bold text-[#1c3633] group-hover:text-[#c08f34]">Bill #{{ inv.invoice_number }}</p>
                                        <p class="text-[11px] text-surface-500">{{ inv.customer?.name || 'Walk-in' }} • ₹{{ Number(inv.total_amount).toLocaleString('en-IN') }}</p>
                                    </div>
                                </div>
                                <span class="text-[11px] font-bold text-emerald-700 bg-emerald-50 px-2 py-0.5 border border-emerald-200">View Bill</span>
                            </a>
                        </div>
                    </div>

                    <!-- Products -->
                    <div v-if="searchResults.products?.length" class="space-y-1">
                        <span class="text-[10px] font-bold uppercase tracking-wider text-surface-400 px-2">Inventory & Barcodes</span>
                        <div class="divide-y divide-surface-100">
                            <a
                                v-for="p in searchResults.products"
                                :key="p.id"
                                :href="`/products`"
                                class="flex items-center justify-between p-2.5 hover:bg-[#f4f7f6] transition-colors group"
                            >
                                <div class="flex items-center gap-2.5">
                                    <div class="h-7 w-7 rounded-full bg-zinc-100 text-zinc-700 flex items-center justify-center text-xs font-mono font-bold">
                                        🏷️
                                    </div>
                                    <div>
                                        <p class="text-xs font-bold text-[#1c3633] group-hover:text-[#c08f34]">{{ p.name }}</p>
                                        <p class="text-[11px] font-mono text-surface-500">Tag: {{ p.barcode }} • {{ p.gross_weight }}g</p>
                                    </div>
                                </div>
                                <span class="text-[10px] font-mono font-bold bg-surface-100 px-2 py-0.5 text-surface-700">{{ p.barcode }}</span>
                            </a>
                        </div>
                    </div>

                    <!-- Empty Results -->
                    <div
                        v-if="!searchResults.customers?.length && !searchResults.invoices?.length && !searchResults.products?.length"
                        class="py-8 text-center text-surface-400 text-xs"
                    >
                        <i class="pi pi-search text-2xl mb-2 block text-surface-300"></i>
                        No matching customers, invoices, or products found for "{{ searchQuery }}"
                    </div>
                </template>

                <!-- Default Quick Jump Links -->
                <div v-else class="space-y-2">
                    <span class="text-[10px] font-bold uppercase tracking-wider text-surface-400 px-2">Quick Store Actions</span>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
                        <a
                            v-for="item in quickLinks"
                            :key="item.title"
                            :href="item.url"
                            class="flex items-start gap-3 p-3 bg-surface-50 hover:bg-[#eef4f2] border border-surface-200 hover:border-[#1c3633]/30 transition-all group"
                        >
                            <i :class="item.icon" class="text-sm text-[#1c3633] mt-0.5 group-hover:text-[#c08f34]"></i>
                            <div>
                                <p class="text-xs font-bold text-[#1c3633]">{{ item.title }}</p>
                                <p class="text-[11px] text-surface-500 leading-tight">{{ item.desc }}</p>
                            </div>
                        </a>
                    </div>
                </div>
            </div>

            <!-- Modal Footer -->
            <div class="px-4 py-2.5 bg-surface-50 border-t border-surface-200 flex items-center justify-between text-[11px] text-surface-500">
                <div class="flex items-center gap-3">
                    <span><kbd class="px-1 py-0.5 bg-white border border-surface-200 text-[9px] font-mono">F2</kbd> New Invoice</span>
                    <span><kbd class="px-1 py-0.5 bg-white border border-surface-200 text-[9px] font-mono">ESC</kbd> Close</span>
                </div>
                <span class="text-[10px] font-semibold text-[#1c3633]/60">KaratSetu Omnisearch</span>
            </div>
        </div>
    </div>

    <!-- 🪙 QUICK UPDATE BULLION RATES MODAL -->
    <div
        v-if="isRatesModalOpen"
        class="fixed inset-0 z-[9999] flex items-center justify-center p-4 bg-black/50 backdrop-blur-xs animate-in fade-in duration-150"
        @click.self="isRatesModalOpen = false"
    >
        <div class="w-full max-w-md bg-white border border-surface-300 shadow-2xl p-6 space-y-4">
            <div class="flex items-center justify-between border-b border-surface-100 pb-3">
                <div class="flex items-center gap-2">
                    <span class="text-lg">🪙</span>
                    <div>
                        <h3 class="text-sm font-bold text-[#1c3633]">Today's Market Bullion Rates</h3>
                        <p class="text-[11px] text-surface-500">Live base prices used across sales billing & valuations</p>
                    </div>
                </div>
                <button type="button" @click="isRatesModalOpen = false" class="text-surface-400 hover:text-surface-700">
                    <i class="pi pi-times"></i>
                </button>
            </div>

            <form @submit.prevent="saveRates" class="space-y-4">
                <div class="space-y-1">
                    <label class="text-xs font-bold text-surface-700">24K Gold Sell Rate (₹/gram)</label>
                    <div class="relative">
                        <span class="absolute left-3 top-2.5 text-xs text-surface-400 font-bold">₹</span>
                        <input
                            v-model="rateForm.gold_sell"
                            type="number"
                            step="0.01"
                            required
                            placeholder="e.g. 7160.00"
                            class="w-full pl-7 pr-3 py-2 border border-surface-300 text-sm font-bold text-[#1c3633] outline-hidden focus:border-[#1c3633]"
                        />
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div class="space-y-1">
                        <label class="text-xs font-bold text-surface-700">Gold Buy Rate (₹/g)</label>
                        <div class="relative">
                            <span class="absolute left-3 top-2.5 text-xs text-surface-400 font-bold">₹</span>
                            <input
                                v-model="rateForm.gold_buy"
                                type="number"
                                step="0.01"
                                required
                                placeholder="e.g. 7010.00"
                                class="w-full pl-7 pr-3 py-2 border border-surface-300 text-sm font-medium text-surface-800 outline-hidden focus:border-[#1c3633]"
                            />
                        </div>
                    </div>

                    <div class="space-y-1">
                        <label class="text-xs font-bold text-surface-700">Silver Sell Rate (₹/g)</label>
                        <div class="relative">
                            <span class="absolute left-3 top-2.5 text-xs text-surface-400 font-bold">₹</span>
                            <input
                                v-model="rateForm.silver_sell"
                                type="number"
                                step="0.01"
                                required
                                placeholder="e.g. 88.00"
                                class="w-full pl-7 pr-3 py-2 border border-surface-300 text-sm font-medium text-surface-800 outline-hidden focus:border-[#1c3633]"
                            />
                        </div>
                    </div>
                </div>

                <div class="flex items-center justify-end gap-2 pt-3 border-t border-surface-100">
                    <button
                        type="button"
                        @click="isRatesModalOpen = false"
                        class="px-4 py-2 border border-surface-300 text-xs font-semibold text-surface-600 hover:bg-surface-50 cursor-pointer"
                    >
                        Cancel
                    </button>
                    <button
                        type="submit"
                        :disabled="rateUpdating"
                        class="px-5 py-2 bg-[#1c3633] hover:bg-[#254642] text-white text-xs font-bold cursor-pointer disabled:opacity-50"
                    >
                        {{ rateUpdating ? 'Saving...' : 'Update Market Rates' }}
                    </button>
                </div>
            </form>
        </div>
    </div>
</template>
