<?php
require __DIR__ . '/includes/bootstrap.php';
require __DIR__ . '/includes/layout.php';

if (current_customer()) { redirect('dashboard.php'); }

 $countries = country_list();
 $errors = [];
 $v = ['company_name' => '', 'email' => '', 'country' => '', 'contact_person' => '', 'contact_number' => '', 'address' => '', 'account_type' => '', 'industry' => ''];

if (is_post()) {
    csrf_verify();
    foreach ($v as $k => $_) { $v[$k] = post($k); }
    $v['email'] = strtolower($v['email']);
    $password = (string)($_POST['password'] ?? '');
    $confirm  = (string)($_POST['password_confirm'] ?? '');

    if (post('website') !== '') { redirect('index.php'); } // honeypot

    $len = function (string $s): int { return function_exists('mb_strlen') ? mb_strlen($s) : strlen($s); };

    if ($len($v['company_name']) < 2 || $len($v['company_name']) > 150) { $errors['company_name'] = t('err_company_name'); }
    if (!filter_var($v['email'], FILTER_VALIDATE_EMAIL) || $len($v['email']) > 190) { $errors['email'] = t('err_email_invalid'); }
    if (strlen($password) < 8 || !preg_match('/[A-Za-z]/', $password) || !preg_match('/\d/', $password)) {
        $errors['password'] = t('err_password_policy');
    } elseif ($password !== $confirm) {
        $errors['password_confirm'] = t('err_password_match');
    }
    if (!in_array($v['country'], $countries, true)) { $errors['country'] = t('err_country'); }
    if ($len($v['contact_person']) < 2 || $len($v['contact_person']) > 100) { $errors['contact_person'] = t('err_contact_person'); }
    if ($v['contact_number'] !== '' && !preg_match('/^[+0-9()\-\s]{6,25}$/', $v['contact_number'])) { $errors['contact_number'] = t('err_contact_number'); }
    if ($len($v['address']) < 5 || $len($v['address']) > 255) { $errors['address'] = t('err_address'); }
    if (!in_array($v['account_type'], ['Manufacturer', 'Importer'], true)) { $errors['account_type'] = t('err_account_type'); }
    
    $allowed_industries = ['Tobacco', 'Pharmaceutical', 'Medical Devices', 'Cosmetics & Personal Care', 'Food & Beverage', 'Dairy', 'Bottled Water', 'Alcohol & Spirits', 'Automotive Parts', 'Electronics & Appliances', 'Apparel & Textiles', 'Luxury Goods', 'Agrochemicals & Seeds', 'Logistics & Supply Chain', 'Other'];
    if (!in_array($v['industry'], $allowed_industries, true)) { $errors['industry'] = t('err_industry'); }

    if (!isset($errors['email'])) {
        $st = db()->prepare('SELECT id FROM drc_customers WHERE email = ?');
        $st->execute([$v['email']]);
        if ($st->fetch()) { $errors['email'] = t('err_email_exists'); }
    }

    if (!$errors) {
        $st = db()->prepare(
            'INSERT INTO drc_customers (company_name, email, password_hash, country, contact_person, contact_number, address, account_type, Industry, status)
             VALUES (?,?,?,?,?,?,?,?,?,\'pending\')'
        );
        $st->execute([
            $v['company_name'], $v['email'], password_hash($password, PASSWORD_DEFAULT), $v['country'],
            $v['contact_person'], ($v['contact_number'] !== '' ? $v['contact_number'] : null), $v['address'], $v['account_type'], $v['industry'],
        ]);
        $id = (int)db()->lastInsertId();

        // Tell the admin there is a new registration waiting
        $link = SITE_URL . BASE_URL . '/admin/customer.php?id=' . $id;
        $body = '<p>' . e(t('admin_email_intro')) . '</p>'
              . '<table cellpadding="6" style="font-size:14px">'
              . '<tr><td><b>' . e(t('admin_email_company')) . '</b></td><td>' . e($v['company_name']) . '</td></tr>'
              . '<tr><td><b>' . e(t('admin_email_type')) . '</b></td><td>' . e($v['account_type']) . '</td></tr>'
              . '<tr><td><b>' . e(t('industry')) . '</b></td><td>' . e($v['industry']) . '</td></tr>'
              . '<tr><td><b>' . e(t('admin_email_country')) . '</b></td><td>' . e($v['country']) . '</td></tr>'
              . '<tr><td><b>' . e(t('admin_email_contact')) . '</b></td><td>' . e($v['contact_person']) . ($v['contact_number'] !== '' ? ', ' . e($v['contact_number']) : '') . '</td></tr>'
              . '<tr><td><b>' . e(t('email')) . '</b></td><td>' . e($v['email']) . '</td></tr></table>'
              . email_button($link, t('admin_email_btn'));
        foreach (admin_notify_emails() as $adminMail) {
            send_mail($adminMail, t('admin_email_subject') . $v['company_name'], t('admin_email_intro'), $body);
        }

        flash('success', t('reg_success') . $v['email'] . '.');
        redirect('index.php');
    }
}

function fld_err(array $errors, string $k): string {
    return isset($errors[$k]) ? '<div class="hint bad" id="err-' . e($k) . '">' . e($errors[$k]) . '</div>' : '';
}
function aria_inv(array $errors, string $k): string {
    return isset($errors[$k]) ? ' aria-invalid="true" aria-describedby="err-' . e($k) . '"' : '';
}

// Current page URL for language switcher
 $current_page = basename($_SERVER['PHP_SELF']);
html_head(t('customer_registration'), 'register');
?>
<div class="reg-top">
  <a class="brand" href="<?= e(url('index.php')) ?>"><?= brand_mark() ?><span><strong><?= e(SITE_NAME) ?></strong><small>DRC portal</small></span></a>
  <div style="display:flex; gap:16px; align-items:center;">
    <div class="lang-switcher" style="margin:0;">
      <a href="<?= e(url($current_page . '?lang=en')) ?>" class="<?= ($_SESSION['lang'] ?? 'en') === 'en' ? 'active' : '' ?>">EN</a>
      <a href="<?= e(url($current_page . '?lang=fr')) ?>" class="<?= ($_SESSION['lang'] ?? 'en') === 'fr' ? 'active' : '' ?>">FR</a>
    </div>
    <a class="back" href="<?= e(url('index.php')) ?>"><?= e(t('already_registered_signin')) ?></a>
  </div>
</div>

<div class="reg-wrap">
  <h1><?= e(t('drc_customer_registration')) ?></h1>
  <p class="lead"><?= e(t('registration_lead')) ?></p>

  <?php if ($errors): ?>
    <div class="alert error" role="alert"><?= e(t('fix_highlighted_fields')) ?></div>
  <?php endif; ?>

  <form method="post" class="panel" novalidate>
    <?= csrf_field() ?>
    <div class="hp" aria-hidden="true"><label>Website <input type="text" name="website" tabindex="-1" autocomplete="off"></label></div>

    <div class="form-section">
      <h2><?= e(t('company')) ?></h2>
      <div class="grid-2">
        <div class="field span-2">
          <label for="company_name"><?= e(t('company_name')) ?></label>
          <input id="company_name" name="company_name" value="<?= e($v['company_name']) ?>" maxlength="150" required<?= aria_inv($errors, 'company_name') ?>>
          <?= fld_err($errors, 'company_name') ?>
        </div>
        <div class="field">
          <label for="account_type"><?= e(t('customer_type')) ?></label>
          <select id="account_type" name="account_type" required<?= aria_inv($errors, 'account_type') ?>>
            <option value=""><?= e(t('select_customer_type')) ?></option>
            <option value="Manufacturer"<?= $v['account_type'] === 'Manufacturer' ? ' selected' : '' ?>><?= e(t('manufacturer')) ?></option>
            <option value="Importer"<?= $v['account_type'] === 'Importer' ? ' selected' : '' ?>><?= e(t('importer')) ?></option>
          </select>
          <?= fld_err($errors, 'account_type') ?>
        </div>
        <div class="field">
          <label for="industry"><?= e(t('industry')) ?></label>
          <select id="industry" name="industry" required<?= aria_inv($errors, 'industry') ?>>
            <option value=""><?= e(t('select_industry')) ?></option>
            <option value="Tobacco"<?= $v['industry'] === 'Tobacco' ? ' selected' : '' ?>><?= e(t('ind_tobacco')) ?></option>
            <option value="Pharmaceutical"<?= $v['industry'] === 'Pharmaceutical' ? ' selected' : '' ?>><?= e(t('ind_pharma')) ?></option>
            <option value="Medical Devices"<?= $v['industry'] === 'Medical Devices' ? ' selected' : '' ?>><?= e(t('ind_medical')) ?></option>
            <option value="Cosmetics & Personal Care"<?= $v['industry'] === 'Cosmetics & Personal Care' ? ' selected' : '' ?>><?= e(t('ind_cosmetics')) ?></option>
            <option value="Food & Beverage"<?= $v['industry'] === 'Food & Beverage' ? ' selected' : '' ?>><?= e(t('ind_food_beverage')) ?></option>
            <option value="Dairy"<?= $v['industry'] === 'Dairy' ? ' selected' : '' ?>><?= e(t('ind_dairy')) ?></option>
            <option value="Bottled Water"<?= $v['industry'] === 'Bottled Water' ? ' selected' : '' ?>><?= e(t('ind_water')) ?></option>
            <option value="Alcohol & Spirits"<?= $v['industry'] === 'Alcohol & Spirits' ? ' selected' : '' ?>><?= e(t('ind_alcohol')) ?></option>
            <option value="Automotive Parts"<?= $v['industry'] === 'Automotive Parts' ? ' selected' : '' ?>><?= e(t('ind_auto')) ?></option>
            <option value="Electronics & Appliances"<?= $v['industry'] === 'Electronics & Appliances' ? ' selected' : '' ?>><?= e(t('ind_electronics')) ?></option>
            <option value="Apparel & Textiles"<?= $v['industry'] === 'Apparel & Textiles' ? ' selected' : '' ?>><?= e(t('ind_apparel')) ?></option>
            <option value="Luxury Goods"<?= $v['industry'] === 'Luxury Goods' ? ' selected' : '' ?>><?= e(t('ind_luxury')) ?></option>
            <option value="Agrochemicals & Seeds"<?= $v['industry'] === 'Agrochemicals & Seeds' ? ' selected' : '' ?>><?= e(t('ind_agro')) ?></option>
            <option value="Logistics & Supply Chain"<?= $v['industry'] === 'Logistics & Supply Chain' ? ' selected' : '' ?>><?= e(t('ind_logistics')) ?></option>
            <option value="Other"<?= $v['industry'] === 'Other' ? ' selected' : '' ?>><?= e(t('ind_other')) ?></option>
          </select>
          <?= fld_err($errors, 'industry') ?>
        </div>
        <div class="field">
          <label for="country"><?= e(t('country')) ?></label>
          <select id="country" name="country" required<?= aria_inv($errors, 'country') ?>>
            <option value=""><?= e(t('select_country')) ?></option>
            <?php foreach ($countries as $c): ?>
              <option value="<?= e($c) ?>"<?= $v['country'] === $c ? ' selected' : '' ?>><?= e($c) ?></option>
            <?php endforeach; ?>
          </select>
          <?= fld_err($errors, 'country') ?>
        </div>
        <div class="field span-2">
          <label for="address"><?= e(t('address')) ?></label>
          <input id="address" name="address" value="<?= e($v['address']) ?>" maxlength="255" autocomplete="street-address" required<?= aria_inv($errors, 'address') ?>>
          <?= fld_err($errors, 'address') ?>
        </div>
      </div>
    </div>

    <div class="form-section">
      <h2><?= e(t('contact')) ?></h2>
      <div class="grid-2">
        <div class="field">
          <label for="contact_person"><?= e(t('contact_person')) ?></label>
          <input id="contact_person" name="contact_person" value="<?= e($v['contact_person']) ?>" maxlength="100" autocomplete="name" required<?= aria_inv($errors, 'contact_person') ?>>
          <?= fld_err($errors, 'contact_person') ?>
        </div>
        <div class="field">
          <label for="contact_number"><?= e(t('contact_number')) ?> <span class="muted" style="font-weight:400"><?= e(t('optional')) ?></span></label>
          <input id="contact_number" name="contact_number" type="tel" value="<?= e($v['contact_number']) ?>" maxlength="25" autocomplete="tel"<?= aria_inv($errors, 'contact_number') ?>>
          <?= fld_err($errors, 'contact_number') ?>
        </div>
      </div>
    </div>

    <div class="form-section">
      <h2><?= e(t('signin_details')) ?></h2>
      <div class="grid-2">
        <div class="field span-2">
          <label for="email"><?= e(t('email')) ?></label>
          <input id="email" name="email" type="email" value="<?= e($v['email']) ?>" maxlength="190" autocomplete="email" required<?= aria_inv($errors, 'email') ?>>
          <div class="hint"><?= e(t('email_hint')) ?></div>
          <?= fld_err($errors, 'email') ?>
        </div>
        <div class="field">
          <label for="password"><?= e(t('password')) ?></label>
          <div class="input-wrap">
            <input id="password" name="password" type="password" autocomplete="new-password" required<?= aria_inv($errors, 'password') ?>>
            <button type="button" class="btn-icon" data-toggle-password="password" aria-label="<?= e(t('show_password')) ?>" aria-pressed="false"><?= icon('eye') ?></button>
          </div>
          <div class="hint"><?= e(t('password_hint')) ?></div>
          <?= fld_err($errors, 'password') ?>
        </div>
        <div class="field">
          <label for="password_confirm"><?= e(t('confirm_password')) ?></label>
          <input id="password_confirm" name="password_confirm" type="password" autocomplete="new-password" required<?= aria_inv($errors, 'password_confirm') ?>>
          <?= fld_err($errors, 'password_confirm') ?>
        </div>
      </div>
    </div>

    <button type="submit" class="btn"><?= e(t('submit_registration')) ?></button>
  </form>
</div>
<?php html_foot();