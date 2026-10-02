<?php
require __DIR__ . '/../includes/bootstrap.php';
require __DIR__ . '/../includes/layout.php';
$me = require_admin();
$form = ['username' => '', 'email' => ''];

if (is_post()) {
    csrf_verify();
    $action = post('action');

    if ($action === 'invite') {
        $form = ['username' => post('username'), 'email' => strtolower(post('email'))];
        $err = [];
        if (!preg_match('/^[A-Za-z0-9._-]{3,60}$/', $form['username'])) { $err[] = 'Username: 3 to 60 letters, numbers, dots, dashes or underscores.'; }
        if (!filter_var($form['email'], FILTER_VALIDATE_EMAIL) || strlen($form['email']) > 190) { $err[] = 'Enter a valid email address.'; }
        if (!$err) {
            $st = db()->prepare('SELECT id FROM drc_admins WHERE username = ? OR email = ?');
            $st->execute([$form['username'], $form['email']]);
            if ($st->fetch()) { $err[] = 'An admin with this username or email already exists.'; }
        }
        if ($err) {
            flash('error', implode(' ', $err));
        } else {
            db()->prepare("INSERT INTO drc_admins (username, email, password_hash, status, invited_by) VALUES (?, ?, NULL, 'pending', ?)")
                ->execute([$form['username'], $form['email'], $me['id']]);
            $newId = (int)db()->lastInsertId();
            $sent = send_admin_invite($newId, $form['username'], $form['email']);
            flash($sent ? 'success' : 'error', $sent
                ? 'Admin added. An activation email has been sent to ' . $form['email'] . '.'
                : 'Admin added, but the activation email could not be sent. Check the mail settings, then use “Resend invite”.');
            redirect('admin/admins.php');
        }
    } else {
        $id = (int)post('id');
        $st = db()->prepare('SELECT * FROM drc_admins WHERE id = ?');
        $st->execute([$id]);
        $a = $st->fetch();
        if ($a) {
            if ($action === 'resend' && $a['status'] === 'pending') {
                $sent = send_admin_invite((int)$a['id'], $a['username'], $a['email']);
                flash($sent ? 'success' : 'error', $sent ? 'Activation email sent again.' : 'The email could not be sent. Check the mail settings.');
            } elseif ($action === 'disable' && $a['status'] === 'active' && (int)$a['id'] !== (int)$me['id']) {
                db()->prepare("UPDATE drc_admins SET status = 'disabled' WHERE id = ?")->execute([$id]);
                flash('success', 'Admin deactivated.');
            } elseif ($action === 'enable' && $a['status'] === 'disabled') {
                $next = empty($a['password_hash']) ? 'pending' : 'active';
                db()->prepare('UPDATE drc_admins SET status = ? WHERE id = ?')->execute([$next, $id]);
                flash('success', $next === 'active' ? 'Admin reactivated.' : 'Admin restored. They still need to use their activation link, or you can resend it.');
            }
        }
        redirect('admin/admins.php');
    }
}

$admins = db()->query('SELECT a.*, i.username AS inviter FROM drc_admins a LEFT JOIN drc_admins i ON i.id = a.invited_by ORDER BY a.created_at')->fetchAll();

app_head('Admins', 'admins', 'admin', 'Add people who can review and activate customers. Each new admin gets an email to activate their account.');
?>
<div class="cols">
  <section class="panel">
    <div class="panel-head"><h2>Admins (<?= count($admins) ?>)</h2></div>
    <div class="table-wrap"><table>
      <thead><tr><th>Admin</th><th>Status</th><th>Added</th><th></th></tr></thead>
      <tbody>
      <?php foreach ($admins as $a): $isMe = (int)$a['id'] === (int)$me['id']; ?>
        <tr>
          <td><strong><?= e($a['username']) ?></strong><?= $isMe ? ' <span class="muted small">(you)</span>' : '' ?><span class="sub"><?= e($a['email']) ?></span></td>
          <td><span class="pill <?= e($a['status']) ?>"><?= e($a['status'] === 'pending' ? 'Invited' : ($a['status'] === 'disabled' ? 'Deactivated' : 'Active')) ?></span></td>
          <td class="muted"><?= e(fmt_date($a['created_at'])) ?><?php if (!empty($a['inviter'])): ?><span class="sub">by <?= e($a['inviter']) ?></span><?php endif; ?></td>
          <td class="actions">
            <?php if ($a['status'] === 'pending'): ?>
              <form method="post" class="inline"><?= csrf_field() ?><input type="hidden" name="action" value="resend"><input type="hidden" name="id" value="<?= (int)$a['id'] ?>"><button class="btn btn-sm btn-ghost" type="submit">Resend invite</button></form>
            <?php elseif ($a['status'] === 'active' && !$isMe): ?>
              <form method="post" class="inline" data-confirm="Deactivate this admin? They will no longer be able to sign in."><?= csrf_field() ?><input type="hidden" name="action" value="disable"><input type="hidden" name="id" value="<?= (int)$a['id'] ?>"><button class="btn btn-sm btn-ghost" type="submit">Deactivate</button></form>
            <?php elseif ($a['status'] === 'disabled'): ?>
              <form method="post" class="inline"><?= csrf_field() ?><input type="hidden" name="action" value="enable"><input type="hidden" name="id" value="<?= (int)$a['id'] ?>"><button class="btn btn-sm btn-ghost" type="submit">Reactivate</button></form>
            <?php endif; ?>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table></div>
  </section>

  <section class="panel">
    <div class="panel-head"><h2>Add an admin</h2></div>
    <form method="post" novalidate>
      <?= csrf_field() ?><input type="hidden" name="action" value="invite">
      <div class="field">
        <label for="username">Username</label>
        <input id="username" name="username" value="<?= e($form['username']) ?>" maxlength="60" required autocomplete="off">
      </div>
      <div class="field">
        <label for="email">Email</label>
        <input id="email" name="email" type="email" value="<?= e($form['email']) ?>" maxlength="190" required autocomplete="off">
        <div class="hint">We email an activation link here. They choose their own password.</div>
      </div>
      <button class="btn" type="submit">Add admin and send email</button>
    </form>
  </section>
</div>
<?php app_foot();
