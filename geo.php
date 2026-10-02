<?php
// Reverse geocoding: GPS coordinates -> country, for scan logs that have GPS but no country yet.
// Uses OpenStreetMap Nominatim first, then BigDataCloud as a fallback (both free, no API key).
if (!defined('GEO_NOMINATIM_URL'))    define('GEO_NOMINATIM_URL',    'https://nominatim.openstreetmap.org/reverse');
if (!defined('GEO_BIGDATACLOUD_URL')) define('GEO_BIGDATACLOUD_URL', 'https://api.bigdatacloud.net/data/reverse-geocode-client');
if (!defined('GEO_USER_AGENT'))       define('GEO_USER_AGENT',       'AuthenticatorGlobal/1.0 (+https://authenticatorglobal.com)');

function geo_http_json(string $url): ?array {
    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 3, CURLOPT_CONNECTTIMEOUT => 2,
            CURLOPT_USERAGENT => GEO_USER_AGENT,
        ]);
        $body = curl_exec($ch);
        $http = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        if ($body === false || $http !== 200) return null;
    } else {
        $ctx  = stream_context_create(['http' => ['timeout' => 3, 'header' => 'User-Agent: ' . GEO_USER_AGENT . "\r\n"]]);
        $body = @file_get_contents($url, false, $ctx);
        if ($body === false) return null;
    }
    $j = json_decode($body, true);
    return is_array($j) ? $j : null;
}

/** @return array{0:string,1:string}|null  [country_code, country_name] */
function geo_country(float $lat, float $lng): ?array {
    $la = number_format($lat, 6, '.', ''); $lo = number_format($lng, 6, '.', '');

    $j    = geo_http_json(GEO_NOMINATIM_URL . "?format=jsonv2&zoom=3&accept-language=en&lat=$la&lon=$lo");
    $cc   = strtoupper((string)($j['address']['country_code'] ?? ''));
    $name = (string)($j['address']['country'] ?? '');
    if (preg_match('/^[A-Z]{2}$/', $cc) && $name !== '') return [$cc, mb_substr($name, 0, 100)];

    $j    = geo_http_json(GEO_BIGDATACLOUD_URL . "?localityLanguage=en&latitude=$la&longitude=$lo");
    $cc   = strtoupper((string)($j['countryCode'] ?? ''));
    $name = (string)($j['countryName'] ?? '');
    if (preg_match('/^[A-Z]{2}$/', $cc) && $name !== '') return [$cc, mb_substr($name, 0, 100)];

    return null;
}

/**
 * Fill country for scans that have GPS but no country. Scans within ~1 km share one lookup.
 * Processes at most $maxGroups locations per call (newest first).
 * @return array{filled:int, failed:int}
 */
function backfill_countries(PDO $pdo, int $maxGroups): array {
    $groups = $pdo->query(
        "SELECT ROUND(latitude,2) AS la, ROUND(longitude,2) AS lo, AVG(latitude) AS alat, AVG(longitude) AS alng
         FROM scan_logs WHERE latitude IS NOT NULL AND (country_code IS NULL OR country_code = '')
         GROUP BY la, lo ORDER BY MAX(id) DESC LIMIT " . max(1, $maxGroups)
    )->fetchAll();

    $up = $pdo->prepare("UPDATE scan_logs SET country = ?, country_code = ?
                         WHERE latitude IS NOT NULL AND ROUND(latitude,2) = ? AND ROUND(longitude,2) = ?
                           AND (country_code IS NULL OR country_code = '')");
    $filled = $failed = 0;
    foreach ($groups as $i => $g) {
        if ($i > 0) usleep(1100000);                 // Nominatim allows max 1 request/second
        $r = geo_country((float)$g['alat'], (float)$g['alng']);
        if (!$r) { $failed++; continue; }
        $up->execute([$r[1], $r[0], $g['la'], $g['lo']]);
        $filled += $up->rowCount();
    }
    return ['filled' => $filled, 'failed' => $failed];
}