<script setup>
import { usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import AppMenuItem from './AppMenuItem.vue';

const page = usePage();
const can = page.props.auth?.can || {};

const model = [
    {
        label: 'Home',
        items: [{ label: 'Dashboard', icon: 'pi pi-fw pi-home', to: '/dashboard', visible: Boolean(can.view_dashboard) }],
    },
    {
        label: 'Sales & Billing',
        items: [
            { label: 'New Invoice', icon: 'pi pi-fw pi-file-plus', to: '/invoices/create', visible: Boolean(can.manage_invoices) },
            { label: 'Invoices', icon: 'pi pi-fw pi-file', to: '/invoices', visible: Boolean(can.manage_invoices) },
            { label: 'Verification Tags', icon: 'pi pi-fw pi-verified', to: '/verification-tags', visible: Boolean(can.manage_invoices) },
        ],
    },
    {
        label: 'Inventory',
        items: [
            { label: 'Gold Products', icon: 'pi pi-fw pi-box', to: '/products', visible: Boolean(can.manage_products) },
            { label: 'Silver Products', icon: 'pi pi-fw pi-database', to: '/silver-products', visible: Boolean(can.manage_products) },
            { label: 'Categories', icon: 'pi pi-fw pi-tags', to: '/categories', visible: Boolean(can.manage_categories) },
        ],
    },
    {
        label: 'Orders & Karigar',
        items: [
            { label: 'Orders', icon: 'pi pi-fw pi-clipboard', to: '/orders', visible: Boolean(can.create_order || can.manage_orders) },
        ],
    },
    {
        label: 'Finance',
        items: [
            { label: 'Expenses', icon: 'pi pi-fw pi-wallet', to: '/expenses', visible: Boolean(can.manage_expenses) },
            { label: 'Gold Schemes', icon: 'pi pi-fw pi-star', to: '/gold-schemes', visible: Boolean(can.manage_gold_schemes) },
            { label: 'Mortgages (Girvi)', icon: 'pi pi-fw pi-lock', to: '/mortgages', visible: Boolean(can.manage_mortgages) },
        ],
    },
    {
        label: 'People',
        items: [
            { label: 'Customers', icon: 'pi pi-fw pi-users', to: '/customers', visible: Boolean(can.manage_customers) },
            { label: 'Suppliers', icon: 'pi pi-fw pi-truck', to: '/suppliers', visible: Boolean(can.manage_suppliers) },
            { label: 'Staff', icon: 'pi pi-fw pi-id-card', to: '/staff', visible: Boolean(can.manage_users) },
            { label: 'Attendance', icon: 'pi pi-fw pi-calendar-clock', to: '/attendance', visible: Boolean(can.manage_users) },
            { label: 'Karigars', icon: 'pi pi-fw pi-briefcase', to: '/karigars', visible: Boolean(can.manage_orders || can.settle_karigar) },
            { label: 'Users', icon: 'pi pi-fw pi-user-plus', to: '/users', visible: Boolean(can.manage_users) },
        ],
    },
    {
        label: 'Settings',
        items: [
            { label: 'Business Profile', icon: 'pi pi-fw pi-building', to: '/settings/business-profile', visible: Boolean(can.manage_users) },
            { label: 'Profile', icon: 'pi pi-fw pi-user', to: '/settings/profile' },
            { label: 'Password', icon: 'pi pi-fw pi-key', to: '/settings/password' },
            { label: 'Two-Factor Auth', icon: 'pi pi-fw pi-shield', to: '/settings/two-factor' },
        ],
    },
];

const visibleModel = computed(() =>
    model
        .map((section) => ({
            ...section,
            items: (section.items || []).filter((item) => item.visible !== false),
        }))
        .filter((section) => (section.items || []).length > 0),
);
</script>

<template>
    <ul class="layout-menu">
        <template v-for="(item, i) in visibleModel" :key="i">
            <AppMenuItem :item="item" :index="i" />
        </template>
    </ul>
</template>
