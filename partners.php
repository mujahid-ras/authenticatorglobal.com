<?php
require __DIR__ . '/includes/bootstrap.php';
require __DIR__ . '/includes/layout.php';
$c = require_customer();
$cid = (int)$c['id'];

// A manufacturer adds importers; an importer adds manufacturers.
$type   = $c['account_type'] === 'Manufacturer' ? 'Importer' : 'Manufacturer';
$plural = $type . 's';
$form   = ['name' => '', 'gln' => '', 'address' => ''];

if (is_post()) {
    csrf_verify();
    $action = post('action');
    if ($action === 'add') {
        $form = ['name' => post('name'), 'gln' => digits_only(post('gln')), 'address' => post('address')];
        $len = function (string $s): int { return function_exists('mb_strlen') ? mb_strlen($s) : strlen($s); };
        $err = [];
        if ($len($form['name']) < 2 || $len($form['name']) > 150) { $err[] = $type . ' name must be 2 to 150 characters.'; }
        if (!is_gln($form['gln'])) { $err[] = gs1_error('GLN', $form['gln'], [13]); }
        if ($len($form['address']) < 5 || $len($form['address']) > 255) { $err[] = 'Enter the full address.'; }
        if (!$err) {
            $st = db()->prepare('SELECT id FROM drc_partners WHERE customer_id = ? AND partner_type = ? AND gln = ?');
            $st->execute([$cid, $type, $form['gln']]);
            if ($st->fetch()) { $err[] = 'A ' . strtolower($type) . ' with this GLN is already on your list.'; }
        }
        if ($err) {
            flash('error', implode(' ', $err));
        } else {
            db()->prepare('INSERT INTO drc_partners (customer_id, partner_type, name, gln, address) VALUES (?,?,?,?,?)')
                ->execute([$cid, $type, $form['name'], $form['gln'], $form['address']]);
            flash('success', $type . ' “' . $form['name'] . '” added.');
            redirect('partners.php');
        }
    } elseif ($action === 'delete') {
        db()->prepare('DELETE FROM drc_partners WHERE id = ? AND customer_id = ?')->execute([(int)post('id'), $cid]);
        flash('success', $type . ' removed.');
        redirect('partners.php');
    }
}

$st = db()->prepare('SELECT * FROM drc_partners WHERE customer_id = ? AND partner_type = ? ORDER BY name');
$st->execute([$cid, $type]);
$rows = $st->fetchAll();

app_head($plural, 'partners', 'customer', $type === 'Importer'
    ? 'Add the importers you supply. You can add as many as you need.'
    : 'Add the manufacturers you import from. You can add as many as you need.');
?>
<div class="cols">
  <section class="panel">
    <div class="panel-head"><h2>Your <?= e(strtolower($plural)) ?> (<?= count($rows) ?>)</h2></div>
    <?php if (!$rows): ?>
      <div class="empty"><strong>No <?= e(strtolower($plural)) ?> yet</strong>Add your first <?= e(strtolower($type)) ?> using the form.</div>
    <?php else: ?>
      <div class="table-wrap"><table>
        <thead><tr><th><?= e($type) ?></th><th>GLN</th><th>Address</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($rows as $r): ?>
          <tr>
            <td><strong><?= e($r['name']) ?></strong></td>
            <td class="num"><?= e($r['gln']) ?></td>
            <td><?= e($r['address']) ?></td>
            <td class="actions">
              <form method="post" class="inline" data-confirm="Remove this <?= e(strtolower($type)) ?>?">
                <?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
                <button class="btn-icon danger" type="submit" aria-label="Remove <?= e($r['name']) ?>"><?= icon('trash') ?></button>
              </form>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table></div>
    <?php endif; ?>
  </section>

  <section class="panel">
    <div class="panel-head"><h2>Add <?= e(strtolower($type) === 'importer' ? 'an importer' : 'a manufacturer') ?></h2></div>
    <form method="post" novalidate>
      <?= csrf_field() ?><input type="hidden" name="action" value="add">
      <div class="field">
        <label for="name"><?= e($type) ?> name</label>
        <input id="name" name="name" value="<?= e($form['name']) ?>" maxlength="150" required>
      </div>
      <div class="field">
        <label for="gln">GLN</label>
        <input id="gln" name="gln" inputmode="numeric" maxlength="13" value="<?= e($form['gln']) ?>" data-gs1="gln" placeholder="13 digits" required>
      </div>
      <div class="field">
        <label for="address">Address</label>
        <textarea id="address" name="address" maxlength="255" required><?= e($form['address']) ?></textarea>
      </div>
      <button class="btn" type="submit">Add <?= e(strtolower($type)) ?></button>
    </form>
  </section>
</div>
<?php app_foot();
