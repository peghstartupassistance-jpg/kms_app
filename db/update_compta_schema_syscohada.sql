-- Mise à jour du schéma pour supporter SYSCOHADA
-- À exécuter AVANT d'importer le plan comptable SYSCOHADA

-- Ajouter les types MIXTE et ANALYTIQUE à l'ENUM type_compte
ALTER TABLE compta_comptes 
MODIFY COLUMN type_compte ENUM('ACTIF', 'PASSIF', 'CHARGE', 'PRODUIT', 'MIXTE', 'ANALYTIQUE') DEFAULT 'ACTIF';

-- Vérification
SELECT 'Schéma mis à jour avec succès. Vous pouvez maintenant importer import_plan_syscohada.sql' as message;
