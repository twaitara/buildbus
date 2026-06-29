<?php
/**
 * Blueprint login users — TEMPLATE.
 * Copy this file to `secret.php` (same folder) and set real passwords.
 * `secret.php` is gitignored and must never be committed.
 *
 * Two users: you (developer) and Ann. Give each person their own
 * username + password. 'name' is the label shown after login and stamped
 * on each save. 'role' => 'admin' can view the activity logs; 'editor' cannot.
 */
return [
    'users' => [
        ['user' => 'tito', 'pass' => 'set-a-strong-password', 'name' => 'Tito (developer)', 'role' => 'admin'],
        ['user' => 'ann',  'pass' => 'set-a-strong-password', 'name' => 'Ann',              'role' => 'editor'],
    ],
];
