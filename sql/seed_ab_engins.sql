-- ============================================================================
-- AB ENGINS SARL - Jeu de donnees de demonstration (coherent avec les maquettes)
-- ============================================================================
USE ab_engins;

-- 1. Secteurs
INSERT INTO secteur_activite (libelle_secteur) VALUES
 ('Foresterie'), ('Mines'), ('Agriculture'), ('Lotissement'), ('Travaux publics');

-- 2. Clients
INSERT INTO client (nom_client, nom_representant, cni_representant, telephone_client, adresse_client, email_client, id_secteur) VALUES
 ('SOTRAF SARL',    'Paul Etoundi',    'CM-100234567', '699123456', 'Douala, Bonabéri',       'contact@sotraf.cm',    1),
 ('MINEX Cameroun', 'Marie Ngo Bidjeck','CM-100234889','677882103', 'Yaoundé, Nkolbisson',    'info@minexcam.cm',     2),
 ('AGRO-PLUS',      'Jean Fotso',      'CM-100235120', '655430977', 'Bafoussam, Centre-ville','agroplus@mail.cm',     3),
 ('LOTICAM',        'André Mbarga',    'CM-100235401', '690551240', 'Douala, Akwa',           'loticam@mail.cm',      4),
 ('BTP Horizon',    'Sylvie Kenfack',  'CM-100235602', '676209115', 'Yaoundé, Mvan',          'contact@btphorizon.cm',5);

-- 3. Permis (statut recalculé côté application selon date_expiration; ici valeur de depart)
INSERT INTO permis (numero_permis, region, departement, arrondissement, foret_concernee, superficie_ha, date_delivrance, date_expiration, statut_permis, id_client) VALUES
 ('PF-2024-118', 'Est',       'Haut-Nyong',   'Lomié',      'UFA 10-063',        1200.50, '2024-08-02', '2026-08-02', 'valide',  1),
 ('PM-2025-046', 'Sud',       'Dja-et-Lobo',  'Sangmélima', 'Zone minière Sud',   340.00,  '2025-08-15', '2026-08-15', 'valide',  2),
 ('PA-2023-201', 'Ouest',     'Mifi',         'Bafoussam',  'Périmètre Agro-1',   85.00,   '2023-08-28', '2025-01-10', 'expire',  3),
 ('PL-2026-012', 'Littoral',  'Wouri',        'Douala 5e',  'Lotissement Nord',   12.00,   '2026-01-05', '2027-06-12', 'valide',  4),
 -- Delivre valide : la suspension administrative n'intervient qu'apres la
 -- signature des contrats de ce client (voir fin de seed_extra_contracts.sql).
 ('PT-2025-089', 'Centre',    'Mfoundi',      'Yaoundé 3e', 'Voirie Mvan',        6.50,    '2025-03-01', '2026-09-01', 'valide',  5),
 -- Renouvellement du permis d'AGRO-PLUS : l'ancien (PA-2023-201) reste expire
 -- dans l'historique, celui-ci couvre les contrats signes depuis 2025.
 ('PA-2025-114', 'Ouest',     'Mifi',         'Bafoussam',  'Périmètre Agro-1',   85.00,   '2025-01-11', '2027-01-10', 'valide',  3);

-- 4. Engins
INSERT INTO engin (code_engin, type_engin, modele_engin, numero_serie, etat_engin, disponibilite) VALUES
 ('ENG-004', 'Bulldozer',   'D7G',       '8C4T-1192', 'bon', 'loue'),
 ('ENG-011', 'Grumier',     'MAN TGS',   'WMA-33071', 'bon', 'disponible'),
 ('ENG-017', 'Débardeur',   '648L',      'JD-64822',  'bon', 'disponible'),
 ('ENG-021', 'Abatteuse',   '953M',      'CAT-95310', 'bon', 'maintenance'),
 ('ENG-025', 'Tronçonneuse','méc.',      'ST-77012',  'bon', 'disponible'),
 ('ENG-030', 'Niveleuse',   '140K',      'CAT-14077', 'bon', 'loue');

-- 5. Employés
INSERT INTO employe (nom_employe, prenom_employe, poste, telephone_employe) VALUES
 ('Kamdem', 'Aliou',   'agent_reception', '677001122'),
 ('Mballa', 'Eric',    'conducteur',      '677002233'),
 ('Ateba',  'Josiane', 'mecanicien',      '677003344'),
 ('Nguema', 'Serge',   'technicien',      '677004455');

-- 6. Contrats
INSERT INTO contrat (date_signature, lieu_signature, date_effet, duree_jours, tacite_reconduction, statut_contrat, montant_ht, id_client, id_permis) VALUES
 ('2026-07-10', 'Douala',  '2026-07-15', 45, FALSE, 'actif',   3600000.00, 1, 1),
 ('2026-07-05', 'Yaoundé', '2026-07-10', 30, FALSE, 'actif',   2100000.00, 2, 2),
 ('2026-06-28', 'Bafoussam','2026-07-02', 20, FALSE, 'termine', 950000.00, 3, 6),
 ('2026-06-20', 'Douala',  '2026-06-28', 15, FALSE, 'resilie', 600000.00, 4, 4);

-- 7. Contrat_engin
INSERT INTO contrat_engin (id_contrat, id_engin, date_mise_disposition, etat_depart) VALUES
 (1, 1, '2026-07-15', 'Bon état, plein carburant'),
 (2, 2, '2026-07-10', 'Bon état'),
 (3, 4, '2026-07-02', 'Bon état'),
 (4, 6, '2026-06-28', 'Bon état');

-- 8. Facture (correspond à la maquette FA-2026-118)
INSERT INTO facture (numero_facture, date_facture, montant_ht_facture, taux_tva, statut_paiement, id_contrat) VALUES
 ('FA-2026-118', '2026-08-05', 4030000.00, 19.25, 'partiel', 1);

INSERT INTO ligne_facture (designation_ligne, quantite, prix_unitaire, id_facture) VALUES
 ('Location Bulldozer D7G — 45 j', 45, 80000.00, 1),
 ('Transport aller-retour',         1, 350000.00, 1),
 ('Pénalité retard restitution',    2, 40000.00,  1);

INSERT INTO paiement (date_paiement, montant_paye, mode_paiement, id_facture) VALUES
 ('2026-08-05', 2000000.00, 'virement',     1),
 ('2026-08-20', 1000000.00, 'mobile_money', 1);
