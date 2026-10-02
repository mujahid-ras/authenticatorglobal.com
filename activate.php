<?php
require __DIR__ . '/../includes/bootstrap.php';
require __DIR__ . '/../includes/layout.php';

$token = (string)($_GET['token'] ?? $_POST['token'] ?? '');
$admin = null;
if (preg_match('/^[a-f0-9]{64}$/', $token)) {
    $st = db()->prepare("SELECT * FROM drc_admins WHERE invite_token = ? AND status = 'pending' AND invite_expires > NOW()");
    $st->execute([hash('sha256', $token)]);
    $admin = $st->fetch() ?: null;
}

$err = '';
if ($admin && is_post()) {
    csrf_verify();
    $pass = (string)($_POST['password'] ?? '');
    $conf = (string)($_POST['password_confirm'] ?? '');
    if (strlen($pass) < 10) {
        $err = 'Use a password of at least 10 characters.';
    } elseif ($pass !== $conf) {
        $err = 'The two passwords do not match.';
    } else {
        db()->prepare("UPDATE drc_admins SET password_hash = ?, status = 'active', invite_token = NULL, invite_expires = NULL WHERE id = ?")
            ->execute([password_hash($pass, PASSWORD_DEFAULT), $admin['id']]);
        flash('success', 'Your admin account is active. Sign in with username “' . $admin['username'] . '”.');
        redirect('admin/login.php');
    }
}

html_head('Activate admin account', 'landing');
?>
<div class="login-panel" style="min-height:100vh">
  <div class="card">
  <?php if (!$admin): ?>
    <h2>Link not valid</h2>
    <p class="lead">This activation link is invalid or has expired. Ask an existing admin to resend your invite.</p>
    <a class="btn btn-block" href="<?= e(url('admin/login.php')) ?>">Go to admin sign in</a>
  <?php else: ?>
    <h2>Activate your account</h2>
    <p class="lead">Welcome, <?= e($admin['username']) ?>. Choose a password to finish setting up your admin account.</p>
    <?php if ($err): ?><div class="alert error" role="alert"><?= e($err) ?></div><?php endif; ?>
    <form method="post" novalidate>
      <?= csrf_field() ?><input type="hidden" name="token" value="<?= e($token) ?>">
      <div class="field">
        <label for="password">Password</label>
        <div class="input-wrap">
          <input id="password" name="password" type="password" autocomplete="new-password" required autofocus>
          <button type="button" class="btn-icon" data-toggle-password="password" aria-label="Show password" aria-pressed="false"><?= icon('eye') ?></button>
        </div>
        <div class="hint">At least 10 characters.</div>
      </div>
      <div class="field"><label for="password_confirm">Confirm password</label><input id="password_confirm" name="password_confirm" type="password" autocomplete="new-password" required></div>
      <button class="btn btn-block" type="submit">Activate account</button>
    </form>
  <?php endif; ?>
  </div>
</div>
<?php html_foot();
