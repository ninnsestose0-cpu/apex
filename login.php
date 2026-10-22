<?php
require_once __DIR__ . '/include/db.php';

function home_for(string $role): string
{
    return ['kitchen' => 'kitchen.php', 'cashier' => 'cashier.php', 'admin' => 'reports.php'][$role] ?? 'index.php';
}

if ($u = current_user()) {
    redirect(home_for($u['role']));
}

$error = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    if (login($pdo, trim($_POST['username'] ?? ''), $_POST['password'] ?? '')) {
        redirect(home_for(current_user()['role']));
    }
    $error = 'Wrong username or password.';
}

$pageTitle = 'Login';
require __DIR__ . '/include/staff_header.php';
?>
<section class="card login">
  <h1>Staff Login</h1>
  <?php if ($error): ?><div class="alert error"><?= e($error) ?></div><?php endif; ?>
  <form method="post">
    <?= csrf_field() ?>
    <label>Username</label>
    <input name="username" required autofocus>
    <label>Password</label>
    <input type="password" name="password" required>
    <button class="btn full">Log in</button>
  </form>
</section>
<?php require __DIR__ . '/include/footer.php';