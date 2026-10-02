<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/countries.php';

if (session_status() === PHP_SESSION_NONE) {
    $https = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
    session_name('drc_session');
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => (BASE_URL === '' ? '/' : BASE_URL . '/'),
        'secure'   => $https,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

// Initialize language from URL parameter
if (isset($_GET['lang']) && in_array($_GET['lang'], ['en', 'fr'], true)) {
    $_SESSION['drc_language'] = $_GET['lang'];
}
set_exception_handler(function ($ex) {
    $ref = bin2hex(random_bytes(4));
    error_log('DRC error [' . $ref . ']: ' . $ex->getMessage() . ' in ' . $ex->getFile() . ':' . $ex->getLine());
    if (!headers_sent()) { http_response_code(500); header('Content-Type: text/html; charset=UTF-8'); }
    echo '<!doctype html><meta charset="utf-8"><title>Something went wrong</title>'
       . '<body style="font-family:Arial,sans-serif;max-width:560px;margin:12vh auto;padding:0 20px;color:#111">'
       . '<h1>Something went wrong</h1><p>This page could not be loaded. Please try again. If it keeps happening, send this reference to support: <b>' . $ref . '</b></p>';
    if (defined('APP_DEBUG') && APP_DEBUG) {
        echo '<pre style="white-space:pre-wrap;background:#EBEBEB;padding:12px">' . htmlspecialchars($ex->getMessage() . "\n" . $ex->getFile() . ':' . $ex->getLine(), ENT_QUOTES, 'UTF-8') . '</pre>';
    }
    echo '</body>';
});
header('X-Frame-Options: SAMEORIGIN');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: strict-origin-when-cross-origin');

/* ---------- basics ---------- */
function e($v): string { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
function url(string $path = ''): string { return BASE_URL . '/' . ltrim($path, '/'); }
function redirect(string $path): void {
    header('Location: ' . (preg_match('#^https?://#', $path) ? $path : url($path)));
    exit;
}
function post(string $key): string {
    $v = $_POST[$key] ?? '';
    return is_string($v) ? trim($v) : '';
}
function is_post(): bool { return $_SERVER['REQUEST_METHOD'] === 'POST'; }

function db(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        try {
            $pdo = new PDO(
                'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4',
                DB_USER, DB_PASS,
                [
                    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES   => false,
                ]
            );
        } catch (PDOException $ex) {
            error_log('DRC DB connection failed: ' . $ex->getMessage());
            http_response_code(500);
            exit('The portal is temporarily unavailable. Please try again in a few minutes.');
        }
    }
    return $pdo;
}

/* ---------- flash messages ---------- */
function flash(string $type, string $msg): void { $_SESSION['flash'][] = ['t' => $type, 'm' => $msg]; }
function flashes_html(): string {
    $out = '';
    foreach ($_SESSION['flash'] ?? [] as $f) {
        $out .= '<div class="alert ' . e($f['t']) . '" role="status">' . e($f['m']) . '</div>';
    }
    unset($_SESSION['flash']);
    return $out;
}

/* ---------- CSRF ---------- */
function csrf_token(): string {
    if (empty($_SESSION['csrf'])) { $_SESSION['csrf'] = bin2hex(random_bytes(32)); }
    return $_SESSION['csrf'];
}
function csrf_field(): string { return '<input type="hidden" name="_csrf" value="' . e(csrf_token()) . '">'; }
function csrf_verify(): void {
    $t = $_POST['_csrf'] ?? '';
    if (!is_string($t) || !hash_equals(csrf_token(), $t)) {
        http_response_code(419);
        exit('Your session expired. Go back, refresh the page and try again.');
    }
}

/* ---------- Language ---------- */
function get_language(): string {
    $lang = $_SESSION['drc_language'] ?? 'en';
    // Ensure it's either 'en' or 'fr'
    return in_array($lang, ['en', 'fr'], true) ? $lang : 'en';
}

function t($key, $args = []): string {
    $lang = get_language();
    $file = __DIR__ . "/lang-{$lang}.php";

    if (!file_exists($file)) {
        // Fallback to English if French file doesn't exist
        $file = __DIR__ . "/lang-en.php";
    }

    $translations = require $file;
    $value = $translations[$key] ?? $key;

    // Simple argument replacement if needed
    foreach ($args as $k => $v) {
        $value = str_replace('{' . $k . '}', (string)$v, $value);
    }

    return (string)$value;
}

function set_language($lang): void {
    if (in_array($lang, ['en', 'fr'], true)) {
        $_SESSION['drc_language'] = $lang;
    }
}

function language_selector(): string {
    $current_lang = get_language();
    $html = '<div class="language-selector">';
    $html .= '<a href="?lang=en" class="' . ($current_lang === 'en' ? 'active' : '') . '">English</a>';
    $html .= '<a href="?lang=fr" class="' . ($current_lang === 'fr' ? 'active' : '') . '">Français</a>';
    $html .= '</div>';
    return $html;
}

/* ---------- auth: customer ---------- */
function html_head(string $title, string $bodyClass = ''): void {
    header('Content-Type: text/html; charset=UTF-8');
    $lang = get_language();
    echo '<!doctype html><html lang="' . $lang . '"><head><meta charset="utf-8">'
       . '<meta name="viewport" content="width=device-width,initial-scale=1">'
       . '<title>' . e($title) . ' — DRC Portal</title>'
       . '<link rel="icon" href="data:image/svg+xml,%3Csvg xmlns=\'http://www.w3.org/2000/svg\' viewBox=\'0 0 32 32\'%3E%3Crect width=\'32\' height=\'32\' rx=\'7\' fill=\'%230095DA\'/%3E%3Cpath d=\'M9 9h3v14H9zm5 0h1.5v14H14zm3.5 0H21v14h-3.5zm5 0H24v14h-1.5z\' fill=\'%23FCD116\'/%3E%3C/svg%3E">'
       . '<link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>'
       . '<link href="https://fonts.googleapis.com/css2?family=Bricolage+Grotesque:opsz,wght@12..96,500;12..96,700;12..96,800&family=Public+Sans:wght@400;500;600&display=swap" rel="stylesheet">'
       . '<link rel="stylesheet" href="' . e(url('assets/css/app.css')) . '?v=5">' // v=4 update kiya hai cache break karne ke liye
       . '</head><body class="' . e($bodyClass) . '">';
}

function current_customer(): ?array {
    static $cache = false;
    if ($cache !== false) { return $cache; }
    $cache = null;
    if (!empty($_SESSION['customer_id'])) {
        $st = db()->prepare("SELECT * FROM drc_customers WHERE id = ? AND status = 'active'");
        $st->execute([$_SESSION['customer_id']]);
        $row = $st->fetch();
        if ($row) { $cache = $row; } else { unset($_SESSION['customer_id']); }
    }
    return $cache;
}
function require_customer(): array {
    $c = current_customer();
    if (!$c) { redirect('index.php'); }
    return $c;
}

/* ---------- auth: admin ---------- */
function current_admin(): ?array {
    static $cache = false;
    if ($cache !== false) { return $cache; }
    $cache = null;
    if (!empty($_SESSION['admin_id'])) {
        $st = db()->prepare("SELECT * FROM drc_admins WHERE id = ? AND status = 'active'");
        $st->execute([$_SESSION['admin_id']]);
        $row = $st->fetch();
        if ($row) { $cache = $row; } else { unset($_SESSION['admin_id']); }
    }
    return $cache;
}
function require_admin(): array {
    $a = current_admin();
    if (!$a) {
        $_SESSION['admin_next'] = $_SERVER['REQUEST_URI'] ?? '';
        redirect('admin/login.php');
    }
    return $a;
}

/* ---------- login throttling ---------- */
function client_ip(): string { return substr($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0', 0, 45); }
function too_many_attempts(string $key): bool {
    $st = db()->prepare(
        'SELECT SUM(login_key = ?) AS by_key, SUM(ip = ?) AS by_ip
           FROM drc_login_attempts WHERE attempted_at > (NOW() - INTERVAL 15 MINUTE)'
    );
    $st->execute([$key, client_ip()]);
    $r = $st->fetch();
    return (int)($r['by_key'] ?? 0) >= 6 || (int)($r['by_ip'] ?? 0) >= 25;
}
function log_attempt(string $key): void {
    db()->prepare('INSERT INTO drc_login_attempts (ip, login_key) VALUES (?, ?)')->execute([client_ip(), $key]);
    if (random_int(1, 40) === 1) {
        db()->exec('DELETE FROM drc_login_attempts WHERE attempted_at < (NOW() - INTERVAL 1 DAY)');
    }
}
function clear_attempts(string $key): void {
    db()->prepare('DELETE FROM drc_login_attempts WHERE login_key = ?')->execute([$key]);
}

/* ---------- validation ---------- */
function gs1_check_ok(string $d): bool {
    if ($d === '' || !ctype_digit($d)) { return false; }
    $len = strlen($d);
    $sum = 0;
    $w = 3;
    for ($i = $len - 2; $i >= 0; $i--) {
        $sum += ((int)$d[$i]) * $w;
        $w = ($w === 3) ? 1 : 3;
    }
    return ((10 - ($sum % 10)) % 10) === (int)$d[$len - 1];
}
function digits_only(string $v): string { return preg_replace('/\D+/', '', $v) ?? ''; }
function is_gln(string $v): bool { return strlen($v) === 13 && gs1_check_ok($v); }
function is_gtin(string $v): bool { return in_array(strlen($v), [8, 12, 13, 14], true) && gs1_check_ok($v); }
function gs1_calc_check(string $payload): int {
    $sum = 0;
    $w = 3;
    for ($i = strlen($payload) - 1; $i >= 0; $i--) {
        $sum += ((int)$payload[$i]) * $w;
        $w = ($w === 3) ? 1 : 3;
    }
    return (10 - ($sum % 10)) % 10;
}
/* Corrected numbers to offer when a GLN/GTIN is missing its check digit or has a wrong one. */
function gs1_suggest(string $d, array $lengths): array {
    $out = [];
    if ($d === '' || !ctype_digit($d)) { return $out; }
    $len = strlen($d);
    if (in_array($len, $lengths, true) && gs1_check_ok($d)) { return $out; }
    if (in_array($len, $lengths, true) && $len > 1) { $out[] = substr($d, 0, -1) . gs1_calc_check(substr($d, 0, -1)); }
    if (in_array($len + 1, $lengths, true)) { $out[] = $d . gs1_calc_check($d); }
    return array_values(array_unique($out));
}
function gs1_error(string $label, string $d, array $lengths): string {
    if ($d === '') { return $label . ' is required.'; }
    $last = array_pop($lengths);
    $lenText = $lengths ? implode(', ', $lengths) . ' or ' . $last : (string)$last;
    $lengths[] = $last;
    $msg = in_array(strlen($d), $lengths, true)
        ? $label . ' check digit does not match.'
        : $label . ' must be ' . $lenText . ' digits (you entered ' . strlen($d) . ').';
    $s = gs1_suggest($d, $lengths);
    if ($s) { $msg .= ' Did you mean ' . implode(' or ', $s) . '?'; }
    return $msg;
}
function periods(): array { return ['Monthly', 'Quarterly', 'Half-yearly', 'Yearly']; }
function fmt_date(?string $d): string { return $d ? date('d M Y', strtotime($d)) : '—'; }

/* ---------- admin helpers ---------- */
function admin_notify_emails(): array {
    $all = [ADMIN_EMAIL];
    foreach (db()->query("SELECT email FROM drc_admins WHERE status = 'active'") as $r) { $all[] = $r['email']; }
    $seen = []; $out = [];
    foreach ($all as $m) {
        $k = strtolower(trim($m));
        if ($k !== '' && !isset($seen[$k])) { $seen[$k] = true; $out[] = $m; }
    }
    return $out;
}
function send_admin_invite(int $adminId, string $username, string $email): bool {
    $token = bin2hex(random_bytes(32));
    db()->prepare('UPDATE drc_admins SET invite_token = ?, invite_expires = DATE_ADD(NOW(), INTERVAL 48 HOUR) WHERE id = ?')
        ->execute([hash('sha256', $token), $adminId]);
    $link = SITE_URL . BASE_URL . '/admin/activate.php?token=' . $token;
    $body = '<p>Hello ' . e($username) . ',</p>'
          . '<p>You have been added as an administrator of the DRC portal. Activate your account by choosing a password. This link works for 48 hours.</p>'
          . email_button($link, 'Activate admin account')
          . '<p style="font-size:13px;color:#43403F">Your username is <strong>' . e($username) . '</strong>.</p>';
    return send_mail($email, 'Activate your DRC admin account', 'You are now a DRC admin', $body);
}

/* ---------- email ---------- */
function email_wrap(string $title, string $bodyHtml): string {
    return '<!doctype html><html><body style="margin:0;background:#EBEBEB;font-family:Arial,Helvetica,sans-serif;color:#111111">'
        . '<table width="100%" cellpadding="0" cellspacing="0" style="background:#EBEBEB;padding:24px 12px"><tr><td align="center">'
        . '<table width="560" cellpadding="0" cellspacing="0" style="max-width:560px;width:100%;background:#ffffff;border-radius:12px;overflow:hidden">'
        . '<tr><td style="background:#111111;padding:20px 28px;color:#EBEBEB;font-size:16px;font-weight:bold">' . e(SITE_NAME) . ' &nbsp;<span style="color:#A51616">|</span>&nbsp; DRC Portal</td></tr>'
        . '<tr><td style="padding:28px;font-size:15px;line-height:1.6;color:#3A3A3A"><h2 style="margin:0 0 14px;font-size:20px;color:#111111">' . e($title) . '</h2>' . $bodyHtml . '</td></tr>'
        . '<tr><td style="padding:16px 28px;background:#EBEBEB;font-size:12px;color:#43403F">This is an automated message from the DRC portal. Please do not reply.</td></tr>'
        . '</table></td></tr></table></body></html>';
}
function email_button(string $href, string $label): string {
    return '<p style="margin:22px 0"><a href="' . e($href) . '" style="background:#A51616;color:#ffffff;text-decoration:none;padding:12px 22px;border-radius:8px;font-weight:bold;display:inline-block">' . e($label) . '</a></p>';
}
function send_mail(string $to, string $subject, string $title, string $bodyHtml): bool {
    $subject = preg_replace('/[\r\n]+/', ' ', $subject);
    $headers = [
        'MIME-Version: 1.0',
        'Content-Type: text/html; charset=UTF-8',
        'From: ' . MAIL_FROM_NAME . ' <' . MAIL_FROM . '>',
        'Reply-To: ' . MAIL_FROM,
    ];
    $ok = @mail($to, '=?UTF-8?B?' . base64_encode($subject) . '?=', email_wrap($title, $bodyHtml), implode("\r\n", $headers), '-f' . MAIL_FROM);
    if (!$ok) { error_log('DRC mail failed to ' . $to . ' (' . $subject . ')'); }
    return $ok;
}
