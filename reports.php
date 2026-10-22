<?php
require_once __DIR__ . '/include/db.php';
require_role('admin');

$from = $_GET['from'] ?? date('Y-m-01');
$to   = $_GET['to']   ?? date('Y-m-d');
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $from)) $from = date('Y-m-01');
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $to))   $to   = date('Y-m-d');

$sum     = sales_summary($pdo, $from, $to);
$byDay   = sales_by_day($pdo, $from, $to);
$top     = top_products($pdo, $from, $to, 5);
$byServ  = sales_by_service($pdo, $from, $to);
$maxDay  = max(array_map(fn($d) => (float)$d['revenue'], $byDay) ?: [1]);

$pageTitle = 'Reports';
require __DIR__ . '/include/staff_header.php';
?>
<h1>Sales &amp; Transactions</h1>
<form method="get" class="filters card">
  <label>From <input type="date" name="from" value="<?= e($from) ?>"></label>
  <label>To <input type="date" name="to" value="<?= e($to) ?>"></label>
  <button class="btn">Show</button>
</form>

<div class="stats">
  <div class="card"><small>Revenue</small><strong><?= money((float)$sum['revenue']) ?></strong></div>
  <div class="card"><small>Paid orders</small><strong><?= (int)$sum['orders'] ?></strong></div>
  <div class="card"><small>Average order</small><strong><?= money((float)$sum['avg_order']) ?></strong></div>
</div>
<p class="muted">Only paid, non-cancelled orders are counted. Confirm payments in the Cashier page first.</p>

<div class="two">
  <section class="card">
    <h2>Sales per day</h2>
    <?php foreach ($byDay as $d): ?>
      <div class="bar"><span><?= e(date('M j', strtotime($d['day']))) ?></span>
        <i style="width:<?= max(3, round($d['revenue'] / $maxDay * 100)) ?>%"></i>
        <b><?= money((float)$d['revenue']) ?></b></div>
    <?php endforeach; ?>
    <?php if (!$byDay): ?><p class="muted">No paid orders in this range.</p><?php endif; ?>
  </section>

  <section class="card">
    <h2>Top products</h2>
    <table class="tbl">
      <?php foreach ($top as $t): ?>
        <tr><td><?= e($t['product_name']) ?></td><td class="r"><?= (int)$t['qty'] ?> sold</td><td class="r"><?= money((float)$t['revenue']) ?></td></tr>
      <?php endforeach; ?>
    </table>
    <h2>By service</h2>
    <table class="tbl">
      <?php foreach ($byServ as $s): ?>
        <tr><td><?= e(SERVICE_TYPES[$s['service_type']]) ?></td><td class="r"><?= (int)$s['orders'] ?> orders</td><td class="r"><?= money((float)$s['revenue']) ?></td></tr>
      <?php endforeach; ?>
    </table>
  </section>
</div>
<?php require __DIR__ . '/include/footer.php';