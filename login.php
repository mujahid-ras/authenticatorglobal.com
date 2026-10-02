<?php
require __DIR__ . '/../includes/bootstrap.php';
require __DIR__ . '/../includes/layout.php';

if (current_admin()) { redirect('admin/index.php'); }

$error = '';
$user = '';
if (is_post()) {
    csrf_verify();
    $user = post('username');
    $pass = (string)($_POST['password'] ?? '');
    $key  = 'admin:' . strtolower($user);
    if (too_many_attempts($key)) {
        $error = 'Too many sign-in attempts. Please wait 15 minutes and try again.';
    } else {
        $st = db()->prepare('SELECT * FROM drc_admins WHERE username = ?');
        $st->execute([$user]);
        $a = $st->fetch();
        if (!$a || empty($a['password_hash']) || !password_verify($pass, $a['password_hash'])) {
            log_attempt($key);
            $error = 'Username or password is incorrect.';
        } elseif ($a['status'] === 'pending') {
            $error = 'This admin account is not activated yet. Use the activation link we emailed you.';
        } elseif ($a['status'] === 'disabled') {
            $error = 'This admin account has been deactivated.';
        } else {
            clear_attempts($key);
            session_regenerate_id(true);
            $_SESSION['admin_id'] = (int)$a['id'];
            $next = $_SESSION['admin_next'] ?? '';
            unset($_SESSION['admin_next']);
            if (is_string($next) && strpos($next, BASE_URL . '/admin/') === 0) { header('Location: ' . $next); exit; }
            redirect('admin/index.php');
        }
    }
}

html_head('Admin sign in', 'landing');
?>
<div class="login-panel" style="min-height:100vh">
  <div class="card">
    <h2>Admin sign in</h2>
    <p class="lead">Review and activate DRC customer accounts.</p>
    <?= flashes_html() ?>
    <?php if ($error): ?><div class="alert error" role="alert"><?= e($error) ?></div><?php endif; ?>
    <form method="post" novalidate>
      <?= csrf_field() ?>
      <div class="field"><label for="username">Username</label><input id="username" name="username" value="<?= e($user) ?>" autocomplete="username" required autofocus></div>
      <div class="field">
        <label for="password">Password</label>
        <div class="input-wrap">
          <input id="password" name="password" type="password" autocomplete="current-password" required>
          <button type="button" class="btn-icon" data-toggle-password="password" aria-label="Show password" aria-pressed="false"><?= icon('eye') ?></button>
        </div>
      </div>
      <button class="btn btn-block" type="submit">Sign in</button>
    </form>
  </div>
</div>
<?php html_foot();
