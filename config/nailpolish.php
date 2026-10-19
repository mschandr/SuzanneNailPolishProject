<?php

return [
    // The account with this email becomes an Admin when it is a new account, or when no Admin exists (recovery).
    'admin_email' => env('ADMIN_EMAIL'),

    // Number of accounts that may self-signup before it closes to invite-only.
    'self_signup_limit' => (int) env('SELF_SIGNUP_LIMIT', 100),

    // Which filesystem disk holds Manicure photos (see config/filesystems.php).
    'photos_disk' => env('PHOTOS_DISK', 'photos'),

    // Soft cap on photos per User, so storage costs stay predictable.
    'max_photos_per_user' => (int) env('MAX_PHOTOS_PER_USER', 500),

    // FOR LOAD TESTING ONLY. When true, every rate limit is lifted so one test machine can act as many users.
    // Never set this in a real production .env: it removes the protection against sign-in and report floods.
    'load_test_mode' => (bool) env('LOAD_TEST_MODE', false),
];
