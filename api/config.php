<?php
declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Connexion PDO à la base ab_engins.
// Priorité : variables d'environnement (Railway) > api/config.local.php (poste local,
// non versionné, ex. port MySQL différent) > valeurs par défaut XAMPP.
function db(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        $local = is_file(__DIR__ . '/config.local.php') ? (array) (require __DIR__ . '/config.local.php') : [];
        $host = getenv('DB_HOST') ?: ($local['host'] ?? '127.0.0.1');
        $port = getenv('DB_PORT') ?: ($local['port'] ?? '3306');
        $name = getenv('DB_NAME') ?: ($local['name'] ?? 'ab_engins');
        $user = getenv('DB_USER') ?: ($local['user'] ?? 'root');
        $pass = getenv('DB_PASS') ?: ($local['pass'] ?? '');
        $pdo = new PDO(
            "mysql:host={$host};port={$port};dbname={$name};charset=utf8mb4",
            $user,
            $pass,
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ]
        );
    }
    return $pdo;
}

function json_out($data, int $status = 200): void {
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    header('Access-Control-Allow-Origin: *');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function handle_error(Throwable $e): void {
    json_out(['error' => $e->getMessage()], 500);
}

/**
 * Le dictionnaire de donnees classe statut_permis comme un champ "Calcule",
 * mais le script SQL le stocke en ENUM statique : sans recalcul, un permis
 * "valide" ne bascule jamais tout seul en "expire" au fil du temps.
 * On recalcule donc le statut effectif a la volee a partir de la date,
 * tout en respectant une suspension manuelle (qui ne se deduit pas d'une date).
 */
function permitStatusExpr(string $alias = 'p', bool $nullable = false): string {
    $nullGuard = $nullable ? "WHEN {$alias}.id_permis IS NULL THEN NULL\n        " : '';
    return "CASE
        {$nullGuard}WHEN {$alias}.statut_permis = 'suspendu' THEN 'suspendu'
        WHEN {$alias}.date_expiration < CURDATE() THEN 'expire'
        ELSE 'valide'
    END";
}

// Bloque l'appel si aucune session valide n'est ouverte (utilisé par chaque endpoint protégé).
function requireAuth(): array {
    if (empty($_SESSION['user'])) {
        json_out(['error' => 'Authentification requise'], 401);
    }
    return $_SESSION['user'];
}
