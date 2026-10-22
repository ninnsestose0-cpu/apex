<?php
/**
 * include/db.php
 * APEx settings, session, database connection.
 * Default XAMPP credentials: user "root", empty password.
 */

const APP_NAME      = 'APEx';
const BUSINESS_NAME = "Argie's Food Hub & Pizza";
const BRANCH_ID     = 1;        // the Ilaud, Inabanga pilot branch
const DELIVERY_FEE  = 50.00;    // flat delivery fee (PHP)
const CURRENCY      = '₱';

const DB_HOST = '127.0.0.1';
const DB_NAME = 'apex';
const DB_USER = 'root';
const DB_PASS = '';

define('ROOT_PATH', dirname(__DIR__));

date_default_timezone_set('Asia/Manila');

if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params(['httponly' => true, 'samesite' => 'Lax']);
    session_start();
}

try {
    $pdo = new PDO(
        'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4',
        DB_USER,
        DB_PASS,
        [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]
    );
    $pdo->exec("SET time_zone = '+08:00'");
} catch (PDOException $e) {
    http_response_code(500);
    exit('<div style="font-family:sans-serif;max-width:560px;margin:60px auto;padding:24px;border:4px solid #7A0C14;border-radius:20px">'
       . '<h2 style="color:#C1121F;margin-top:0">APEx cannot reach the database</h2>'
       . '<p>1. Start <b>Apache</b> and <b>MySQL</b> in the XAMPP Control Panel.<br>'
       . '2. In phpMyAdmin, import <b>database/apex.sql</b> (it creates the <b>apex</b> database).<br>'
       . '3. If your MySQL has a password, set it in <b>include/db.php</b>.</p></div>');
}

require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/orders.php';