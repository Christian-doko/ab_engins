<?php
declare(strict_types=1);
require_once __DIR__ . '/../api/config.php';

if (empty($_SESSION['user'])) {
    header('Location: login.php');
    exit;
}

$currentUser = $_SESSION['user'];
