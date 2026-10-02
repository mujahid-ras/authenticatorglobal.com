<?php
require __DIR__ . '/includes/bootstrap.php';
require __DIR__ . '/includes/layout.php';
$c = require_customer();
$cid = (int)$c['id'];

if (is_post()) {
    csrf_verify();
    $gln = digits_only(post('gln'));
    if (!is_gln($gln)) {
        flash('error', gs1_error('GLN', $gln, [13]));
    } else {
        db()->prepare('UPDATE drc_customers SET gln = ? WHERE id = ?')->execute([$gln, $cid]);
        flash('success', 'Company GLN saved.');
    }
    redirect('dashboard.php');
}

$count = function (string $table) use ($cid): int {
    $st = db()->prepare("SELECT COUNT(*) FROM $table WHERE customer_id = ?");
    $st->execute([$cid]);
    return (int)$st->fetchColumn();
};
$nLines = $count('drc_lines');
$nPartners = $count('drc_partners');
$nProducts = $count('drc_products');
$partnerWord = $c['account_type'] === 'Manufacturer' ? 'importer' : 'manufacturer';
$partnerArticle = $c['account_type'] === 'Manufacturer' ? 'an' : 'a';

$steps = [
    [!empty($c['gln']), 'Enter your company GLN', 'Your 13-digit Global Location Number identifies your company.', null],
    [$nLines > 0, 'Register a production line', 'Add each line you run. You can add as many as you need.', 'lines.php'],
    [$nPartners > 0, 'Add ' . $partnerArticle . ' ' . $partnerWord, 'Record the ' . $partnerWord . 's you work with, with their GLN and address.', 'partners.php'],
    [$nProducts > 0, 'Register your products', 'Add each product with its GTIN and the quantity you expect.', 'products.php'],
];

app_head('Overview', 'overview', 'customer', 'Welcome, ' . $c['contact_person'] . '. Finish these steps to complete your registration.');
?>
<div class="stack">
  <div class="stats">
    <div class="stat"><b><?= $nLines ?></b><span>Production lines</span></div>
    <div class="stat"><b><?= $nPartners ?></b><span><?= e($c['account_type'] === 'Manufacturer' ? 'Importers' : 'Manufacturers') ?></span></div>
    <div class="stat"><b><?= $nProducts ?></b><span>Products</span></div>
  </div>

  <div class="cols">
    <section class="panel">
      <div class="panel-head"><h2>Setup checklist</h2></div>
      <ol class="steps">
        <?php foreach ($steps as $i => [$done, $title, $desc, $href]): ?>
          <li class="<?= $done ? 'done' : '' ?>">
            <span class="step-dot"><?= $done ? icon('check') : ($i + 1) ?></span>
            <div>
              <strong><?= e($title) ?></strong>
              <span class="small"><?= e($desc) ?></span>
              <?php if ($href && !$done): ?><div style="margin-top:8px"><a class="btn btn-sm btn-ghost" href="<?= e(url($href)) ?>">Go to this step</a></div><?php endif; ?>
            </div>
          </li>
        <?php endforeach; ?>
      </ol>
    </section>

    <div class="stack">
      <section class="panel">
        <div class="panel-head"><h2>Company GLN</h2></div>
        <form method="post" novalidate>
          <?= csrf_field() ?>
          <div class="field">
            <label for="gln">Global Location Number</label>
            <input id="gln" name="gln" inputmode="numeric" maxlength="13" value="<?= e($c['gln'] ?? '') ?>" data-gs1="gln" placeholder="13 digits">
          </div>
          <button class="btn" type="submit">Save GLN</button>
        </form>
      </section>

      <section class="panel">
        <div class="panel-head"><h2>Company details</h2></div>
        <dl class="details">
          <div><dt>Company</dt><dd><?= e($c['company_name']) ?></dd></div>
          <div><dt>Customer type</dt><dd><?= e($c['account_type']) ?></dd></div>
          <div><dt>Country</dt><dd><?= e($c['country']) ?></dd></div>
          <div><dt>Industry</dt><dd><?= e($c['Industry']) ?></dd></div>
          <div><dt>Contact</dt><dd><?= e($c['contact_person']) ?><?php if (!empty($c['contact_number'])): ?><span class="muted"> · <?= e($c['contact_number']) ?></span><?php endif; ?></dd></div>
          <div><dt>Email</dt><dd><?= e($c['email']) ?></dd></div>
          <div><dt>Address</dt><dd><?= e($c['address']) ?></dd></div>
        </dl>
      </section>
    </div>
  </div>
</div>
<?php app_foot();
