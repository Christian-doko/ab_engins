-- ============================================================================
-- AB ENGINS SARL - Contrats historiques additionnels (fev-juin 2026)
-- Objectif : donner au graphique "Locations par mois" du tableau de bord
-- une serie realiste sur 6 mois. Tous "termine" -> aucun conflit possible
-- avec le trigger de non-chevauchement (qui ne regarde que les actifs).
-- ============================================================================
USE ab_engins;

INSERT INTO contrat (date_signature, lieu_signature, date_effet, duree_jours, tacite_reconduction, statut_contrat, montant_ht, id_client, id_permis) VALUES
-- Fevrier 2026 (2 contrats)
('2026-02-01','Douala',   '2026-02-03', 10, FALSE, 'termine',  480000.00, 2, 2),
('2026-02-14','Yaoundé',  '2026-02-16', 12, FALSE, 'termine',  620000.00, 4, 4),
-- Mars 2026 (3 contrats)
('2026-03-02','Bafoussam','2026-03-04', 14, FALSE, 'termine',  700000.00, 3, 6),
('2026-03-11','Douala',   '2026-03-13', 8,  FALSE, 'termine',  350000.00, 1, 1),
('2026-03-20','Yaoundé',  '2026-03-22', 15, FALSE, 'termine',  810000.00, 5, 5),
-- Avril 2026 (2 contrats)
('2026-04-05','Douala',   '2026-04-07', 10, FALSE, 'termine',  540000.00, 2, 2),
('2026-04-18','Bafoussam','2026-04-20', 12, FALSE, 'termine',  600000.00, 4, 4),
-- Mai 2026 (4 contrats)
('2026-05-02','Douala',   '2026-05-04', 20, FALSE, 'termine', 1100000.00, 1, 1),
('2026-05-10','Yaoundé',  '2026-05-12', 14, FALSE, 'termine',  760000.00, 3, 6),
('2026-05-16','Bafoussam','2026-05-18', 9,  FALSE, 'termine',  420000.00, 5, 5),
('2026-05-25','Douala',   '2026-05-27', 11, FALSE, 'termine',  590000.00, 2, 2),
-- Juin 2026 (2 contrats supplementaires, en plus du CT existant LOTICAM)
('2026-06-03','Yaoundé',  '2026-06-05', 10, FALSE, 'termine',  510000.00, 1, 1),
('2026-06-12','Douala',   '2026-06-14', 13, FALSE, 'termine',  680000.00, 3, 6);

-- Association engin (reutilisation des engins existants, contrats tous clotures)
INSERT INTO contrat_engin (id_contrat, id_engin, date_mise_disposition, etat_depart, etat_retour, date_retour_effective) VALUES
(5,  2, '2026-02-03', 'Bon état', 'Bon état', '2026-02-13'),
(6,  3, '2026-02-16', 'Bon état', 'Bon état', '2026-02-28'),
(7,  5, '2026-03-04', 'Bon état', 'Bon état', '2026-03-18'),
(8,  1, '2026-03-13', 'Bon état', 'Bon état', '2026-03-21'),
(9,  6, '2026-03-22', 'Bon état', 'Bon état', '2026-04-06'),
(10, 2, '2026-04-07', 'Bon état', 'Bon état', '2026-04-17'),
(11, 4, '2026-04-20', 'Bon état', 'Bon état', '2026-05-02'),
(12, 1, '2026-05-04', 'Bon état', 'Bon état', '2026-05-24'),
(13, 5, '2026-05-12', 'Bon état', 'Bon état', '2026-05-26'),
(14, 3, '2026-05-18', 'Bon état', 'Bon état', '2026-05-27'),
(15, 2, '2026-05-27', 'Bon état', 'Bon état', '2026-06-07'),
(16, 6, '2026-06-05', 'Bon état', 'Bon état', '2026-06-15'),
(17, 1, '2026-06-14', 'Bon état', 'Bon état', '2026-06-27');

-- Suspension administrative du permis de BTP Horizon, posterieure a la
-- signature de ses contrats : le client ne peut plus en obtenir de nouveau.
UPDATE permis SET statut_permis = 'suspendu' WHERE numero_permis = 'PT-2025-089';
