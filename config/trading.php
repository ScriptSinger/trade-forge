<?php

declare(strict_types=1);

return [
    'bot' => [
        'cycle_interval_seconds' => (int) env('BOT_CYCLE_INTERVAL_SECONDS', 15),
    ],

    'telegram' => [
        'bot_token' => env('TELEGRAM_BOT_TOKEN'),
        'chat_id' => env('TELEGRAM_CHAT_ID'),
    ],

    'z_report' => [
        'enabled' => env('Z_REPORT_ENABLED', true),
        'time' => env('Z_REPORT_TIME', '05:05'),
        'timezone' => env('Z_REPORT_TIMEZONE', env('APP_TIMEZONE', 'Asia/Yekaterinburg')),
        'moonshine_notify' => env('Z_REPORT_MOONSHINE_NOTIFY', true),
    ],
];