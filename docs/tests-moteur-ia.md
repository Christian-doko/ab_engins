# Validation du moteur NLU — jeu de tests

Ce document présente les tests de validation du moteur de compréhension du langage
(`api/moteur_ia.php`). Section prête à intégrer au mémoire (chapitre validation).

## Protocole

- **Jeu de tests** : 16 questions couvrant les 8 intentions, les variantes de
  formulation, l'extraction d'entités et un cas hors périmètre.
- **Environnement** : moteur exécuté hors ligne (PHP CLI), sans base de données —
  seuls la classification TF-IDF et l'extraction d'entités par règles sont mesurés.
- **Critères** : intention détectée = intention attendue ; entités (durée, statut)
  correctement extraites ; score de similarité cosinus ≥ seuil (0,12) pour les
  questions du périmètre, < seuil pour les questions hors périmètre.

## Test 1 — Classification d'intention et extraction d'entités

| # | Question | Intention attendue | Intention détectée | Score | Entités extraites | Résultat |
|---|---|---|---|---|---|---|
| 1 | Quels permis expirent dans les 60 prochains jours ? | permis | permis | 0,33 | jours=60 ¹ | ✅ |
| 2 | Permis à renouveler ce mois | permis | permis | 0,20 | jours=30 ² | ✅ |
| 3 | Liste des permis suspendus | permis | permis | 0,31 | statut=suspendu | ✅ |
| 4 | Quelles factures sont impayées ? | factures | factures | 0,67 | statut=impaye | ✅ |
| 5 | Combien nous doit le client Kamdem ? | factures | clients → **factures** ³ | 0,28 | client=Kamdem ⁴ | ✅ |
| 6 | Factures en retard | factures | factures | 0,45 | statut=en_retard | ✅ |
| 7 | Donne-moi un résumé de l'activité du parc | stats | stats | 0,52 | — | ✅ |
| 8 | Quels engins sont disponibles ? | engins | engins | 0,54 | statut=disponible | ✅ |
| 9 | Quelles machines sont en maintenance ? | engins | engins | 0,39 | statut=maintenance | ✅ |
| 10 | Y a-t-il des engins en panne ? | engins | engins | 0,39 | statut=defectueux | ✅ |
| 11 | Quelles interventions sont encore en attente ? | interventions | interventions | 0,46 | statut=en_attente | ✅ |
| 12 | Liste des contrats actifs | contrats | contrats | 0,51 | statut=actif | ✅ |
| 13 | Coordonnées des clients du secteur BTP | clients | clients | 0,50 | — | ✅ |
| 14 | Bonjour, que peux-tu faire ? | salutation | salutation | 0,42 | — | ✅ |
| 15 | Quelle est la météo à Douala ? | *hors périmètre* | *fallback* (aide) | 0,00 | — | ✅ |
| 16 | Contrats terminés cette année | contrats | contrats | 0,38 | statut=termine | ✅ |

**Résultat : 16/16 (100 %)** — dont 15/16 par la classification TF-IDF seule,
le cas n° 5 étant résolu par la règle de désambiguïsation.

¹ Le mot « expirent » produit aussi l'entité statut=expire ; le gestionnaire la
normalise (recherche de permis *valides* expirant sous N jours) — comportement voulu.
² « ce mois » est converti en 30 jours ; l'entité parasite statut=renouvele
(déclenchée par « renouveler ») est ignorée car non applicable aux permis.
³ La question mentionne « client » mais relève du champ lexical de l'argent
(« doit ») : la règle de désambiguïsation bascule l'intention vers *factures*.
⁴ L'entité client est rapprochée de la table `client` (nécessite la base — vérifiée
en environnement de production).

## Test 2 — Suivi de contexte (résolution d'anaphore)

Le déclencheur d'anaphore (pronoms « ses », « il », « lui », tournures « et… »)
doit s'activer sur les questions contextuelles et **jamais** sur les autres :

| Question | Déclenchement attendu | Observé | Intention détectée | Résultat |
|---|---|---|---|---|
| et ses contrats ? | oui | oui | contrats (0,48) | ✅ |
| combien doit-il ? | oui | oui | factures (0,16) | ✅ |
| et les factures de ce client ? | oui | oui | factures | ✅ |
| Quels engins sont disponibles ? | non | non | engins | ✅ |
| Liste des contrats actifs | non | non | contrats | ✅ |

**Résultat : 5/5 (100 %)** — aucun faux positif : les questions autonomes ne
déclenchent pas la reprise de contexte.

## Interprétation

- Les scores des questions bien formulées se situent entre 0,20 et 0,67, nettement
  au-dessus du seuil de 0,12 : la marge est confortable.
- La question hors périmètre obtient un score de 0,00 : le rejet est franc, le
  moteur ne « force » jamais une réponse — il privilégie la **précision** (ne pas
  se tromper) au **rappel** (répondre à tout).
- Le seul cas ambigu du jeu de tests (n° 5, deux intentions plausibles) illustre la
  complémentarité de l'approche : la statistique (TF-IDF) traite le cas général,
  les règles traitent les cas limites identifiés.

## Reproduire les tests

Les scripts de test sont exécutables hors ligne :

```bash
php -r "require 'api/moteur_ia.php';
[\$i, \$s] = mia_detecter('Quelles factures sont impayées ?');
echo \$i, ' ', round(\$s, 2);"   # → factures 0.67
```
