<?php
session_start();
require_once 'config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php');
    exit;
}

$client_id = strtoupper(trim($_POST['client_id'] ?? ''));
$username  = trim($_POST['username'] ?? '');
$password  = $_POST['password'] ?? '';

if (!$client_id || !$username || !$password) {
    $_SESSION['login_error'] = 'All fields are required.';
    header('Location: index.php');
    exit;
}

$stmt = $pdo->prepare("SELECT * FROM partners WHERE client_id = ? AND username = ? LIMIT 1");
$stmt->execute([$client_id, $username]);
$user = $stmt->fetch();

if (!$user) {
    sleep(1);
    $_SESSION['login_error'] = 'Invalid Client ID, username or password.';
    header('Location: index.php');
    exit;
}
if (!$user['is_active']) {
    $_SESSION['login_error'] = 'Your access has been suspended. Contact info@reliableglobal.com';
    header('Location: index.php');
    exit;
}
if (!password_verify($password, $user['password_hash'])) {
    sleep(1);
    $_SESSION['login_error'] = 'Invalid Client ID, username or password.';
    header('Location: index.php');
    exit;
}

// ── Build Hive URL ────────────────────────────────────────────────────────
$hive_base      = rtrim($user['redirect_url'], '/');
$hive_login_url = $hive_base . '/login.aspx';

// ── Check if Hive server is reachable via socket ──────────────────────────
$parsed     = parse_url($hive_base);
$host       = $parsed['host'] ?? '';
$port       = $parsed['port'] ?? (($parsed['scheme'] ?? 'http') === 'https' ? 443 : 80);
$server_ok  = false;

if ($host) {
    $sock = @fsockopen($host, $port, $errno, $errstr, 5);
    if ($sock) {
        fclose($sock);
        $server_ok = true;
    }
}

// ── Server DOWN — show maintenance page ──────────────────────────────────
if (!$server_ok) {
    $hive_name = (stripos($hive_base, 'ddns') !== false || stripos($hive_base, 'cloud') !== false)
                 ? 'Hive Cloud' : 'Hive Server';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Server Unavailable — Authenticator Global</title>
  <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,500;0,600;1,400&family=DM+Sans:opsz,wght@9..40,300;9..40,400;9..40,500&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet"/>
  <style>
    *,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
    :root{
      --bg:#F6F6F6;--border:rgba(58,58,58,0.10);--border-med:rgba(58,58,58,0.18);
      --text-dark:#3A3A3A;--text-mid:#6b6b6b;--text-light:#9a9a9a;
      --crimson:#930E16;--crimson-d:#A60F19;--crimson-bg:rgba(147,14,22,0.06);
      --crimson-bd:rgba(147,14,22,0.2);--white:#FFFFFF;
      --amber:#b45309;--amber-bg:rgba(180,83,9,0.06);--amber-bd:rgba(180,83,9,0.22);
    }
    body{background:var(--bg);color:var(--text-dark);font-family:'DM Sans',sans-serif;
      min-height:100vh;display:flex;flex-direction:column;overflow-x:hidden;}
    body::before{content:'';position:fixed;inset:0;
      background-image:url("data:image/svg+xml,%3Csvg width='60' height='60' viewBox='0 0 60 60' xmlns='http://www.w3.org/2000/svg'%3E%3Cg fill='none'%3E%3Cg fill='%233A3A3A' fill-opacity='0.025'%3E%3Cpath d='M36 34v-4h-2v4h-4v2h4v4h2v-4h4v-2h-4zm0-30V0h-2v4h-4v2h4v4h2V6h4V4h-4zM6 34v-4H4v4H0v2h4v4h2v-4h4v-2H6zM6 4V0H4v4H0v2h4v4h2V6h4V4H6z'/%3E%3C/g%3E%3C/g%3E%3C/svg%3E");
      pointer-events:none;z-index:0;}
    header{position:relative;z-index:10;padding:20px 48px;
      display:flex;align-items:center;justify-content:space-between;
      background:var(--white);border-bottom:1px solid var(--border);
      box-shadow:0 2px 16px rgba(58,58,58,0.05);}
    .logo{display:flex;align-items:center;gap:14px;text-decoration:none;}
    .logo-mark{width:40px;height:40px;background:var(--crimson);border-radius:8px;
      display:flex;align-items:center;justify-content:center;
      box-shadow:0 2px 12px rgba(147,14,22,0.25);}
    .logo-mark svg{width:20px;height:20px;color:#fff;}
    .logo-wordmark{display:flex;flex-direction:column;line-height:1.1;}
    .logo-name{font-family:'Cormorant Garamond',serif;font-weight:600;font-size:17px;color:var(--text-dark);}
    .logo-sub{font-size:9.5px;letter-spacing:2.5px;text-transform:uppercase;color:var(--crimson);font-weight:500;}
    .back-btn{font-size:12.5px;font-weight:500;letter-spacing:0.8px;text-transform:uppercase;
      color:var(--text-mid);text-decoration:none;padding:8px 18px;
      border:1px solid var(--border-med);border-radius:6px;transition:all 0.2s;white-space:nowrap;}
    .back-btn:hover{border-color:var(--crimson);color:var(--crimson);background:var(--crimson-bg);}
    main{position:relative;z-index:5;flex:1;display:flex;align-items:center;
      justify-content:center;padding:60px 24px;}
    .card{width:100%;max-width:520px;background:var(--white);border:1px solid var(--border);
      border-radius:16px;padding:48px 44px;box-shadow:0 8px 40px rgba(58,58,58,0.08);
      text-align:center;position:relative;overflow:hidden;animation:fadeUp 0.6s ease both;}
    .card::before{content:'';position:absolute;top:0;left:0;right:0;height:3px;background:var(--amber);}
    .icon-wrap{width:72px;height:72px;border-radius:50%;background:var(--amber-bg);
      border:1.5px solid var(--amber-bd);display:flex;align-items:center;justify-content:center;
      margin:0 auto 28px;}
    .icon-wrap svg{width:32px;height:32px;color:var(--amber);}
    .badge{display:inline-flex;align-items:center;gap:8px;background:var(--amber-bg);
      border:1px solid var(--amber-bd);border-radius:4px;padding:5px 14px;
      font-size:10px;font-family:'JetBrains Mono',monospace;letter-spacing:2px;
      color:var(--amber);text-transform:uppercase;margin-bottom:20px;}
    .badge-dot{width:6px;height:6px;border-radius:50%;background:var(--amber);
      animation:pulse 2s ease-in-out infinite;}
    h1{font-family:'Cormorant Garamond',serif;font-size:clamp(28px,5vw,40px);
      font-weight:500;line-height:1.1;color:var(--text-dark);margin-bottom:8px;}
    h1 em{font-style:italic;color:var(--amber);}
    .divider{width:40px;height:2px;background:var(--amber);margin:20px auto;}
    .main-msg{font-size:15px;font-weight:300;color:var(--text-mid);line-height:1.7;margin-bottom:28px;}
    .suggestion-box{background:var(--crimson-bg);border:1px solid var(--crimson-bd);
      border-radius:10px;padding:18px 22px;margin-bottom:28px;text-align:left;}
    .suggestion-label{font-size:9.5px;font-family:'JetBrains Mono',monospace;letter-spacing:2px;
      color:var(--crimson);text-transform:uppercase;display:flex;align-items:center;
      gap:7px;margin-bottom:10px;}
    .suggestion-label::before{content:'';display:inline-block;width:10px;height:1px;background:var(--crimson);}
    .suggestion-text{font-size:14px;color:var(--text-dark);line-height:1.5;}
    .suggestion-text strong{font-weight:600;color:var(--crimson);}
    .contact-line{display:flex;align-items:center;justify-content:center;gap:8px;
      font-size:12.5px;color:var(--text-mid);font-family:'JetBrains Mono',monospace;flex-wrap:wrap;}
    .contact-line svg{width:13px;height:13px;color:var(--crimson);flex-shrink:0;}
    .contact-line a{color:var(--crimson);text-decoration:none;font-weight:500;}
    .contact-line a:hover{text-decoration:underline;}
    .retry-btn{display:inline-flex;align-items:center;gap:8px;margin-top:24px;
      background:var(--crimson);color:#fff;border:none;border-radius:8px;
      padding:12px 24px;font-family:'DM Sans',sans-serif;font-size:13.5px;
      font-weight:500;cursor:pointer;text-decoration:none;
      transition:background 0.2s,transform 0.15s,box-shadow 0.2s;}
    .retry-btn:hover{background:var(--crimson-d);transform:translateY(-1px);
      box-shadow:0 5px 16px rgba(147,14,22,0.25);}
    .retry-btn svg{width:14px;height:14px;}
    footer{position:relative;z-index:5;text-align:center;padding:20px 24px 24px;
      border-top:1px solid var(--border);background:var(--white);}
    .footer-text{font-size:11.5px;color:var(--text-light);}
    .footer-text a{color:var(--crimson);text-decoration:none;font-weight:500;}
    @keyframes fadeUp{from{opacity:0;transform:translateY(16px)}to{opacity:1;transform:translateY(0)}}
    @keyframes pulse{0%,100%{opacity:1;transform:scale(1)}50%{opacity:0.4;transform:scale(0.7)}}
    @media(max-width:600px){
      header{padding:14px 16px;}
      .logo-name{font-size:14px;}.logo-sub{font-size:8px;}
      .logo-mark{width:34px;height:34px;}.logo-mark svg{width:17px;height:17px;}
      .back-btn{font-size:10.5px;padding:7px 11px;}
      main{padding:40px 16px;}.card{padding:32px 22px;}
    }
    @media(max-width:380px){.logo-sub{display:none;}.back-btn{font-size:10px;padding:6px 10px;}}
  </style>
</head>
<body>
  <header>
    <a href="/" class="logo">
      <div class="logo-mark">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/>
          <path d="M9 12l2 2 4-4" stroke-linecap="round" stroke-linejoin="round"/>
        </svg>
      </div>
      <div class="logo-wordmark">
        <span class="logo-name">Authenticator Global</span>
        <span class="logo-sub">By Reliable Global</span>
      </div>
    </a>
    <a href="/partners/" class="back-btn">← Try Again</a>
  </header>
  <main>
    <div class="card">
      <div class="icon-wrap">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
          <path d="M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/>
          <line x1="12" y1="9" x2="12" y2="13"/>
          <line x1="12" y1="17" x2="12.01" y2="17"/>
        </svg>
      </div>
      <div class="badge"><span class="badge-dot"></span> Server Status</div>
      <h1><?php echo $hive_name; ?> is Currently<br><em>Under Maintenance</em></h1>
      <div class="divider"></div>
      <p class="main-msg">
        We are unable to reach your assigned server at this time.<br>
        Our team is working to restore service as soon as possible.
      </p>
      <div class="suggestion-box">
        <div class="suggestion-label">Recommended Action</div>
        <div class="suggestion-text">
          Kindly use <strong>Hive On-Premise</strong> to continue your work
          while the cloud server is being restored.
        </div>
      </div>
      <div class="contact-line">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/>
          <polyline points="22,6 12,13 2,6"/>
        </svg>
        For more information contact:&nbsp;
        <a href="mailto:info@reliableglobal.com">info@reliableglobal.com</a>
      </div>
      <a href="/partners/" class="retry-btn">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <polyline points="1 4 1 10 7 10"/>
          <path d="M3.51 15a9 9 0 102.13-9.36L1 10"/>
        </svg>
        Back to Login
      </a>
    </div>
  </main>
  <footer>
    <p class="footer-text">
      Authenticator Global &mdash; by
      <a href="mailto:info@reliableglobal.com">Reliable Global</a>
    </p>
  </footer>
</body>
</html>
<?php
    exit;
}

// ── Server is UP — proceed with working login ─────────────────────────────
$credentials = json_encode(['u' => $username, 'p' => $password]);
$encoded     = base64_encode($credentials);

header('Location: ' . $hive_login_url . '#ag=' . $encoded);
exit;