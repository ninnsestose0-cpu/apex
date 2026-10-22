<?php
require_once __DIR__ . '/include/db.php';
require_role('kitchen');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    try {
        set_order_status($pdo, (int)$_POST['order_id'], $_POST['status'] ?? '');
    } catch (Exception $ex) {
        flash('error', $ex->getMessage());
    }
    redirect('kitchen.php');
}

$orders = kds_orders($pdo);
$cols = [
    'pending'   => ['New orders',  'preparing', 'Start cooking'],
    'preparing' => ['Cooking',     'ready',     'Mark ready'],
    'ready'     => ['Ready',       'completed', 'Served / picked up'],
];

$pageTitle = 'Kitchen';
$refresh = 15; // auto-refresh every 15 seconds
require __DIR__ . '/include/staff_header.php';
?>
<h1>Kitchen Display <small class="muted">auto-refreshes every 15s</small></h1>
<div class="kds">
  <?php foreach ($cols as $status => [$title, $next, $btn]): ?>
    <section>
      <h2><?= $title ?> (<?= count(array_filter($orders, fn($o) => $o['status'] === $status)) ?>)</h2>
      <?php foreach ($orders as $o): if ($o['status'] !== $status) continue; ?>
        <article class="card ticket <?= $o['service_type'] ?>">
          <div class="row">
            <strong><?= e($o['order_no']) ?></strong>
            <span class="badge"><?= e(SERVICE_TYPES[$o['service_type']]) ?><?= $o['table_no'] ? ' · T' . e($o['table_no']) : '' ?></span>
          </div>
          <small class="muted"><?= e($o['customer_name']) ?> · <?= date('g:i A', strtotime($o['created_at'])) ?></small>
          <ul>
            <?php foreach ($o['items'] as $it): ?>
              <li><b><?= (int)$it['quantity'] ?>×</b> <?= e($it['product_name']) ?></li>
            <?php endforeach; ?>
          </ul>
          <?php if ($o['notes']): ?><p class="note">Note: <?= e($o['notes']) ?></p><?php endif; ?>
          <form method="post">
            <?= csrf_field() ?>
            <input type="hidden" name="order_id" value="<?= (int)$o['id'] ?>">
            <input type="hidden" name="status" value="<?= $next ?>">
            <button class="btn full"><?= $btn ?></button>
          </form>
        </article>
      <?php endforeach; ?>
    </section>
  <?php endforeach; ?>
</div>
<?php require __DIR__ . '/include/footer.php';