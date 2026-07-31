<?php
declare(strict_types=1);
require __DIR__ . '/partials/guard.php';

/**
 * Version imprimable d'une facture : mise en page A4 autonome,
 * à imprimer ou enregistrer en PDF via le navigateur (Ctrl+P).
 */

$idFacture = (int) ($_GET['id'] ?? 0);
if ($idFacture <= 0) {
    header('Location: factures.php');
    exit;
}

$pdo = db();

$stmt = $pdo->prepare(
    "SELECT f.numero_facture, f.date_facture, f.montant_ht_facture, f.taux_tva, f.montant_ttc,
            f.statut_paiement, ct.id_contrat, ct.date_effet,
            c.nom_client, c.nom_representant, c.adresse_client, c.telephone_client, c.email_client
     FROM facture f
     INNER JOIN contrat ct ON ct.id_contrat = f.id_contrat
     INNER JOIN client c ON c.id_client = ct.id_client
     WHERE f.id_facture = :id"
);
$stmt->execute(['id' => $idFacture]);
$f = $stmt->fetch();
if (!$f) {
    header('Location: factures.php');
    exit;
}

$lignes = $pdo->prepare('SELECT designation_ligne, quantite, prix_unitaire, montant_ligne FROM ligne_facture WHERE id_facture = :id ORDER BY id_ligne');
$lignes->execute(['id' => $idFacture]);
$lignes = $lignes->fetchAll();

$paiements = $pdo->prepare('SELECT date_paiement, montant_paye, mode_paiement FROM paiement WHERE id_facture = :id ORDER BY date_paiement');
$paiements->execute(['id' => $idFacture]);
$paiements = $paiements->fetchAll();

$totalPaye = array_sum(array_map(fn($p) => (float) $p['montant_paye'], $paiements));
$reste = (float) $f['montant_ttc'] - $totalPaye;
$montantTva = (float) $f['montant_ttc'] - (float) $f['montant_ht_facture'];

function fcfa(float $m): string { return number_format($m, 0, ',', ' ') . ' FCFA'; }
function dfr(string $d): string { return (new DateTime($d))->format('d/m/Y'); }

$modeLabel = ['especes' => 'Espèces', 'virement' => 'Virement', 'mobile_money' => 'Mobile Money', 'cheque' => 'Chèque'];
$statutLabel = ['paye' => 'Payée', 'partiel' => 'Partiellement payée', 'en_retard' => 'En retard', 'impaye' => 'Impayée'];
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="utf-8">
<title>Facture <?= htmlspecialchars($f['numero_facture']) ?> — AB ENGINS</title>
<style>
  :root { --vert: #14532d; --ligne: #d8e2db; }
  * { box-sizing: border-box; margin: 0; }
  body { font-family: Georgia, "Times New Roman", serif; color: #1c2620; background: #f0f2f0; }
  .page { max-width: 210mm; min-height: 260mm; margin: 24px auto; background: #fff; padding: 22mm 18mm; box-shadow: 0 4px 24px rgba(0,0,0,.12); }
  header { display: flex; justify-content: space-between; align-items: flex-start; border-bottom: 3px solid var(--vert); padding-bottom: 14px; }
  .societe h1 { font-size: 26px; color: var(--vert); letter-spacing: 1px; }
  .societe p { font-size: 12.5px; color: #4a564e; margin-top: 4px; }
  .doc-titre { text-align: right; }
  .doc-titre h2 { font-size: 21px; color: var(--vert); }
  .doc-titre p { font-size: 13px; margin-top: 4px; }
  .blocs { display: flex; justify-content: space-between; margin: 22px 0; gap: 20px; }
  .bloc { font-size: 13.5px; line-height: 1.55; }
  .bloc h3 { font-size: 12px; text-transform: uppercase; letter-spacing: .8px; color: var(--vert); margin-bottom: 6px; }
  table { width: 100%; border-collapse: collapse; margin-top: 8px; font-size: 13.5px; }
  th { background: var(--vert); color: #fff; text-align: left; padding: 8px 10px; font-weight: 600; }
  th.num, td.num { text-align: right; }
  td { padding: 8px 10px; border-bottom: 1px solid var(--ligne); }
  .totaux { margin-top: 14px; margin-left: auto; width: 62%; font-size: 14px; }
  .totaux td { padding: 6px 10px; }
  .totaux .ttc td { border-top: 2px solid var(--vert); font-weight: 700; font-size: 15.5px; color: var(--vert); }
  .paiements { margin-top: 26px; }
  .paiements h3 { font-size: 12px; text-transform: uppercase; letter-spacing: .8px; color: var(--vert); margin-bottom: 6px; }
  .solde { margin-top: 12px; font-size: 14.5px; }
  .solde strong { color: <?= $reste > 0 ? '#b3261e' : 'var(--vert)' ?>; }
  footer { margin-top: 40px; padding-top: 12px; border-top: 1px solid var(--ligne); font-size: 11.5px; color: #4a564e; text-align: center; }
  .barre { position: sticky; top: 0; background: #fff; border-bottom: 1px solid var(--ligne); padding: 10px 16px; display: flex; gap: 10px; justify-content: center; }
  .barre button, .barre a { font: 14px system-ui, sans-serif; padding: 9px 18px; border-radius: 8px; border: 1px solid var(--ligne); cursor: pointer; text-decoration: none; color: #1c2620; background: #fff; }
  .barre button.primaire { background: var(--vert); border-color: var(--vert); color: #fff; }
  @media print {
    body { background: #fff; }
    .page { margin: 0; box-shadow: none; padding: 12mm 10mm; max-width: none; min-height: 0; }
    .barre { display: none; }
  }
</style>
</head>
<body>
  <div class="barre">
    <button class="primaire" onclick="window.print()">Imprimer / Enregistrer en PDF</button>
    <a href="facture-detail.php?id=<?= $idFacture ?>">← Retour au détail</a>
  </div>

  <div class="page">
    <header>
      <div class="societe">
        <h1>AB ENGINS SARL</h1>
        <p>Location d'engins lourds — Exploitation forestière<br>Cameroun</p>
      </div>
      <div class="doc-titre">
        <h2>FACTURE</h2>
        <p><strong><?= htmlspecialchars($f['numero_facture']) ?></strong><br>
           Date : <?= dfr($f['date_facture']) ?><br>
           Statut : <?= $statutLabel[$f['statut_paiement']] ?? $f['statut_paiement'] ?></p>
      </div>
    </header>

    <div class="blocs">
      <div class="bloc">
        <h3>Facturé à</h3>
        <strong><?= htmlspecialchars($f['nom_client']) ?></strong><br>
        Représentant : <?= htmlspecialchars($f['nom_representant']) ?><br>
        <?php if ($f['adresse_client']): ?><?= htmlspecialchars($f['adresse_client']) ?><br><?php endif; ?>
        <?php if ($f['telephone_client']): ?>Tél. : <?= htmlspecialchars($f['telephone_client']) ?><br><?php endif; ?>
        <?php if ($f['email_client']): ?><?= htmlspecialchars($f['email_client']) ?><?php endif; ?>
      </div>
      <div class="bloc">
        <h3>Référence contrat</h3>
        CT-<?= substr($f['date_effet'], 0, 4) ?>-<?= sprintf('%03d', (int) $f['id_contrat']) ?><br>
        Date d'effet : <?= dfr($f['date_effet']) ?>
      </div>
    </div>

    <table>
      <thead>
        <tr><th>Désignation</th><th class="num">Qté</th><th class="num">P.U. (FCFA)</th><th class="num">Montant (FCFA)</th></tr>
      </thead>
      <tbody>
        <?php foreach ($lignes as $l): ?>
        <tr>
          <td><?= htmlspecialchars($l['designation_ligne']) ?></td>
          <td class="num"><?= rtrim(rtrim(number_format((float) $l['quantite'], 2, ',', ' '), '0'), ',') ?></td>
          <td class="num"><?= number_format((float) $l['prix_unitaire'], 0, ',', ' ') ?></td>
          <td class="num"><?= number_format((float) $l['montant_ligne'], 0, ',', ' ') ?></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>

    <table class="totaux">
      <tr><td>Total HT</td><td class="num"><?= fcfa((float) $f['montant_ht_facture']) ?></td></tr>
      <tr><td>TVA (<?= rtrim(rtrim(number_format((float) $f['taux_tva'], 2, ',', ''), '0'), ',') ?> %)</td><td class="num"><?= fcfa($montantTva) ?></td></tr>
      <tr class="ttc"><td>Total TTC</td><td class="num"><?= fcfa((float) $f['montant_ttc']) ?></td></tr>
    </table>

    <?php if ($paiements !== []): ?>
    <div class="paiements">
      <h3>Paiements reçus</h3>
      <table>
        <thead><tr><th>Date</th><th>Mode</th><th class="num">Montant (FCFA)</th></tr></thead>
        <tbody>
          <?php foreach ($paiements as $p): ?>
          <tr>
            <td><?= dfr($p['date_paiement']) ?></td>
            <td><?= $modeLabel[$p['mode_paiement']] ?? $p['mode_paiement'] ?></td>
            <td class="num"><?= number_format((float) $p['montant_paye'], 0, ',', ' ') ?></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?php endif; ?>

    <p class="solde">
      Total payé : <?= fcfa($totalPaye) ?> —
      <strong><?= $reste > 0 ? 'Reste à payer : ' . fcfa($reste) : 'Facture soldée' ?></strong>
    </p>

    <footer>
      AB ENGINS SARL — Document généré le <?= date('d/m/Y') ?> par le système de gestion interne.
    </footer>
  </div>
</body>
</html>
