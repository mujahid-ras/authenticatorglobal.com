<?php
require __DIR__ . '/includes/bootstrap.php';
require __DIR__ . '/includes/layout.php';
$c = require_customer();
$cid = (int)$c['id'];

if (is_post()) {
    csrf_verify();
    $action = post('action');
    if ($action === 'add') {
        $name = post('line_name');
        $len = function_exists('mb_strlen') ? mb_strlen($name) : strlen($name);
        if ($len < 2 || $len > 100) {
            flash('error', 'Enter a line name between 2 and 100 characters.');
        } else {
            $st = db()->prepare('SELECT id FROM drc_lines WHERE customer_id = ? AND line_name = ?');
            $st->execute([$cid, $name]);
            if ($st->fetch()) {
                flash('error', 'You already have a line called “' . $name . '”.');
            } else {
                db()->prepare('INSERT INTO drc_lines (customer_id, line_name) VALUES (?, ?)')->execute([$cid, $name]);
                flash('success', 'Production line “' . $name . '” added.');
            }
        }
    } elseif ($action === 'delete') {
        db()->prepare('DELETE FROM drc_lines WHERE id = ? AND customer_id = ?')->execute([(int)post('id'), $cid]);
        flash('success', 'Production line removed.');
    }
    redirect('lines.php');
}

$st = db()->prepare('SELECT * FROM drc_lines WHERE customer_id = ? ORDER BY line_name');
$st->execute([$cid]);
$lines = $st->fetchAll();

app_head('Production lines', 'lines', 'customer', 'Add every line you run. You can register as many as you need.');
?>
<div class="cols">
  <section class="panel">
    <div class="panel-head"><h2>Your lines (<?= count($lines) ?>)</h2></div>
    <?php if (!$lines): ?>
      <div class="empty"><strong>No production lines yet</strong>Add your first line using the form.</div>
    <?php else: ?>
      <div class="table-wrap"><table>
        <thead><tr><th>Line name</th><th>Added</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($lines as $l): ?>
          <tr>
            <td><strong><?= e($l['line_name']) ?></strong></td>
            <td class="muted"><?= e(fmt_date($l['created_at'])) ?></td>
            <td class="actions">
              <form method="post" class="inline" data-confirm="Remove this production line?">
                <?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int)$l['id'] ?>">
                <button class="btn-icon danger" type="submit" aria-label="Remove <?= e($l['line_name']) ?>"><?= icon('trash') ?></button>
              </form>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table></div>
    <?php endif; ?>
  </section>

  <section class="panel">
    <div class="panel-head"><h2>Add a line</h2></div>
    <form method="post" novalidate>
      <?= csrf_field() ?><input type="hidden" name="action" value="add">
      <div class="field">
        <label for="line_name">Line name</label>
        <input id="line_name" name="line_name" maxlength="100" required placeholder="For example, Blister Line 1">
      </div>
      <button class="btn" type="submit">Add line</button>
    </form>
  </section>
</div>
<?php app_foot();
