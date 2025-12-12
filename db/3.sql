-- ==========================================================
--  PERMISSION : gestion des utilisateurs (ADMIN uniquement)
-- ==========================================================

INSERT INTO permissions (code, description) VALUES
 ('UTILISATEURS_GERER', 'Créer, modifier et désactiver les utilisateurs internes');

-- Associer la permission UTILISATEURS_GERER au rôle ADMIN
INSERT INTO role_permission (role_id, permission_id)
SELECT r.id, p.id
FROM roles r
JOIN permissions p
  ON p.code = 'UTILISATEURS_GERER'
WHERE r.code = 'ADMIN';
