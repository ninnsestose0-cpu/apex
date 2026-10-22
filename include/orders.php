<?php
/**
 * include/orders.php
 * Orders, payments, Kitchen Display System, inventory, sales analytics.
 */

const SERVICE_TYPES = ['dine_in' => 'Dine-in', 'takeout' => 'Takeout', 'delivery' => 'Delivery'];
const PAY_METHODS   = ['cash' => 'Cash', 'qr_gcash' => 'GCash QR', 'qr_maya' => 'Maya QR', 'bank_qr' => 'Bank QR'];

function generate_order_no(PDO $pdo): string
{
    $prefix = date('ymd');
    $st = $pdo->prepare("SELECT COUNT(*) FROM orders WHERE order_no LIKE ?");
    $st->execute([$prefix . '-%']);
    return $prefix . '-' . str_pad((string)($st->fetchColumn() + 1), 4, '0', STR_PAD_LEFT);
}

/**
 * Create an order from the session cart.
 * $d: service_type, customer_name, customer_phone, table_no, delivery_address, notes
 * Returns the new order id. Throws Exception on validation / stock errors.
 */
function create_order(PDO $pdo, array $d): int
{
    [$items, $subtotal] = cart_items($pdo);
    if (!$items) {
        throw new Exception('Your cart is empty.');
    }
    if (!isset(SERVICE_TYPES[$d['service_type'] ?? ''])) {
        throw new Exception('Please choose Dine-in, Takeout or Delivery.');
    }
    if (trim($d['customer_name'] ?? '') === '') {
        throw new Exception('Please enter your name.');
    }
    if ($d['service_type'] === 'delivery' && trim($d['delivery_address'] ?? '') === '') {
        throw new Exception('Please enter a delivery address.');
    }
    if ($d['service_type'] === 'dine_in' && trim($d['table_no'] ?? '') === '') {
        throw new Exception('Please enter your table number.');
    }

    $fee   = $d['service_type'] === 'delivery' ? DELIVERY_FEE : 0.0;
    $total = $subtotal + $fee;

    $pdo->beginTransaction();
    try {
        $st = $pdo->prepare(
            'INSERT INTO orders (branch_id, order_no, service_type, table_no, customer_name, customer_phone,
                                 delivery_address, notes, subtotal, delivery_fee, total)
             VALUES (?,?,?,?,?,?,?,?,?,?,?)'
        );
        $st->execute([
            BRANCH_ID, generate_order_no($pdo), $d['service_type'],
            $d['table_no'] ?: null, trim($d['customer_name']), $d['customer_phone'] ?: null,
            $d['delivery_address'] ?: null, $d['notes'] ?: null, $subtotal, $fee, $total,
        ]);
        $orderId = (int)$pdo->lastInsertId();

        $ins = $pdo->prepare(
            'INSERT INTO order_items (order_id, product_id, product_name, unit_price, quantity, line_total)
             VALUES (?,?,?,?,?,?)'
        );
        $dec = $pdo->prepare(
            'UPDATE inventory SET stock = stock - ? WHERE branch_id = ? AND product_id = ? AND stock >= ?'
        );
        foreach ($items as $it) {
            $p = $it['product'];
            $dec->execute([$it['qty'], BRANCH_ID, $p['id'], $it['qty']]);
            if ($dec->rowCount() === 0) {
                throw new Exception('Sorry, "' . $p['name'] . '" does not have enough stock.');
            }
            $ins->execute([$orderId, $p['id'], $p['name'], $p['price'], $it['qty'], $it['line']]);
        }
        $pdo->commit();
        cart_clear();
        return $orderId;
    } catch (Throwable $t) {
        $pdo->rollBack();
        throw $t;
    }
}

function get_order(PDO $pdo, int $id): ?array
{
    $st = $pdo->prepare('SELECT * FROM orders WHERE id = ?');
    $st->execute([$id]);
    $o = $st->fetch();
    if (!$o) {
        return null;
    }
    $st = $pdo->prepare('SELECT * FROM order_items WHERE order_id = ?');
    $st->execute([$id]);
    $o['items'] = $st->fetchAll();
    $st = $pdo->prepare('SELECT * FROM payments WHERE order_id = ? ORDER BY id DESC');
    $st->execute([$id]);
    $o['payments'] = $st->fetchAll();
    return $o;
}

/* ---------- Payments (cash + QR with reference number) ---------- */
function record_payment(PDO $pdo, int $orderId, string $method, ?string $ref = null): void
{
    if (!isset(PAY_METHODS[$method])) {
        throw new Exception('Invalid payment method.');
    }
    $o = get_order($pdo, $orderId);
    if (!$o) {
        throw new Exception('Order not found.');
    }
    // Cash is confirmed by staff at the counter; QR waits for staff to verify the reference no.
    $status = $method === 'cash' ? 'pending' : 'pending';
    $st = $pdo->prepare('INSERT INTO payments (order_id, method, reference_no, amount, status) VALUES (?,?,?,?,?)');
    $st->execute([$orderId, $method, $ref ?: null, $o['total'], $status]);
}

/** Staff confirms or rejects a payment. Confirming marks the order paid. */
function confirm_payment(PDO $pdo, int $paymentId, bool $ok): void
{
    $pdo->beginTransaction();
    $st = $pdo->prepare('UPDATE payments SET status = ? WHERE id = ?');
    $st->execute([$ok ? 'confirmed' : 'rejected', $paymentId]);
    if ($ok) {
        $pdo->prepare('UPDATE orders o JOIN payments p ON p.order_id = o.id
                       SET o.payment_status = "paid" WHERE p.id = ?')->execute([$paymentId]);
    }
    $pdo->commit();
}

/* ---------- Kitchen Display System ---------- */
function kds_orders(PDO $pdo): array
{
    $st = $pdo->prepare(
        "SELECT * FROM orders
         WHERE branch_id = ? AND status IN ('pending','preparing','ready')
         ORDER BY created_at ASC"
    );
    $st->execute([BRANCH_ID]);
    $orders = $st->fetchAll();
    $itemSt = $pdo->prepare('SELECT product_name, quantity FROM order_items WHERE order_id = ?');
    foreach ($orders as &$o) {
        $itemSt->execute([$o['id']]);
        $o['items'] = $itemSt->fetchAll();
    }
    return $orders;
}

function set_order_status(PDO $pdo, int $orderId, string $status): void
{
    if (!in_array($status, ['pending', 'preparing', 'ready', 'completed', 'cancelled'], true)) {
        throw new Exception('Invalid status.');
    }
    $pdo->beginTransaction();
    if ($status === 'cancelled') {
        // return the stock
        $pdo->prepare(
            'UPDATE inventory i JOIN order_items oi ON oi.product_id = i.product_id
             SET i.stock = i.stock + oi.quantity
             WHERE oi.order_id = ? AND i.branch_id = ?'
        )->execute([$orderId, BRANCH_ID]);
    }
    $pdo->prepare('UPDATE orders SET status = ? WHERE id = ? AND status <> "cancelled"')
        ->execute([$status, $orderId]);
    $pdo->commit();
}

/* ---------- Inventory ---------- */
function inventory_list(PDO $pdo): array
{
    $st = $pdo->prepare(
        'SELECT p.id, p.name, c.name AS category, i.stock, i.reorder_level,
                (i.stock <= i.reorder_level) AS is_low
         FROM inventory i
         JOIN products p ON p.id = i.product_id
         JOIN categories c ON c.id = p.category_id
         WHERE i.branch_id = ? ORDER BY is_low DESC, p.name'
    );
    $st->execute([BRANCH_ID]);
    return $st->fetchAll();
}

function adjust_stock(PDO $pdo, int $productId, int $delta): void
{
    $pdo->prepare('UPDATE inventory SET stock = GREATEST(0, stock + ?) WHERE branch_id = ? AND product_id = ?')
        ->execute([$delta, BRANCH_ID, $productId]);
}

/* ---------- Sales & Transaction Analytics ---------- */
/** Totals for a date range (inclusive, Y-m-d). Only paid, non-cancelled orders count. */
function sales_summary(PDO $pdo, string $from, string $to): array
{
    $st = $pdo->prepare(
        "SELECT COUNT(*) AS orders, COALESCE(SUM(total),0) AS revenue, COALESCE(AVG(total),0) AS avg_order
         FROM orders
         WHERE branch_id = ? AND payment_status = 'paid' AND status <> 'cancelled'
           AND DATE(created_at) BETWEEN ? AND ?"
    );
    $st->execute([BRANCH_ID, $from, $to]);
    return $st->fetch();
}

function sales_by_day(PDO $pdo, string $from, string $to): array
{
    $st = $pdo->prepare(
        "SELECT DATE(created_at) AS day, COUNT(*) AS orders, SUM(total) AS revenue
         FROM orders
         WHERE branch_id = ? AND payment_status = 'paid' AND status <> 'cancelled'
           AND DATE(created_at) BETWEEN ? AND ?
         GROUP BY DATE(created_at) ORDER BY day"
    );
    $st->execute([BRANCH_ID, $from, $to]);
    return $st->fetchAll();
}

function top_products(PDO $pdo, string $from, string $to, int $limit = 5): array
{
    $st = $pdo->prepare(
        "SELECT oi.product_name, SUM(oi.quantity) AS qty, SUM(oi.line_total) AS revenue
         FROM order_items oi JOIN orders o ON o.id = oi.order_id
         WHERE o.branch_id = ? AND o.payment_status = 'paid' AND o.status <> 'cancelled'
           AND DATE(o.created_at) BETWEEN ? AND ?
         GROUP BY oi.product_name ORDER BY qty DESC LIMIT " . (int)$limit
    );
    $st->execute([BRANCH_ID, $from, $to]);
    return $st->fetchAll();
}

function sales_by_service(PDO $pdo, string $from, string $to): array
{
    $st = $pdo->prepare(
        "SELECT service_type, COUNT(*) AS orders, SUM(total) AS revenue
         FROM orders
         WHERE branch_id = ? AND payment_status = 'paid' AND status <> 'cancelled'
           AND DATE(created_at) BETWEEN ? AND ?
         GROUP BY service_type"
    );
    $st->execute([BRANCH_ID, $from, $to]);
    return $st->fetchAll();
}