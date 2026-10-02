<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
session_start();
if (!isset($_SESSION['admin_logged_in'])) { header('Location: login.php'); exit; }

require __DIR__ . '/../config.php';
require __DIR__ . '/../../api/geo.php';
// ── helpers ──────────────────────────────────────────────
function h($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
 
/** Current query string with overrides (null/'' removes a key). */
function qs(array $o = []): string {
    $q = array_merge($_GET, $o);
    foreach ($q as $k => $v) if ($v === null || $v === '') unset($q[$k]);
    return '?' . http_build_query($q);
}
function csv_safe($v): string {           // stop spreadsheet formula injection
    $v = (string)$v;
    return ($v !== '' && strpos("=+-@\t\r", $v[0]) !== false) ? "'" . $v : $v;
}
function status_badge(string $s): string {
    $lbl = ['authentic' => 'Authentic', 'suspicious' => 'Suspicious', 'fake' => 'Fake', 'invalid' => 'Invalid'];
    return '<span class="badge b-' . h($s) . '">' . h($lbl[$s] ?? $s) . '</span>';
}
function loc_cell(array $l): string {
    $name = ($l['country'] ?? '') ?: ($l['country_code'] ?? '');
    $html = '<div>' . h($name ?: 'Unknown') . '</div>';
    if ($l['latitude'] !== null) {
        $html .= '<a class="lnk" target="_blank" rel="noopener" href="https://www.google.com/maps?q='
               . h($l['latitude']) . ',' . h($l['longitude']) . '">Click here to see precise location ↗</a>';
    } else {
        $html .= '<span class="mut">Precise location not shared</span>';
    }
    return $html;
}
function pager(int $page, int $pages, int $total, int $per): void {
    $link = function ($p, $label, $off = false) {
        return $off ? '<span class="dis">' . $label . '</span>' : '<a href="' . h(qs(['page' => $p])) . '">' . $label . '</a>';
    };
    echo '<div class="pager"><span>' . number_format($total) . ' records · Page ' . $page . ' of ' . $pages . '</span><span class="pg">';
    echo $link(1, '« First', $page <= 1), $link($page - 1, '‹ Prev', $page <= 1);
    for ($i = max(1, $page - 2); $i <= min($pages, $page + 2); $i++) echo $i == $page ? '<b>' . $i . '</b>' : $link($i, (string)$i);
    echo $link($page + 1, 'Next ›', $page >= $pages), $link($pages, 'Last »', $page >= $pages), '</span><span class="pg">Per page: ';
    foreach ([25, 50, 100] as $n) echo $n == $per ? '<b>' . $n . '</b>' : '<a href="' . h(qs(['per' => $n, 'page' => 1])) . '">' . $n . '</a>';
    echo '</span></div>';
}
 
if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(16));
// Country columns exist? (they are added by api/migrate_v2.sql)
$hasCountry = (bool)$pdo->query("SHOW COLUMNS FROM scan_logs LIKE 'country_code'")->fetch();
// Package type column exists? (added by api/migrate_v3.sql)
$hasPkg = (bool)$pdo->query("SHOW COLUMNS FROM products LIKE 'package_type'")->fetch();
$pkSel  = $hasPkg ? 'p.package_type' : "'' AS package_type";
$msg = ''; $msgType = 'success';
 
// ── ACTIONS (add product / add codes) ────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hash_equals($_SESSION['csrf'], $_POST['csrf'] ?? '')) {
        $msg = 'Invalid session token. Reload the page.'; $msgType = 'error';
    } elseif (($_POST['action'] ?? '') === 'fill_countries') {
        if (!$hasCountry) { $msg = 'Run api/migrate_v2.sql first (country columns are missing).'; $msgType = 'error'; }
        else {
            try {
                $r = backfill_countries($pdo, 25);
                if ($r['filled'] > 0) $msg = $r['filled'] . ' scan(s) updated with their country.' . ($r['failed'] ? ' Some locations could not be looked up — press the button again.' : '');
                elseif ($r['failed'] > 0) { $msg = 'Could not reach the geocoding services from the server. Check that outbound HTTPS is allowed.'; $msgType = 'error'; }
                else $msg = 'Nothing to update.';
            } catch (Throwable $e) { error_log('fill_countries: ' . $e->getMessage()); $msg = 'Country update failed.'; $msgType = 'error'; }
        }
    } elseif (($_POST['action'] ?? '') === 'add_product') {
        $name = trim($_POST['name'] ?? '');
        $gtin = preg_replace('/\s+/', '', $_POST['gtin'] ?? '');
        $pkg  = (string)($_POST['package_type'] ?? '');
        if ($name === '' || !preg_match('/^\d{8,14}$/', $gtin)) {
            $msg = 'Product name and a numeric GTIN (8–14 digits) are required.'; $msgType = 'error';
        } elseif (!in_array($pkg, PACKAGE_TYPES, true)) {
            $msg = 'Choose a package type: ' . implode(', ', PACKAGE_TYPES) . '.'; $msgType = 'error';
        } elseif (!$hasPkg) {
            $msg = 'Run api/migrate_v3.sql first (package_type column is missing).'; $msgType = 'error';
        } else {
            try {
                $pdo->prepare("INSERT INTO products (name, gtin, package_type) VALUES (?,?,?)")->execute([$name, $gtin, $pkg]);
                $msg = 'Product added.';
            } catch (PDOException $e) { $msg = 'A product with this GTIN already exists.'; $msgType = 'error'; }
        }
    } elseif (($_POST['action'] ?? '') === 'set_package_type') {
        $pkg = (string)($_POST['package_type'] ?? '');
        if (!$hasPkg || !in_array($pkg, PACKAGE_TYPES, true)) { $msg = 'Invalid package type.'; $msgType = 'error'; }
        else {
            $pdo->prepare("UPDATE products SET package_type = ? WHERE id = ?")->execute([$pkg, (int)($_POST['product_id'] ?? 0)]);
            $msg = 'Package type updated.';
        }
    } elseif (($_POST['action'] ?? '') === 'add_codes') {
        $pid   = (int)($_POST['product_id'] ?? 0);
        $codes = [];
        foreach (preg_split('/\R/', (string)($_POST['codes'] ?? '')) as $l) {
            $l = normalize_code($l);
            if ($l !== '' && strlen($l) <= 128) $codes[$l] = true;
        }
        if (!$pid || !$codes) {
            $msg = 'Select a product and paste at least one code.'; $msgType = 'error';
        } else {
            $pdo->beginTransaction();
            $ins = $pdo->prepare("INSERT IGNORE INTO product_codes (product_id, code) VALUES (?,?)");
            $added = 0;
            foreach (array_keys($codes) as $c) { $ins->execute([$pid, $c]); $added += $ins->rowCount(); }
            $pdo->commit();
            $skipped = count($codes) - $added;
            $msg = "$added code(s) added" . ($skipped ? ", $skipped skipped (already exist)." : '.');
        }
    }
}
 
// ── AUTO-FILL COUNTRIES (a few locations per page view; pauses 10 min if the lookup service is unreachable) ──
if ($hasCountry && $_SERVER['REQUEST_METHOD'] === 'GET' && !isset($_GET['export']) && time() >= (int)($_SESSION['geo_skip_until'] ?? 0)) {
    try {
        $r = backfill_countries($pdo, 2);
        if ($r['failed'] > 0 && $r['filled'] === 0) $_SESSION['geo_skip_until'] = time() + 600;
    } catch (Throwable $e) { error_log('auto backfill: ' . $e->getMessage()); $_SESSION['geo_skip_until'] = time() + 600; }
}
$missingGeo = $hasCountry ? (int)$pdo->query("SELECT COUNT(*) FROM scan_logs WHERE latitude IS NOT NULL AND (country_code IS NULL OR country_code = '')")->fetchColumn() : 0;
 
// ── REQUEST STATE ────────────────────────────────────────
$S = (int)SUSPICIOUS_SCAN_THRESHOLD; $F = (int)FAKE_SCAN_THRESHOLD;
 
$tab  = $_GET['tab'] ?? 'top';
$code = normalize_code((string)($_GET['code'] ?? ''));
if ($code !== '') $tab = 'history';
if (!in_array($tab, ['top', 'recent', 'search', 'history'], true) || ($tab === 'history' && $code === '')) $tab = 'top';
 
$perReq = (int)($_GET['per'] ?? 25);
$per  = in_array($perReq, [25, 50, 100], true) ? $perReq : 25;
$page = max(1, (int)($_GET['page'] ?? 1));
 
$f = [
    'cc'     => strtoupper(substr(preg_replace('/[^A-Za-z]/', '', (string)($_GET['cc'] ?? '')), 0, 2)),
    'from'   => preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['from'] ?? '') ? $_GET['from'] : '',
    'to'     => preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['to'] ?? '') ? $_GET['to'] : '',
    'status' => in_array($_GET['status'] ?? '', ['authentic', 'suspicious', 'fake', 'invalid'], true) ? $_GET['status'] : '',
    'q'      => normalize_code((string)($_GET['q'] ?? '')),
];
 
// ── QUERY FOR THE ACTIVE TAB ─────────────────────────────
$params = [];
if ($tab === 'top') {
    $from  = "FROM product_codes c JOIN products p ON p.id = c.product_id WHERE c.scan_count > 0";
    $cols  = "c.code, c.scan_count, c.last_scanned_at, p.name, p.gtin, $pkSel";
    $order = "ORDER BY c.scan_count DESC, c.last_scanned_at DESC";
} else {
    $w = [];
    if ($tab === 'history') { $w[] = 'l.code = ?'; $params[] = $code; }
    if ($tab === 'search') {
        if ($f['cc'] !== '' && $hasCountry) { $w[] = 'l.country_code = ?'; $params[] = $f['cc']; }
        if ($f['from'] !== '')   { $w[] = 'l.scanned_at >= ?';  $params[] = $f['from'] . ' 00:00:00'; }
        if ($f['to'] !== '')     { $w[] = 'l.scanned_at < DATE_ADD(?, INTERVAL 1 DAY)'; $params[] = $f['to']; }
        if ($f['status'] !== '') { $w[] = 'l.status = ?';       $params[] = $f['status']; }
        if ($f['q'] !== '')      { $w[] = 'l.code LIKE ?';      $params[] = '%' . str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $f['q']) . '%'; }
    }
    $from  = "FROM scan_logs l LEFT JOIN product_codes c ON c.id = l.code_id LEFT JOIN products p ON p.id = c.product_id"
           . ($w ? ' WHERE ' . implode(' AND ', $w) : '');
    $cols  = "l.*, p.name, p.gtin, $pkSel";
    $order = "ORDER BY l.scanned_at DESC, l.id DESC";
}
$cs = $pdo->prepare("SELECT COUNT(*) $from"); $cs->execute($params);
$total = (int)$cs->fetchColumn();
$pages = max(1, (int)ceil($total / $per));
$page  = min($page, $pages);
 
// ── CSV EXPORT (all rows matching the current tab/filters) ──
if (isset($_GET['export'])) {
    session_write_close();
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="ag_' . $tab . '_' . date('Ymd_His') . '.csv"');
    echo "\xEF\xBB\xBF";
    $o = fopen('php://output', 'w');
    $put = function (array $r) use ($o) { fputcsv($o, $r, ',', '"', '\\'); };
    $st = $pdo->prepare("SELECT $cols $from $order"); $st->execute($params);
    if ($tab === 'top') {
        $put(['Code', 'Product', 'Package type', 'GTIN', 'Total scans', 'Last scan', 'Status']);
        while ($r = $st->fetch()) $put([csv_safe($r['code']), csv_safe($r['name']), $r['package_type'], $r['gtin'], $r['scan_count'], $r['last_scanned_at'], scan_status((int)$r['scan_count'])]);
    } else {
        $put(['Time', 'Code', 'Product', 'Package type', 'GTIN', 'Status', 'Scan #', 'Country', 'Country code', 'Latitude', 'Longitude', 'Google Maps', 'IP']);
        while ($r = $st->fetch()) $put([
            $r['scanned_at'], csv_safe($r['code']), csv_safe($r['name'] ?? ''), $r['package_type'] ?? '', $r['gtin'] ?? '', $r['status'], $r['scan_number'] ?? '',
            csv_safe($r['country'] ?? ''), $r['country_code'] ?? '', $r['latitude'] ?? '', $r['longitude'] ?? '',
            $r['latitude'] !== null ? 'https://www.google.com/maps?q=' . $r['latitude'] . ',' . $r['longitude'] : '', $r['ip_address'],
        ]);
    }
    exit;
}
 
$st = $pdo->prepare("SELECT $cols $from $order LIMIT $per OFFSET " . (($page - 1) * $per));
$st->execute($params);
$rows = $st->fetchAll();
 
// ── STATS / PRODUCTS / FILTER LISTS ──────────────────────
$stats = $pdo->query("SELECT
    (SELECT COUNT(*) FROM products) AS products,
    (SELECT COALESCE(SUM(scan_count),0) FROM product_codes) AS scans,
    (SELECT COUNT(*) FROM product_codes WHERE scan_count BETWEEN 1 AND $S) AS authentic,
    (SELECT COUNT(*) FROM product_codes WHERE scan_count > $S AND scan_count <= $F) AS suspicious,
    (SELECT COUNT(*) FROM product_codes WHERE scan_count > $F) AS fake,
    (SELECT COUNT(*) FROM scan_logs WHERE status='invalid') AS invalid")->fetch();
 
$products = $pdo->query("SELECT p.id, p.name, p.gtin, $pkSel, COUNT(c.id) AS codes,
    COALESCE(SUM(c.scan_count),0) AS scans, COALESCE(SUM(c.scan_count > $S),0) AS flagged
    FROM products p LEFT JOIN product_codes c ON c.product_id = p.id
    GROUP BY p.id ORDER BY scans DESC, p.name")->fetchAll();
 
$countries = !$hasCountry ? [] : $pdo->query("SELECT country_code, MAX(country) AS country FROM scan_logs
    WHERE country_code IS NOT NULL GROUP BY country_code ORDER BY country")->fetchAll();
 
$hist = null;
if ($tab === 'history') {
    $hs = $pdo->prepare("SELECT c.scan_count, p.name, p.gtin, $pkSel FROM product_codes c JOIN products p ON p.id = c.product_id WHERE c.code = ?");
    $hs->execute([$code]); $hist = $hs->fetch();
}
$tabTitle = ['top' => 'Most Scanned Codes', 'recent' => 'Recent Scans', 'search' => 'Search Results', 'history' => 'Scan history'][$tab];
?>
<!DOCTYPE html>
<html lang="en"><head>
<meta charset="UTF-8"/><meta name="viewport" content="width=device-width, initial-scale=1.0"/>
<title>Scan Dashboard — Authenticator Global</title>
<style>
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
:root{--bg:#F6F6F6;--border:rgba(58,58,58,.10);--bm:rgba(58,58,58,.18);--dark:#3A3A3A;--mid:#6b6b6b;--light:#9a9a9a;
--crimson:#930E16;--cbg:rgba(147,14,22,.06);--cbd:rgba(147,14,22,.2);--green:#1a7a3c;--amber:#8a6100;--yellow:#F2C200}
body{background:var(--bg);color:var(--dark);font-family:system-ui,sans-serif;font-size:14px}
.top{background:#fff;border-bottom:1px solid var(--border);padding:16px 32px;display:flex;justify-content:space-between;align-items:center}
.top h1{font-size:18px;font-weight:600}.top h1 em{color:var(--crimson)}
.top a{color:var(--mid);text-decoration:none;border:1px solid var(--bm);padding:6px 14px;border-radius:6px;font-size:12px}
.top a:hover{color:var(--crimson);border-color:var(--crimson)}
.wrap{max-width:1250px;margin:0 auto;padding:28px 24px}
.stats{display:grid;grid-template-columns:repeat(6,1fr);gap:14px;margin-bottom:24px}
.stat{background:#fff;border:1px solid var(--border);border-top:3px solid var(--bm);border-radius:10px;padding:16px 18px}
.stat b{font-size:28px;font-weight:500;display:block}.stat span{font-size:11px;letter-spacing:1px;text-transform:uppercase;color:var(--light)}
.stat.ok{border-top-color:var(--green)}.stat.ok b{color:var(--green)}
.stat.warn{background:#FFF8D6;border-color:rgba(196,150,0,.45);border-top-color:var(--yellow)}.stat.warn b,.stat.warn span{color:var(--amber)}
.stat.bad{background:var(--cbg);border-color:var(--cbd);border-top-color:var(--crimson)}.stat.bad b,.stat.bad span{color:var(--crimson)}
.panel{background:#fff;border:1px solid var(--border);border-radius:12px;margin-bottom:24px;overflow:hidden}
.ph{padding:16px 22px;border-bottom:1px solid var(--border);font-weight:600;display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap}
.pb{padding:22px}
.msg{padding:12px 16px;border-radius:8px;margin-bottom:20px;font-size:13px}
.msg.success{background:rgba(26,122,60,.07);border:1px solid rgba(26,122,60,.25);color:var(--green)}
.msg.error{background:var(--cbg);border:1px solid var(--cbd);color:var(--crimson)}
.grid{display:grid;grid-template-columns:1fr 1fr;gap:24px}
label{display:block;font-size:11px;letter-spacing:1.5px;text-transform:uppercase;color:var(--light);margin:0 0 6px}
input,select,textarea{width:100%;background:var(--bg);border:1.5px solid var(--bm);border-radius:8px;padding:10px 12px;font:inherit;margin-bottom:14px}
textarea{min-height:110px;font-family:ui-monospace,monospace;font-size:12.5px}
input:focus,select:focus,textarea:focus{outline:none;border-color:var(--crimson)}
button,.btn{background:var(--crimson);color:#fff;border:0;border-radius:7px;padding:10px 20px;font:inherit;font-weight:500;cursor:pointer;text-decoration:none;display:inline-block}
.btn.ghost{background:#fff;color:var(--mid);border:1px solid var(--bm)}.btn.ghost:hover{color:var(--crimson);border-color:var(--crimson)}
.btn.sm{padding:7px 14px;font-size:12px}
.tw{overflow-x:auto}table{width:100%;border-collapse:collapse}
th{font-size:10px;letter-spacing:1.5px;text-transform:uppercase;color:var(--light);text-align:left;padding:10px 14px;border-bottom:1px solid var(--border);white-space:nowrap}
td{padding:11px 14px;border-bottom:1px solid var(--border);font-size:13px;vertical-align:top}tr:last-child td{border-bottom:0}
.mono{font-family:ui-monospace,monospace;font-size:12px}.mut{color:var(--light);font-size:12px}
.badge{display:inline-block;padding:2px 9px;border-radius:4px;font-size:11px;font-family:ui-monospace,monospace}
.b-authentic{background:rgba(26,122,60,.08);color:var(--green);border:1px solid rgba(26,122,60,.25)}
.b-suspicious{background:#FFF8D6;color:var(--amber);border:1px solid rgba(196,150,0,.45)}
.b-fake,.b-invalid{background:var(--cbg);color:var(--crimson);border:1px solid var(--cbd)}
a.lnk{color:var(--crimson);text-decoration:none}a.lnk:hover{text-decoration:underline}
.empty{padding:28px;text-align:center;color:var(--light)}
.tabs{display:flex;gap:4px;border-bottom:1px solid var(--border);padding:0 14px;flex-wrap:wrap;background:#fff}
.tabs a{padding:14px 18px;text-decoration:none;color:var(--mid);font-weight:500;border-bottom:2px solid transparent;margin-bottom:-1px}
.tabs a:hover{color:var(--crimson)}.tabs a.on{color:var(--crimson);border-bottom-color:var(--crimson)}
.filters{display:grid;grid-template-columns:repeat(5,1fr) auto;gap:12px;align-items:end;padding:18px 22px;border-bottom:1px solid var(--border);background:#fbfbfb}
.filters input,.filters select{margin-bottom:0}.filters .act{display:flex;gap:8px}
.pager{display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap;padding:14px 22px;border-top:1px solid var(--border);font-size:12.5px;color:var(--mid)}
.pager .pg{display:flex;gap:6px;align-items:center}.pager a,.pager b,.pager .dis{padding:4px 9px;border-radius:5px;text-decoration:none}
.pager a{color:var(--crimson);border:1px solid var(--bm)}.pager b{background:var(--crimson);color:#fff}.pager .dis{color:var(--light);border:1px solid var(--border)}
.sum{padding:14px 22px;border-bottom:1px solid var(--border);display:flex;gap:22px;flex-wrap:wrap;font-size:13px}
details.manage summary{padding:16px 22px;font-weight:600;cursor:pointer}details.manage[open] summary{border-bottom:1px solid var(--border)}
@media(max-width:900px){.stats{grid-template-columns:1fr 1fr}.grid{grid-template-columns:1fr}.filters{grid-template-columns:1fr 1fr}}
</style></head><body>
<div class="top"><h1>Scan <em>Dashboard</em></h1><a href="index.php">← Partners</a></div>
<div class="wrap">
 
<?php if ($msg): ?><div class="msg <?= h($msgType) ?>"><?= h($msg) ?></div><?php endif; ?>
 
<?php if (!$hasCountry): ?><div class="msg error">Country columns are missing in the database. Run <b>api/migrate_v2.sql</b> in phpMyAdmin, then reload.</div><?php endif; ?>
 
<?php if (!$hasPkg): ?><div class="msg error">Package type column is missing in the database. Run <b>api/migrate_v3.sql</b> in phpMyAdmin, then reload.</div><?php endif; ?>
 
<div class="stats">
  <div class="stat"><b><?= (int)$stats['products'] ?></b><span>Products</span></div>
  <div class="stat"><b><?= number_format((int)$stats['scans']) ?></b><span>Total scans</span></div>
  <div class="stat ok"><b><?= (int)$stats['authentic'] ?></b><span>Authentic codes (≤<?= $S ?> scans)</span></div>
  <div class="stat warn"><b><?= (int)$stats['suspicious'] ?></b><span>⚠ Suspicious codes (&gt;<?= $S ?>)</span></div>
  <div class="stat bad"><b><?= (int)$stats['fake'] ?></b><span>Fake codes (&gt;<?= $F ?> scans)</span></div>
  <div class="stat bad"><b><?= (int)$stats['invalid'] ?></b><span>Invalid attempts (not in system)</span></div>
</div>
 
<details class="panel manage" <?= $msg ? 'open' : '' ?>><summary>Manage products &amp; codes</summary>
  <div class="pb"><div class="grid">
    <div><form method="POST"><input type="hidden" name="csrf" value="<?= h($_SESSION['csrf']) ?>"/><input type="hidden" name="action" value="add_product"/>
      <label>Product name</label><input name="name" required/>
      <label>GTIN</label><input name="gtin" inputmode="numeric" placeholder="8–14 digits" required/>
      <label>Package type</label>
      <select name="package_type" required><option value="">Select…</option>
        <?php foreach (PACKAGE_TYPES as $pt): ?><option value="<?= h($pt) ?>"><?= h($pt) ?></option><?php endforeach; ?>
      </select>
      <button>Add product</button></form></div>
    <div><form method="POST"><input type="hidden" name="csrf" value="<?= h($_SESSION['csrf']) ?>"/><input type="hidden" name="action" value="add_codes"/>
      <label>Product</label>
      <select name="product_id" required><option value="">Select…</option>
        <?php foreach ($products as $p): ?><option value="<?= (int)$p['id'] ?>"><?= h($p['name']) ?> — <?= h($p['package_type'] ?: 'no type') ?> (<?= h($p['gtin']) ?>)</option><?php endforeach; ?>
      </select>
      <label>Codes (one per line)</label><textarea name="codes" required></textarea>
      <button>Add codes</button></form></div>
  </div></div>
  <div class="tw">
  <?php if (!$products): ?><div class="empty">No products yet.</div><?php else: ?>
  <table><thead><tr><th>Product</th><th>Package type</th><th>GTIN</th><th>Codes</th><th>Total scans</th><th>Flagged codes</th></tr></thead><tbody>
  <?php foreach ($products as $p): ?><tr>
    <td><?= h($p['name']) ?></td>
    <td><form method="POST" style="display:flex;gap:6px;align-items:center">
      <input type="hidden" name="csrf" value="<?= h($_SESSION['csrf']) ?>"/><input type="hidden" name="action" value="set_package_type"/><input type="hidden" name="product_id" value="<?= (int)$p['id'] ?>"/>
      <select name="package_type" style="margin:0;padding:6px 8px;width:auto"><?php foreach (PACKAGE_TYPES as $pt): ?><option <?= $p['package_type'] === $pt ? 'selected' : '' ?>><?= h($pt) ?></option><?php endforeach; ?></select>
      <button class="btn sm" style="padding:6px 10px">Save</button></form></td>
    <td class="mono"><?= h($p['gtin']) ?></td><td><?= (int)$p['codes'] ?></td>
    <td><b><?= (int)$p['scans'] ?></b></td><td><?= (int)$p['flagged'] ? '<span class="badge b-suspicious">' . (int)$p['flagged'] . '</span>' : '—' ?></td>
  </tr><?php endforeach; ?></tbody></table><?php endif; ?></div>
</details>
 
<div class="panel">
  <div class="tabs">
    <a class="<?= $tab === 'top' ? 'on' : '' ?>" href="?tab=top">Most Scanned Codes</a>
    <a class="<?= $tab === 'recent' ? 'on' : '' ?>" href="?tab=recent">Recent Scans</a>
    <a class="<?= $tab === 'search' ? 'on' : '' ?>" href="?tab=search">Search Filter</a>
    <?php if ($code !== ''): ?><a class="on" href="<?= h(qs()) ?>">Code History: <?= h(strlen($code) > 14 ? substr($code, 0, 6) . '…' . substr($code, -6) : $code) ?></a><?php endif; ?>
  </div>
 
  <?php if ($tab === 'search'): ?>
  <form method="GET" class="filters"><input type="hidden" name="tab" value="search"/><input type="hidden" name="per" value="<?= $per ?>"/>
    <div><label>Country</label><select name="cc"><option value="">All countries</option>
      <?php foreach ($countries as $c): ?><option value="<?= h($c['country_code']) ?>" <?= $f['cc'] === $c['country_code'] ? 'selected' : '' ?>><?= h($c['country'] ?: $c['country_code']) ?></option><?php endforeach; ?></select></div>
    <div><label>Status</label><select name="status"><option value="">All statuses</option>
      <?php foreach (['authentic' => 'Authentic', 'suspicious' => 'Suspicious', 'fake' => 'Fake', 'invalid' => 'Invalid'] as $k => $v): ?>
      <option value="<?= $k ?>" <?= $f['status'] === $k ? 'selected' : '' ?>><?= $v ?></option><?php endforeach; ?></select></div>
    <div><label>From date</label><input type="date" name="from" value="<?= h($f['from']) ?>"/></div>
    <div><label>To date</label><input type="date" name="to" value="<?= h($f['to']) ?>"/></div>
    <div><label>Code contains</label><input name="q" value="<?= h($f['q']) ?>" placeholder="optional"/></div>
    <div class="act"><button>Search</button><a class="btn ghost" href="?tab=search">Reset</a></div>
  </form>
  <?php endif; ?>
 
  <?php if ($tab === 'history'): ?>
  <div class="sum">
    <span><b class="mono"><?= h($code) ?></b></span>
    <?php if ($hist): ?>
      <span>Product: <b><?= h($hist['name']) ?></b> (<?= h($hist['gtin']) ?>)<?= !empty($hist['package_type']) ? ' · ' . h($hist['package_type']) : '' ?></span>
      <span>Total scans: <b><?= (int)$hist['scan_count'] ?></b></span>
      <span><?= status_badge(scan_status((int)$hist['scan_count'])) ?></span>
    <?php else: ?><span class="mut">This code is not in the system (invalid attempts only).</span><?php endif; ?>
  </div>
  <?php endif; ?>
 
  <?php if ($missingGeo > 0): ?>
  <form method="POST" class="sum" style="justify-content:space-between;align-items:center;background:#FFF8D6">
    <span>⚠ <?= $missingGeo ?> scan(s) have GPS but no country yet.</span>
    <input type="hidden" name="csrf" value="<?= h($_SESSION['csrf']) ?>"/><input type="hidden" name="action" value="fill_countries"/>
    <button class="btn sm" style="margin:0">Fill missing countries</button>
  </form>
  <?php endif; ?>
 
  <div class="ph"><span><?= h($tabTitle) ?></span>
    <a class="btn ghost sm" href="<?= h(qs(['export' => 1])) ?>">⬇ Export CSV</a></div>
 
  <div class="tw">
  <?php if (!$rows): ?><div class="empty">No records found.</div>
  <?php elseif ($tab === 'top'): ?>
    <table><thead><tr><th>Code</th><th>Product</th><th>Package</th><th>GTIN</th><th>Scans</th><th>Last scan</th><th>Status</th></tr></thead><tbody>
    <?php foreach ($rows as $r): ?><tr>
      <td><a class="lnk mono" href="?tab=history&amp;code=<?= urlencode($r['code']) ?>"><?= h($r['code']) ?></a></td>
      <td><?= h($r['name']) ?></td><td><?= h($r['package_type'] ?: '—') ?></td><td class="mono"><?= h($r['gtin']) ?></td><td><b><?= (int)$r['scan_count'] ?></b></td>
      <td class="mono"><?= h($r['last_scanned_at']) ?></td><td><?= status_badge(scan_status((int)$r['scan_count'])) ?></td>
    </tr><?php endforeach; ?></tbody></table>
  <?php else: ?>
    <table><thead><tr><th>Time</th><th>Code</th><th>Product</th><th>Status</th><th>Scan #</th><th>Location</th><th>IP</th></tr></thead><tbody>
    <?php foreach ($rows as $r): ?><tr>
      <td class="mono" style="white-space:nowrap"><?= h($r['scanned_at']) ?></td>
      <td><a class="lnk mono" href="?tab=history&amp;code=<?= urlencode($r['code']) ?>"><?= h($r['code']) ?></a></td>
      <td><?= h($r['name'] ?: '—') ?><?php if (!empty($r['package_type'])): ?><div class="mut"><?= h($r['package_type']) ?></div><?php endif; ?></td><td><?= status_badge($r['status']) ?></td>
      <td><?= $r['scan_number'] !== null ? (int)$r['scan_number'] : '—' ?></td>
      <td><?= loc_cell($r) ?></td><td class="mono"><?= h($r['ip_address']) ?></td>
    </tr><?php endforeach; ?></tbody></table>
  <?php endif; ?>
  </div>
  <?php if ($rows) pager($page, $pages, $total, $per); ?>
</div>
 
</div></body></html>
 