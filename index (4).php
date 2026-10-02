<?php
require __DIR__ . '/includes/bootstrap.php';
require __DIR__ . '/includes/layout.php';

if (current_customer()) { redirect('dashboard.php'); }

 $error = '';
 $email = '';
if (is_post()) {
    csrf_verify();
    $email = strtolower(post('email'));
    $pass  = (string)($_POST['password'] ?? '');

    if (too_many_attempts($email)) {
        $error = 'Too many sign-in attempts. Please wait 15 minutes and try again.';
    } else {
        $st = db()->prepare('SELECT * FROM drc_customers WHERE email = ?');
        $st->execute([$email]);
        $c = $st->fetch();
        if (!$c || !password_verify($pass, $c['password_hash'])) {
            log_attempt($email);
            $error = 'Email or password is incorrect.';
        } elseif ($c['status'] === 'pending') {
            $error = 'Your account is waiting for admin approval. We will email you as soon as it is activated.';
        } elseif ($c['status'] === 'disabled') {
            $error = 'This account has been deactivated. Please contact support.';
        } else {
            clear_attempts($email);
            session_regenerate_id(true);
            $_SESSION['customer_id'] = (int)$c['id'];
            db()->prepare('UPDATE drc_customers SET last_login = NOW() WHERE id = ?')->execute([$c['id']]);
            redirect('dashboard.php');
        }
    }
}

html_head(t('sign_in_title'), 'landing');
?>
<!-- Yeh Flag Background pure page ke piche laga hai -->
<div class="flag-bg" aria-hidden="true"><?= flag_backdrop_svg() ?></div>

<?= language_selector() ?>
<div class="split">
  <section class="brand-panel">
    <!-- flag_backdrop_svg() yahan se hata diya gaya hai -->
    <a class="brand" href="<?= e(url('index.php')) ?>"><?= brand_mark() ?><span><strong><?= e(SITE_NAME) ?></strong><small>DRC portal</small></span></a>

    <div class="brand-copy">
      <h1><?= t('Register your lines & products once.')?></h1>
      <p><?= t('The DRC portal holds the master data behind your serialized packs.') ?></p>
    </div>

    <div class="label-sample" aria-hidden="true">
      <?= datamatrix_svg() ?>
      <dl>
        <div><dt>(01)</dt> <dd>09501101530003</dd></div>
        <div><dt>(21)</dt> <dd>8K42QX9M7T3B</dd></div>
        <div><dt>(10)</dt> <dd>LOT2609A</dd></div>
      </dl>
    </div>
  </section>

  <section class="login-panel">
    <div class="card">
      <h2><?= t('sign_in_title') ?></h2>
      <p class="lead"><?= t('sign_in_subtitle') ?></p>

      <?= flashes_html() ?>
      <?php if ($error): ?><div class="alert error" role="alert"><?= e($error) ?></div><?php endif; ?>

      <form method="post" novalidate>
        <?= csrf_field() ?>
        <div class="field">
          <label for="email"><?= t('email') ?></label>
          <input type="email" id="email" name="email" value="<?= e($email) ?>" autocomplete="username" required autofocus>
        </div>
        <div class="field">
          <label for="password"><?= t('password') ?></label>
          <div class="input-wrap">
            <input type="password" id="password" name="password" autocomplete="current-password" required>
            <button type="button" class="btn-icon" data-toggle-password="password" aria-label="Show password" aria-pressed="false"><?= icon('eye') ?></button>
          </div>
        </div>
        <button type="submit" class="btn btn-block"><?= t('sign_in') ?></button>
      </form>

      <p class="alt"><?= t('new_account') ?> <a href="<?= e(url('register.php')) ?>"><?= t('create_customer_account') ?></a></p>
    </div>
  </section>
</div>
<?php html_foot();