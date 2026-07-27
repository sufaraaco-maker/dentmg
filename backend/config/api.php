<?php

return [
    // General-purpose api/* throttle (AppServiceProvider's RateLimiter::for('api', ...)).
    // Raised only in CI's E2E .env (see .github/workflows/ci.yml) — production keeps this default.
    'throttle_per_minute' => (int) env('API_THROTTLE_PER_MINUTE', 120),
];
