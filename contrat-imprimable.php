<?php
declare(strict_types=1);
require __DIR__ . '/partials/guard-commun.php';

/**
 * Version imprimable d'un contrat de location : mise en page A4 autonome,
 * à imprimer ou enregistrer en PDF via le navigateur (Ctrl+P).
 * Accessible au personnel, et au client pour ses propres contrats.
 */

$retour = $estClient ? 'espace-client.php' : 'contrats.php';

$idContrat = (int) ($_GET['id'] ?? 0);
if ($idContrat <= 0) {
    header('Location: ' . $retour);
    exit;
}

$pdo = db();

$stmt = $pdo->prepare(
    "SELECT ct.id_contrat, ct.date_signature, ct.lieu_signature, ct.date_effet, ct.duree_jours,
            ct.date_fin_prevue, ct.tacite_reconduction, ct.statut_contrat, ct.montant_ht,
            ct.id_client,
            c.nom_client, c.nom_representant, c.cni_representant, c.adresse_client,
            c.telephone_client, c.email_client,
            p.numero_permis, p.region, p.departement, p.arrondissement,
            p.foret_concernee, p.superficie_ha, p.date_delivrance, p.date_expiration
     FROM contrat ct
     INNER JOIN client c ON c.id_client = ct.id_client
     INNER JOIN permis p ON p.id_permis = ct.id_permis
     WHERE ct.id_contrat = :id"
);
$stmt->execute(['id' => $idContrat]);
$ct = $stmt->fetch();
if (!$ct) {
    header('Location: ' . $retour);
    exit;
}

// Un client ne peut imprimer que ses propres contrats.
if ($estClient && (int) $ct['id_client'] !== $idClientSession) {
    http_response_code(403);
    exit('Accès refusé : ce contrat ne vous appartient pas.');
}

$engins = $pdo->prepare(
    "SELECT e.code_engin, e.type_engin, e.modele_engin, e.numero_serie,
            ce.date_mise_disposition, ce.etat_depart, ce.date_retour_effective
     FROM contrat_engin ce
     INNER JOIN engin e ON e.id_engin = ce.id_engin
     WHERE ce.id_contrat = :id
     ORDER BY e.type_engin"
);
$engins->execute(['id' => $idContrat]);
$engins = $engins->fetchAll();

function fcfa(float $m): string { return number_format($m, 0, ',', ' ') . ' FCFA'; }
function dfr(?string $d): string { return $d ? (new DateTime($d))->format('d/m/Y') : '—'; }

$reference = sprintf('CT-%s-%03d', substr((string) $ct['date_effet'], 0, 4), (int) $ct['id_contrat']);
$statutLabel = ['actif' => 'En cours', 'termine' => 'Terminé', 'resilie' => 'Résilié', 'renouvele' => 'Renouvelé'];
$tva = 19.25;
$montantTva = (float) $ct['montant_ht'] * $tva / 100;
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="utf-8">
<title>Contrat <?= htmlspecialchars($reference) ?> — AB ENGINS</title>
<link rel="icon" href="assets/logo.svg" type="image/svg+xml">
<style>
  :root { --vert: #14532d; --ligne: #d8e2db; }
  * { box-sizing: border-box; margin: 0; }
  body { font-family: Georgia, "Times New Roman", serif; color: #1c2620; background: #f0f2f0; }
  .page { max-width: 210mm; min-height: 260mm; margin: 24px auto; background: #fff; padding: 22mm 18mm; box-shadow: 0 4px 24px rgba(0,0,0,.12); }
  header { display: flex; justify-content: space-between; align-items: flex-start; border-bottom: 3px solid var(--vert); padding-bottom: 14px; gap: 16px; }
  .societe { display: flex; gap: 12px; align-items: center; }
  .societe img { width: 62px; height: 62px; }
  .societe h1 { font-size: 24px; color: var(--vert); letter-spacing: 1px; }
  .societe p { font-size: 12.5px; color: #4a564e; margin-top: 4px; }
  .doc-titre { text-align: right; }
  .doc-titre h2 { font-size: 21px; color: var(--vert); }
  .doc-titre p { font-size: 13px; margin-top: 4px; }
  .blocs { display: flex; justify-content: space-between; margin: 22px 0; gap: 20px; }
  .bloc { font-size: 13.5px; line-height: 1.55; flex: 1; }
  .bloc h3 { font-size: 12px; text-transform: uppercase; letter-spacing: .8px; color: var(--vert); margin-bottom: 6px; }
  h4.section { font-size: 12px; text-transform: uppercase; letter-spacing: .8px; color: var(--vert); margin: 22px 0 6px; }
  table { width: 100%; border-collapse: collapse; font-size: 13px; }
  th { background: var(--vert); color: #fff; text-align: left; padding: 8px 10px; font-weight: 600; }
  th.num, td.num { text-align: right; }
  td { padding: 7px 10px; border-bottom: 1px solid var(--ligne); }
  .totaux { margin-top: 14px; margin-left: auto; width: 60%; font-size: 14px; }
  .totaux td { padding: 6px 10px; }
  .totaux .ttc td { border-top: 2px solid var(--vert); font-weight: 700; font-size: 15.5px; color: var(--vert); }
  .conditions { margin-top: 24px; font-size: 12.5px; line-height: 1.6; }
  .conditions li { margin-left: 18px; margin-bottom: 4px; }
  .signatures { display: flex; gap: 40px; margin-top: 34px; }
  .signature { flex: 1; font-size: 12.5px; }
  .signature .ligne-sign { margin-top: 46px; border-top: 1px solid #1c2620; padding-top: 5px; }
  footer { margin-top: 34px; padding-top: 12px; border-top: 1px solid var(--ligne); font-size: 11.5px; color: #4a564e; text-align: center; }
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
    <a href="<?= $estClient ? 'espace-client.php' : 'contrats.php' ?>">← Retour</a>
  </div>

  <div class="page">
    <header>
      <div class="societe">
        <img src="assets/logo.svg" alt="AB ENGINS">
        <div>
          <h1>AB ENGINS SARL</h1>
          <p>Location d'engins lourds — Exploitation forestière<br>Cameroun</p>
        </div>
      </div>
      <div class="doc-titre">
        <h2>CONTRAT DE LOCATION</h2>
        <p><strong><?= htmlspecialchars($reference) ?></strong><br>
           Signé le <?= dfr($ct['date_signature']) ?><?= $ct['lieu_signature'] ? ' à ' . htmlspecialchars($ct['lieu_signature']) : '' ?><br>
           Statut : <?= $statutLabel[$ct['statut_contrat']] ?? $ct['statut_contrat'] ?></p>
      </div>
    </header>

    <div class="blocs">
      <div class="bloc">
        <h3>Le loueur</h3>
        <strong>AB ENGINS SARL</strong><br>
        Location d'engins de chantier<br>
        Cameroun
      </div>
      <div class="bloc">
        <h3>Le locataire</h3>
        <strong><?= htmlspecialchars($ct['nom_client']) ?></strong><br>
        Représentant : <?= htmlspecialchars($ct['nom_representant']) ?><br>
        CNI : <?= htmlspecialchars($ct['cni_representant']) ?><br>
        <?php if ($ct['adresse_client']): ?><?= htmlspecialchars($ct['adresse_client']) ?><br><?php endif; ?>
        <?php if ($ct['telephone_client']): ?>Tél. : <?= htmlspecialchars($ct['telephone_client']) ?><?php endif; ?>
      </div>
    </div>

    <h4 class="section">Permis d'exploitation présenté</h4>
    <table>
      <tbody>
        <tr>
          <td><strong><?= htmlspecialchars($ct['numero_permis']) ?></strong></td>
          <td><?= htmlspecialchars($ct['region']) ?> / <?= htmlspecialchars($ct['departement']) ?> / <?= htmlspecialchars($ct['arrondissement']) ?></td>
          <td><?= $ct['foret_concernee'] ? htmlspecialchars($ct['foret_concernee']) : '—' ?><?= $ct['superficie_ha'] ? ' (' . number_format((float) $ct['superficie_ha'], 2, ',', ' ') . ' ha)' : '' ?></td>
          <td>Valide du <?= dfr($ct['date_delivrance']) ?> au <?= dfr($ct['date_expiration']) ?></td>
        </tr>
      </tbody>
    </table>

    <h4 class="section">Engins mis à disposition</h4>
    <table>
      <thead>
        <tr><th>Code</th><th>Type / Modèle</th><th>N° de série</th><th>Mise à disposition</th><th>État au départ</th></tr>
      </thead>
      <tbody>
        <?php foreach ($engins as $e): ?>
        <tr>
          <td><strong><?= htmlspecialchars($e['code_engin']) ?></strong></td>
          <td><?= htmlspecialchars(trim($e['type_engin'] . ' ' . ($e['modele_engin'] ?? ''))) ?></td>
          <td><?= htmlspecialchars($e['numero_serie'] ?? '—') ?></td>
          <td><?= dfr($e['date_mise_disposition']) ?></td>
          <td><?= htmlspecialchars($e['etat_depart'] ?? '—') ?></td>
        </tr>
        <?php endforeach; ?>
        <?php if ($engins === []): ?>
        <tr><td colspan="5">Aucun engin rattaché à ce contrat.</td></tr>
        <?php endif; ?>
      </tbody>
    </table>

    <h4 class="section">Durée et conditions financières</h4>
    <table>
      <tbody>
        <tr><td>Date d'effet</td><td><strong><?= dfr($ct['date_effet']) ?></strong></td>
            <td>Durée</td><td><strong><?= (int) $ct['duree_jours'] ?> jours</strong></td></tr>
        <tr><td>Fin prévue</td><td><strong><?= dfr($ct['date_fin_prevue']) ?></strong></td>
            <td>Tacite reconduction</td><td><strong><?= $ct['tacite_reconduction'] ? 'Oui' : 'Non' ?></strong></td></tr>
      </tbody>
    </table>

    <table class="totaux">
      <tr><td>Montant HT</td><td class="num"><?= fcfa((float) $ct['montant_ht']) ?></td></tr>
      <tr><td>TVA (<?= number_format($tva, 2, ',', '') ?> %)</td><td class="num"><?= fcfa($montantTva) ?></td></tr>
      <tr class="ttc"><td>Montant TTC</td><td class="num"><?= fcfa((float) $ct['montant_ht'] + $montantTva) ?></td></tr>
    </table>

    <div class="conditions">
      <h4 class="section">Conditions générales</h4>
      <ol>
        <li>Le locataire déclare avoir présenté un permis d'exploitation valide, référencé ci-dessus, couvrant la durée du présent contrat.</li>
        <li>Les engins sont remis en bon état de fonctionnement et doivent être restitués dans le même état, hors usure normale.</li>
        <li>Le locataire assume la garde des engins pendant toute la durée de la location et répond des dommages qui leur seraient causés.</li>
        <li>Toute intervention technique doit être signalée sans délai au service assistance d'AB ENGINS.</li>
        <li>Le règlement s'effectue selon les modalités portées sur la facture correspondante.</li>
      </ol>
    </div>

    <div class="signatures">
      <div class="signature">
        <strong>Pour AB ENGINS SARL</strong>
        <div class="ligne-sign">Nom, qualité et signature</div>
      </div>
      <div class="signature">
        <strong>Pour <?= htmlspecialchars($ct['nom_client']) ?></strong>
        <div class="ligne-sign"><?= htmlspecialchars($ct['nom_representant']) ?> — signature</div>
      </div>
    </div>

    <footer>
      AB ENGINS SARL — Document généré le <?= date('d/m/Y') ?> depuis le système de gestion.
    </footer>
  </div>
</body>
</html>
