<?php
// Saves a "report suspicious product" submission. Pilot: stored in the database only — no email / notification.
require __DIR__ . '/config.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

function out(array $data, int $http = 200): void {
    http_response_code($http);
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}
function clean(string $s, int $max, bool $multiline = false): string {
    $s = (string)preg_replace($multiline ? '/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u' : '/[\x00-\x1F\x7F]/u', '', $s);
    return mb_substr(trim($s), 0, $max);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') out(['status' => 'error', 'message' => 'Method not allowed'], 405);

$in = json_decode(file_get_contents('php://input'), true);
if (!is_array($in)) out(['status' => 'error', 'message' => 'Invalid request.'], 400);

// Honeypot: real users never fill this hidden field. Pretend success, store nothing.
if (!empty($in['website'])) out(['status' => 'ok']);

$code    = normalize_code((string)($in['code'] ?? ''));
$email   = clean((string)($in['email'] ?? ''), 255);
$mobile  = clean((string)($in['mobile'] ?? ''), 30);
$country = clean((string)($in['country'] ?? ''), 100);
$shop    = clean((string)($in['shop'] ?? ''), 255);
$address = clean((string)($in['address'] ?? ''), 500);
$remarks = clean((string)($in['remarks'] ?? ''), 2000, true);

$errors = [];
if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL))          $errors['email']   = 'Enter a valid email address.';
if (!preg_match('/^\+?[0-9][0-9\s\-()]{5,19}$/', $mobile))                $errors['mobile']  = 'Enter a valid mobile number.';
if ($country === '')                                                      $errors['country'] = 'Country is required.';
if ($shop === '')                                                         $errors['shop']    = 'Shop / Retailer name is required.';
if ($errors) out(['status' => 'error', 'message' => reset($errors), 'fields' => $errors], 400);

try {
    // Only codes that are really in the "suspicious" range can be reported
    $st = $pdo->prepare("SELECT id, scan_count FROM product_codes WHERE code = ?");
    $st->execute([$code]);
    $row = $st->fetch();
    if (!$row || (int)$row['scan_count'] <= SUSPICIOUS_SCAN_THRESHOLD) {
        out(['status' => 'error', 'message' => 'This code cannot be reported.'], 422);
    }

    // Simple spam guard: max 5 reports per IP per hour
    $ip = $_SERVER['REMOTE_ADDR'] ?? null;
    $rc = $pdo->prepare("SELECT COUNT(*) FROM suspicious_reports WHERE ip_address = ? AND created_at > (NOW() - INTERVAL 1 HOUR)");
    $rc->execute([$ip]);
    if ((int)$rc->fetchColumn() >= 5) out(['status' => 'error', 'message' => 'Too many reports. Please try again later.'], 429);

    $pdo->prepare("INSERT INTO suspicious_reports
        (code_id, code, scan_count, email, mobile, country, shop_name, address, remarks, ip_address, user_agent)
        VALUES (?,?,?,?,?,?,?,?,?,?,?)")
        ->execute([$row['id'], $code, (int)$row['scan_count'], $email, $mobile, $country, $shop,
                   $address !== '' ? $address : null, $remarks !== '' ? $remarks : null,
                   $ip, substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255)]);

    out(['status' => 'ok']);
} catch (Throwable $e) {
    error_log('report.php: ' . $e->getMessage());
    out(['status' => 'error', 'message' => 'Server error. Please try again.'], 500);
}
