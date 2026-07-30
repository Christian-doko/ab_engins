<?php
declare(strict_types=1);
require __DIR__ . '/api/config.php';

/**
 * Installation de la base — à usage unique.
 * Exécute les scripts de sql/ sur la base configurée (variables DB_*).
 * Sécurité : refuse de s'exécuter si la base contient déjà des tables,
 * donc cette page devient inerte après le premier import.
 */

set_time_limit(300);
header('Content-Type: text/html; charset=utf-8');

// Fichiers à exécuter, dans l'ordre.
const SQL_FILES = [
    'sql/script_ab_engins.sql',
    'sql/auth_utilisateur.sql',
    'sql/seed_ab_engins.sql',
    'sql/seed_extra_contracts.sql',
];

/** Découpe un script SQL en instructions, en respectant chaînes et commentaires. */
function splitSqlStatements(string $sql): array {
    $statements = [];
    $current = '';
    $len = strlen($sql);
    $i = 0;
    $state = 'normal'; // normal | squote | dquote | linecomment | blockcomment
    while ($i < $len) {
        $c = $sql[$i];
        $next = $i + 1 < $len ? $sql[$i + 1] : '';
        switch ($state) {
            case 'normal':
                if ($c === "'") { $state = 'squote'; $current .= $c; }
                elseif ($c === '"') { $state = 'dquote'; $current .= $c; }
                elseif ($c === '-' && $next === '-') { $state = 'linecomment'; $i++; }
                elseif ($c === '#') { $state = 'linecomment'; }
                elseif ($c === '/' && $next === '*') { $state = 'blockcomment'; $i++; }
                elseif ($c === ';') { $statements[] = trim($current); $current = ''; }
                else { $current .= $c; }
                break;
            case 'squote':
                $current .= $c;
                if ($c === '\\' && $next !== '') { $current .= $next; $i++; }
                elseif ($c === "'") { $state = 'normal'; }
                break;
            case 'dquote':
                $current .= $c;
                if ($c === '\\' && $next !== '') { $current .= $next; $i++; }
                elseif ($c === '"') { $state = 'normal'; }
                break;
            case 'linecomment':
                if ($c === "\n") { $state = 'normal'; $current .= $c; }
                break;
            case 'blockcomment':
                if ($c === '*' && $next === '/') { $state = 'normal'; $i++; }
                break;
        }
        $i++;
    }
    if (trim($current) !== '') { $statements[] = trim($current); }
    return array_values(array_filter($statements, fn($s) => $s !== ''));
}

/**
 * Prépare un fichier SQL : extrait les blocs DELIMITER $$ (triggers),
 * supprime CREATE DATABASE / USE (la base Railway a un autre nom),
 * et retourne la liste ordonnée des instructions à exécuter.
 */
function prepareSqlFile(string $path): array {
    $sql = file_get_contents($path);
    if ($sql === false) {
        throw new RuntimeException("Fichier illisible : {$path}");
    }

    // Blocs DELIMITER $$ ... DELIMITER ; → chaque segment $$ est une instruction complète.
    $delimiterStatements = [];
    $sql = preg_replace_callback('/DELIMITER\s+\$\$(.*?)DELIMITER\s+;/s', function ($m) use (&$delimiterStatements) {
        foreach (explode('$$', $m[1]) as $part) {
            if (trim($part) !== '') { $delimiterStatements[] = trim($part); }
        }
        return '';
    }, $sql);

    // La connexion sélectionne déjà la base (DB_NAME) : on neutralise ces instructions.
    $sql = preg_replace('/^\s*CREATE\s+DATABASE\b[^;]*;/mi', '', $sql);
    $sql = preg_replace('/^\s*USE\s+[^;]*;/mi', '', $sql);

    return array_merge(splitSqlStatements($sql), $delimiterStatements);
}

echo "<!DOCTYPE html><html lang='fr'><head><meta charset='utf-8'><title>Installation AB ENGINS</title>";
echo "<style>body{font-family:system-ui,sans-serif;max-width:640px;margin:48px auto;padding:0 16px;color:#16211b}
h1{font-size:22px}li{margin:4px 0}.ok{color:#1a6b39}.err{color:#dc2626}
a.btn{display:inline-block;background:#1a6b39;color:#fff;padding:10px 18px;border-radius:8px;text-decoration:none}</style></head><body>";
echo "<h1>Installation de la base AB ENGINS</h1>";

try {
    $pdo = db();

    $tables = $pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
    if ($tables !== []) {
        echo "<p class='err'>La base contient déjà " . count($tables) . " table(s) : installation refusée.
              Cette page ne fonctionne que sur une base vide.</p></body></html>";
        exit;
    }

    if (($_GET['confirm'] ?? '') !== '1') {
        echo "<p>La base est vide. Cliquer ci-dessous exécutera les scripts suivants :</p><ul>";
        foreach (SQL_FILES as $f) { echo '<li>' . htmlspecialchars($f) . '</li>'; }
        echo "</ul><p><a class='btn' href='install.php?confirm=1'>Lancer l'installation</a></p></body></html>";
        exit;
    }

    echo '<ul>';
    foreach (SQL_FILES as $file) {
        $path = __DIR__ . '/' . $file;
        $statements = prepareSqlFile($path);
        foreach ($statements as $stmt) {
            $pdo->exec($stmt);
        }
        echo "<li class='ok'>" . htmlspecialchars($file) . ' — ' . count($statements) . " instruction(s) exécutée(s)</li>";
    }
    echo "</ul><p class='ok'><strong>Installation terminée.</strong>
          Cette page est désormais inerte (la base n'est plus vide).</p>
          <p><a class='btn' href='login.php'>Aller à la connexion</a></p>";
} catch (Throwable $e) {
    echo "<p class='err'>Erreur : " . htmlspecialchars($e->getMessage()) . "</p>";
}
echo '</body></html>';
