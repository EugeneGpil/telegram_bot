<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Bot credentials
    |--------------------------------------------------------------------------
    */

    'token' => env('TELEGRAM_BOT_TOKEN'),

    // Default chat to send to when --chat-id is not given.
    'chat_id' => env('TELEGRAM_CHAT_ID'),

    /*
    |--------------------------------------------------------------------------
    | Scheduled messages
    |--------------------------------------------------------------------------
    |
    | --at times and the late-send cutoff are interpreted in this timezone
    | ("+07:00" or "Asia/Bangkok" style).
    |
    */

    'schedule_timezone' => env('SCHEDULE_TZ', '+07:00'),

    // A due message this many minutes late (or less) still counts as on time.
    'on_time_grace_minutes' => 5,

    // A late message is only sent while it is still the same day and before
    // this hour; otherwise it is cancelled.
    'late_cutoff_hour' => 18,

    // A failed send is retried on later dispatch runs up to this many times.
    'max_send_attempts' => 5,

];
