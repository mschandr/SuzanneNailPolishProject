<?php

return [
    // The account with this email becomes an Admin when it is a new account, or when no Admin exists (recovery).
    'admin_email' => env('ADMIN_EMAIL'),

    // Number of accounts that may self-signup before it closes to invite-only.
    'self_signup_limit' => (int) env('SELF_SIGNUP_LIMIT', 100),
];
