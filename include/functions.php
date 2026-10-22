<?php
/**
 * include/functions.php
 * General helpers: escaping, money, flash messages, CSRF, auth, menu queries.
 */

function e(?string $s): string
{
    return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
}

function money(float $n): string
{
    return CURRENCY . number_format($n, 2);
}

function redirect(string $url): never
{
    header('Location: ' . $url);
    exit;
}

/* ---------- Flash messages ---------- */
function flash(string $type, string $msg): void
{
    $_SESSION['flash'][] = ['type' => $type, 'msg' => $msg];
}

function flash_all(): array
{
    $f = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $f;
}

/* ---------- CSRF ---------- */
function csrf_token(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf" value="' . csrf_token() . '">';
}

function csrf_check(): void
{
    $ok = isset($_POST['csrf'], $_SESSION['csrf'])
        && hash_equals($_SESSION['csrf'], $_POST['csrf']);
    if (!$ok) {
        http_response_code(419);
        exit('Invalid or expired form. Go back and try again.');
    }
}

/* ---------- Auth (admin / cashier / kitchen) ---------- */
function login(PDO $pdo, string $username, string $password): bool
{
    $st = $pdo->prepare('SELECT * FROM users WHERE username = ? AND is_active = 1');
    $st->execute([$username]);
    $u = $st->fetch();
    if ($u && password_verify($password, $u['password_hash'])) {
        session_regenerate_id(true);
        $_SESSION['user'] = [
            'id' => (int)$u['id'], 'name' => $u['full_name'],
            'role' => $u['role'], 'branch_id' => (int)$u['branch_id'],
        ];
        return true;
    }
    return false;
}

function logout(): void
{
    $_SESSION = [];
    session_destroy();
}

function current_user(): ?array
{
    return $_SESSION['user'] ?? null;
}

/** Usage: require_role('admin','cashier'); */
function require_role(string ...$roles): void
{
    $u = current_user();
    if (!$u) {
        redirect('login.php');
    }
    if ($roles && !in_array($u['role'], $roles, true) && $u['role'] !== 'admin') {
        http_response_code(403);
        exit('You do not have access to this page.');
    }
}

/* ---------- Menu / Product Catalog ---------- */
function get_categories(PDO $pdo): array
{
    return $pdo->query('SELECT * FROM categories ORDER BY sort_order, name')->fetchAll();
}

/** Available products with current stock for the branch. */
function get_menu(PDO $pdo, ?int $categoryId = null): array
{
    $sql = 'SELECT p.*, c.name AS category, COALESCE(i.stock,0) AS stock
            FROM products p
            JOIN categories c ON c.id = p.category_id
            LEFT JOIN inventory i ON i.product_id = p.id AND i.branch_id = :b
            WHERE p.is_available = 1';
    $args = [':b' => BRANCH_ID];
    if ($categoryId) {
        $sql .= ' AND p.category_id = :c';
        $args[':c'] = $categoryId;
    }
    $sql .= ' ORDER BY c.sort_order, p.name';
    $st = $pdo->prepare($sql);
    $st->execute($args);
    return $st->fetchAll();
}

function get_product(PDO $pdo, int $id): ?array
{
    $st = $pdo->prepare('SELECT * FROM products WHERE id = ?');
    $st->execute([$id]);
    return $st->fetch() ?: null;
}

/* ---------- Cart (session based) ---------- */
function cart_add(int $productId, int $qty = 1): void
{
    $_SESSION['cart'][$productId] = ($_SESSION['cart'][$productId] ?? 0) + max(1, $qty);
}

function cart_set(int $productId, int $qty): void
{
    if ($qty <= 0) {
        unset($_SESSION['cart'][$productId]);
    } else {
        $_SESSION['cart'][$productId] = $qty;
    }
}

function cart_clear(): void
{
    unset($_SESSION['cart']);
}

/** Returns [items[], subtotal] with fresh prices from the DB. */
function cart_items(PDO $pdo): array
{
    $cart = $_SESSION['cart'] ?? [];
    $items = [];
    $subtotal = 0.0;
    foreach ($cart as $pid => $qty) {
        $p = get_product($pdo, (int)$pid);
        if (!$p || !$p['is_available']) {
            continue;
        }
        $line = $p['price'] * $qty;
        $items[] = ['product' => $p, 'qty' => $qty, 'line' => $line];
        $subtotal += $line;
    }
    return [$items, $subtotal];
}