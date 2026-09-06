<?php

/*
 * Everything a package can switch on or put a ceiling on.
 *
 * 'limit'  — a counted thing. limit_value null means no ceiling.
 * 'switch' — either the store has it or it does not.
 *
 * 'default' is what applies to a store with no entitlement row for the feature.
 * Keep these conservative: a store with no plan should not get the paid extras.
 */

return [

    'products' => [
        'type' => 'limit',
        'label' => 'Products',
        'default' => 10,
    ],

    'staff_accounts' => [
        'type' => 'limit',
        'label' => 'Staff accounts',
        'default' => 1,
    ],

    'custom_domains' => [
        'type' => 'limit',
        'label' => 'Custom domains',
        'default' => 0,
    ],

    'storage_mb' => [
        'type' => 'limit',
        'label' => 'Image storage (MB)',
        'default' => 100,
    ],

    'orders_per_month' => [
        'type' => 'limit',
        'label' => 'Orders per month',
        'default' => 50,
    ],

    'online_payments' => [
        'type' => 'switch',
        'label' => 'Take payment online',
        'default' => false,
    ],

    'cash_on_delivery' => [
        'type' => 'switch',
        'label' => 'Cash on delivery',
        'default' => true,
    ],

    'courier_pickup' => [
        'type' => 'switch',
        'label' => 'Book courier pickups',
        'default' => false,
    ],

    'discount_codes' => [
        'type' => 'switch',
        'label' => 'Discount codes',
        'default' => false,
    ],

];
