<?php
declare(strict_types=1);
/**
 * Attendu avant l'inclusion : $activeNav, $pageTitle, $pageSubtitle, $currentUser (via guard.php).
 * Optionnel : $showToday (bool) pour afficher la date du jour a cote du sous-titre.
 */

$pdo = db();

$navCounts = [
    'clients' => (int) $pdo->query("SELECT COUNT(*) FROM client")->fetchColumn(),
    'contrats' => (int) $pdo->query("SELECT COUNT(*) FROM contrat WHERE statut_contrat = 'actif'")->fetchColumn(),
    'permis' => (int) $pdo->query(
        "SELECT COUNT(*) FROM permis p
         WHERE " . permitStatusExpr('p') . " = 'valide'
           AND p.date_expiration BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY)"
    )->fetchColumn(),
];

$totalEngins = (int) $pdo->query("SELECT COUNT(*) FROM engin")->fetchColumn();
$enginsDispo = (int) $pdo->query("SELECT COUNT(*) FROM engin WHERE disponibilite = 'disponible'")->fetchColumn();
$dispoPct = $totalEngins > 0 ? round(($enginsDispo / $totalEngins) * 100) : 0;

$navItems = [
    ['key' => 'dashboard', 'label' => 'Tableau de bord', 'href' => 'index.php',       'icon' => 'dashboard'],
    ['key' => 'clients',   'label' => 'Clients',         'href' => 'clients.php',     'icon' => 'clients',  'count' => $navCounts['clients']],
    ['key' => 'permis',    'label' => 'Permis',          'href' => 'permis.php',      'icon' => 'permis',   'count' => $navCounts['permis']],
    ['key' => 'contrats',  'label' => 'Contrats',        'href' => 'contrats.php',    'icon' => 'contrats', 'count' => $navCounts['contrats']],
    ['key' => 'engins',    'label' => 'Engins',          'href' => 'engins.php',      'icon' => 'engins'],
    ['key' => 'factures',  'label' => 'Facturation',     'href' => 'factures.php',    'icon' => 'factures'],
    ['key' => 'assist',    'label' => 'Assistance',      'href' => 'assistance.php',  'icon' => 'assist'],
];

function svg_icon(string $key): string {
    $icons = [
        'dashboard' => '<svg viewBox="0 0 24 24"><rect x="3" y="3" width="7" height="9"/><rect x="14" y="3" width="7" height="5"/><rect x="14" y="12" width="7" height="9"/><rect x="3" y="16" width="7" height="5"/></svg>',
        'clients'   => '<svg viewBox="0 0 24 24"><circle cx="9" cy="7" r="4"/><path d="M2 21v-2a6 6 0 0 1 6-6h2"/><path d="M16 11l2 2 4-4"/></svg>',
        'permis'    => '<svg viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><path d="M14 2v6h6"/><path d="M9 15l2 2 4-4"/></svg>',
        'contrats'  => '<svg viewBox="0 0 24 24"><path d="M8 2h9l3 3v15a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2z"/><path d="M9 9h7M9 13h7M9 17h4"/></svg>',
        'engins'    => '<svg viewBox="0 0 24 24"><path d="M3 17h13v-4l-3-4H3z"/><circle cx="6" cy="19" r="2"/><circle cx="14" cy="19" r="2"/><path d="M16 13h3l2 3v1h-5"/></svg>',
        'factures'  => '<svg viewBox="0 0 24 24"><path d="M6 2h12v20l-3-2-3 2-3-2-3 2z"/><path d="M9 8h6M9 12h6"/></svg>',
        'assist'    => '<svg viewBox="0 0 24 24"><path d="M14.7 6.3a4 4 0 0 0-5.4 5.4l-6 6 2 2 6-6a4 4 0 0 0 5.4-5.4l-2.3 2.3-2-2z"/></svg>',
    ];
    return $icons[$key] ?? '';
}

$initials = '';
foreach (preg_split('/\s+/', trim($currentUser['nom_complet'])) as $part) {
    if ($part !== '') { $initials .= mb_strtoupper(mb_substr($part, 0, 1)); }
}
$initials = mb_substr($initials, 0, 2) ?: '?';
$roleLabel = ['admin' => 'Administrateur', 'agent' => 'Agent', 'technicien' => 'Technicien'][$currentUser['role']] ?? $currentUser['role'];
?>
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>AB ENGINS — <?= htmlspecialchars($pageTitle) ?></title>
  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet" />
  <link rel="stylesheet" href="css/styles.css" />
</head>
<body>
  <div class="app-shell">
    <!-- ====================== SIDEBAR ====================== -->
    <aside class="sidebar" id="sidebar">
      <div class="brand">
        <span class="brand-badge">AB</span>
        <span class="brand-text">
          <strong>AB ENGINS</strong>
          <small>SARL</small>
        </span>
      </div>
      <nav class="nav" id="nav">
        <?php foreach ($navItems as $item): ?>
          <?php $countBadge = isset($item['count']) ? '<span class="count">' . (int) $item['count'] . '</span>' : ''; ?>
          <?php if ($item['href']): ?>
            <a class="nav-item <?= $item['key'] === $activeNav ? 'active' : '' ?>" href="<?= htmlspecialchars($item['href']) ?>">
              <?= svg_icon($item['icon']) ?><span><?= htmlspecialchars($item['label']) ?></span><?= $countBadge ?>
            </a>
          <?php else: ?>
            <span class="nav-item disabled" title="Bientôt disponible">
              <?= svg_icon($item['icon']) ?><span><?= htmlspecialchars($item['label']) ?></span><?= $countBadge ?>
            </span>
          <?php endif; ?>
        <?php endforeach; ?>
      </nav>
      <div class="sidebar-foot">
        <div class="foot-card">
          <span class="dot"></span>
          Parc opérationnel
          <b><?= $dispoPct ?>%</b>
        </div>
      </div>
    </aside>

    <!-- ====================== MAIN ====================== -->
    <div class="main">
      <header class="topbar">
        <button class="burger" id="burger" aria-label="Menu">
          <span></span><span></span><span></span>
        </button>
        <div class="topbar-title">
          <h1><?= htmlspecialchars($pageTitle) ?></h1>
          <p><?= htmlspecialchars($pageSubtitle) ?><?php if (!empty($showToday)): ?> — <span id="today"></span><?php endif; ?></p>
        </div>
        <div class="topbar-actions">
          <button class="icon-btn" title="Notifications" id="bell">
            <svg viewBox="0 0 24 24"><path d="M12 2a6 6 0 0 0-6 6c0 4-1.5 6-2 7h16c-.5-1-2-3-2-7a6 6 0 0 0-6-6Zm0 20a3 3 0 0 0 3-3H9a3 3 0 0 0 3 3Z"/></svg>
            <span class="badge-dot" id="bellDot" hidden></span>
          </button>
          <button class="icon-btn" title="Se déconnecter" id="logoutBtn">
            <svg viewBox="0 0 24 24"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><path d="M16 17l5-5-5-5"/><path d="M21 12H9"/></svg>
          </button>
          <div class="user">
            <div class="user-meta">
              <strong><?= htmlspecialchars($currentUser['nom_complet']) ?></strong>
              <small><?= htmlspecialchars($roleLabel) ?></small>
            </div>
            <span class="avatar"><?= htmlspecialchars($initials) ?></span>
          </div>
        </div>
      </header>

      <main class="content">
