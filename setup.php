<?php
// One-time: creates the first admin. Locks itself as soon as any admin exists.
require __DIR__ . '/../includes/bootstrap.php';
require __DIR__ . '/../includes/layout.php';

$exists = (int)db()->query('SELECT COUNT(*) FROM drc_admins')->fetchColumn() > 0;
if ($exists) {
    http_response_code(404);
    exit('Setup is already complete. You can delete admin/setup.php from the server.');
}

$err = [];
$v = ['username' => '', 'email' => ''];
if (is_post()) {
    csrf_verify();
    $v['username'] = post('username');
    $v['email'] = strtolower(post('email'));
    $pass = (string)($_POST['password'] ?? '');
    if (!preg_match('/^[A-Za-z0-9._-]{3,60}$/', $v['username'])) { $err[] = 'Username: 3 to 60 letters, numbers, dots, dashes or underscores.'; }
    if (!filter_var($v['email'], FILTER_VALIDATE_EMAIL)) { $err[] = 'Enter a valid email address.'; }
    if (strlen($pass) < 10) { $err[] = 'Use a password of at least 10 characters.'; }
    if (!$err) {
        db()->prepare('INSERT INTO drc_admins (username, email, password_hash) VALUES (?,?,?)')
            ->execute([$v['username'], $v['email'], password_hash($pass, PASSWORD_DEFAULT)]);
        flash('success', 'Admin account created. Sign in below.');
        redirect('admin/login.php');
    }
}

html_head('Create admin', 'landing');
?>
<div class="login-panel" style="min-height:100vh">
  <div class="card">
    <h2>Create the first admin</h2>
    <p class="lead">This page works once. After an admin exists it locks itself.</p>
    <?php if ($err): ?><div class="alert error" role="alert"><ul><?php foreach ($err as $x): ?><li><?= e($x) ?></li><?php endforeach; ?></ul></div><?php endif; ?>
    <form method="post" novalidate>
      <?= csrf_field() ?>
      <div class="field"><label for="username">Username</label><input id="username" name="username" value="<?= e($v['username']) ?>" required autocomplete="username"></div>
      <div class="field"><label for="email">Email</label><input id="email" name="email" type="email" value="<?= e($v['email']) ?>" required></div>
      <div class="field"><label for="password">Password</label><input id="password" name="password" type="password" autocomplete="new-password" required><div class="hint">At least 10 characters.</div></div>
      <button class="btn btn-block" type="submit">Create admin</button>
    </form>
  </div>
</div>
<?php html_foot();
