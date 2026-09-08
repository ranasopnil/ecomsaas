<?php

/*
 * Couriers a shop can hand parcels to.
 *
 * 'drivers' are the couriers this repository can actually talk to. They are
 * first-party modules: nothing is downloaded or loaded at runtime, and a shop
 * switches one on by entering its own account details. A courier with no
 * driver here is still perfectly usable — the shopkeeper types the
 * consignment number in by hand, which is how every courier worked before.
 *
 * 'fields' is what the shop has to enter to talk to that courier. 'secret'
 * fields are encrypted, hidden, and shown afterwards only as their last four
 * characters.
 *
 * 'suggestions' are the couriers most shops in a country use, offered in one
 * press so a shop does not start from an empty list. {code} in a tracking
 * address is replaced with the consignment number.
 */

return [

    'drivers' => [

        'pathao' => [
            'name' => 'Pathao Courier',
            'tracking_url' => 'https://merchant.pathao.com/tracking?consignment_id={code}',
            'blurb' => 'Books the parcel with Pathao as you hand it over, and brings the consignment number back.',
            'fields' => [
                'client_id' => ['label' => 'Client ID', 'type' => 'text', 'secret' => false,
                    'hint' => 'From Pathao Merchant → Developer API.'],
                'client_secret' => ['label' => 'Client secret', 'type' => 'password', 'secret' => true],
                'username' => ['label' => 'Pathao username', 'type' => 'text', 'secret' => false,
                    'hint' => 'The email you sign in to Pathao Merchant with.'],
                'password' => ['label' => 'Pathao password', 'type' => 'password', 'secret' => true],
                'store_id' => ['label' => 'Store ID', 'type' => 'text', 'secret' => false,
                    'hint' => 'Which of your Pathao stores the parcel is collected from.'],
                'sandbox' => ['label' => 'Use the test system', 'type' => 'checkbox', 'secret' => false],
            ],
        ],

        'steadfast' => [
            'name' => 'Steadfast Courier',
            'tracking_url' => 'https://steadfast.com.bd/t/{code}',
            'blurb' => 'Books the parcel with Steadfast and brings back its consignment and tracking number.',
            'fields' => [
                'api_key' => ['label' => 'API key', 'type' => 'password', 'secret' => true,
                    'hint' => 'From your Steadfast merchant portal.'],
                'secret_key' => ['label' => 'Secret key', 'type' => 'password', 'secret' => true],
            ],
        ],

        'redx' => [
            'name' => 'RedX',
            'tracking_url' => 'https://redx.com.bd/track-parcel/?trackingId={code}',
            'blurb' => 'Books the parcel with RedX. You choose the delivery area from RedX\'s own list.',
            'fields' => [
                'access_token' => ['label' => 'API access token', 'type' => 'password', 'secret' => true,
                    'hint' => 'The token RedX issued you. Paste it without the word "Bearer".'],
                'pickup_store_id' => ['label' => 'Pickup store ID', 'type' => 'text', 'secret' => false,
                    'hint' => 'Which of your RedX stores the parcel is collected from.'],
            ],
        ],

    ],

    'suggestions' => [

        'BD' => [
            ['name' => 'Pathao Courier', 'driver' => 'pathao'],
            ['name' => 'Steadfast Courier', 'driver' => 'steadfast'],
            ['name' => 'RedX', 'driver' => 'redx'],
            ['name' => 'Paperfly', 'driver' => null, 'tracking_url' => 'https://go.paperfly.com.bd/track/{code}'],
            ['name' => 'eCourier', 'driver' => null, 'tracking_url' => 'https://ecourier.com.bd/track?id={code}'],
            ['name' => 'Sundarban Courier', 'driver' => null, 'tracking_url' => null],
            ['name' => 'SA Paribahan', 'driver' => null, 'tracking_url' => null],
            ['name' => 'Own delivery man', 'driver' => null, 'tracking_url' => null],
        ],

    ],

];
