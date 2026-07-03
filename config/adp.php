<?php

return [
    /*
     * ADP company code — printed in the "Co Code" column of every export row.
     * Override in .env with ADP_COMPANY_CODE.
     */
    'company_code' => env('ADP_COMPANY_CODE', 'VB'),

    /*
     * ADP earnings code for streamer payouts.
     * Common values: REG (regular), BONUS, COMMS (commission).
     */
    'earnings_code' => env('ADP_EARNINGS_CODE', 'REG'),
];
