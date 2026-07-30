<?php

return [
    'enabled' => env('LIMIT_ACCESS_ENABLED', false),

    'ips' => array_values(array_filter(array_map(
        'trim',
        explode(',', (string) env('LIMIT_ACCESS_IPS', ''))
    ))),
];
