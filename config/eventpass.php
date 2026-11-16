<?php

return [
    // SSE: how long one connection lives, how often it polls, how often it pings.
    'stream_seconds' => (int) env('EVENTPASS_STREAM_SECONDS', 30),
    'stream_poll_ms' => (int) env('EVENTPASS_STREAM_POLL_MS', 1000),
    'heartbeat_seconds' => 15,

    'max_events_per_user' => 20,
    'max_devices_per_event' => 10,

    'max_demo_users' => (int) env('EVENTPASS_MAX_DEMO_USERS', 200),
    'demo_ttl_hours' => 24,

    'horizon_emails' => array_values(array_filter(array_map(
        'trim',
        explode(',', (string) env('HORIZON_ALLOWED_EMAILS', '')),
    ))),
];
