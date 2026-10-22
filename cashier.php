<?php
require_once __DIR__ . '/include/db.php';
require_role('cashier');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    try {
        $a = $_POST['action'] ?? '';
        if ($a === 'pay_ok' || $a === 'pay_no') {
            confirm_payment($pdo, (int)$_POST['payment_id'], $a === 'pay_ok');
        } elseif ($a === 'status') {
            set_order_status($pdo, (int)$_POST['order_id'], $_POST['status'] ?? '');
        }
    } catch (Exception $ex) {
        flash('error', $ex->getMessage());
    }
    redirect('cashier.php');
}

$st = $pdo->prepare(
    "SELECT o.*, p.id AS pay_id, p.method, p.reference_no, p.status AS pay_status
     FROM orders o
     LEFT JOIN payments p ON p.id = (SELECT MAX(id) FROM payments WHERE order_id = o.id)
     WHERE o.branch_id = ?
       AND (DATE(o.created_at) = CURDATE() OR o.status NOT IN ('completed','cancelled'))
     ORDER BY o.id DESC LIMIT 100"
);
$st->execute([BRANCH_ID]);
$orders = $st->fetchAll();

$pageTitle = 'Cashier';
$refresh = 30;
require __DIR__ . '/include/staff_header.php';
?>
<h1>Orders &amp; Payments</h1>
<div class="card scroll">
<table class="tbl wide">
  <tr><th>Order</th><th>Time</th><th>Customer</th><th>Service</th><th class="r">Total</th><th>Payment</th><th>Status</th><th>Actions</th></tr>
  <?php foreach ($orders as $o): ?>
  <tr>
    <td><b><?= e($o['order_no']) ?></b></td>
    <td><?= date('g:i A', strtotime($o['created_at'])) ?></td>
    <td><?= e($o['customer_name']) ?><br><small class="muted"><?= e($o['customer_phone'] ?? '') ?></small></td>
    <td><?= e(SERVICE_TYPES[$o['service_type']]) ?><?= $o['table_no'] ? ' · T' . e($o['table_no']) : '' ?>
        <?php if ($o['delivery_address']): ?><br><small><?= e($o['delivery_address']) ?></small><?php endif; ?></td>
    <td class="r"><?= money((float)$o['total']) ?></td>
    <td>
      <?= e(PAY_METHODS[$o['method']] ?? '—') ?>
      <?= $o['reference_no'] ? '<br><small>Ref: ' . e($o['reference_no']) . '</small>' : '' ?>
      <br><span class="badge <?= e($o['payment_status']) ?>"><?= e($o['payment_status']) ?></span>
    </td>
    <td><span class="badge <?= e($o['status']) ?>"><?= e($o['status']) ?></span></td>
    <td class="acts">
      <?php if ($o['pay_id'] && $o['pay_status'] === 'pending'): ?>
        <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="pay_ok"><input type="hidden" name="payment_id" value="<?= (int)$o['pay_id'] ?>"><button class="btn sm">Confirm payment</button></form>
        <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="pay_no"><input type="hidden" name="payment_id" value="<?= (int)$o['pay_id'] ?>"><button class="btn sm ghost">Reject</button></form>
      <?php endif; ?>
      <?php if (!in_array($o['status'], ['completed', 'cancelled'], true)): ?>
        <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="status"><input type="hidden" name="order_id" value="<?= (int)$o['id'] ?>"><input type="hidden" name="status" value="completed"><button class="btn sm ghost">Complete</button></form>
        <form method="post" onsubmit="return confirm('Cancel this order and return the stock?')"><?= csrf_field() ?><input type="hidden" name="action" value="status"><input type="hidden" name="order_id" value="<?= (int)$o['id'] ?>"><input type="hidden" name="status" value="cancelled"><button class="btn sm ghost">Cancel</button></form>
      <?php endif; ?>
    </td>
  </tr>
  <?php endforeach; ?>
  <?php if (!$orders): ?><tr><td colspan="8" class="muted">No orders yet today.</td></tr><?php endif; ?>
</table>
</div>
<?php require __DIR__ . '/include/footer.php';