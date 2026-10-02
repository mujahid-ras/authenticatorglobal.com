<?php
session_start();

// Already logged in? redirect to their cloud
if (isset($_SESSION['client_redirect'])) {
    header('Location: ' . $_SESSION['client_redirect']);
    exit;
}

$error = '';
if (isset($_SESSION['login_error'])) {
    $error = $_SESSION['login_error'];
    unset($_SESSION['login_error']);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Partner Login — Authenticator Global</title>
  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
  <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,500;0,600;1,400&family=DM+Sans:opsz,wght@9..40,300;9..40,400;9..40,500&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet" />
  <link rel="icon" type="image/png" href="/favicon-96x96.png" sizes="96x96" />
<link rel="icon" type="image/svg+xml" href="/favicon.svg" />
<link rel="shortcut icon" href="/favicon.ico" />
<link rel="apple-touch-icon" sizes="180x180" href="/apple-touch-icon.png" />
<meta name="apple-mobile-web-app-title" content="Authenticator Global" />
<link rel="manifest" href="/site.webmanifest" />
  <style>
    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
    :root {
      --bg:         #F6F6F6;
      --surface:    #E8E8E8;
      --border:     rgba(58,58,58,0.10);
      --border-med: rgba(58,58,58,0.18);
      --text-dark:  #3A3A3A;
      --text-mid:   #6b6b6b;
      --text-light: #9a9a9a;
      --crimson:    #930E16;
      --crimson-d:  #A60F19;
      --crimson-bg: rgba(147,14,22,0.06);
      --crimson-bd: rgba(147,14,22,0.2);
      --white:      #FFFFFF;
      --error-bg:   rgba(147,14,22,0.06);
      --error-bd:   rgba(147,14,22,0.22);
    }
    html { scroll-behavior: smooth; }
    body {
      background: var(--bg);
      color: var(--text-dark);
      font-family: 'DM Sans', sans-serif;
      min-height: 100vh;
      display: flex;
      flex-direction: column;
      overflow-x: hidden;
    }
    body::before {
      content: '';
      position: fixed; inset: 0;
      background-image: url("data:image/svg+xml,%3Csvg width='60' height='60' viewBox='0 0 60 60' xmlns='http://www.w3.org/2000/svg'%3E%3Cg fill='none'%3E%3Cg fill='%233A3A3A' fill-opacity='0.025'%3E%3Cpath d='M36 34v-4h-2v4h-4v2h4v4h2v-4h4v-2h-4zm0-30V0h-2v4h-4v2h4v4h2V6h4V4h-4zM6 34v-4H4v4H0v2h4v4h2v-4h4v-2H6zM6 4V0H4v4H0v2h4v4h2V6h4V4H6z'/%3E%3C/g%3E%3C/g%3E%3C/svg%3E");
      pointer-events: none; z-index: 0;
    }
    .deco { position: fixed; border-radius: 50%; pointer-events: none; z-index: 0; }
    .deco-1 { width:500px;height:500px; background:radial-gradient(circle,rgba(147,14,22,0.05),transparent 65%); top:-150px;right:-100px; }
    .deco-2 { width:400px;height:400px; background:radial-gradient(circle,rgba(58,58,58,0.04),transparent 65%); bottom:-100px;left:-80px; }

    /* HEADER */
    header {
      position: relative; z-index: 10;
      padding: 20px 48px;
      display: flex; align-items: center; justify-content: space-between;
      background: var(--white);
      border-bottom: 1px solid var(--border);
      box-shadow: 0 2px 16px rgba(58,58,58,0.05);
    }
    .logo { display:flex; align-items:center; gap:14px; text-decoration:none; }
    .logo-mark {
      width:40px;height:40px; background:var(--crimson); border-radius:8px;
      display:flex;align-items:center;justify-content:center;
      box-shadow:0 2px 12px rgba(147,14,22,0.25);
    }
    .logo-mark svg { width:20px;height:20px;color:#fff; }
    .logo-wordmark { display:flex;flex-direction:column;line-height:1.1; }
    .logo-name { font-family:'Cormorant Garamond',serif;font-weight:600;font-size:17px;color:var(--text-dark); }
    .logo-sub { font-size:9.5px;letter-spacing:2.5px;text-transform:uppercase;color:var(--crimson);font-weight:500; }
    .back-link {
      font-size:12.5px;font-weight:500;letter-spacing:0.8px;text-transform:uppercase;
      color:var(--text-mid);text-decoration:none;padding:8px 18px;
      border:1px solid var(--border-med);border-radius:6px;transition:all 0.2s;
    }
    .back-link:hover { border-color:var(--crimson);color:var(--crimson);background:var(--crimson-bg); }

    /* MAIN */
    main {
      position:relative;z-index:5;flex:1;
      display:flex;flex-direction:column;align-items:center;justify-content:center;
      padding:60px 24px 60px;
    }

    .login-wrap {
      width:100%;max-width:460px;
      animation: fadeUp 0.6s ease both;
    }

    .login-eyebrow {
      display:inline-flex;align-items:center;gap:10px;
      background:var(--crimson-bg);border:1px solid var(--crimson-bd);
      border-radius:4px;padding:5px 16px;
      font-size:10px;font-family:'JetBrains Mono',monospace;
      letter-spacing:2.5px;color:var(--crimson);text-transform:uppercase;
      margin-bottom:24px;
    }
    .eyebrow-rule { width:20px;height:1px;background:var(--crimson);opacity:0.5; }

    .login-heading {
      font-family:'Cormorant Garamond',serif;
      font-size:clamp(36px,5vw,52px);font-weight:500;line-height:1;
      color:var(--text-dark);margin-bottom:6px;
    }
    .login-heading em { font-style:italic;color:var(--crimson); }
    .login-sub {
      font-size:14px;font-weight:300;color:var(--text-mid);
      margin-bottom:36px;
    }

    /* CARD */
    .login-card {
      background:var(--white);
      border:1px solid var(--border);
      border-radius:12px;
      padding:36px;
      box-shadow:0 8px 40px rgba(58,58,58,0.08),0 2px 8px rgba(58,58,58,0.04);
      position:relative;
    }
    .login-card::before {
      content:'';position:absolute;top:0;left:36px;right:36px;
      height:2px;background:var(--crimson);border-radius:0 0 4px 4px;
    }

    /* ERROR */
    .error-msg {
      display:flex;align-items:center;gap:10px;
      background:var(--error-bg);border:1px solid var(--error-bd);
      border-radius:8px;padding:12px 16px;
      font-size:13px;font-family:'JetBrains Mono',monospace;
      color:var(--crimson-d);margin-bottom:24px;
    }
    .error-msg svg { width:15px;height:15px;flex-shrink:0; }

    /* FORM */
    .field { margin-bottom:20px; }
    .field:last-of-type { margin-bottom:0; }
    label {
      display:flex;align-items:center;gap:8px;
      font-size:10.5px;font-family:'JetBrains Mono',monospace;
      letter-spacing:2px;color:var(--text-light);text-transform:uppercase;
      margin-bottom:10px;
    }
    label::before {
      content:'';display:inline-block;width:10px;height:1px;background:var(--crimson);
    }
    input[type="text"], input[type="password"] {
      width:100%;
      background:var(--bg);
      border:1.5px solid var(--border-med);
      border-radius:8px;
      padding:14px 18px;
      font-family:'JetBrains Mono',monospace;
      font-size:14px;
      color:var(--text-dark);
      outline:none;
      transition:border-color 0.25s,box-shadow 0.25s,background 0.2s;
      letter-spacing:1px;
    }
    input[type="text"]::placeholder,
    input[type="password"]::placeholder {
      color:var(--text-light);
      font-family:'DM Sans',sans-serif;
      font-size:13px;letter-spacing:0;
    }
    input:focus {
      border-color:var(--crimson);
      box-shadow:0 0 0 3px rgba(147,14,22,0.07);
      background:var(--white);
    }

    .divider-form { height:1px;background:var(--border);margin:24px 0; }

    .submit-btn {
      width:100%;
      background:var(--crimson);
      border:none;border-radius:8px;
      padding:16px;
      color:#fff;
      font-family:'DM Sans',sans-serif;
      font-size:14px;font-weight:500;letter-spacing:0.8px;
      cursor:pointer;
      transition:background 0.2s,transform 0.15s,box-shadow 0.2s;
      display:flex;align-items:center;justify-content:center;gap:10px;
    }
    .submit-btn:hover { background:var(--crimson-d);transform:translateY(-1px);box-shadow:0 6px 20px rgba(147,14,22,0.28); }
    .submit-btn:active { transform:translateY(0);box-shadow:none; }
    .submit-btn svg { width:16px;height:16px; }

    /* SECURE NOTE */
    .secure-note {
      display:flex;align-items:center;justify-content:center;gap:8px;
      margin-top:20px;
      font-size:11px;color:var(--text-light);font-family:'JetBrains Mono',monospace;letter-spacing:0.3px;
    }
    .secure-note svg { width:12px;height:12px; }

    /* FOOTER */
    footer {
      position:relative;z-index:5;text-align:center;
      padding:20px 24px 24px;
      border-top:1px solid var(--border);background:var(--white);
    }
    .footer-text { font-size:11.5px;color:var(--text-light); }
    .footer-text a { color:var(--crimson);text-decoration:none;font-weight:500; }
    .footer-text a:hover { text-decoration:underline; }

    @keyframes fadeUp { from{opacity:0;transform:translateY(16px)} to{opacity:1;transform:translateY(0)} }
    @keyframes spin { to{transform:rotate(360deg)} }

    @media(max-width:600px){
      header { padding: 14px 16px; gap: 8px; }
      .logo { gap: 9px; }
      .logo-mark { width: 34px; height: 34px; }
      .logo-mark svg { width: 17px; height: 17px; }
      .logo-name { font-size: 14px; }
      .logo-sub { font-size: 8px; letter-spacing: 1.8px; }
      .back-link { font-size: 10.5px; padding: 7px 11px; letter-spacing: 0.3px; white-space: nowrap; }
      main { padding: 36px 16px 40px; }
      .login-card { padding: 24px 18px; }
      .login-heading { font-size: 32px; }
    }
    @media(max-width:380px){
      .logo-sub { display: none; }
      .back-link { font-size: 10px; padding: 6px 10px; }
    }
  </style>
</head>
<body>
  <div class="deco deco-1"></div>
  <div class="deco deco-2"></div>

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
    <a href="/" class="back-link">← Back to Home</a>
  </header>

  <main>
    <div class="login-wrap">
      <div class="login-eyebrow">
        <span class="eyebrow-rule"></span>
        Partner Access Portal
        <span class="eyebrow-rule"></span>
      </div>
      <h1 class="login-heading">Partner <em>Login</em></h1>
      <p class="login-sub">Enter your credentials to access your Hive dashboard.</p>

      <div class="login-card">

        <?php if ($error): ?>
        <div class="error-msg">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <circle cx="12" cy="12" r="10"/>
            <line x1="12" y1="8" x2="12" y2="12"/>
            <line x1="12" y1="16" x2="12.01" y2="16"/>
          </svg>
          <?php echo htmlspecialchars($error); ?>
        </div>
        <?php endif; ?>

        <form method="POST" action="auth.php" id="loginForm">
          <div class="field">
            <label for="client_id">Client ID</label>
            <input type="text" id="client_id" name="client_id"
              placeholder="e.g. AG-CLIENT-001"
              autocomplete="off" spellcheck="false" autocapitalize="characters"
              required />
          </div>

          <div class="field">
            <label for="username">Username</label>
            <input type="text" id="username" name="username"
              placeholder="Your username"
              autocomplete="username" required />
          </div>

          <div class="field">
            <label for="password">Password</label>
            <input type="password" id="password" name="password"
              placeholder="••••••••••"
              autocomplete="current-password" required />
          </div>

          <div class="divider-form"></div>

          <button type="submit" class="submit-btn" id="submitBtn">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <path d="M15 3h4a2 2 0 012 2v14a2 2 0 01-2 2h-4"/>
              <polyline points="10 17 15 12 10 7"/>
              <line x1="15" y1="12" x2="3" y2="12"/>
            </svg>
            Access Dashboard
          </button>
        </form>

        <div class="secure-note">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <rect x="3" y="11" width="18" height="11" rx="2" ry="2"/>
            <path d="M7 11V7a5 5 0 0110 0v4"/>
          </svg>
          Secured &amp; encrypted connection
        </div>
      </div>
    </div>
  </main>

  <footer>
    <p class="footer-text">
      Need access? Contact <a href="mailto:info@reliableglobal.com">info@reliableglobal.com</a>
    </p>
  </footer>

  <script>
    // Auto uppercase client ID
    const clientInput = document.getElementById('client_id');
    clientInput.addEventListener('input', () => {
      const pos = clientInput.selectionStart;
      clientInput.value = clientInput.value.toUpperCase();
      clientInput.setSelectionRange(pos, pos);
    });

    // Loading state on submit
    document.getElementById('loginForm').addEventListener('submit', function() {
      const btn = document.getElementById('submitBtn');
      btn.innerHTML = `
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"
          style="animation:spin 0.8s linear infinite">
          <path d="M21 12a9 9 0 11-6.219-8.56"/>
        </svg>
        Authenticating...
      `;
      btn.disabled = true;
    });
  </script>
</body>
</html>