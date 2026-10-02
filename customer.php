<?php
require __DIR__ . '/../includes/bootstrap.php';
require __DIR__ . '/../includes/layout.php';
require_admin();

$id = (int)($_GET['id'] ?? 0);
$st = db()->prepare('SELECT * FROM drc_customers WHERE id = ?');
$st->execute([$id]);
$c = $st->fetch();
if (!$c) { http_response_code(404); exit('Customer not found.'); }

if (is_post()) {
    csrf_verify();
    $action = post('action');
    if ($action === 'activate' && $c['status'] === 'pending') {
        db()->prepare("UPDATE drc_customers SET status = 'active', activated_at = NOW() WHERE id = ?")->execute([$id]);
        $body = '<p>Hello ' . e($c['contact_person']) . ',</p>'
              . '<p>Good news: the DRC account for <strong>' . e($c['company_name']) . '</strong> has been activated and is ready to use.</p>'
              . '<p>Sign in with the email and password you registered with, then add your company GLN, production lines, partners and products.</p>'
              . email_button(SITE_URL . BASE_URL . '/index.php', 'Sign in to the DRC portal');
        $sent = send_mail($c['email'], 'Your DRC account is activated', 'Your account is ready', $body);
        flash('success', 'Account activated.' . ($sent ? ' The customer has been emailed.' : ' The email could not be sent, so please let the customer know.'));
    } elseif ($action === 'reactivate' && $c['status'] === 'disabled') {
        db()->prepare("UPDATE drc_customers SET status = 'active' WHERE id = ?")->execute([$id]);
        flash('success', 'Account reactivated.');
    } elseif ($action === 'disable' && $c['status'] === 'active') {
        db()->prepare("UPDATE drc_customers SET status = 'disabled' WHERE id = ?")->execute([$id]);
        flash('success', 'Account deactivated. The customer can no longer sign in.');
    }
    redirect('admin/customer.php?id=' . $id);
}

$fetch = function (string $table, string $order) use ($id): array {
    $st = db()->prepare("SELECT * FROM $table WHERE customer_id = ? ORDER BY $order");
    $st->execute([$id]);
    return $st->fetchAll();
};
$lines = $fetch('drc_lines', 'line_name');
$partners = $fetch('drc_partners', 'name');
$products = $fetch('drc_products', 'product_name');
$partnerLabel = $c['account_type'] === 'Manufacturer' ? 'Importers' : 'Manufacturers';
$statusLabel = $c['status'] === 'disabled' ? 'Deactivated' : ucfirst($c['status']);

app_head($c['company_name'], 'customers', 'admin');
?>
<div class="stack">
  <p><a href="<?= e(url('admin/index.php')) ?>">Back to customers</a></p>

  <div class="cols">
    <section class="panel">
      <div class="panel-head"><h2>Registration details</h2><span class="pill <?= e($c['status']) ?>"><?= e($statusLabel) ?></span></div>
      <dl class="details">
        <div><dt>Company</dt><dd><?= e($c['company_name']) ?></dd></div>
        <div><dt>Customer type</dt><dd><?= e($c['account_type']) ?></dd></div>
         <div><dt>Industry</dt><dd><?= e($c['Industry']) ?></dd></div>
        <div><dt>Country</dt><dd><?= e($c['country']) ?></dd></div>
        <div><dt>Contact person</dt><dd><?= e($c['contact_person']) ?></dd></div>
        <div><dt>Contact number</dt><dd><?= e($c['contact_number'] ?: '—') ?></dd></div>
        <div><dt>Email</dt><dd><?= e($c['email']) ?></dd></div>
        <div><dt>Address</dt><dd><?= e($c['address']) ?></dd></div>
        <div><dt>Company GLN</dt><dd class="num"><?= e($c['gln'] ?: 'Not entered yet') ?></dd></div>
        <div><dt>Registered</dt><dd><?= e(fmt_date($c['created_at'])) ?></dd></div>
        <div><dt>Activated</dt><dd><?= e(fmt_date($c['activated_at'])) ?></dd></div>
        <div><dt>Last sign-in</dt><dd><?= e(fmt_date($c['last_login'])) ?></dd></div>
      </dl>
    </section>

    <section class="panel">
      <div class="panel-head"><h2>Access</h2></div>
      <?php if ($c['status'] === 'pending'): ?>
        <p class="muted">This customer cannot sign in yet. Activating sends them an email to say the account is ready.</p>
        <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="activate"><button class="btn" type="submit">Activate account</button></form>
      <?php elseif ($c['status'] === 'active'): ?>
        <p class="muted">This customer can sign in and manage their lines, partners and products.</p>
        <form method="post" data-confirm="Deactivate this account? The customer will no longer be able to sign in."><?= csrf_field() ?><input type="hidden" name="action" value="disable"><button class="btn btn-ghost" type="submit">Deactivate account</button></form>
      <?php else: ?>
        <p class="muted">This account is deactivated and cannot sign in.</p>
        <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="reactivate"><button class="btn" type="submit">Reactivate account</button></form>
      <?php endif; ?>
    </section>
  </div>

  <section class="panel">
    <div class="panel-head"><h2>Production lines (<?= count($lines) ?>)</h2></div>
    <?php if (!$lines): ?><p class="muted">None registered.</p><?php else: ?>
      <div class="table-wrap"><table><thead><tr><th>Line name</th><th>Added</th></tr></thead><tbody>
      <?php foreach ($lines as $l): ?><tr><td><?= e($l['line_name']) ?></td><td class="muted"><?= e(fmt_date($l['created_at'])) ?></td></tr><?php endforeach; ?>
      </tbody></table></div>
    <?php endif; ?>
  </section>

  <section class="panel">
    <div class="panel-head"><h2><?= e($partnerLabel) ?> (<?= count($partners) ?>)</h2></div>
    <?php if (!$partners): ?><p class="muted">None registered.</p><?php else: ?>
      <div class="table-wrap"><table><thead><tr><th>Name</th><th>GLN</th><th>Address</th></tr></thead><tbody>
      <?php foreach ($partners as $p): ?><tr><td><?= e($p['name']) ?></td><td class="num"><?= e($p['gln']) ?></td><td><?= e($p['address']) ?></td></tr><?php endforeach; ?>
      </tbody></table></div>
    <?php endif; ?>
  </section>

  <section class="panel">
    <div class="panel-head"><h2>Products (<?= count($products) ?>)</h2></div>
    <?php if (!$products): ?><p class="muted">None registered.</p><?php else: ?>
      <div class="table-wrap"><table><thead><tr><th>Product</th><th>Unit GTIN</th><th>Outer</th><th>Master</th><th>Pallet</th><th>Quantity</th></tr></thead><tbody>
      <?php foreach ($products as $p): ?>
        <tr>
          <td><?= e($p['product_name']) ?></td>
          <td class="num"><?= e($p['gtin_unit']) ?></td>
          <td class="num"><?= e($p['gtin_outer'] ?: '—') ?></td>
          <td class="num"><?= e($p['gtin_master'] ?: '—') ?></td>
          <td class="num"><?= e($p['gtin_pallet'] ?: '—') ?></td>
          <td class="num"><?= e(number_format((int)$p['quantity'])) ?><span class="sub"><?= e($p['period']) ?></span></td>
        </tr>
      <?php endforeach; ?>
      </tbody></table></div>
    <?php endif; ?>
  </section>
</div>
<?php app_foot();
