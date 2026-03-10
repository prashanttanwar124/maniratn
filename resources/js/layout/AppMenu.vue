<script setup>
import AppMenuItem from './AppMenuItem.vue';

const model = [
    {
        label: 'Home',
        items: [{ label: 'Dashboard', icon: 'pi pi-fw pi-home', to: '/dashboard' }],
    },
    {
        label: 'Sales & Billing',
        items: [
            { label: 'New Invoice', icon: 'pi pi-fw pi-file-plus', to: '/invoices/create' },
            { label: 'Invoices', icon: 'pi pi-fw pi-file', to: '/invoices' },
        ],
    },
    {
        label: 'Inventory',
        items: [
            { label: 'Products', icon: 'pi pi-fw pi-box', to: '/products' },
            { label: 'Print Barcodes', icon: 'pi pi-fw pi-barcode', to: '/products/print-barcodes' },
            { label: 'Categories', icon: 'pi pi-fw pi-tags', to: '/categories' },
        ],
    },
    {
        label: 'Orders & Karigar',
        items: [{ label: 'Orders', icon: 'pi pi-fw pi-clipboard', to: '/orders' }],
    },
    {
        label: 'Finance',
        items: [
            { label: 'Expenses', icon: 'pi pi-fw pi-wallet', to: '/expenses' },
            { label: 'Mortgages (Girvi)', icon: 'pi pi-fw pi-lock', to: '/mortgages' },
        ],
    },
    {
        label: 'People',
        items: [
            { label: 'Customers', icon: 'pi pi-fw pi-users', to: '/customers' },
            { label: 'Suppliers', icon: 'pi pi-fw pi-truck', to: '/suppliers' },
        ],
    },
    {
        label: 'Settings',
        items: [
            { label: 'Profile', icon: 'pi pi-fw pi-user', to: '/settings/profile' },
            { label: 'Password', icon: 'pi pi-fw pi-key', to: '/settings/password' },
            { label: 'Two-Factor Auth', icon: 'pi pi-fw pi-shield', to: '/settings/two-factor' },
            { label: 'Appearance', icon: 'pi pi-fw pi-palette', to: '/settings/appearance' },
        ],
    },
];
</script>

<template>
    <ul class="layout-menu">
        <template v-for="(item, i) in model" :key="i">
            <AppMenuItem :item="item" :index="i" />
        </template>
    </ul>
</template>
