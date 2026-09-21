<?php
// 1. Copy this file to "config.local.php" in the same folder and fill in your details.
//    (Easier: run configure-site.ps1 / 1-configure-site.bat, which writes this file for you.)
// 2. config.local.php overrides config.php and is never pushed to GitHub (it is git-ignored).
// 3. Delete any line you don't need - anything missing falls back to config.php.
return [
    // MySQL is recommended on Network Solutions. Create a database + user in your hosting control panel,
    // then copy the details here. Remove this whole 'db' block to use the built-in SQLite file instead.
    'db' => [
        'driver'     => 'mysql',
        'mysql_host' => 'localhost',          // the database host name shown in your control panel
        'mysql_port' => 3306,
        'mysql_name' => 'your_database_name',
        'mysql_user' => 'your_database_user',
        'mysql_pass' => 'your_database_password',
    ],

    // Who gets an email for every new submission, and the sender address (use one on your own domain).
    'notify_email' => 'info@camoncenter.org',
    'mail_from'    => 'no-reply@camoncenter.org',

    // Visit /admin/ on your site once: it asks you to pick a password and prints the line to paste here.
    'admin_password_hash' => '',
];
