<?php
require __DIR__ . '/includes/bootstrap.php';
require __DIR__ . '/includes/layout.php';
$c = require_customer();
$cid = (int)$c['id'];

$blank = ['id' => 0, 'product_name' => '', 'gtin_unit' => '', 'gtin_outer' => '', 'gtin_master' => '', 'gtin_pallet' => '', 'quantity' => '', 'period' => 'Monthly'];
$form = $blank;

if (is_post()) {
    csrf_verify();
    $action = post('action');

    if ($action === 'delete') {
        db()->prepare('DELETE FROM drc_products WHERE id = ? AND customer_id = ?')->execute([(int)post('id'), $cid]);
        flash('success', 'Product removed.');
        redirect('products.php');
    }

    if ($action === 'save') {
        $form = [
            'id'           => (int)post('id'),
            'product_name' => post('product_name'),
            'gtin_unit'    => digits_only(post('gtin_unit')),
            'gtin_outer'   => digits_only(post('gtin_outer')),
            'gtin_master'  => digits_only(post('gtin_master')),
            'gtin_pallet'  => digits_only(post('gtin_pallet')),
            'quantity'     => digits_only(post('quantity')),
            'period'       => post('period'),
        ];
        $len = function_exists('mb_strlen') ? mb_strlen($form['product_name']) : strlen($form['product_name']);
        $err = [];
        if ($len < 2 || $len > 150) { $err[] = 'Product name must be 2 to 150 characters.'; }
        if (!is_gtin($form['gtin_unit'])) { $err[] = gs1_error('Unit GTIN', $form['gtin_unit'], [8, 12, 13, 14]); }
        foreach (['gtin_outer' => 'Outer', 'gtin_master' => 'Master', 'gtin_pallet' => 'Pallet'] as $k => $label) {
            if ($form[$k] !== '' && !is_gtin($form[$k])) { $err[] = gs1_error($label . ' GTIN', $form[$k], [8, 12, 13, 14]); }
        }
        if ($form['quantity'] === '' || (int)$form['quantity'] < 1) { $err[] = 'Enter a quantity greater than zero.'; }
        if (!in_array($form['period'], periods(), true)) { $err[] = 'Choose how often this quantity applies.'; }

        if (!$err) {
            $st = db()->prepare('SELECT id FROM drc_products WHERE customer_id = ? AND gtin_unit = ? AND id <> ?');
            $st->execute([$cid, $form['gtin_unit'], $form['id']]);
            if ($st->fetch()) { $err[] = 'A product with this unit GTIN is already registered.'; }
        }

        if ($err) {
            flash('error', implode(' ', $err));
        } else {
            $vals = [$form['product_name'], $form['gtin_unit'], $form['gtin_outer'] ?: null, $form['gtin_master'] ?: null, $form['gtin_pallet'] ?: null, $form['quantity'], $form['period']];
            if ($form['id'] > 0) {
                $st = db()->prepare('UPDATE drc_products SET product_name=?, gtin_unit=?, gtin_outer=?, gtin_master=?, gtin_pallet=?, quantity=?, period=?, updated_at=NOW() WHERE id=? AND customer_id=?');
                $st->execute(array_merge($vals, [$form['id'], $cid]));
                flash('success', 'Product updated.');
            } else {
                $st = db()->prepare('INSERT INTO drc_products (product_name, gtin_unit, gtin_outer, gtin_master, gtin_pallet, quantity, period, customer_id) VALUES (?,?,?,?,?,?,?,?)');
                $st->execute(array_merge($vals, [$cid]));
                flash('success', 'Product “' . $form['product_name'] . '” registered.');
            }
            redirect('products.php');
        }
    }
} elseif (isset($_GET['edit'])) {
    $st = db()->prepare('SELECT * FROM drc_products WHERE id = ? AND customer_id = ?');
    $st->execute([(int)$_GET['edit'], $cid]);
    if ($row = $st->fetch()) {
        foreach ($row as $k => $val) { $row[$k] = ($val === null) ? '' : $val; }
        $form = array_merge($blank, $row);
    }
}

$st = db()->prepare('SELECT * FROM drc_products WHERE customer_id = ? ORDER BY product_name');
$st->execute([$cid]);
$rows = $st->fetchAll();
$editing = (int)$form['id'] > 0;

app_head('Products', 'products', 'customer', 'Register each product with its GTIN. Outer, master and pallet GTINs are optional.');
?>
<div class="stack">
  <section class="panel" id="product-form">
    <div class="panel-head">
      <h2><?= $editing ? 'Edit product' : 'Register a product' ?></h2>
      <?php if ($editing): ?><a class="btn btn-sm btn-ghost" href="<?= e(url('products.php')) ?>">Cancel editing</a><?php endif; ?>
    </div>
    <form method="post" novalidate>
      <?= csrf_field() ?><input type="hidden" name="action" value="save"><input type="hidden" name="id" value="<?= (int)$form['id'] ?>">
      <div class="grid-2">
        <div class="field">
          <label for="product_name">Product name</label>
          <input id="product_name" name="product_name" maxlength="150" value="<?= e($form['product_name']) ?>" required>
        </div>
        <div class="field">
          <label for="gtin_unit">Unit GTIN</label>
          <input id="gtin_unit" name="gtin_unit" inputmode="numeric" maxlength="14" value="<?= e($form['gtin_unit']) ?>" data-gs1="gtin" required>
        </div>
      </div>
      <fieldset>
        <legend>Higher packaging levels (optional)</legend>
        <div class="grid-3">
          <div class="field"><label for="gtin_outer">Outer GTIN</label><input id="gtin_outer" name="gtin_outer" inputmode="numeric" maxlength="14" value="<?= e($form['gtin_outer']) ?>" data-gs1="gtin"></div>
          <div class="field"><label for="gtin_master">Master GTIN</label><input id="gtin_master" name="gtin_master" inputmode="numeric" maxlength="14" value="<?= e($form['gtin_master']) ?>" data-gs1="gtin"></div>
          <div class="field"><label for="gtin_pallet">Pallet GTIN</label><input id="gtin_pallet" name="gtin_pallet" inputmode="numeric" maxlength="14" value="<?= e($form['gtin_pallet']) ?>" data-gs1="gtin"></div>
        </div>
      </fieldset>
      <div class="grid-2">
        <div class="field">
          <label for="quantity">Quantity</label>
          <input id="quantity" name="quantity" inputmode="numeric" value="<?= e($form['quantity']) ?>" required>
        </div>
        <div class="field">
          <label for="period">Per</label>
          <select id="period" name="period">
            <?php foreach (periods() as $p): ?><option value="<?= e($p) ?>"<?= $form['period'] === $p ? ' selected' : '' ?>><?= e($p) ?></option><?php endforeach; ?>
          </select>
        </div>
      </div>
      <button class="btn" type="submit"><?= $editing ? 'Save changes' : 'Register product' ?></button>
    </form>
  </section>

  <section class="panel">
    <div class="panel-head"><h2>Registered products (<?= count($rows) ?>)</h2></div>
    <?php if (!$rows): ?>
      <div class="empty"><strong>No products yet</strong>Register your first product using the form above.</div>
    <?php else: ?>
      <div class="table-wrap"><table>
        <thead><tr><th>Product</th><th>Unit GTIN</th><th>Outer / Master / Pallet</th><th>Quantity</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($rows as $r): ?>
          <tr>
            <td><strong><?= e($r['product_name']) ?></strong></td>
            <td class="num"><?= e($r['gtin_unit']) ?></td>
            <td class="num small">
              <?= e($r['gtin_outer'] ?: '—') ?><span class="sub"><?= e($r['gtin_master'] ?: '—') ?></span><span class="sub"><?= e($r['gtin_pallet'] ?: '—') ?></span>
            </td>
            <td class="num"><?= e(number_format((int)$r['quantity'])) ?><span class="sub"><?= e($r['period']) ?></span></td>
            <td class="actions">
              <a class="btn-icon" href="<?= e(url('products.php?edit=' . (int)$r['id'])) ?>#product-form" aria-label="Edit <?= e($r['product_name']) ?>"><?= icon('edit') ?></a>
              <form method="post" class="inline" data-confirm="Remove this product?">
                <?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
                <button class="btn-icon danger" type="submit" aria-label="Remove <?= e($r['product_name']) ?>"><?= icon('trash') ?></button>
              </form>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table></div>
    <?php endif; ?>
  </section>
</div>
<?php app_foot();
