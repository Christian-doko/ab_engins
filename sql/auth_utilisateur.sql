-- ============================================================================
-- AB ENGINS SARL - Extension authentification
-- Le MCD/MLDR d'origine couvre le domaine metier (clients, contrats, engins...)
-- mais ne modelise aucune entite de connexion. Cette table est necessaire pour
-- une authentification reelle et n'existait pas dans le script d'origine.
-- ============================================================================
USE ab_engins;

CREATE TABLE IF NOT EXISTS utilisateur (
  id_utilisateur     INT AUTO_INCREMENT PRIMARY KEY,
  identifiant        VARCHAR(50) NOT NULL UNIQUE,
  mot_de_passe_hash  VARCHAR(255) NOT NULL,
  role               ENUM('admin','agent','technicien') NOT NULL DEFAULT 'agent',
  actif              BOOLEAN NOT NULL DEFAULT TRUE,
  derniere_connexion DATETIME NULL,
  id_employe         INT NULL,
  CONSTRAINT fk_utilisateur_employe
    FOREIGN KEY (id_employe) REFERENCES employe(id_employe)
    ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE INDEX idx_utilisateur_employe ON utilisateur(id_employe);
