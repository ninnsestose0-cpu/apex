<?php
require_once __DIR__ . '/include/db.php';

$error = null;

/* ---------- Handle form actions ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $action = $_POST['action'] ?? '';

    if ($action === 'checkout') {
        try {
            $id = create_order($pdo, $_POST);
            record_payment($pdo, $id, $_POST['pay_method'] ?? 'cash', trim($_POST['reference_no'] ?? ''));
            $_SESSION['last_order'] = $id;
            redirect('index.php?order=' . $id);
        } catch (Exception $ex) {
            $error = $ex->getMessage();
        }
    } else {
        if ($action === 'add')   cart_add((int)$_POST['product_id']);
        if ($action === 'set')   cart_set((int)$_POST['product_id'], (int)$_POST['qty']);
        if ($action === 'clear') cart_clear();
        redirect('index.php#cart');
    }
}

/* ---------- Order confirmation view ---------- */
$order = null;
if (isset($_GET['order']) && (int)$_GET['order'] === ($_SESSION['last_order'] ?? 0)) {
    $order = get_order($pdo, (int)$_GET['order']);
}

$pageTitle = $order ? 'Order Received' : 'Order Online';
require __DIR__ . '/include/header.php';

if ($order): ?>
  <section class="card receipt">
    <h1>Thank you, <?= e($order['customer_name']) ?>!</h1>
    <p>Your order number is <strong class="big"><?= e($order['order_no']) ?></strong></p>
    <p>Service: <b><?= e(SERVICE_TYPES[$order['service_type']]) ?></b>
      <?= $order['table_no'] ? ' &middot; Table ' . e($order['table_no']) : '' ?></p>
    <table class="tbl">
      <?php foreach ($order['items'] as $it): ?>
        <tr><td><?= (int)$it['quantity'] ?> &times; <?= e($it['product_name']) ?></td>
            <td class="r"><?= money((float)$it['line_total']) ?></td></tr>
      <?php endforeach; ?>
      <?php if ($order['delivery_fee'] > 0): ?>
        <tr><td>Delivery fee</td><td class="r"><?= money((float)$order['delivery_fee']) ?></td></tr>
      <?php endif; ?>
      <tr class="total"><td>Total</td><td class="r"><?= money((float)$order['total']) ?></td></tr>
    </table>
    <?php $pay = $order['payments'][0] ?? null; if ($pay): ?>
      <p>Payment: <b><?= e(PAY_METHODS[$pay['method']]) ?></b> &mdash;
        <?= $pay['method'] === 'cash' ? 'please pay at the counter or to the rider.' : 'our staff will verify your reference no. ' . e($pay['reference_no'] ?? '') . '.' ?></p>
    <?php endif; ?>
    <a class="btn" href="index.php">Order again</a>
  </section>
<?php else:
  $cat   = (int)($_GET['cat'] ?? 0);
  $menu  = get_menu($pdo, $cat ?: null);
  $cats  = get_categories($pdo);
  [$items, $subtotal] = cart_items($pdo);
?>
  <div class="layout">
    <section id="menu">
      <h1>Our Menu</h1>
      <nav class="tabs">
        <a href="index.php" class="<?= $cat ? '' : 'on' ?>">All</a>
        <?php foreach ($cats as $c): ?>
          <a href="index.php?cat=<?= (int)$c['id'] ?>" class="<?= $cat === (int)$c['id'] ? 'on' : '' ?>"><?= e($c['name']) ?></a>
        <?php endforeach; ?>
      </nav>
      <div class="grid">
        <?php foreach ($menu as $p): ?>
          <article class="card item">
            <small><?= e($p['category']) ?></small>
            <h3><?= e($p['name']) ?></h3>
            <p><?= e($p['description']) ?></p>
            <div class="row">
              <strong><?= money((float)$p['price']) ?></strong>
              <form method="post">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="add">
                <input type="hidden" name="product_id" value="<?= (int)$p['id'] ?>">
                <button class="btn sm" <?= $p['stock'] <= 0 ? 'disabled' : '' ?>>
                  <?= $p['stock'] <= 0 ? 'Sold out' : 'Add' ?></button>
              </form>
            </div>
          </article>
        <?php endforeach; ?>
      </div>
    </section>

    <aside id="cart" class="card cart">
      <h2>Your Order</h2>
      <?php if ($error): ?><div class="alert error"><?= e($error) ?></div><?php endif; ?>
      <?php if (!$items): ?>
        <p class="muted">Your cart is empty. Add something tasty!</p>
      <?php else: ?>
        <?php foreach ($items as $it): ?>
          <form method="post" class="line">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="set">
            <input type="hidden" name="product_id" value="<?= (int)$it['product']['id'] ?>">
            <span><?= e($it['product']['name']) ?></span>
            <input type="number" name="qty" value="<?= (int)$it['qty'] ?>" min="0" max="99" onchange="this.form.submit()">
            <span class="r"><?= money((float)$it['line']) ?></span>
          </form>
        <?php endforeach; ?>

        <form method="post" id="checkout" data-subtotal="<?= $subtotal ?>" data-fee="<?= DELIVERY_FEE ?>" data-cur="<?= CURRENCY ?>">
          <?= csrf_field() ?>
          <input type="hidden" name="action" value="checkout">

          <label>Service</label>
          <div class="seg">
            <?php foreach (SERVICE_TYPES as $k => $v): ?>
              <label><input type="radio" name="service_type" value="<?= $k ?>" <?= (($_POST['service_type'] ?? 'dine_in') === $k) ? 'checked' : '' ?>> <?= $v ?></label>
            <?php endforeach; ?>
          </div>

          <label>Name</label>
          <input name="customer_name" required value="<?= e($_POST['customer_name'] ?? '') ?>">
          <label>Phone</label>
          <input name="customer_phone" value="<?= e($_POST['customer_phone'] ?? '') ?>">
          <div data-show="dine_in"><label>Table no.</label><input name="table_no" value="<?= e($_POST['table_no'] ?? '') ?>"></div>
          <div data-show="delivery"><label>Delivery address</label><input name="delivery_address" value="<?= e($_POST['delivery_address'] ?? '') ?>"></div>
          <label>Notes</label>
          <input name="notes" placeholder="e.g. no onions" value="<?= e($_POST['notes'] ?? '') ?>">

          <label>Payment</label>
          <select name="pay_method" id="pay_method">
            <?php foreach (PAY_METHODS as $k => $v): ?>
              <option value="<?= $k ?>"><?= $v ?></option>
            <?php endforeach; ?>
          </select>
          <div id="qrbox" hidden>
            <label>QR reference no.</label>
            <input name="reference_no" placeholder="From your payment receipt">
          </div>

          <div class="sum">
            <div><span>Subtotal</span><span><?= money($subtotal) ?></span></div>
            <div id="feeRow" hidden><span>Delivery fee</span><span><?= money(DELIVERY_FEE) ?></span></div>
            <div class="total"><span>Total</span><span id="grand"><?= money($subtotal) ?></span></div>
          </div>
          <button class="btn full">Place Order</button>
        </form>
        <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="clear">
          <button class="link">Clear cart</button></form>
      <?php endif; ?>
    </aside>
  </div>
<?php endif;


require __DIR__ . '/include/footer.php';