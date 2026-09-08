<?php

/*
 * Every way a shop can take money. These are first-party modules: nothing is
 * downloaded or loaded at runtime, and a shop switches one on by having it
 * allowed in its country (or granted to it) and entering its own account
 * details.
 *
 * kind:
 *   offline  — no money moves online (cash on delivery)
 *   manual   — the customer sends money themselves and types a reference;
 *              the shop confirms it by hand (a personal bKash number)
 *   online   — the customer pays on a hosted page or card form, and the
 *              gateway tells us the result
 *   platform — the platform's own account; the shop enters nothing
 *
 * fields: what the shop has to enter. 'secret' fields are encrypted, hidden,
 * and shown afterwards only as their last four characters.
 *
 * countries: where a gateway is allowed by default. '*' means everywhere.
 * Staff can change this per country, and grant a gateway to one shop.
 */

return [

    'cod' => [
        'name' => 'Cash on delivery',
        'kind' => 'offline',
        'entitlement' => 'cash_on_delivery',
        'countries' => ['*'],
        'fields' => [
            'instructions' => ['label' => 'A note for customers', 'type' => 'text', 'secret' => false,
                'hint' => 'For example: please have the exact amount ready.'],
        ],
        'blurb' => 'The customer pays the courier when the parcel arrives.',
    ],

    'self_mfs' => [
        'name' => 'Mobile money to your own number',
        'kind' => 'manual',
        'entitlement' => 'cash_on_delivery',
        'countries' => ['BD'],
        'fields' => [
            'provider' => ['label' => 'Service', 'type' => 'select', 'secret' => false,
                'options' => ['bkash' => 'bKash', 'nagad' => 'Nagad', 'rocket' => 'Rocket', 'upay' => 'Upay']],
            'account_number' => ['label' => 'Your number', 'type' => 'text', 'secret' => false,
                'hint' => 'The number customers send money to.'],
            'account_type' => ['label' => 'Account type', 'type' => 'select', 'secret' => false,
                'options' => ['personal' => 'Personal', 'merchant' => 'Merchant']],
            'instructions' => ['label' => 'What to tell customers', 'type' => 'text', 'secret' => false,
                'hint' => 'For example: Send Money to this number, then enter the transaction ID.'],
        ],
        'blurb' => 'The customer sends money to your own bKash, Nagad or Rocket number and enters the transaction ID. You confirm it by hand.',
    ],

    'bkash' => [
        'name' => 'bKash (merchant)',
        'kind' => 'online',
        'entitlement' => 'online_payments',
        'countries' => ['BD'],
        'fields' => [
            'app_key' => ['label' => 'App key', 'type' => 'text', 'secret' => true],
            'app_secret' => ['label' => 'App secret', 'type' => 'password', 'secret' => true],
            'username' => ['label' => 'Username', 'type' => 'text', 'secret' => false],
            'password' => ['label' => 'Password', 'type' => 'password', 'secret' => true],
            'sandbox' => ['label' => 'Use the test system', 'type' => 'checkbox', 'secret' => false],
        ],
        'blurb' => 'Customers pay with bKash on a hosted page. Needs a bKash merchant account.',
    ],

    'nagad' => [
        'name' => 'Nagad (merchant)',
        'kind' => 'online',
        'entitlement' => 'online_payments',
        'countries' => ['BD'],
        'fields' => [
            'merchant_id' => ['label' => 'Merchant ID', 'type' => 'text', 'secret' => false],
            'merchant_number' => ['label' => 'Merchant number', 'type' => 'text', 'secret' => false],
            'public_key' => ['label' => 'Public key', 'type' => 'textarea', 'secret' => true],
            'private_key' => ['label' => 'Private key', 'type' => 'textarea', 'secret' => true],
            'sandbox' => ['label' => 'Use the test system', 'type' => 'checkbox', 'secret' => false],
        ],
        'blurb' => 'Customers pay with Nagad on a hosted page. Needs a Nagad merchant account.',
    ],

    'amarpay' => [
        'name' => 'AmarPay',
        'kind' => 'online',
        'entitlement' => 'online_payments',
        'countries' => ['BD'],
        'fields' => [
            'store_id' => ['label' => 'Store ID', 'type' => 'text', 'secret' => false],
            'signature_key' => ['label' => 'Signature key', 'type' => 'password', 'secret' => true],
            'sandbox' => ['label' => 'Use the test system', 'type' => 'checkbox', 'secret' => false],
        ],
        'blurb' => 'Cards, bKash, Nagad, Rocket and banks through one hosted page.',
    ],

    'sslcommerz' => [
        'name' => 'SSLCommerz',
        'kind' => 'online',
        'entitlement' => 'online_payments',
        'countries' => ['BD'],
        'fields' => [
            'store_id' => ['label' => 'Store ID', 'type' => 'text', 'secret' => false,
                'hint' => 'From your SSLCommerz merchant panel. Looks like yourshop0live.'],
            'store_password' => ['label' => 'Store password', 'type' => 'password', 'secret' => true,
                'hint' => 'The store password from the same panel, not the one you sign in with.'],
            'sandbox' => ['label' => 'Use the test system', 'type' => 'checkbox', 'secret' => false],
        ],
        'blurb' => 'Cards, bKash, Nagad, Rocket and banks through one hosted page.',
    ],

    'stripe' => [
        'name' => 'Stripe',
        'kind' => 'online',
        'entitlement' => 'online_payments',
        'countries' => ['MY', 'AE', 'SA', 'ID'],
        'fields' => [
            'secret_key' => ['label' => 'Secret key', 'type' => 'password', 'secret' => true,
                'hint' => 'From Stripe → Developers → API keys. Starts sk_test_ while you are testing and sk_live_ when you are taking real money.'],
            'webhook_secret' => ['label' => 'Webhook signing secret', 'type' => 'password', 'secret' => true, 'optional' => true,
                'hint' => 'From the webhook you add in Stripe. Without it a customer who closes the tab after paying leaves the order unfinished.'],
            'publishable_key' => ['label' => 'Publishable key', 'type' => 'text', 'secret' => false, 'optional' => true,
                'hint' => 'Not needed for the hosted payment page. Fill it in only if Stripe asks you for it.'],
        ],
        'blurb' => 'Cards, Apple Pay and Google Pay on a hosted page. Needs a Stripe account.',
    ],

    'airwallex' => [
        'name' => 'Airwallex',
        'kind' => 'online',
        'entitlement' => 'online_payments',
        'countries' => ['MY', 'AE', 'SA', 'ID'],
        'fields' => [
            'client_id' => ['label' => 'Client ID', 'type' => 'text', 'secret' => false],
            'api_key' => ['label' => 'API key', 'type' => 'password', 'secret' => true],
            'webhook_secret' => ['label' => 'Webhook secret', 'type' => 'password', 'secret' => true],
            'sandbox' => ['label' => 'Use the demo system', 'type' => 'checkbox', 'secret' => false],
        ],
        'blurb' => 'Cards and local payment methods across Asia and the Gulf.',
    ],

    'platform' => [
        'name' => 'Pay through the platform',
        'kind' => 'platform',
        'entitlement' => 'online_payments',
        'countries' => [],
        'fields' => [],
        'blurb' => 'Use the platform\'s own payment account. Nothing to sign up for or enter; the platform settles with you.',
    ],

];
