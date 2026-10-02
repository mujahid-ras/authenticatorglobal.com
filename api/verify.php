<?php
require __DIR__ . '/config.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

function out(array $data, int $http = 200): void {
    http_response_code($http);
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    out(['status' => 'error', 'message' => 'Method not allowed'], 405);
}

$in   = json_decode(file_get_contents('php://input'), true) ?: [];
$code = normalize_code((string)($in['code'] ?? ''));
if ($code === '' || strlen($code) > 128) {
    out(['status' => 'error', 'message' => 'Please enter a valid code.'], 400);
}

// GPS (optional) — store only if both values are valid
$lat = $lng = $acc = null;
if (isset($in['lat'], $in['lng']) && is_numeric($in['lat']) && is_numeric($in['lng'])) {
    $la = (float)$in['lat']; $lo = (float)$in['lng'];
    if ($la >= -90 && $la <= 90 && $lo >= -180 && $lo <= 180) {
        $lat = $la; $lng = $lo;
        $acc = isset($in['acc']) && is_numeric($in['acc']) ? max(0, (int)$in['acc']) : null;
    }
}

// Country: from the browser's reverse-geocode of the GPS point; falls back to Cloudflare's IP country
$cc      = strtoupper(preg_replace('/[^A-Za-z]/', '', (string)($in['cc'] ?? '')));
$country = mb_substr(trim((string)preg_replace('/[^\p{L}\p{M}\s\'.,()\-]/u', '', (string)($in['country'] ?? ''))), 0, 100);
if (strlen($cc) !== 2 || $country === '') {
    $cc = ''; $country = '';
    $cf = strtoupper($_SERVER['HTTP_CF_IPCOUNTRY'] ?? '');
    if (preg_match('/^[A-Z]{2}$/', $cf) && !in_array($cf, ['XX', 'T1'], true)) {
        $cc = $cf;
        $country = class_exists('Locale') ? (Locale::getDisplayRegion('-' . $cf, 'en') ?: $cf) : $cf;
    }
}
$cc      = $cc !== '' ? $cc : null;
$country = $country !== '' ? $country : null;

$ip = $_SERVER['REMOTE_ADDR'] ?? null;
$ua = substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255);

$logSql = "INSERT INTO scan_logs (code_id, code, status, scan_number, latitude, longitude, accuracy_m, country, country_code, ip_address, user_agent)
           VALUES (?,?,?,?,?,?,?,?,?,?,?)";

try {
    $pdo->beginTransaction();

    // Lock the code row so simultaneous scans count correctly
    $st = $pdo->prepare("SELECT id, product_id, scan_count FROM product_codes WHERE code = ? FOR UPDATE");
    $st->execute([$code]);
    $row = $st->fetch();

    // ── Not in the system → Invalid ──
    if (!$row) {
        $pdo->prepare($logSql)->execute([null, $code, 'invalid', null, $lat, $lng, $acc, $country, $cc, $ip, $ua]);
        $pdo->commit();
        out([
            'status'  => 'invalid',
            'code'    => $code,
            'title'   => 'Invalid Code',
            'message' => 'Code not found. Please check the code and try again.',
        ]);
    }

    // Count this scan, then judge
    $count = (int)$row['scan_count'] + 1;
    $pdo->prepare("UPDATE product_codes
                   SET scan_count = ?, first_scanned_at = COALESCE(first_scanned_at, NOW()), last_scanned_at = NOW()
                   WHERE id = ?")
        ->execute([$count, $row['id']]);

    $status = scan_status($count);

    $pdo->prepare($logSql)->execute([$row['id'], $code, $status, $count, $lat, $lng, $acc, $country, $cc, $ip, $ua]);
    $pdo->commit();

    $resp = ['status' => $status, 'code' => $code, 'scan_count' => $count];

    if ($status === 'fake') {
        $resp['title']   = 'Code Status is Fake';
        $resp['message'] = 'Scanned more than ' . FAKE_SCAN_THRESHOLD . ' times. This code has been invalidated.';
    } else {
        try {
            $ps = $pdo->prepare("SELECT name, gtin, package_type FROM products WHERE id = ?");
            $ps->execute([$row['product_id']]);
        } catch (PDOException $e) {                    // package_type column not added yet
            $ps = $pdo->prepare("SELECT name, gtin FROM products WHERE id = ?");
            $ps->execute([$row['product_id']]);
        }
        $product = $ps->fetch() ?: ['name' => 'Unknown', 'gtin' => ''];
        $resp['product']      = $product['name'];
        $resp['gtin']         = $product['gtin'];
        $resp['package_type'] = $product['package_type'] ?? '';
        $resp['title']   = $status === 'suspicious'
            ? 'Code is valid but Scanned Multiple Times'
            : 'Product is genuine (a low scan count)';
    }
    out($resp);

} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    error_log('verify.php: ' . $e->getMessage());
    out(['status' => 'error', 'message' => 'Server error. Please try again.'], 500);
}
