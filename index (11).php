<?php
session_start();
if (!isset($_SESSION['admin_logged_in'])) {
    header('Location: login.php'); exit;
}

define('DB_HOST', 'localhost');
define('DB_NAME', 'adminag_clientlogin');
define('DB_USER', 'adminag_mujahid');
define('DB_PASS', '9cKX@XQ,#(HR');

try {
    $pdo = new PDO("mysql:host=".DB_HOST.";dbname=".DB_NAME.";charset=utf8mb4",
        DB_USER, DB_PASS, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);
} catch (PDOException $e) {
    die('DB connection failed.');
}

$msg = '';
$msg_type = '';

// ── ACTIONS ──────────────────────────────────────────────

// CREATE partner
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'create') {
    $cid      = strtoupper(trim($_POST['client_id'] ?? ''));
    $uname    = trim($_POST['username'] ?? '');
    $pass     = $_POST['password'] ?? '';
    $redirect = trim($_POST['redirect_url'] ?? '');
    $label    = trim($_POST['label'] ?? '');

    if ($cid && $uname && $pass && $redirect) {
        $hash = password_hash($pass, PASSWORD_BCRYPT);
        try {
            $pdo->prepare("INSERT INTO partners (client_id, username, password_hash, redirect_url, label, is_active, created_at)
                VALUES (?,?,?,?,?,1,NOW())")
                ->execute([$cid, $uname, $hash, $redirect, $label]);
            $msg = "Partner <strong>{$cid}</strong> created successfully.";
            $msg_type = 'success';
        } catch (PDOException $e) {
            $msg = "Error: Client ID or username already exists.";
            $msg_type = 'error';
        }
    } else {
        $msg = "All fields except Label are required.";
        $msg_type = 'error';
    }
}

// EDIT partner
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'edit') {
    $pid      = (int)($_POST['partner_id'] ?? 0);
    $cid      = strtoupper(trim($_POST['client_id'] ?? ''));
    $uname    = trim($_POST['username'] ?? '');
    $redirect = trim($_POST['redirect_url'] ?? '');
    $label    = trim($_POST['label'] ?? '');
    $isActive = isset($_POST['is_active']) ? 1 : 0;

    if ($pid && $cid && $uname && $redirect) {
        // Check uniqueness for client_id and username (excluding current partner)
        $check = $pdo->prepare("SELECT id FROM partners WHERE (username = ?) AND id != ? LIMIT 1");
        $check->execute([ $uname, $pid]);
        if ($check->fetch()) {
            $msg = "Error: Username already used by another partner.";
            $msg_type = 'error';
        } else {
            $update = $pdo->prepare("UPDATE partners 
                SET client_id = ?, username = ?, redirect_url = ?, label = ?, is_active = ?
                WHERE id = ?");
            $update->execute([$cid, $uname, $redirect, $label, $isActive, $pid]);
            $msg = "Partner <strong>{$cid}</strong> updated successfully.";
            $msg_type = 'success';
        }
    } else {
        $msg = "All fields except Label are required.";
        $msg_type = 'error';
    }
}

// TOGGLE active/inactive (legacy, but keep for compatibility)
if (isset($_GET['toggle'])) {
    $pid = (int)$_GET['toggle'];
    $pdo->prepare("UPDATE partners SET is_active = NOT is_active WHERE id = ?")->execute([$pid]);
    header('Location: index.php?toggled=1'); exit;
}

// DELETE partner
if (isset($_GET['delete'])) {
    $pid = (int)$_GET['delete'];
    $pdo->prepare("DELETE FROM partners WHERE id = ?")->execute([$pid]);
    header('Location: index.php?deleted=1'); exit;
}

// RESET PASSWORD
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'reset_pass') {
    $pid      = (int)($_POST['partner_id'] ?? 0);
    $new_pass = $_POST['new_password'] ?? '';
    if ($pid && strlen($new_pass) >= 6) {
        $hash = password_hash($new_pass, PASSWORD_BCRYPT);
        $pdo->prepare("UPDATE partners SET password_hash = ? WHERE id = ?")->execute([$hash, $pid]);
        $msg = "Password updated successfully.";
        $msg_type = 'success';
    } else {
        $msg = "Password must be at least 6 characters.";
        $msg_type = 'error';
    }
}

// Flash msgs from redirects
if (isset($_GET['toggled'])) { $msg = "Partner status updated."; $msg_type = 'success'; }
if (isset($_GET['deleted']))  { $msg = "Partner deleted.";        $msg_type = 'success'; }

// FETCH all partners
$partners = $pdo->query("SELECT * FROM partners ORDER BY created_at DESC")->fetchAll();
$total    = count($partners);
$active = count(array_filter($partners, function($p) {
    return $p['is_active'];
}));
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Admin Panel — Authenticator Global</title>
  <link rel="preconnect" href="https://fonts.googleapis.com"/>
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin/>
  <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,500;0,600;1,400&family=DM+Sans:opsz,wght@9..40,300;9..40,400;9..40,500&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet"/>
  <style>
    *,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
    :root{
      --bg:#F6F6F6;--surface:#E8E8E8;--border:rgba(58,58,58,0.10);
      --border-med:rgba(58,58,58,0.18);--text-dark:#3A3A3A;--text-mid:#6b6b6b;
      --text-light:#9a9a9a;--crimson:#930E16;--crimson-d:#A60F19;
      --crimson-bg:rgba(147,14,22,0.06);--crimson-bd:rgba(147,14,22,0.2);
      --white:#FFFFFF;--green:#1a7a3c;--green-bg:rgba(26,122,60,0.07);--green-bd:rgba(26,122,60,0.25);
    }
    body{background:var(--bg);color:var(--text-dark);font-family:'DM Sans',sans-serif;min-height:100vh;}

    /* TOPBAR */
    .topbar{background:var(--white);border-bottom:1px solid var(--border);
      padding:0 32px;display:flex;align-items:center;justify-content:space-between;height:60px;
      box-shadow:0 2px 12px rgba(58,58,58,0.05);position:sticky;top:0;z-index:100;}
    .tb-logo{display:flex;align-items:center;gap:12px;}
    .logo-mark{width:34px;height:34px;background:var(--crimson);border-radius:7px;
      display:flex;align-items:center;justify-content:center;}
    .logo-mark svg{width:17px;height:17px;color:#fff;}
    .tb-title{font-family:'Cormorant Garamond',serif;font-size:17px;font-weight:600;color:var(--text-dark);}
    .tb-title span{font-style:italic;color:var(--crimson);}
    .tb-right{display:flex;align-items:center;gap:16px;}
    .admin-badge-sm{font-size:9.5px;font-family:'JetBrains Mono',monospace;letter-spacing:2px;
      color:var(--crimson);text-transform:uppercase;background:var(--crimson-bg);
      border:1px solid var(--crimson-bd);padding:3px 10px;border-radius:4px;}
    .logout-btn{font-size:12px;font-weight:500;color:var(--text-mid);text-decoration:none;
      padding:6px 14px;border:1px solid var(--border-med);border-radius:6px;transition:all 0.2s;}
    .logout-btn:hover{border-color:var(--crimson);color:var(--crimson);background:var(--crimson-bg);}

    /* LAYOUT */
    .container{max-width:1100px;margin:0 auto;padding:32px 24px;}

    /* STATS */
    .stats-row{display:grid;grid-template-columns:repeat(3,1fr);gap:16px;margin-bottom:32px;}
    .stat-card{background:var(--white);border:1px solid var(--border);border-radius:10px;
      padding:20px 24px;position:relative;overflow:hidden;}
    .stat-card::before{content:'';position:absolute;top:0;left:0;right:0;
      height:2px;background:var(--crimson);}
    .stat-val{font-family:'Cormorant Garamond',serif;font-size:40px;font-weight:500;
      color:var(--text-dark);line-height:1;}
    .stat-val em{font-style:italic;color:var(--crimson);}
    .stat-label{font-size:11px;font-family:'JetBrains Mono',monospace;letter-spacing:1.5px;
      text-transform:uppercase;color:var(--text-light);margin-top:6px;}

    /* MSG */
    .msg{display:flex;align-items:center;gap:10px;border-radius:8px;padding:13px 18px;
      font-size:13px;margin-bottom:24px;font-family:'JetBrains Mono',monospace;}
    .msg.success{background:var(--green-bg);border:1px solid var(--green-bd);color:var(--green);}
    .msg.error{background:var(--crimson-bg);border:1px solid var(--crimson-bd);color:var(--crimson-d);}
    .msg svg{width:15px;height:15px;flex-shrink:0;}

    /* PANELS */
    .panel{background:var(--white);border:1px solid var(--border);border-radius:12px;
      margin-bottom:28px;overflow:hidden;}
    .panel-head{padding:20px 28px;border-bottom:1px solid var(--border);
      display:flex;align-items:center;justify-content:space-between;}
    .panel-title{font-family:'Cormorant Garamond',serif;font-size:22px;font-weight:500;color:var(--text-dark);}
    .panel-title em{font-style:italic;color:var(--crimson);}
    .panel-body{padding:28px;}

    /* FORMS (create & edit) */
    .form-grid{display:grid;grid-template-columns:1fr 1fr;gap:16px;}
    .form-grid .full{grid-column:1/-1;}
    .field{display:flex;flex-direction:column;gap:7px;}
    .field label{font-size:10px;font-family:'JetBrains Mono',monospace;letter-spacing:2px;
      color:var(--text-light);text-transform:uppercase;display:flex;align-items:center;gap:7px;}
    .field label::before{content:'';display:inline-block;width:10px;height:1px;background:var(--crimson);}
    .field input,.field select{background:var(--bg);border:1.5px solid var(--border-med);
      border-radius:8px;padding:12px 16px;font-family:'JetBrains Mono',monospace;font-size:13.5px;
      color:var(--text-dark);outline:none;transition:border-color 0.2s,box-shadow 0.2s,background 0.2s;}
    .field input:focus,.field select:focus{border-color:var(--crimson);
      box-shadow:0 0 0 3px rgba(147,14,22,0.07);background:var(--white);}
    .checkbox-field{flex-direction:row;align-items:center;gap:12px;}
    .checkbox-field label::before{display:none;}
    .checkbox-field input{width:18px;height:18px;margin:0;}

    .btn{display:inline-flex;align-items:center;gap:8px;border:none;border-radius:7px;
      padding:11px 22px;font-family:'DM Sans',sans-serif;font-size:13px;font-weight:500;
      cursor:pointer;transition:all 0.2s;letter-spacing:0.3px;}
    .btn-primary{background:var(--crimson);color:#fff;}
    .btn-primary:hover{background:var(--crimson-d);transform:translateY(-1px);box-shadow:0 5px 16px rgba(147,14,22,0.25);}
    .btn-sm{padding:7px 14px;font-size:12px;}
    .btn-ghost{background:transparent;color:var(--text-mid);border:1px solid var(--border-med);}
    .btn-ghost:hover{border-color:var(--crimson);color:var(--crimson);background:var(--crimson-bg);}
    .btn-danger{background:transparent;color:var(--crimson);border:1px solid var(--crimson-bd);}
    .btn-danger:hover{background:var(--crimson);color:#fff;}
    .btn svg{width:14px;height:14px;}

    /* TABLE */
    .table-wrap{overflow-x:auto;}
    table{width:100%;border-collapse:collapse;}
    th{font-size:10px;font-family:'JetBrains Mono',monospace;letter-spacing:2px;text-transform:uppercase;
      color:var(--text-light);text-align:left;padding:10px 16px;border-bottom:1px solid var(--border);
      white-space:nowrap;}
    td{padding:14px 16px;border-bottom:1px solid var(--border);font-size:13.5px;vertical-align:middle;}
    tr:last-child td{border-bottom:none;}
    tr:hover td{background:rgba(58,58,58,0.018);}

    .code-cell{font-family:'JetBrains Mono',monospace;font-size:12.5px;
      background:var(--surface);padding:4px 10px;border-radius:5px;display:inline-block;letter-spacing:1px;}
    .url-cell{font-size:12px;color:var(--text-mid);max-width:220px;
      overflow:hidden;text-overflow:ellipsis;white-space:nowrap;}

    .badge{display:inline-flex;align-items:center;gap:5px;border-radius:4px;
      padding:3px 10px;font-size:11px;font-family:'JetBrains Mono',monospace;letter-spacing:1px;}
    .badge-active{background:var(--green-bg);border:1px solid var(--green-bd);color:var(--green);}
    .badge-inactive{background:rgba(58,58,58,0.07);border:1px solid rgba(58,58,58,0.18);color:var(--text-light);}
    .badge-dot{width:5px;height:5px;border-radius:50%;background:currentColor;}

    .actions{display:flex;align-items:center;gap:8px;flex-wrap:wrap;}

    /* MODAL common */
    .modal-overlay{display:none;position:fixed;inset:0;background:rgba(58,58,58,0.4);
      z-index:1000;align-items:center;justify-content:center;backdrop-filter:blur(4px);}
    .modal-overlay.open{display:flex;}
    .modal{background:var(--white);border:1px solid var(--border);border-radius:12px;
      padding:32px;width:100%;max-width:520px;position:relative;
      box-shadow:0 20px 60px rgba(58,58,58,0.15);}
    .modal h3{font-family:'Cormorant Garamond',serif;font-size:24px;font-weight:500;
      margin-bottom:20px;color:var(--text-dark);}
    .modal h3 em{font-style:italic;color:var(--crimson);}
    .modal-close{position:absolute;top:16px;right:16px;background:none;border:none;
      cursor:pointer;color:var(--text-light);font-size:20px;line-height:1;padding:4px;}
    .modal-close:hover{color:var(--text-dark);}
    .modal-actions{display:flex;gap:12px;margin-top:24px;}
    .modal-actions .btn{flex:1;justify-content:center;}

    @media(max-width:768px){
      .stats-row{grid-template-columns:1fr 1fr;}
      .form-grid{grid-template-columns:1fr;}
      .form-grid .full{grid-column:1;}
      .topbar{padding:0 16px;}
      .container{padding:20px 16px;}
      .modal{max-width:calc(100% - 40px);padding:24px;}
    }
  </style>
</head>
<body>

<!-- TOPBAR -->
<div class="topbar">
  <div class="tb-logo">
    <div class="logo-mark">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
        <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/>
        <path d="M9 12l2 2 4-4" stroke-linecap="round" stroke-linejoin="round"/>
      </svg>
    </div>
    <span class="tb-title">Authenticator <span>Global</span></span>
  </div>
  <div class="tb-right">
    <span class="admin-badge-sm">Admin Panel</span>
    <span style="font-size:12px;color:var(--text-light);font-family:'JetBrains Mono',monospace;">
      <?php echo htmlspecialchars($_SESSION['admin_username']); ?>
    </span>
    <a href="logout.php" class="logout-btn">Logout</a>
  </div>
</div>

<div class="container">

  <!-- STATS -->
  <div class="stats-row">
    <div class="stat-card">
      <div class="stat-val"><?php echo $total; ?></div>
      <div class="stat-label">Total Partners</div>
    </div>
    <div class="stat-card">
      <div class="stat-val"><em><?php echo $active; ?></em></div>
      <div class="stat-label">Active</div>
    </div>
    <div class="stat-card">
      <div class="stat-val"><?php echo $total - $active; ?></div>
      <div class="stat-label">Suspended</div>
    </div>
  </div>

  <!-- MSG -->
  <?php if ($msg): ?>
  <div class="msg <?php echo $msg_type; ?>">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
      <?php if ($msg_type === 'success'): ?>
        <polyline points="20 6 9 17 4 12"/>
      <?php else: ?>
        <circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/>
        <line x1="12" y1="16" x2="12.01" y2="16"/>
      <?php endif; ?>
    </svg>
    <?php echo $msg; ?>
  </div>
  <?php endif; ?>

  <!-- CREATE PARTNER -->
  <div class="panel">
    <div class="panel-head">
      <div class="panel-title">Add New <em>Partner</em></div>
    </div>
    <div class="panel-body">
      <form method="POST" action="">
        <input type="hidden" name="action" value="create"/>
        <div class="form-grid">
          <div class="field">
            <label for="client_id">Client ID</label>
            <input type="text" id="client_id" name="client_id" placeholder="AG-CLIENT-001" required/>
          </div>
          <div class="field">
            <label for="label">Company Label</label>
            <input type="text" id="label" name="label" placeholder="e.g. Acme Corp"/>
          </div>
          <div class="field">
            <label for="username">Username</label>
            <input type="text" id="username" name="username" placeholder="client_username" required/>
          </div>
          <div class="field">
            <label for="password">Password</label>
            <input type="password" id="password" name="password" placeholder="Min. 8 characters" required/>
          </div>
          <div class="field full">
            <label for="redirect_url">Hive Dashboard URL</label>
            <input type="text" id="redirect_url" name="redirect_url"
              placeholder="https://hive.client-domain.com/dashboard" required/>
          </div>
        </div>
        <div style="margin-top:20px;">
          <button type="submit" class="btn btn-primary">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/>
            </svg>
            Create Partner
          </button>
        </div>
      </form>
    </div>
  </div>

  <!-- PARTNERS TABLE -->
  <div class="panel">
    <div class="panel-head">
      <div class="panel-title">All <em>Partners</em></div>
      <span style="font-size:12px;color:var(--text-light);font-family:'JetBrains Mono',monospace;">
        <?php echo $total; ?> records
      </span>
    </div>
    <div class="table-wrap">
      <?php if (empty($partners)): ?>
        <div style="padding:40px;text-align:center;color:var(--text-light);
          font-family:'JetBrains Mono',monospace;font-size:13px;">
          No partners yet. Create one above.
        </div>
      <?php else: ?>
      <table>
        <thead>
          <tr>
            <th>Client ID</th>
            <th>Label</th>
            <th>Username</th>
            <th>Redirect URL</th>
            <th>Status</th>
            <th>Created</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($partners as $p): ?>
          <tr>
            <td><span class="code-cell"><?php echo htmlspecialchars($p['client_id']); ?></span></td>
            <td><?php echo htmlspecialchars($p['label'] ?: '—'); ?></td>
            <td style="font-family:'JetBrains Mono',monospace;font-size:12.5px;">
              <?php echo htmlspecialchars($p['username']); ?>
            </td>
            <td>
              <span class="url-cell" title="<?php echo htmlspecialchars($p['redirect_url']); ?>">
                <?php echo htmlspecialchars($p['redirect_url']); ?>
              </span>
            </td>
            <td>
              <?php if ($p['is_active']): ?>
                <span class="badge badge-active"><span class="badge-dot"></span> Active</span>
              <?php else: ?>
                <span class="badge badge-inactive"><span class="badge-dot"></span> Suspended</span>
              <?php endif; ?>
            </td>
            <td style="font-size:12px;color:var(--text-light);font-family:'JetBrains Mono',monospace;white-space:nowrap;">
              <?php echo date('d M Y', strtotime($p['created_at'])); ?>
            </td>
            <td>
              <div class="actions">
                <button class="btn btn-sm btn-ghost"
                  onclick="openEditModal(
                    <?php echo $p['id']; ?>,
                    '<?php echo htmlspecialchars($p['client_id']); ?>',
                    '<?php echo htmlspecialchars($p['username']); ?>',
                    '<?php echo htmlspecialchars($p['redirect_url']); ?>',
                    '<?php echo htmlspecialchars($p['label']); ?>',
                    <?php echo $p['is_active'] ? 'true' : 'false'; ?>
                  )">
                  Edit
                </button>
                <a href="?toggle=<?php echo $p['id']; ?>"
                   class="btn btn-sm btn-ghost"
                   onclick="return confirm('Toggle status for <?php echo htmlspecialchars($p['client_id']); ?>?')">
                  <?php echo $p['is_active'] ? 'Suspend' : 'Activate'; ?>
                </a>
                <button class="btn btn-sm btn-ghost"
                  onclick="openResetModal(<?php echo $p['id']; ?>, '<?php echo htmlspecialchars($p['client_id']); ?>')">
                  Reset Pass
                </button>
                <a href="?delete=<?php echo $p['id']; ?>"
                   class="btn btn-sm btn-danger"
                   onclick="return confirm('DELETE partner <?php echo htmlspecialchars($p['client_id']); ?>? This cannot be undone.')">
                  Delete
                </a>
              </div>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
      <?php endif; ?>
    </div>
  </div>
</div>

<!-- EDIT PARTNER MODAL -->
<div class="modal-overlay" id="editModal">
  <div class="modal">
    <button class="modal-close" onclick="closeEditModal()">✕</button>
    <h3>Edit <em>Partner</em></h3>
    <form method="POST" action="">
      <input type="hidden" name="action" value="edit"/>
      <input type="hidden" name="partner_id" id="edit_partner_id"/>
      <div class="form-grid" style="gap:14px;">
        <div class="field">
          <label for="edit_client_id">Client ID</label>
          <input type="text" id="edit_client_id" name="client_id" required/>
        </div>
        <div class="field">
          <label for="edit_label">Company Label</label>
          <input type="text" id="edit_label" name="label" placeholder="Optional"/>
        </div>
        <div class="field">
          <label for="edit_username">Username</label>
          <input type="text" id="edit_username" name="username" required/>
        </div>
        <div class="field full">
          <label for="edit_redirect_url">Hive Dashboard URL</label>
          <input type="text" id="edit_redirect_url" name="redirect_url" required/>
        </div>
        <div class="field checkbox-field">
          <label for="edit_is_active">
            <input type="checkbox" id="edit_is_active" name="is_active" value="1"/>
            Active (allow login)
          </label>
        </div>
      </div>
      <div class="modal-actions">
        <button type="button" class="btn btn-ghost" onclick="closeEditModal()">Cancel</button>
        <button type="submit" class="btn btn-primary">Save Changes</button>
      </div>
    </form>
  </div>
</div>

<!-- RESET PASSWORD MODAL -->
<div class="modal-overlay" id="resetModal">
  <div class="modal">
    <button class="modal-close" onclick="closeResetModal()">✕</button>
    <h3>Reset <em>Password</em></h3>
    <form method="POST" action="">
      <input type="hidden" name="action" value="reset_pass"/>
      <input type="hidden" name="partner_id" id="modal_partner_id"/>
      <div class="field" style="margin-bottom:0;">
        <label for="new_password">New Password for <span id="modal_client_label"
          style="color:var(--crimson);font-style:italic;"></span></label>
        <input type="password" id="new_password" name="new_password"
          placeholder="Min. 6 characters" required/>
      </div>
      <div class="modal-actions">
        <button type="button" class="btn btn-ghost" onclick="closeResetModal()">Cancel</button>
        <button type="submit" class="btn btn-primary">Update Password</button>
      </div>
    </form>
  </div>
</div>

<script>
  // Client ID auto uppercase in create form
  const cidInput = document.getElementById('client_id');
  if (cidInput) {
    cidInput.addEventListener('input', () => {
      const pos = cidInput.selectionStart;
      cidInput.value = cidInput.value.toUpperCase();
      cidInput.setSelectionRange(pos, pos);
    });
  }

  // Edit modal functions
  function openEditModal(id, clientId, username, redirectUrl, label, isActive) {
    document.getElementById('edit_partner_id').value = id;
    document.getElementById('edit_client_id').value = clientId;
    document.getElementById('edit_username').value = username;
    document.getElementById('edit_redirect_url').value = redirectUrl;
    document.getElementById('edit_label').value = label || '';
    document.getElementById('edit_is_active').checked = isActive;
    document.getElementById('editModal').classList.add('open');
  }
  function closeEditModal() {
    document.getElementById('editModal').classList.remove('open');
  }

  // Reset password modal functions
  function openResetModal(id, clientId) {
    document.getElementById('modal_partner_id').value = id;
    document.getElementById('modal_client_label').textContent = clientId;
    document.getElementById('resetModal').classList.add('open');
    document.getElementById('new_password').focus();
  }
  function closeResetModal() {
    document.getElementById('resetModal').classList.remove('open');
    document.getElementById('new_password').value = '';
  }

  // Close modals when clicking overlay
  document.getElementById('editModal').addEventListener('click', function(e) {
    if (e.target === this) closeEditModal();
  });
  document.getElementById('resetModal').addEventListener('click', function(e) {
    if (e.target === this) closeResetModal();
  });
</script>

</body>
</html>