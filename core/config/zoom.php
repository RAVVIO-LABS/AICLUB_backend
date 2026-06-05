<?php

$hostPoolFromEnv = json_decode((string) env('ZOOM_HOST_POOL_JSON', '[]'), true);
$hostPool = is_array($hostPoolFromEnv) && count($hostPoolFromEnv) > 0 ? $hostPoolFromEnv : [
    ['label' => 'AIClub Zoom 2', 'email' => 'az2@aiclub.world', 'role' => 'Owner', 'license_type' => 'Zoom Workplace Pro', 'environment' => 'Production'],
    ['label' => 'AIClub Zoom 10', 'email' => 'az10@aiclub.world', 'role' => 'Member', 'license_type' => 'Zoom Workplace Pro', 'environment' => 'Production'],
    ['label' => 'AIClub Zoom 1', 'email' => 'az1@aiclub.world', 'role' => 'Member', 'license_type' => 'Zoom Workplace Pro', 'environment' => 'Production'],
    ['label' => 'AIClub Zoom 3', 'email' => 'az3@aiclub.world', 'role' => 'Member', 'license_type' => 'Zoom Workplace Pro', 'environment' => 'Production'],
    ['label' => 'AIClub Zoom 4', 'email' => 'az4@aiclub.world', 'role' => 'Member', 'license_type' => 'Zoom Workplace Pro', 'environment' => 'Production'],
    ['label' => 'AIClub Zoom 5', 'email' => 'az5@aiclub.world', 'role' => 'Member', 'license_type' => 'Zoom Workplace Pro', 'environment' => 'Production'],
    ['label' => 'AIClub Zoom 7', 'email' => 'az7@aiclub.world', 'role' => 'Member', 'license_type' => 'Zoom Workplace Pro', 'environment' => 'Production'],
    ['label' => 'AIClub Zoom 8', 'email' => 'az8@aiclub.world', 'role' => 'Member', 'license_type' => 'Zoom Workplace Pro', 'environment' => 'Production'],
    ['label' => 'AIClub Zoom 9', 'email' => 'az9@aiclub.world', 'role' => 'Member', 'license_type' => 'Zoom Workplace Pro', 'environment' => 'Production'],
];

return [
    'account_id' => env('ZOOM_ACCOUNT_ID'),
    'client_id' => env('ZOOM_CLIENT_ID'),
    'client_secret' => env('ZOOM_CLIENT_SECRET'),
    'host_pool' => $hostPool,
    'meeting' => [
        'join_before_host' => false,
        'waiting_room' => true,
        'auto_recording' => 'cloud',
        'timezone' => env('ZOOM_TIMEZONE', config('app.timezone', 'UTC')),
    ],
];
