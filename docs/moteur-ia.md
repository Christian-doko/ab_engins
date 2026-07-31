# Moteur NLU de l'assistant AB ENGINS

Assistant conversationnel développé **entièrement en interne** (PHP pur, aucune API
externe, aucune bibliothèque tierce). Il répond en français à des questions de gestion
portant sur les clients, permis, contrats, engins, factures et interventions.

## Pipeline de traitement

```
                    ┌─────────────────────────────────────────────┐
 Question (français)│  1. Normalisation (minuscules, accents)     │
        │           │  2. Tokenisation + suppression mots vides   │
        ▼           │  3. Racinisation (stemming français léger)  │
 ┌──────────────┐   └─────────────────────────────────────────────┘
 │ moteur_ia.php│                       │
 └──────────────┘                       ▼
                    ┌─────────────────────────────────────────────┐
                    │  4. Vectorisation TF-IDF de la question     │
                    │  5. Similarité cosinus avec les 8 documents │
                    │     d'intention → intention la plus proche  │
                    │     (seuil 0,12 sinon réponse d'aide)       │
                    └─────────────────────────────────────────────┘
                                        │
                                        ▼
                    ┌─────────────────────────────────────────────┐
                    │  6. Extraction d'entités (regex + base) :   │
                    │     durées (« 60 jours », « ce mois »),     │
                    │     statuts (impayé, disponible, résolu…),  │
                    │     noms de clients (comparés à la table)   │
                    │  7. Règles de désambiguïsation              │
                    └─────────────────────────────────────────────┘
                                        │
                                        ▼
 ┌───────────────────┐  ┌─────────────────────────────────────────┐
 │outils_donnees.php │◄─│  8. Exécution de l'outil SQL paramétré  │
 │ (7 requêtes SQL   │  │     (lecture seule, LIMIT 50)           │
 │  en lecture seule)│  └─────────────────────────────────────────┘
 └───────────────────┘                  │
                                        ▼
                    ┌─────────────────────────────────────────────┐
                    │  9. Génération de la réponse en français    │
                    │     (gabarits, montants FCFA, dates JJ/MM)  │
                    └─────────────────────────────────────────────┘
```

## Concepts mis en œuvre

| Étape | Concept | Implémentation |
|---|---|---|
| Normalisation | Prétraitement de texte | `mia_normaliser()` — translittération des accents |
| Tokenisation | Segmentation lexicale | `mia_tokens()` — découpage + mots vides français |
| Racinisation | Stemming par suffixes | `mia_stem()` — suppression itérative (« expirent », « expiration » → « expir ») |
| Représentation | Sac de mots pondéré **TF-IDF** | `mia_tf()`, `mia_idf()` (IDF lissé : log((N+1)/(df+1))+1) |
| Classification | **Similarité cosinus** sur vecteurs creux | `mia_cosinus()`, `mia_detecter()` |
| Entités | Reconnaissance par règles + dictionnaire | `mia_entites()` — regex durées, lexique de statuts, rapprochement avec la table `client` |
| Dialogue | Intentions + fallback | 8 intentions ; sous le seuil de confiance, message d'aide |

## Choix de conception

- **Architecture à intentions** : même principe que les plateformes industrielles
  (Rasa, Dialogflow) — un nombre fini d'intentions couvrant le périmètre métier,
  chacune reliée à une requête SQL paramétrée. Prévisible, explicable, testable.
- **Lecture seule** : l'assistant ne peut jamais modifier les données ; toutes les
  requêtes sont préparées (aucune injection possible) et plafonnées à 50 lignes.
- **Sécurité** : l'endpoint `api/agent.php` exige une session authentifiée.
- **Suivi de contexte (anaphore)** : si une question fait référence à un client
  sans le nommer (« et ses contrats ? », « combien doit-il ? »), le moteur reprend
  le dernier client mentionné dans l'historique de la conversation.
- **Limites assumées** : questions hors périmètre → message d'aide ; le contexte
  suivi se limite à l'entité client.

## Perspectives d'évolution

1. **Classifieur entraîné** (Naïve Bayes ou régression logistique sur vecteurs
   TF-IDF) à partir d'un corpus de phrases d'exemple, pour remplacer le corpus
   de mots-clés rédigé à la main.
2. **Contexte étendu** : suivre d'autres entités que le client (engin, période).
3. **Nouvelles intentions** : chiffre d'affaires par période, planning des
   affectations de personnel, historique d'un engin.
