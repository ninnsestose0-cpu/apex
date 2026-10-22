<?php
require_once __DIR__ . '/include/db.php';
logout();
header('Location: login.php');
exit;