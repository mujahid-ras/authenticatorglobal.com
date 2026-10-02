<?php
// ── SETTINGS ─────────────────────────────────────────────
// Scan-count rules (the count INCLUDES the scan being verified). Change these numbers whenever needed.
//   count <= SUSPICIOUS_SCAN_THRESHOLD                      -> Authentic ("Product is genuine")
//   SUSPICIOUS_SCAN_THRESHOLD < count <= FAKE_SCAN_THRESHOLD -> Suspicious (valid, but scanned multiple times)
//   count  > FAKE_SCAN_THRESHOLD                            -> Fake (code invalidated)
//   code not in the system                                  -> Invalid
define('SUSPICIOUS_SCAN_THRESHOLD', 5);
define('FAKE_SCAN_THRESHOLD', 10);


// Package types allowed for a product (pilot: strictly these four)
const PACKAGE_TYPES = ['Pack', 'Outer', 'Master', 'Pallet'];

function scan_status(int $count): string {
    if ($count > FAKE_SCAN_THRESHOLD)       return 'fake';
    if ($count > SUSPICIOUS_SCAN_THRESHOLD) return 'suspicious';
    return 'authentic';
}
 
// Code matching mode.
//   false (PILOT) : codes are stored and matched in UPPERCASE, so "a9632fwx" and "A9632FWX" are the same code.
//                   Matches the current index.html, which capitalizes everything. No other changes needed.
//   true          : exact-case matching (serials are mixed case in GS1 codes). Before switching, also do the
//                   index.html edits, run the ALTER TABLE lines at the end of schema.sql, and re-import codes.
define('CODES_CASE_SENSITIVE', false);
 
// Cleans what phone scanners may add around the code:
//  - a URL like https://site/?code=XXXX  -> XXXX (also URL-decoded, e.g. %1D)
//  - a symbology prefix such as ]d2 / ]Q1 / ]C1
//  - whitespace, control chars (GS1 group separator \x1D) and ( )
function normalize_code(string $c): string {
    if (preg_match('/[?&]code=([^&#]+)/i', $c, $m)) $c = rawurldecode($m[1]);
    $c = preg_replace('/^\][A-Za-z][0-9A-Za-z]/', '', trim($c));
    $c = preg_replace('/[\x00-\x20\x7F()]/', '', $c);
    return CODES_CASE_SENSITIVE ? $c : strtoupper($c);
}
 
// ── DATABASE ─────────────────────────────────────────────
// Credentials live in api/db_credentials.php (NOT committed to Git).
$cred = require __DIR__ . '/../api/db_credentials.php';
try {
    $pdo = new PDO(
        "mysql:host={$cred['host']};dbname={$cred['name']};charset=utf8mb4",
        $cred['user'],
        $cred['pass'],
        [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]
    );
} catch (PDOException $e) {
    http_response_code(500);
    exit('Database connection failed');
}

/* 
session_start();

$host = "localhost";
$db   = "adminag_clientlogin";
$user = "adminag_mujahid";
$pass = "9cKX@XQ,#(HR";

try {

    $pdo = new PDO(
        "mysql:host=$host;dbname=$db;charset=utf8mb4",
        $user,
        $pass
    );

    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
}
catch(PDOException $e)
{
    die("Database Connection Failed");
} */
?>