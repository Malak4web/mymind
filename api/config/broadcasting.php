<?php

return [

    'default' => env('BROADCAST_CONNECTION', env('BROADCAST_DRIVER', 'pusher')),

    'connections' => [

        'pusher' => [
            'driver' => 'pusher',
            'key' => env('PUSHER_APP_KEY', '7adfbdb785782270cb45'),
            'secret' => env('PUSHER_APP_SECRET', '333a9c33e8ccff7eaa83'),
            'app_id' => env('PUSHER_APP_ID', '2184921'),
            'options' => [
                'cluster' => (env('PUSHER_APP_CLUSTER') && env('PUSHER_APP_CLUSTER') !== 'mt1') ? env('PUSHER_APP_CLUSTER') : 'eu',
                'useTLS' => true,
            ],
        ],

        'log' => [
            'driver' => 'log',
        ],

        'null' => [
            'driver' => 'null',
        ],

    ],

];
