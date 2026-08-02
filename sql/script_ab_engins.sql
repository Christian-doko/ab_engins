-- ============================================================================
-- AB ENGINS SARL - Systeme de gestion de location d'engins lourds
-- Script de creation de la base de donnees MySQL
-- ============================================================================

CREATE DATABASE IF NOT EXISTS ab_engins
  CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

USE ab_engins;

-- ----------------------------------------------------------------------------
-- 1. SECTEUR_ACTIVITE
-- ----------------------------------------------------------------------------
CREATE TABLE secteur_activite (
  id_secteur      INT AUTO_INCREMENT PRIMARY KEY,
  libelle_secteur VARCHAR(50) NOT NULL UNIQUE
) ENGINE=InnoDB;

-- ----------------------------------------------------------------------------
-- 2. CLIENT
-- ----------------------------------------------------------------------------
CREATE TABLE client (
  id_client         INT AUTO_INCREMENT PRIMARY KEY,
  nom_client        VARCHAR(100) NOT NULL,
  nom_representant  VARCHAR(100) NOT NULL,
  cni_representant  VARCHAR(20)  NOT NULL UNIQUE,
  telephone_client  VARCHAR(20),
  adresse_client    VARCHAR(150),
  email_client      VARCHAR(100),
  id_secteur        INT NOT NULL,
  CONSTRAINT fk_client_secteur
    FOREIGN KEY (id_secteur) REFERENCES secteur_activite(id_secteur)
    ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB;

CREATE INDEX idx_client_secteur ON client(id_secteur);

-- ----------------------------------------------------------------------------
-- 3. PERMIS
-- ----------------------------------------------------------------------------
CREATE TABLE permis (
  id_permis        INT AUTO_INCREMENT PRIMARY KEY,
  numero_permis    VARCHAR(30) NOT NULL UNIQUE,
  region           VARCHAR(50) NOT NULL,
  departement      VARCHAR(50) NOT NULL,
  arrondissement   VARCHAR(50) NOT NULL,
  foret_concernee  VARCHAR(100),
  superficie_ha    DECIMAL(10,2),
  date_delivrance  DATE NOT NULL,
  date_expiration  DATE NOT NULL,
  statut_permis    ENUM('valide','expire','suspendu') NOT NULL DEFAULT 'valide',
  id_client        INT NOT NULL,
  CONSTRAINT fk_permis_client
    FOREIGN KEY (id_client) REFERENCES client(id_client)
    ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT chk_permis_dates CHECK (date_expiration > date_delivrance)
) ENGINE=InnoDB;

CREATE INDEX idx_permis_client ON permis(id_client);

-- ----------------------------------------------------------------------------
-- 4. CONTRAT
-- ----------------------------------------------------------------------------
CREATE TABLE contrat (
  id_contrat          INT AUTO_INCREMENT PRIMARY KEY,
  date_signature      DATE NOT NULL,
  lieu_signature      VARCHAR(50),
  date_effet          DATE NOT NULL,
  duree_jours         INT NOT NULL,
  date_fin_prevue     DATE GENERATED ALWAYS AS (DATE_ADD(date_effet, INTERVAL duree_jours DAY)) STORED,
  tacite_reconduction BOOLEAN NOT NULL DEFAULT FALSE,
  statut_contrat      ENUM('actif','termine','resilie','renouvele') NOT NULL DEFAULT 'actif',
  montant_ht          DECIMAL(12,2) NOT NULL,
  id_client           INT NOT NULL,
  -- Regle de gestion : le client doit presenter un permis d'exploitation
  -- pour obtenir un contrat. Le permis est donc obligatoire, et le contrat
  -- ne peut pas exister sans lui (RESTRICT plutot que SET NULL).
  id_permis           INT NOT NULL,
  CONSTRAINT fk_contrat_client
    FOREIGN KEY (id_client) REFERENCES client(id_client)
    ON UPDATE CASCADE ON DELETE RESTRICT,
  CONSTRAINT fk_contrat_permis
    FOREIGN KEY (id_permis) REFERENCES permis(id_permis)
    ON UPDATE CASCADE ON DELETE RESTRICT,
  CONSTRAINT chk_contrat_duree CHECK (duree_jours > 0)
) ENGINE=InnoDB;

CREATE INDEX idx_contrat_client ON contrat(id_client);
CREATE INDEX idx_contrat_permis ON contrat(id_permis);

-- ----------------------------------------------------------------------------
-- 5. ENGIN
-- ----------------------------------------------------------------------------
CREATE TABLE engin (
  id_engin      INT AUTO_INCREMENT PRIMARY KEY,
  code_engin    VARCHAR(20) NOT NULL UNIQUE,
  type_engin    VARCHAR(50) NOT NULL,
  modele_engin  VARCHAR(30),
  numero_serie  VARCHAR(30) UNIQUE,
  etat_engin    ENUM('bon','moyen','defectueux') NOT NULL DEFAULT 'bon',
  disponibilite ENUM('disponible','loue','maintenance') NOT NULL DEFAULT 'disponible'
) ENGINE=InnoDB;

-- ----------------------------------------------------------------------------
-- 6. CONTRAT_ENGIN (association N,N Contrat <-> Engin)
-- ----------------------------------------------------------------------------
CREATE TABLE contrat_engin (
  id_contrat_engin       INT AUTO_INCREMENT PRIMARY KEY,
  id_contrat             INT NOT NULL,
  id_engin               INT NOT NULL,
  date_mise_disposition  DATE NOT NULL,
  etat_depart            VARCHAR(150),
  etat_retour            VARCHAR(150),
  date_retour_effective  DATE NULL,
  CONSTRAINT fk_ce_contrat
    FOREIGN KEY (id_contrat) REFERENCES contrat(id_contrat)
    ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT fk_ce_engin
    FOREIGN KEY (id_engin) REFERENCES engin(id_engin)
    ON UPDATE CASCADE ON DELETE RESTRICT,
  CONSTRAINT uq_contrat_engin UNIQUE (id_contrat, id_engin)
) ENGINE=InnoDB;

CREATE INDEX idx_ce_contrat ON contrat_engin(id_contrat);
CREATE INDEX idx_ce_engin ON contrat_engin(id_engin);

-- ----------------------------------------------------------------------------
-- 7. EMPLOYE
-- ----------------------------------------------------------------------------
CREATE TABLE employe (
  id_employe        INT AUTO_INCREMENT PRIMARY KEY,
  nom_employe       VARCHAR(50) NOT NULL,
  prenom_employe    VARCHAR(50) NOT NULL,
  poste             ENUM('conducteur','mecanicien','motorboy','agent_reception','technicien') NOT NULL,
  telephone_employe VARCHAR(20)
) ENGINE=InnoDB;

CREATE INDEX idx_employe_poste ON employe(poste);

-- ----------------------------------------------------------------------------
-- 8. AFFECTATION_PERSONNEL (association N,N Contrat_Engin <-> Employe)
-- ----------------------------------------------------------------------------
CREATE TABLE affectation_personnel (
  id_affectation        INT AUTO_INCREMENT PRIMARY KEY,
  id_contrat_engin      INT NOT NULL,
  id_employe            INT NOT NULL,
  date_debut_affectation DATE NOT NULL,
  date_fin_affectation   DATE NULL,
  CONSTRAINT fk_aff_contrat_engin
    FOREIGN KEY (id_contrat_engin) REFERENCES contrat_engin(id_contrat_engin)
    ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT fk_aff_employe
    FOREIGN KEY (id_employe) REFERENCES employe(id_employe)
    ON UPDATE CASCADE ON DELETE RESTRICT,
  CONSTRAINT uq_affectation UNIQUE (id_contrat_engin, id_employe, date_debut_affectation)
) ENGINE=InnoDB;

CREATE INDEX idx_aff_contrat_engin ON affectation_personnel(id_contrat_engin);
CREATE INDEX idx_aff_employe ON affectation_personnel(id_employe);

-- ----------------------------------------------------------------------------
-- 9. RECEPTION
-- ----------------------------------------------------------------------------
CREATE TABLE reception (
  id_reception   INT AUTO_INCREMENT PRIMARY KEY,
  date_reception DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  motif_visite   VARCHAR(100),
  id_client      INT NOT NULL,
  id_employe     INT NOT NULL,
  CONSTRAINT fk_reception_client
    FOREIGN KEY (id_client) REFERENCES client(id_client)
    ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT fk_reception_employe
    FOREIGN KEY (id_employe) REFERENCES employe(id_employe)
    ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB;

CREATE INDEX idx_reception_client ON reception(id_client);
CREATE INDEX idx_reception_employe ON reception(id_employe);

-- ----------------------------------------------------------------------------
-- 10. ASSISTANCE
-- ----------------------------------------------------------------------------
CREATE TABLE assistance (
  id_assistance        INT AUTO_INCREMENT PRIMARY KEY,
  date_intervention    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  motif_intervention   VARCHAR(100),
  description_probleme VARCHAR(250),
  resolution           VARCHAR(250),
  statut_intervention  ENUM('en_attente','en_cours','resolu') NOT NULL DEFAULT 'en_attente',
  id_contrat_engin     INT NOT NULL,
  id_employe           INT NOT NULL,
  CONSTRAINT fk_assistance_contrat_engin
    FOREIGN KEY (id_contrat_engin) REFERENCES contrat_engin(id_contrat_engin)
    ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT fk_assistance_employe
    FOREIGN KEY (id_employe) REFERENCES employe(id_employe)
    ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB;

CREATE INDEX idx_assistance_ce ON assistance(id_contrat_engin);
CREATE INDEX idx_assistance_employe ON assistance(id_employe);

-- ----------------------------------------------------------------------------
-- 11. INSPECTION
-- ----------------------------------------------------------------------------
CREATE TABLE inspection (
  id_inspection      INT AUTO_INCREMENT PRIMARY KEY,
  date_inspection    DATE NOT NULL,
  resultat_inspection ENUM('conforme','non_conforme') NOT NULL,
  observations       VARCHAR(250),
  id_contrat         INT NOT NULL,
  id_employe         INT NULL,
  CONSTRAINT fk_inspection_contrat
    FOREIGN KEY (id_contrat) REFERENCES contrat(id_contrat)
    ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT fk_inspection_employe
    FOREIGN KEY (id_employe) REFERENCES employe(id_employe)
    ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE INDEX idx_inspection_contrat ON inspection(id_contrat);

-- ----------------------------------------------------------------------------
-- 12. FACTURE
-- ----------------------------------------------------------------------------
CREATE TABLE facture (
  id_facture         INT AUTO_INCREMENT PRIMARY KEY,
  numero_facture     VARCHAR(20) NOT NULL UNIQUE,
  date_facture       DATE NOT NULL,
  montant_ht_facture DECIMAL(12,2) NOT NULL,
  taux_tva           DECIMAL(5,2) NOT NULL DEFAULT 19.25,
  montant_ttc        DECIMAL(12,2) GENERATED ALWAYS AS
                        (ROUND(montant_ht_facture * (1 + taux_tva/100), 2)) STORED,
  statut_paiement    ENUM('paye','partiel','en_retard','impaye') NOT NULL DEFAULT 'impaye',
  id_contrat         INT NOT NULL,
  CONSTRAINT fk_facture_contrat
    FOREIGN KEY (id_contrat) REFERENCES contrat(id_contrat)
    ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB;

CREATE INDEX idx_facture_contrat ON facture(id_contrat);

-- ----------------------------------------------------------------------------
-- 13. LIGNE_FACTURE
-- ----------------------------------------------------------------------------
CREATE TABLE ligne_facture (
  id_ligne          INT AUTO_INCREMENT PRIMARY KEY,
  designation_ligne VARCHAR(100) NOT NULL,
  quantite          DECIMAL(10,2) NOT NULL DEFAULT 1,
  prix_unitaire     DECIMAL(12,2) NOT NULL,
  montant_ligne     DECIMAL(12,2) GENERATED ALWAYS AS (quantite * prix_unitaire) STORED,
  id_facture        INT NOT NULL,
  CONSTRAINT fk_ligne_facture
    FOREIGN KEY (id_facture) REFERENCES facture(id_facture)
    ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE INDEX idx_ligne_facture ON ligne_facture(id_facture);

-- ----------------------------------------------------------------------------
-- 14. PAIEMENT
-- ----------------------------------------------------------------------------
CREATE TABLE paiement (
  id_paiement    INT AUTO_INCREMENT PRIMARY KEY,
  date_paiement  DATE NOT NULL,
  montant_paye   DECIMAL(12,2) NOT NULL,
  mode_paiement  ENUM('especes','virement','mobile_money','cheque') NOT NULL,
  id_facture     INT NOT NULL,
  CONSTRAINT fk_paiement_facture
    FOREIGN KEY (id_facture) REFERENCES facture(id_facture)
    ON UPDATE CASCADE ON DELETE RESTRICT,
  CONSTRAINT chk_paiement_montant CHECK (montant_paye > 0)
) ENGINE=InnoDB;

CREATE INDEX idx_paiement_facture ON paiement(id_facture);

-- ============================================================================
-- REGLE DE GESTION : un engin ne peut pas etre affecte a deux contrats actifs
-- dont les periodes se chevauchent.
-- ============================================================================
DELIMITER $$

CREATE TRIGGER trg_check_chevauchement_engin
BEFORE INSERT ON contrat_engin
FOR EACH ROW
BEGIN
  DECLARE nb_conflits INT;

  SELECT COUNT(*) INTO nb_conflits
  FROM contrat_engin ce
  INNER JOIN contrat c ON c.id_contrat = ce.id_contrat
  INNER JOIN contrat c_new ON c_new.id_contrat = NEW.id_contrat
  WHERE ce.id_engin = NEW.id_engin
    AND c.statut_contrat = 'actif'
    AND ce.date_mise_disposition < c_new.date_fin_prevue
    AND c.date_fin_prevue > NEW.date_mise_disposition;

  IF nb_conflits > 0 THEN
    SIGNAL SQLSTATE '45000'
    SET MESSAGE_TEXT = 'Conflit : cet engin est deja affecte a un contrat actif sur cette periode.';
  END IF;
END$$

-- ============================================================================
-- REGLE DE GESTION : le client doit presenter un permis d'exploitation valide
-- pour obtenir un contrat. Le permis rattache au contrat doit donc :
--   1. appartenir au client signataire ;
--   2. couvrir la date de signature (delivre avant, expire apres) ;
--   3. ne pas etre suspendu.
-- Procedure partagee par les triggers INSERT et UPDATE de contrat.
-- ============================================================================
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
