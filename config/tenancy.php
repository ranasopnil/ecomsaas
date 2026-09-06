<?php

return [

    /*
     * Hostnames that belong to the platform itself, not to any store:
     * marketing site, merchant sign-up, super admin.
     */
    'central_domains' => array_values(array_filter(array_map(
        'trim',
        explode(',', (string) env('TENANCY_CENTRAL_DOMAINS', 'devecom.gotipay.com')),
    ))),

    /*
     * Free store addresses are <slug> + this suffix.
     */
    'subdomain_suffix' => env('TENANCY_SUBDOMAIN_SUFFIX', '.devecom.gotipay.com'),

    /*
     * Paths that are never tenant specific (health checks, the certificate
     * question Caddy asks us).
     */
    'central_paths' => [
        'internal/*',
        'up',
    ],

    /*
     * Where a merchant points their own domain. Shown to them as the A record
     * to create, and what a domain has to resolve to before we believe it.
     */
    'server_ips' => array_values(array_filter(array_map(
        'trim',
        explode(',', (string) env('TENANCY_SERVER_IPS', '46.224.138.153')),
    ))),

    /*
     * Only these callers may ask the internal domain-check endpoint.
     * Caddy runs on the same machine.
     */
    'internal_ips' => array_values(array_filter(array_map(
        'trim',
        explode(',', (string) env('TENANCY_INTERNAL_IPS', '127.0.0.1,::1')),
    ))),

];
