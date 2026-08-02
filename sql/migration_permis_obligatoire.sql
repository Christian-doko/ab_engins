-- ============================================================================
-- AB ENGINS SARL - Migration : le permis devient obligatoire pour un contrat
--
-- Regle de gestion : un client doit presenter un permis d'exploitation valide
-- avant d'obtenir un contrat de location. Le schema d'origine autorisait
-- contrat.id_permis a NULL, ce qui permettait de creer un contrat sans permis.
--
-- Cette migration est idempotente : elle peut etre rejouee sans effet de bord.
-- Elle est aussi appliquee automatiquement par api/migrations.php.
-- ============================================================================
USE ab_engins;

-- ----------------------------------------------------------------------------
-- 1. Renouvellement de permis manquant
--    AGRO-PLUS a des contrats signes en 2026 alors que son unique permis
--    (PA-2023-201) a expire le 10/01/2025 : on enregistre le renouvellement
--    qui les couvre. L'ancien permis reste expire dans l'historique.
-- ----------------------------------------------------------------------------
INSERT INTO permis (numero_permis, region, departement, arrondissement,
                    foret_concernee, superficie_ha, date_delivrance,
                    date_expiration, statut_permis, id_client)
SELECT 'PA-2025-114', 'Ouest', 'Mifi', 'Bafoussam', 'Périmètre Agro-1', 85.00,
       '2025-01-11', '2027-01-10', 'valide', c.id_client
FROM client c
WHERE c.nom_client = 'AGRO-PLUS'
  AND NOT EXISTS (SELECT 1 FROM permis p WHERE p.numero_permis = 'PA-2025-114');

-- ----------------------------------------------------------------------------
-- 2. Rattachement des contrats a un permis du client couvrant la signature
--    (contrats sans permis, ou dont le permis ne couvre pas la date de
--    signature). On retient le permis dont la periode contient la signature.
-- ----------------------------------------------------------------------------
UPDATE contrat ct
INNER JOIN permis p
        ON p.id_client = ct.id_client
       AND ct.date_signature BETWEEN p.date_delivrance AND p.date_expiration
SET ct.id_permis = p.id_permis
WHERE ct.id_permis IS NULL
   OR NOT EXISTS (
        SELECT 1 FROM permis pv
        WHERE pv.id_permis = ct.id_permis
          AND pv.id_client = ct.id_client
          AND ct.date_signature BETWEEN pv.date_delivrance AND pv.date_expiration
   );

-- ----------------------------------------------------------------------------
-- 3. Le permis devient obligatoire au niveau du schema
--    (RESTRICT : on ne peut plus supprimer un permis rattache a un contrat)
-- ----------------------------------------------------------------------------
ALTER TABLE contrat DROP FOREIGN KEY fk_contrat_permis;
ALTER TABLE contrat MODIFY id_permis INT NOT NULL;
ALTER TABLE contrat ADD CONSTRAINT fk_contrat_permis
  FOREIGN KEY (id_permis) REFERENCES permis(id_permis)
  ON UPDATE CASCADE ON DELETE RESTRICT;

-- ----------------------------------------------------------------------------
-- 4. Controle applicatif en base : appartenance, validite, suspension
-- ----------------------------------------------------------------------------
DROP TRIGGER IF EXISTS trg_check_permis_contrat_insert;
DROP TRIGGER IF EXISTS trg_check_permis_contrat_update;
DROP PROCEDURE IF EXISTS valider_permis_contrat;

DELIMITER $$

CREATE PROCEDURE valider_permis_contrat(
  IN p_id_permis INT, IN p_id_client INT, IN p_date_signature DATE
)
BEGIN
  DECLARE v_id_client_permis INT;
  DECLARE v_delivrance DATE;
  DECLARE v_expiration DATE;
  DECLARE v_statut VARCHAR(20);

  SELECT id_client, date_delivrance, date_expiration, statut_permis
    INTO v_id_client_permis, v_delivrance, v_expiration, v_statut
  FROM permis WHERE id_permis = p_id_permis;

  IF v_id_client_permis IS NULL THEN
    SIGNAL SQLSTATE '45000'
    SET MESSAGE_TEXT = 'Permis introuvable : un contrat exige un permis existant.';
  END IF;

  IF v_id_client_permis <> p_id_client THEN
    SIGNAL SQLSTATE '45000'
    SET MESSAGE_TEXT = 'Le permis presente appartient a un autre client.';
  END IF;

  IF p_date_signature < v_delivrance OR p_date_signature > v_expiration THEN
    SIGNAL SQLSTATE '45000'
    SET MESSAGE_TEXT = 'Le permis presente n est pas valide a la date de signature du contrat.';
  END IF;

  IF v_statut = 'suspendu' THEN
    SIGNAL SQLSTATE '45000'
    SET MESSAGE_TEXT = 'Le permis presente est suspendu : aucun contrat ne peut etre etabli.';
  END IF;
END$$

CREATE TRIGGER trg_check_permis_contrat_insert
BEFORE INSERT ON contrat
FOR EACH ROW
BEGIN
  CALL valider_permis_contrat(NEW.id_permis, NEW.id_client, NEW.date_signature);
END$$

CREATE TRIGGER trg_check_permis_contrat_update
BEFORE UPDATE ON contrat
FOR EACH ROW
BEGIN
  IF NEW.id_permis <> OLD.id_permis
     OR NEW.id_client <> OLD.id_client
     OR NEW.date_signature <> OLD.date_signature THEN
    CALL valider_permis_contrat(NEW.id_permis, NEW.id_client, NEW.date_signature);
  END IF;
END$$

DELIMITER ;
