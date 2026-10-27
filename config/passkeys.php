<?php

return [
    'name' => env('PASSKEY_RP_NAME', env('APP_NAME', 'WIS School System')),
    'rp_id' => env('PASSKEY_RP_ID'),
    'origin' => env('PASSKEY_ORIGIN'),
    'timeout' => (int) env('PASSKEY_TIMEOUT', 60000),
];
