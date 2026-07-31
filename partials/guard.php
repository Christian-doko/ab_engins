<?php
declare(strict_types=1);
require_once __DIR__ . '/../api/config.php';

if (empty($_SESSION['user'])) {
    header('Location: login.php');
    exit;
}

$currentUser = $_SESSION['user'];

// Le rôle client n'accède jamais aux pages internes : il a son propre espace.
if (($currentUser['role'] ?? '') === 'client') {
    header('Location: espace-client.php');
    exit;
}
