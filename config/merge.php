<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Arrears-corrected account numbers (readings:merge)
    |--------------------------------------------------------------------------
    |
    | When merging offline readings, accounts in this list are treated as
    | "arrears already corrected". The new reading/bill is created with
    | zero previous balance (normal reading), so corrected arrears
    | are not re-applied from old offline data.
    |
    | Set via env: ARREARS_CORRECTED_ACCOUNTS (comma-separated account numbers).
    |
    */
    'arrears_corrected_accounts' => array_filter(
        array_map('trim', explode(',', env('ARREARS_CORRECTED_ACCOUNTS', '')))
    ),

];
