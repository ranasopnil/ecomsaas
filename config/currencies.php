<?php

/*
 * The currencies plans can be priced in.
 *
 * 'exponent' is how many decimal places the currency really has. The Indonesian
 * Rupiah has none — 15000 IDR is fifteen thousand Rupiah, not 150.00 — and
 * getting this wrong makes prices a hundred times out.
 *
 * Add a market here and it appears in the super admin plan form.
 */

return [

    'BDT' => ['name' => 'Bangladeshi Taka', 'symbol' => '৳', 'exponent' => 2],
    'MYR' => ['name' => 'Malaysian Ringgit', 'symbol' => 'RM', 'exponent' => 2],
    'IDR' => ['name' => 'Indonesian Rupiah', 'symbol' => 'Rp', 'exponent' => 0],
    'AED' => ['name' => 'UAE Dirham', 'symbol' => 'د.إ', 'exponent' => 2],
    'SAR' => ['name' => 'Saudi Riyal', 'symbol' => 'ر.س', 'exponent' => 2],
    'USD' => ['name' => 'US Dollar', 'symbol' => '$', 'exponent' => 2],

];
