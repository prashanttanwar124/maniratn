<?php

namespace Database\Seeders;

class RolePermissionRegistry
{
    public static function permissions(): array
    {
        return [
            'manage_users',
            'manage_roles_permissions',
            'view_dashboard',
            'manage_daily_rates',
            'view_vault',
            'manage_vault',
            'manage_products',
            'manage_stock_counts',
            'manage_categories',
            'manage_customers',
            'manage_suppliers',
            'create_order',
            'manage_orders',
            'settle_karigar',
            'manage_invoices',
            'manage_gold_schemes',
            'manage_ledgers',
            'manage_expenses',
            'manage_mortgages',
            'view_all_sales',
        ];
    }

    public static function roles(): array
    {
        return [
            'admin' => self::permissions(),
            'manager' => [
                'view_dashboard',
                'manage_daily_rates',
                'view_vault',
                'manage_products',
                'manage_categories',
                'manage_customers',
                'manage_suppliers',
                'create_order',
                'manage_orders',
                'manage_invoices',
                'manage_gold_schemes',
                'manage_ledgers',
                'view_all_sales',
            ],
            'accountant' => [
                'view_dashboard',
                'view_vault',
                'manage_vault',
                'manage_ledgers',
                'manage_expenses',
                'manage_mortgages',
                'manage_gold_schemes',
                'view_all_sales',
            ],
            'staff' => [
                'view_dashboard',
                'manage_customers',
                'create_order',
                'manage_orders',
                'manage_invoices',
            ],
            'basic' => [],
        ];
    }
}
