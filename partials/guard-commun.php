<?php
declare(strict_types=1);
require_once __DIR__ . '/../api/config.php';

/**
 * Garde pour les pages accessibles a tout utilisateur authentifie
 * (personnel comme client) : documents imprimables, profil.
 * Les pages concernees doivent verifier elles-memes que le client
 * connecte est bien proprietaire du document demande.
 */
if (empty($_SESSION['user'])) {
    header('Location: login.php');
    exit;
}

$currentUser = $_SESSION['user'];
$estClient = ($currentUser['role'] ?? '') === 'client';
$idClientSession = $estClient ? (int) ($currentUser['id_client'] ?? 0) : null;
