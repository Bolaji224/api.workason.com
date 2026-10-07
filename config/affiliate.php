<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Affiliate Commission Configuration
    |--------------------------------------------------------------------------
    |
    | commission_type: 'percentage' = % of platform earnings; 'fixed' = flat amount
    | commission_rate: used when type is 'percentage' (0.10 = 10%)
    | fixed_amount:    used when type is 'fixed' (in NGN)
    |
    | The commission is calculated from the platform's earnings on each
    | approved payment — NOT from the employer's total or freelancer's payout.
    |
    */
    'commission_type'  => env('AFFILIATE_COMMISSION_TYPE', 'percentage'),
    'commission_rate'  => (float) env('AFFILIATE_COMMISSION_RATE', 0.10), // 10% of platform earnings
    'fixed_amount'     => (float) env('AFFILIATE_FIXED_AMOUNT', 500),     // NGN 500 flat

    /*
    |--------------------------------------------------------------------------
    | Affiliate ID & Code
    |--------------------------------------------------------------------------
    */
    'id_prefix'        => env('AFFILIATE_ID_PREFIX', 'WRK'),
    'id_padding'       => 5, // WRK-00001 → 5-digit zero-padded number

    /*
    |--------------------------------------------------------------------------
    | Attribution Window
    |--------------------------------------------------------------------------
    | How many days a referral click stays valid for attribution.
    | After this window, a new user who registers is no longer attributed.
    */
    'attribution_days' => (int) env('AFFILIATE_ATTRIBUTION_DAYS', 30),

    /*
    |--------------------------------------------------------------------------
    | Minimum Payout Threshold
    |--------------------------------------------------------------------------
    */
    'min_payout'       => (float) env('AFFILIATE_MIN_PAYOUT', 5000), // NGN 5,000

    /*
    |--------------------------------------------------------------------------
    | Qualifying Event
    |--------------------------------------------------------------------------
    | Which transaction type triggers a commission.
    | Currently: 'employer_payment_approved'
    */
    'qualifying_event' => 'employer_payment_approved',
];
