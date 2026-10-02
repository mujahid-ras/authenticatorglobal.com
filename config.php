<?php
// ---- Database (same cPanel MySQL you used for the main site is fine; tables are prefixed drc_) ----
define('DB_HOST', 'localhost');
define('DB_NAME', 'adminag_drc');
define('DB_USER', 'adminag_mujahid');
define('DB_PASS', '9cKX@XQ,#(HR');

// ---- Site ----
define('SITE_URL', 'https://authenticatorglobal.com'); // no trailing slash
define('BASE_URL', '/drc');                            // folder this portal lives in
define('SITE_NAME', 'Authenticator Global');

// ---- Email ----
define('ADMIN_EMAIL', 'admin@authenticatorglobal.com');      // receives "new registration" emails
define('MAIL_FROM', 'no-reply@authenticatorglobal.com');     // must be a mailbox on your domain
define('MAIL_FROM_NAME', 'Authenticator Global DRC');

// ---- SMTP Configuration ----
// Set to true to use SMTP (PHPMailer), false to use native mail()
define('USE_SMTP', true);

// SMTP server configuration (defaults to same server for now)
define('SMTP_HOST', 'localhost');
define('SMTP_PORT', 587);
define('SMTP_USERNAME', 'no-reply@authenticatorglobal.com');     // Often same as MAIL_FROM
define('SMTP_PASSWORD', '');      // Set your SMTP password here
define('SMTP_ENCRYPTION', 'tls'); // 'tls' or 'ssl' or '' for none
define('SMTP_AUTH', true);
