<?php
require __DIR__ . '/../includes/bootstrap.php';
require __DIR__ . '/../includes/layout.php';
require_admin();

$tab = $_GET['status'] ?? 'pending';
if (!in_array($tab, ['pending', 'active', 'disabled', 'all'], true)) { $tab = 'pending'; }
$q = trim((string)($_GET['q'] ?? ''));

$counts = ['pending' => 0, 'active' => 0, 'disabled' => 0];
foreach (db()->query('SELECT status, COUNT(*) n FROM drc_customers GROUP BY status') as $r) { $counts[$r['status']] = (int)$r['n']; }
$counts['all'] = array_sum($counts);

$where = []; $args = [];
if ($tab !== 'all') { $where[] = 'c.status = ?'; $args[] = $tab; }
if ($q !== '') {
    $where[] = '(c.company_name LIKE ? OR c.email LIKE ? OR c.contact_person LIKE ?)';
    $like = '%' . $q . '%'; array_push($args, $like, $like, $like);
}
$sql = 'SELECT c.* FROM drc_customers c' . ($where ? ' WHERE ' . implode(' AND ', $where) : '') . ' ORDER BY c.created_at DESC LIMIT 300';
$st = db()->prepare($sql);
$st->execute($args);
$rows = $st->fetchAll();

$labels = ['pending' => 'Pending', 'active' => 'Active', 'disabled' => 'Deactivated', 'all' => 'All'];
app_head('Customers', 'customers', 'admin', 'Review new registrations and manage customer access.');
?>
<section class="panel">
  <div class="toolbar">
    <div class="tabs" role="tablist">
      <?php foreach ($labels as $k => $label): ?>
        <a href="?status=<?= e($k) ?><?= $q !== '' ? '&q=' . urlencode($q) : '' ?>" class="<?= $tab === $k ? 'on' : '' ?>"><?= e($label) ?> (<?= (int)$counts[$k] ?>)</a>
      <?php endforeach; ?>
    </div>
    <form method="get">
      <input type="hidden" name="status" value="<?= e($tab) ?>">
      <input type="search" name="q" value="<?= e($q) ?>" placeholder="Search company, email or contact" aria-label="Search customers">
      <button class="btn btn-dark" type="submit">Search</button>
    </form>
  </div>

  <?php if (!$rows): ?>
    <div class="empty"><strong>No customers found</strong>Try another tab or a different search.</div>
  <?php else: ?>
    <div class="table-wrap"><table>
      <thead><tr><th>Company</th><th>Customer type</th><th>Country</th><th>Contact</th><th>Registered</th><th>Status</th><th></th></tr></thead>
      <tbody>
      <?php foreach ($rows as $r): ?>
        <tr>
          <td><strong><?= e($r['company_name']) ?></strong><span class="sub"><?= e($r['email']) ?></span></td>
          <td><?= e($r['account_type']) ?></td>
          <td><?= e($r['country']) ?></td>
          <td><?= e($r['contact_person']) ?><?php if (!empty($r['contact_number'])): ?><span class="sub"><?= e($r['contact_number']) ?></span><?php endif; ?></td>
          <td class="muted"><?= e(fmt_date($r['created_at'])) ?></td>
          <td><span class="pill <?= e($r['status']) ?>"><?= e(ucfirst($r['status'] === 'disabled' ? 'deactivated' : $r['status'])) ?></span></td>
          <td class="actions"><a class="btn btn-sm <?= $r['status'] === 'pending' ? '' : 'btn-ghost' ?>" href="<?= e(url('admin/customer.php?id=' . (int)$r['id'])) ?>"><?= $r['status'] === 'pending' ? 'Review' : 'View' ?></a></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table></div>
  <?php endif; ?>
</section>
<?php app_foot();
