# Scénario de démonstration — Soutenance AB ENGINS

Durée cible : **5 à 6 minutes**. Répéter le parcours au moins deux fois avant le jour J,
en chronométrant. Ne jamais improviser une action non répétée pendant la soutenance.

---

## Avant le jour J (checklist)

- [ ] Vérifier que https://abengins-production.up.railway.app/login.php répond ;
- [ ] Tester la connexion avec le compte admin **la veille et le matin même** ;
- [ ] Créer un compte **agent de test** (menu Utilisateurs) pour la démo des rôles ;
- [ ] Repérer une facture **partiellement payée** et un client avec plusieurs contrats
      (pour les questions à l'assistant) ;
- [ ] **Plan B hors ligne** : lancer XAMPP local, vérifier que l'application complète
      fonctionne sur `http://localhost/AB%20ENGINS/login.php` — le moteur IA n'ayant
      aucune dépendance externe, la démo est identique sans Internet ;
- [ ] Garder les deux onglets prêts (production + localhost) avant de commencer.

---

## Déroulé minuté

### 0:00 — Connexion et tableau de bord (1 min)

1. Ouvrir la page de connexion, se connecter en admin.
2. Commenter le tableau de bord : *« L'application agrège en temps réel les indicateurs
   clés : contrats actifs, disponibilité du parc, permis à renouveler, impayés. »*
3. Montrer les compteurs du menu latéral (clients, contrats, permis) : *« calculés
   à chaque chargement depuis MySQL »*.

### 1:00 — Parcours métier (1 min 30)

4. **Clients** : ouvrir la liste, montrer la recherche.
5. **Contrats** : montrer un contrat et sa période ; mentionner le **trigger SQL**
   qui interdit d'affecter un même engin à deux contrats actifs qui se chevauchent
   (règle de gestion implémentée en base, pas seulement dans l'interface).
6. **Factures** : ouvrir une facture partiellement payée, montrer l'historique des
   paiements et le recalcul automatique du statut (payé / partiel / en retard).
7. Cliquer **Imprimer / PDF** : montrer la facture A4 générée, revenir.

### 2:30 — Assistant IA maison (2 min — le point fort)

Annoncer : *« L'assistant repose sur un moteur de compréhension du langage développé
entièrement pour ce projet — aucune API externe : normalisation, racinisation,
vectorisation TF-IDF, similarité cosinus, extraction d'entités. »*

Poser dans l'ordre :

1. **« Donne-moi un résumé de l'activité du parc »** → synthèse chiffrée ;
2. **« Quels permis expirent dans les 90 prochains jours ? »** → montrer que la
   durée est extraite de la question (entité) ;
3. **« Quelles factures sont impayées ? »** puis, en désignant un client cité dans
   la réponse : **« et ses contrats ? »** → expliquer le **suivi de contexte**
   (résolution d'anaphore : le moteur retrouve le client dans l'historique) ;
4. **« Quelle est la météo à Douala ? »** → montrer le rejet contrôlé : *« le moteur
   mesure un score de similarité ; sous le seuil de confiance, il refuse de répondre
   plutôt que d'inventer — c'est un choix de conception. »*

### 4:30 — Rôles et sécurité (1 min)

8. Menu **Utilisateurs** : créer (ou montrer) le compte agent de test.
9. Se déconnecter, se reconnecter en agent : le menu Utilisateurs a disparu.
   *« Le contrôle du rôle est fait côté serveur à chaque requête, pas seulement
   dans l'affichage. »*
10. Conclure : *« Déploiement continu : chaque push sur GitHub redéploie
    automatiquement l'application sur Railway. »*

---

## Questions probables du jury — réponses préparées

**« Pourquoi ne pas avoir utilisé ChatGPT / une API d'IA ? »**
Choix délibéré : une API externe est une boîte noire payante, dépendante du réseau,
et n'aurait rien démontré de mes compétences. Le moteur maison est explicable de bout
en bout, fonctionne hors ligne et ne fait sortir aucune donnée de l'entreprise —
argument de confidentialité important pour des données de facturation.

**« Comment le moteur comprend-il une question ? »**
Dérouler le pipeline (schéma dans `docs/moteur-ia.md`) : normalisation → tokenisation
et suppression des mots vides → racinisation par suffixes → vectorisation TF-IDF →
similarité cosinus avec les 8 documents d'intention → extraction d'entités par règles
(durées, statuts, noms de clients rapprochés de la base) → requête SQL préparée →
gabarit de réponse.

**« Que se passe-t-il si la question est ambiguë ou hors sujet ? »**
Sous le seuil de similarité (0,12), le moteur répond par un message d'aide listant ses
capacités. Une règle de désambiguïsation traite les cas mixtes (ex. « combien nous doit
le client X » : mots du champ lexical de l'argent → intention factures, pas clients).

**« L'assistant peut-il modifier ou divulguer des données ? »**
Non : toutes ses requêtes sont en lecture seule, préparées (aucune injection SQL
possible), plafonnées à 50 lignes, et l'endpoint exige une session authentifiée.

**« Quelles sont les limites ? »**
Périmètre fermé de 8 intentions (comme les chatbots industriels à intentions type
Rasa/Dialogflow) ; le suivi de contexte se limite à l'entité client ; pas de
reformulation libre. Évolutions prévues : classifieur entraîné (Naïve Bayes sur
TF-IDF), contexte étendu, nouvelles intentions.

**« Et la sécurité de l'application en général ? »**
Mots de passe hashés (`password_hash`/bcrypt), sessions régénérées à la connexion,
contrôle de rôle côté serveur, requêtes préparées partout, secrets hors du dépôt
(variables d'environnement ; push bloqué par la protection GitHub le vérifie).

**« Pourquoi Railway et pas un hébergeur classique ? »**
Déploiement continu depuis GitHub, base MySQL managée, variables d'environnement,
logs — l'infrastructure moderne d'une petite équipe, gratuite à cette échelle.

---

## Réflexes en cas d'imprévu

| Problème | Réaction |
|---|---|
| Pas d'Internet / Railway en panne | Basculer sur l'onglet localhost (XAMPP) sans commenter — la démo est identique |
| L'assistant classe mal une question | Reformuler naturellement, puis assumer : « le seuil de confiance privilégie la précision au rappel » |
| Question du jury sans réponse | « Je n'ai pas implémenté ce cas ; voici comment je m'y prendrais… » (toujours proposer une piste) |
