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

// Dropdowns State
const isUserMenuOpen = ref(false);
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
    }
};

const closeAllDropdowns = (e) => {
    if (!e.target.closest('.user-menu-wrapper')) isUserMenuOpen.value = false;
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
            <button
                type="button"
                class="inline-flex items-center justify-center h-9 w-9 border border-surface-200 bg-surface-50 hover:bg-surface-100 text-surface-700 hover:text-[#1c3633] transition-all cursor-pointer"
                @click="toggleMenu"
                aria-label="Toggle navigation"
                title="Toggle Menu"
            >
                <i class="pi pi-bars text-sm"></i>
            </button>

            <Link href="/dashboard" class="layout-topbar-brand flex items-center gap-2.5 group">
                <img src="/logo-mark.png" alt="KaratSetu" class="h-8.5 w-auto object-contain flex-shrink-0 transition-transform group-hover:scale-105" />
                <div class="flex flex-col">
                    <span class="text-[1.25rem] font-bold leading-none tracking-tight text-[#1c3633]">
                        Karat<span class="text-[#c08f34]">Setu</span>
                    </span>
                    <span class="mt-0.5 text-[9.5px] font-bold uppercase tracking-[0.18em] text-surface-400 leading-none hidden sm:block">
                        Jewellery ERP
                    </span>
                </div>
            </Link>
        </div>

        <!-- 2. CENTER: Spotlight Search Input Bar -->
        <div class="layout-topbar-center max-w-lg mx-auto">
            <button
                type="button"
                @click="openSpotlight"
                class="w-full flex items-center gap-2.5 px-3.5 h-9 bg-[#f8faf9] hover:bg-[#edf2f0] border border-surface-200 hover:border-[#1c3633]/25 text-xs text-surface-500 transition-all cursor-pointer group"
                title="Search customers, bills, tags... (⌘K)"
            >
                <i class="pi pi-search text-surface-400 group-hover:text-[#1c3633] transition-colors"></i>
                <span class="flex-1 text-left truncate font-normal text-surface-500">Search bills, customers, barcode tags...</span>
                <kbd class="px-1.5 py-0.5 bg-white border border-surface-200 text-[10px] font-mono text-surface-400 font-medium">⌘K</kbd>
            </button>
        </div>

        <!-- 3. RIGHT: Actions & Profile -->
        <div class="layout-topbar-actions">
            <!-- Bullion Live Rate Pill -->
            <button
                type="button"
                @click="openRatesModal"
                class="hidden xl:inline-flex items-center gap-2 px-3 h-9 bg-amber-50/80 hover:bg-amber-100/90 border border-amber-200/70 text-xs text-amber-950 transition-all cursor-pointer group"
                title="Click to update today's live rates"
            >
                <span class="h-2 w-2 rounded-full bg-amber-500 animate-pulse"></span>
                <span class="font-bold text-amber-800 text-[11px]">Gold ₹{{ rates?.gold_sell ? Number(rates.gold_sell).toLocaleString('en-IN') : '—' }}</span>
                <span class="text-amber-300">·</span>
                <span class="text-surface-600 text-[11px]">Silver ₹{{ rates?.silver_sell ? Number(rates.silver_sell).toLocaleString('en-IN') : '—' }}</span>
                <i class="pi pi-pencil text-[9px] text-amber-600 opacity-60 group-hover:opacity-100 ml-0.5"></i>
            </button>

            <!-- + New Bill Button (Primary Action) -->
            <Link
                href="/invoices/create"
                class="inline-flex items-center gap-1.5 h-9 px-3.5 bg-[#1c3633] hover:bg-[#254642] text-white text-xs font-semibold transition-all shadow-xs cursor-pointer"
                title="Create New Bill (Shortcut: F2)"
            >
                <i class="pi pi-plus text-xs text-[#c08f34] font-bold"></i>
                <span class="font-bold">New Bill</span>
                <kbd class="hidden lg:inline px-1 py-0.2 bg-white/15 text-[9px] font-mono text-white/90">F2</kbd>
            </Link>

            <!-- Karat AI Copilot -->
            <button
                type="button"
                class="inline-flex items-center justify-center h-9 w-9 border border-surface-200 bg-surface-50 hover:bg-surface-100 text-surface-600 hover:text-amber-700 transition-all cursor-pointer"
                title="Karat AI Assistant"
                @click="emit('openAskAi')"
            >
                <i class="pi pi-sparkles text-xs text-[#c08f34]"></i>
            </button>

            <!-- Day Status Live Indicator -->
            <div
                class="hidden sm:inline-flex items-center gap-1.5 px-3 h-9 text-xs font-medium border"
                :class="dayStatus.is_open ? 'bg-emerald-50 text-emerald-700 border-emerald-200' : 'bg-amber-50 text-amber-700 border-amber-200'"
                :title="dayStatus.is_open ? 'Store Register is Open' : 'Store Register is Closed'"
            >
                <span class="h-1.5 w-1.5 rounded-full" :class="dayStatus.is_open ? 'bg-emerald-500 animate-pulse' : 'bg-amber-500'"></span>
                <span class="text-[11px] font-semibold">{{ dayStatus.is_open ? 'Open' : 'Closed' }}</span>
            </div>

            <div class="h-5 w-px bg-surface-200 hidden sm:block"></div>

            <!-- User Profile Avatar & Dropdown -->
            <div class="relative user-menu-wrapper">
                <button
                    type="button"
                    @click.stop="isUserMenuOpen = !isUserMenuOpen"
                    class="flex items-center gap-2 px-2 h-9 border border-transparent hover:border-surface-200 hover:bg-surface-50 transition-all cursor-pointer group"
                >
                    <div class="h-6.5 w-6.5 bg-[#1c3633] text-white flex items-center justify-center text-[10px] font-bold shadow-xs">
                        {{ initials }}
                    </div>
                    <div class="hidden md:flex flex-col text-left">
                        <span class="text-xs font-bold text-[#1c3633] leading-none">{{ pageUser?.name || 'User' }}</span>
                        <span class="text-[10px] text-surface-400 font-medium leading-tight mt-0.5">{{ roleLabel }}</span>
                    </div>
                    <i class="pi pi-chevron-down text-[10px] text-surface-400 group-hover:text-surface-700 transition-transform" :class="isUserMenuOpen ? 'rotate-180' : ''"></i>
                </button>

                <!-- Dropdown Menu -->
                <div
                    v-if="isUserMenuOpen"
                    class="absolute right-0 mt-2 w-56 bg-white border border-surface-200 shadow-xl z-50 py-1.5 divide-y divide-surface-100 animate-in fade-in zoom-in-95 duration-100"
                >
                    <div class="px-3.5 py-2.5">
                        <p class="text-xs font-bold text-[#1c3633]">{{ pageUser?.name }}</p>
                        <p class="text-[11px] text-surface-400 truncate">{{ pageUser?.email }}</p>
                    </div>

                    <div class="py-1">
                        <Link href="/settings/profile" class="flex items-center gap-2.5 px-3.5 py-2 text-xs text-surface-700 hover:bg-surface-50 mx-1">
                            <i class="pi pi-user text-xs text-surface-400"></i>
                            <span>My Profile</span>
                        </Link>
                        <Link href="/settings/business-profile" class="flex items-center gap-2.5 px-3.5 py-2 text-xs text-surface-700 hover:bg-surface-50 mx-1">
                            <i class="pi pi-building text-xs text-surface-400"></i>
                            <span>Store & GST Settings</span>
                        </Link>
                        <Link href="/settings/password" class="flex items-center gap-2.5 px-3.5 py-2 text-xs text-surface-700 hover:bg-surface-50 mx-1">
                            <i class="pi pi-shield text-xs text-surface-400"></i>
                            <span>Security & Password</span>
                        </Link>
                    </div>

                    <div class="py-1">
                        <button
                            type="button"
                            @click="submitLogout"
                            class="w-full flex items-center gap-2.5 px-3.5 py-2 text-xs text-red-600 hover:bg-red-50 mx-1 text-left cursor-pointer font-medium"
                        >
                            <i class="pi pi-sign-out text-xs text-red-500"></i>
                            <span>Sign Out</span>
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
        <div class="w-full max-w-xl bg-white border border-surface-200 shadow-2xl overflow-hidden flex flex-col max-h-[80vh]">
            <!-- Search Header Input -->
            <div class="flex items-center gap-3 px-4 py-3 border-b border-surface-200 bg-white">
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
                    class="text-surface-400 hover:text-surface-900 cursor-pointer bg-transparent border-0 p-1 transition-colors"
                    title="Close (ESC)"
                >
                    <i class="pi pi-times text-sm"></i>
                </button>
            </div>

            <!-- Search Results Body -->
            <div class="flex-1 overflow-y-auto p-3 space-y-3 max-h-96">
                <!-- Searching Spinner -->
                <div v-if="isSearching" class="flex items-center justify-center py-6 text-surface-400 text-xs gap-2">
                    <i class="pi pi-spin pi-spinner"></i>
                    <span>Searching KaratSetu database...</span>
                </div>

                <!-- Live Results -->
                <template v-else-if="searchQuery.trim()">
                    <!-- Customers -->
                    <div v-if="searchResults.customers?.length" class="space-y-1">
                        <div class="px-2 py-0.5 text-[10px] font-bold uppercase tracking-wider text-surface-500 bg-surface-50 border-b border-surface-100 flex items-center justify-between">
                            <span>Customers ({{ searchResults.customers.length }})</span>
                            <span class="text-[9px] text-surface-400 font-normal lowercase">customer profile & ledger</span>
                        </div>
                        <div class="divide-y divide-surface-100">
                            <Link
                                v-for="c in searchResults.customers"
                                :key="c.id"
                                :href="`/customers/${c.id}`"
                                @click="closeSpotlight"
                                class="flex items-center justify-between px-3 py-2 hover:bg-[#f2f6f5] transition-colors group cursor-pointer"
                            >
                                <div class="flex items-center gap-2.5 min-w-0">
                                    <div class="h-7 w-7 bg-[#1c3633]/10 text-[#1c3633] flex items-center justify-center text-xs font-bold flex-shrink-0">
                                        {{ c.name.charAt(0).toUpperCase() }}
                                    </div>
                                    <div class="min-w-0">
                                        <p class="text-xs font-bold text-[#1c3633] group-hover:text-[#c08f34] truncate">{{ c.name }}</p>
                                        <p class="text-[11px] text-surface-500 truncate">{{ c.mobile || c.phone }} {{ c.city ? '• ' + c.city : '' }}</p>
                                    </div>
                                </div>
                                <span class="text-[10px] font-medium text-surface-500 group-hover:text-[#1c3633] flex items-center gap-1">
                                    <span>Ledger</span>
                                    <i class="pi pi-arrow-right text-[9px] group-hover:translate-x-0.5 transition-transform"></i>
                                </span>
                            </Link>
                        </div>
                    </div>

                    <!-- Invoices -->
                    <div v-if="searchResults.invoices?.length" class="space-y-1">
                        <div class="px-2 py-0.5 text-[10px] font-bold uppercase tracking-wider text-surface-500 bg-surface-50 border-b border-surface-100 flex items-center justify-between">
                            <span>Invoices & Bills ({{ searchResults.invoices.length }})</span>
                            <span class="text-[9px] text-surface-400 font-normal lowercase">tax invoices</span>
                        </div>
                        <div class="divide-y divide-surface-100">
                            <a
                                v-for="inv in searchResults.invoices"
                                :key="inv.id"
                                :href="`/invoices/${inv.id}/print`"
                                target="_blank"
                                rel="noopener noreferrer"
                                @click="closeSpotlight"
                                class="flex items-center justify-between px-3 py-2 hover:bg-[#f2f6f5] transition-colors group cursor-pointer"
                            >
                                <div class="flex items-center gap-2.5 min-w-0">
                                    <div class="h-7 w-7 bg-amber-50 text-amber-800 flex items-center justify-center text-xs font-bold flex-shrink-0 border border-amber-200/60">
                                        <i class="pi pi-receipt text-xs"></i>
                                    </div>
                                    <div class="min-w-0">
                                        <p class="text-xs font-bold text-[#1c3633] group-hover:text-[#c08f34] truncate">{{ inv.invoice_number }}</p>
                                        <p class="text-[11px] text-surface-500 truncate">{{ inv.customer?.name || 'Walk-in' }} • ₹{{ Number(inv.total_amount).toLocaleString('en-IN') }}</p>
                                    </div>
                                </div>
                                <span class="text-[10px] font-bold text-emerald-700 bg-emerald-50 px-2 py-0.5 border border-emerald-200 flex items-center gap-1">
                                    <span>Print</span>
                                    <i class="pi pi-external-link text-[9px]"></i>
                                </span>
                            </a>
                        </div>
                    </div>

                    <!-- Products -->
                    <div v-if="searchResults.products?.length" class="space-y-1">
                        <div class="px-2 py-0.5 text-[10px] font-bold uppercase tracking-wider text-surface-500 bg-surface-50 border-b border-surface-100 flex items-center justify-between">
                            <span>Inventory & Barcodes ({{ searchResults.products.length }})</span>
                            <span class="text-[9px] text-surface-400 font-normal lowercase">stock item</span>
                        </div>
                        <div class="divide-y divide-surface-100">
                            <Link
                                v-for="p in searchResults.products"
                                :key="p.id"
                                :href="`/products`"
                                @click="closeSpotlight"
                                class="flex items-center justify-between px-3 py-2 hover:bg-[#f2f6f5] transition-colors group cursor-pointer"
                            >
                                <div class="flex items-center gap-2.5 min-w-0">
                                    <div class="h-7 w-7 bg-zinc-100 text-zinc-700 flex items-center justify-center text-xs font-mono font-bold flex-shrink-0 border border-zinc-200">
                                        🏷️
                                    </div>
                                    <div class="min-w-0">
                                        <p class="text-xs font-bold text-[#1c3633] group-hover:text-[#c08f34] truncate">{{ p.name }}</p>
                                        <p class="text-[11px] font-mono text-surface-500 truncate">{{ p.barcode }} • {{ p.gross_weight }}g</p>
                                    </div>
                                </div>
                                <span class="text-[10px] font-mono font-bold bg-surface-100 px-1.5 py-0.5 border border-surface-200 text-surface-700">{{ p.barcode }}</span>
                            </Link>
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
                        <Link
                            v-for="item in quickLinks"
                            :key="item.title"
                            :href="item.url"
                            @click="closeSpotlight"
                            class="flex items-start gap-3 p-3 bg-surface-50 hover:bg-[#eef4f2] border border-surface-200 hover:border-[#1c3633]/30 transition-all group cursor-pointer"
                        >
                            <i :class="item.icon" class="text-sm text-[#1c3633] mt-0.5 group-hover:text-[#c08f34]"></i>
                            <div>
                                <p class="text-xs font-bold text-[#1c3633]">{{ item.title }}</p>
                                <p class="text-[11px] text-surface-500 leading-tight">{{ item.desc }}</p>
                            </div>
                        </Link>
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
        <div class="w-full max-w-lg bg-white border border-surface-200 shadow-2xl p-6">
            <!-- Modal Header -->
            <div class="flex items-start justify-between pb-3.5 mb-4 border-b border-surface-200">
                <div>
                    <h3 class="text-base font-bold text-[#1c3633]">Today's Bullion Rates</h3>
                    <p class="text-xs text-surface-500 mt-0.5">Live base prices used across sales billing & valuations</p>
                </div>
                <button
                    type="button"
                    @click="isRatesModalOpen = false"
                    class="text-surface-400 hover:text-surface-900 cursor-pointer bg-transparent border-0 p-1 transition-colors"
                    title="Close (ESC)"
                >
                    <i class="pi pi-times text-sm"></i>
                </button>
            </div>

            <!-- Modal Form -->
            <form @submit.prevent="saveRates" class="space-y-4">
                <!-- 24K Gold Sell Rate -->
                <div class="space-y-1.5">
                    <label class="block text-xs font-bold text-surface-700 uppercase tracking-wider">
                        24K Gold Sell Rate <span class="text-surface-400 font-normal lowercase">(₹/gram)</span>
                    </label>
                    <div class="relative flex items-center">
                        <span class="absolute left-3.5 text-sm text-surface-400 font-bold pointer-events-none">₹</span>
                        <input
                            v-model="rateForm.gold_sell"
                            type="number"
                            step="0.01"
                            required
                            placeholder="e.g. 7160.00"
                            class="w-full pl-8 pr-4 h-11 border border-surface-300 text-sm font-bold text-[#1c3633] outline-hidden focus:border-[#1c3633] [appearance:textfield] [&::-webkit-outer-spin-button]:appearance-none [&::-webkit-inner-spin-button]:appearance-none"
                        />
                    </div>
                </div>

                <!-- 2 Column Grid: Gold Buy & Silver Sell -->
                <div class="grid grid-cols-2 gap-3.5">
                    <div class="space-y-1.5">
                        <label class="block text-xs font-bold text-surface-700 uppercase tracking-wider">
                            Gold Buy Rate <span class="text-surface-400 font-normal lowercase">(₹/g)</span>
                        </label>
                        <div class="relative flex items-center">
                            <span class="absolute left-3.5 text-sm text-surface-400 font-bold pointer-events-none">₹</span>
                            <input
                                v-model="rateForm.gold_buy"
                                type="number"
                                step="0.01"
                                required
                                placeholder="e.g. 7010.00"
                                class="w-full pl-8 pr-4 h-11 border border-surface-300 text-sm font-medium text-surface-800 outline-hidden focus:border-[#1c3633] [appearance:textfield] [&::-webkit-outer-spin-button]:appearance-none [&::-webkit-inner-spin-button]:appearance-none"
                            />
                        </div>
                    </div>

                    <div class="space-y-1.5">
                        <label class="block text-xs font-bold text-surface-700 uppercase tracking-wider">
                            Silver Sell Rate <span class="text-surface-400 font-normal lowercase">(₹/g)</span>
                        </label>
                        <div class="relative flex items-center">
                            <span class="absolute left-3.5 text-sm text-surface-400 font-bold pointer-events-none">₹</span>
                            <input
                                v-model="rateForm.silver_sell"
                                type="number"
                                step="0.01"
                                required
                                placeholder="e.g. 88.00"
                                class="w-full pl-8 pr-4 h-11 border border-surface-300 text-sm font-medium text-surface-800 outline-hidden focus:border-[#1c3633] [appearance:textfield] [&::-webkit-outer-spin-button]:appearance-none [&::-webkit-inner-spin-button]:appearance-none"
                            />
                        </div>
                    </div>
                </div>

                <!-- Modal Footer -->
                <div class="flex items-center justify-end gap-2.5 pt-4 mt-2 border-t border-surface-100">
                    <button
                        type="button"
                        @click="isRatesModalOpen = false"
                        class="px-4 h-10 border border-surface-300 text-xs font-semibold text-surface-600 hover:bg-surface-50 cursor-pointer transition-colors"
                    >
                        Cancel
                    </button>
                    <button
                        type="submit"
                        :disabled="rateUpdating"
                        class="px-5 h-10 bg-[#1c3633] hover:bg-[#254642] text-white text-xs font-bold cursor-pointer disabled:opacity-50 transition-colors shadow-xs"
                    >
                        {{ rateUpdating ? 'Saving Rates...' : 'Update Market Rates' }}
                    </button>
                </div>
            </form>
        </div>
    </div>
</template>
