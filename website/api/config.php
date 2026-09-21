<?php
// Settings for the website forms.
// Private values (database password, admin password hash) belong in api/config.local.php,
// which uses the same format, overrides this file, and is NOT pushed to GitHub.
return [
    'db' => [
        'driver'     => 'sqlite',  // 'sqlite' works with no setup; 'mysql' is recommended on shared hosting
        'mysql_host' => 'localhost',
        'mysql_port' => 3306,
        'mysql_name' => '',
        'mysql_user' => '',
        'mysql_pass' => '',
    ],
    'notify_email' => '',          // e.g. 'info@camoncenter.org' - gets an email for every new submission
    'mail_from'    => '',          // e.g. 'no-reply@camoncenter.org' - use an address on your own domain
    'admin_password_hash' => '',   // create it on the first visit to /admin/, then paste it into config.local.php
    'rate_limit_per_hour' => 8,    // max submissions per visitor (IP) per hour
];
