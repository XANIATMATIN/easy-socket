<?php

return [
    'filePath' => env('SOCKET_FILE_PATH', 'bootstrap/easySocket'),
    'host' => env('SOCKET_HOST', '127.0.0.1'),
    'port' => env('SOCKET_PORT', 5000),
    'maxClientNumber' => env('MAX_SOCKET_CLIENTS', 50),
    'interval' => env('SOCKET_INTERVAL', null),
    'ipRangeMax' => env('SOCKET_IP_RANGE_MAX', 3),
    'defaultProtocol' => '',
    'protocols' => [
    ],
    'middlewares' => [
    ]
];
