<?php
function icon(string $name): string {
    $p = [
        'home'     => '<path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/>',
        'lines'    => '<polygon points="12 2 2 7 12 12 22 7 12 2"/><polyline points="2 17 12 22 22 17"/><polyline points="2 12 12 17 22 12"/>',
        'partners' => '<path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/>',
        'package'  => '<path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/><polyline points="3.27 6.96 12 12.01 20.73 6.96"/><line x1="12" y1="22.08" x2="12" y2="12"/>',
        'logout'   => '<path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/>',
        'check'    => '<polyline points="20 6 9 17 4 12"/>',
        'trash'    => '<polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/><path d="M10 11v6"/><path d="M14 11v6"/><path d="M9 6V4a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v2"/>',
        'edit'     => '<path d="M12 20h9"/><path d="M16.5 3.5a2.12 2.12 0 0 1 3 3L7 19l-4 1 1-4z"/>',
        'shield'   => '<path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/><polyline points="9 12 11 14 15 10"/>',
        'eye'      => '<path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/>',
    ];
    return '<svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . ($p[$name] ?? '') . '</svg>';
}

function datamatrix_svg(): string {
    $m = [
        '101010101010101010101010',
        '110010001001100010111111',
        '100010011101111100001000',
        '101111010010001100100001',
        '111010001000001001100010',
        '110010011110110011001001',
        '101011010101001100000100',
        '100100010100110001111001',
        '111000011100101011100010',
        '110101010011011100101101',
        '100001101010011011111000',
        '101001101000001101100101',
        '101110000110100100011110',
        '110010010111011100000101',
        '111111011001010000100110',
        '101100101000011110001111',
        '100001111010010011001000',
        '100100110101101100000111',
        '110111110011100001011100',
        '100101111001000011110011',
        '111101110001100101010000',
        '111010001010111011000101',
        '110101011001010001101010',
        '111111111111111111111111'
    ];
    $out = '';
    foreach ($m as $y => $row) {
        for ($x = 0; $x < 24; $x++) {
            if ($row[$x] === '1') { $out .= '<rect x="' . $x . '" y="' . $y . '" width="1.02" height="1.02"/>'; }
        }
    }
    return '<svg class="datamatrix" viewBox="0 0 24 24" shape-rendering="crispEdges" aria-hidden="true" fill="currentColor">' . $out . '</svg>';
}

/* DRC flag background with star moved to bottom-right to avoid brand obstruction */
function flag_backdrop_svg(): string {
    // Star ko bottom-right (blue field) par move kar diya hai
    $cx = 1700; $cy = 900; $R = 120; $r = 48; $pts = [];
    for ($i = 0; $i < 10; $i++) {
        $a  = deg2rad(-90 + $i * 36);
        $rad = ($i % 2 === 0) ? $R : $r;
        $pts[] = round($cx + $rad * cos($a), 1) . ',' . round($cy + $rad * sin($a), 1);
    }

    return '<svg class="backdrop" viewBox="0 0 1920 1080" preserveAspectRatio="xMidYMid slice" aria-hidden="true">'
         . '<rect width="1920" height="1080" fill="#0095DA"/>'
         . '<polygon points="0,1080 1920,0 1920,330 330,1080" fill="#FCD116"/>'
         . '<polygon points="' . implode(' ', $pts) . '" fill="#CE1021" stroke="#FCD116" stroke-width="10" stroke-linejoin="round"/>'
         . '</svg>';
}

function brand_mark(): string {
    return '<svg class="brand-mark" viewBox="0 0 32 32" aria-hidden="true">'
         . '<rect width="32" height="32" rx="7" fill="#0095DA"/>'
         . '<path d="M9 9h3v14H9zm5 0h1.5v14H14zm3.5 0H21v14h-3.5zm5 0H24v14h-1.5z" fill="#FCD116"/>'
         . '</svg>';
}

function html_foot(): void {
    echo '<script src="' . e(url('assets/js/app.js')) . '?v=5" defer></script></body></html>'; // v=5 update kiya hai
}

/* Customer / admin app shell */
function app_head(string $title, string $active, string $mode = 'customer', string $subtitle = ''): void {
    html_head($title, 'app-body');
    if ($mode === 'admin') {
        $who   = current_admin()['username'] ?? '';
        $items = [
            ['customers', 'Customers', 'admin/index.php', 'partners'],
            ['admins', 'Admins', 'admin/admins.php', 'shield'],
        ];
        $logout = url('admin/logout.php');
    } else {
        $c = current_customer();
        $who = $c['company_name'] ?? '';
        $partnerLabel = ($c['account_type'] ?? '') === 'Manufacturer' ? 'Importers' : 'Manufacturers';
        $items = [
            ['overview', 'Overview', 'dashboard.php', 'home'],
            ['lines', 'Production lines', 'lines.php', 'lines'],
            ['partners', $partnerLabel, 'partners.php', 'partners'],
            ['products', 'Products', 'products.php', 'package'],
        ];
        $logout = url('logout.php');
    }
    echo '<div class="app"><aside class="sidebar">'
       . '<a class="brand" href="' . e(url($mode === 'admin' ? 'admin/index.php' : 'dashboard.php')) . '">' . brand_mark()
       . '<span><strong>' . e(SITE_NAME) . '</strong><small>' . ($mode === 'admin' ? 'DRC admin' : 'DRC portal') . '</small></span></a>'
       . '<nav aria-label="Main">';
    foreach ($items as [$key, $label, $href, $ic]) {
        echo '<a href="' . e(url($href)) . '" class="' . ($key === $active ? 'active' : '') . '"' . ($key === $active ? ' aria-current="page"' : '') . '>' . icon($ic) . '<span>' . e($label) . '</span></a>';
    }
    echo '</nav><div class="sidebar-foot"><div class="who" title="' . e($who) . '">' . e($who) . '</div>'
       . '<a class="signout" href="' . e($logout) . '">' . icon('logout') . '<span>Sign out</span></a></div></aside>'
       . '<main class="content"><header class="page-head"><h1>' . e($title) . '</h1>'
       . ($subtitle !== '' ? '<p>' . e($subtitle) . '</p>' : '') . '</header>'
       . flashes_html();
}
function app_foot(): void {
    echo '</main></div>';
    html_foot();
}
