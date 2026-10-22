<?php
require_once __DIR__ . '/include/db.php';
require_role('admin');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $pid = (int)$_POST['product_id'];
    if (($_POST['action'] ?? '') === 'stock') {
        adjust_stock($pdo, $pid, (int)$_POST['delta']);
        flash('success', 'Stock updated.');
    } elseif (($_POST['action'] ?? '') === 'toggle') {
        $pdo->prepare('UPDATE products SET is_available = 1 - is_available WHERE id = ?')->execute([$pid]);
        flash('success', 'Availability updated.');
    }
    redirect('inventory.php');
}

$rows = inventory_list($pdo);
$avail = $pdo->query('SELECT id, is_available FROM products')->fetchAll(PDO::FETCH_KEY_PAIR);

$pageTitle = 'Inventory';
require __DIR__ . '/include/staff_header.php';
?>
<h1>Inventory</h1>
<div class="card scroll">
<table class="tbl wide">
  <tr><th>Product</th><th>Category</th><th class="r">Stock</th><th class="r">Reorder at</th><th>Add / remove stock</th><th>Menu</th></tr>
  <?php foreach ($rows as $r): ?>
  <tr class="<?= $r['is_low'] ? 'low' : '' ?>">
    <td><b><?= e($r['name']) ?></b> <?= $r['is_low'] ? '<span class="badge cancelled">LOW</span>' : '' ?></td>
    <td><?= e($r['category']) ?></td>
    <td class="r"><?= (int)$r['stock'] ?></td>
    <td class="r"><?= (int)$r['reorder_level'] ?></td>
    <td>
      <form method="post" class="inline">
        <?= csrf_field() ?><input type="hidden" name="action" value="stock"><input type="hidden" name="product_id" value="<?= (int)$r['id'] ?>">
        <input type="number" name="delta" value="10" class="num"> <button class="btn sm">Apply</button>
      </form>
    </td>
    <td>
      <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="toggle"><input type="hidden" name="product_id" value="<?= (int)$r['id'] ?>">
        <button class="btn sm ghost"><?= $avail[$r['id']] ? 'Hide from menu' : 'Show on menu' ?></button></form>
    </td>
  </tr>
  <?php endforeach; ?>
</table>
</div>
<p class="muted">Use a negative number (for example -5) to remove stock, such as for spoilage.</p>
<?php require __DIR__ . '/include/footer.php';