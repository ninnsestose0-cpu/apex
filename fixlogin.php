<?php
// TEMPORARY helper. Run once at http://localhost/apex/fixlogin.php, then DELETE this file.
require_once __DIR__ . '/include/db.php';

if (!in_array($_SERVER['REMOTE_ADDR'] ?? '', ['127.0.0.1', '::1'], true)) {
    http_response_code(403);
    exit('Local access only.');
}

header('Content-Type: text/plain; charset=utf-8');

$hash = password_hash('password', PASSWORD_DEFAULT);

// Make sure the three staff accounts exist, are active, and use the password "password"
$accounts = [
    ['admin',   'Administrator', 'admin'],
    ['cashier', 'Cashier',       'cashier'],
    ['kitchen', 'Kitchen',       'kitchen'],
];
foreach ($accounts as [$user, $name, $role]) {
    $st = $pdo->prepare('SELECT id FROM users WHERE username = ?');
    $st->execute([$user]);
    if ($st->fetch()) {
        $pdo->prepare('UPDATE users SET password_hash = ?, is_active = 1, role = ? WHERE username = ?')
            ->execute([$hash, $role, $user]);
        echo "Updated  : $user\n";
    } else {
        $pdo->prepare('INSERT INTO users (branch_id, full_name, username, password_hash, role) VALUES (?,?,?,?,?)')
            ->execute([BRANCH_ID, $name, $user, $hash, $role]);
        echo "Created  : $user\n";
    }
}

echo "\nCurrent users in the database:\n";
foreach ($pdo->query('SELECT id, username, role, is_active, LENGTH(password_hash) AS hash_len FROM users') as $u) {
    echo " - {$u['username']} | role={$u['role']} | active={$u['is_active']} | hash_length={$u['hash_len']}\n";
}

echo "\nSelf-test (password = 'password'):\n";
foreach ($accounts as [$user]) {
    $st = $pdo->prepare('SELECT password_hash FROM users WHERE username = ?');
    $st->execute([$user]);
    $ok = password_verify('password', (string)$st->fetchColumn());
    echo " - $user: " . ($ok ? 'LOGIN OK' : 'FAILED') . "\n";
}

echo "\nDone. Now go to login.php, then DELETE fixlogin.php.\n";