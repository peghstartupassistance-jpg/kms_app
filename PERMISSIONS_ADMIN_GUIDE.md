# 🔐 GUIDE RAPIDE - PERMISSIONS ADMIN

## ✅ Configuration Terminée

**Le rôle ADMIN dispose désormais de TOUTES les permissions (22 permissions)**

### 📊 Permissions Attribuées par Module

| Module          | Permissions |
|----------------|-------------|
| ACHATS         | 1           |
| CAISSE         | 2           |
| CLIENTS        | 2           |
| COMPTABILITE   | 2           |
| DEVIS          | 3           |
| FORMATION      | 1           |
| HOTEL          | 1           |
| PRODUITS       | 4           |
| PROMOTIONS     | 1           |
| REPORTING      | 1           |
| SATISFACTION   | 1           |
| VENTES         | 3           |
| **TOTAL**      | **22**      |

---

## 🔄 Activer les Permissions (IMPORTANT)

**VOUS DEVEZ VOUS RECONNECTER** pour que les permissions soient effectives :

### Étape 1 : Déconnexion
1. Cliquez sur votre nom (en haut à droite)
2. Cliquez sur **"Déconnexion"**
3. OU allez directement sur : http://localhost/kms_app/logout.php

### Étape 2 : Reconnexion
1. Login : **admin**
2. Mot de passe : (votre mot de passe habituel)
3. OU allez sur : http://localhost/kms_app/login.php

### Étape 3 : Vérification
✅ Vous devriez maintenant avoir accès à **TOUS les modules** dans le menu latéral :
- Dashboard
- Produits & Stock
- Clients
- Devis
- Ventes
- Achats
- **Dashboard Magasinier** ← NOUVEAU
- Coordination
- Caisse
- Comptabilité
- Hôtel
- Formation
- Promotions
- Satisfaction
- Reporting

---

## 🔍 Comment Vérifier Vos Permissions

### Option 1 : Via le Dashboard
```
1. Connectez-vous avec admin
2. Allez sur http://localhost/kms_app/index.php
3. Tous les widgets devraient être visibles
```

### Option 2 : Test Direct
```
1. Essayez d'accéder à chaque module
2. Si vous voyez "Accès refusé" → Problème de permission
3. Si la page s'affiche → Permission OK ✅
```

### Option 3 : Script de Vérification
```bash
cd c:\xampp\htdocs\kms_app
C:\xampp\php\php.exe -r "session_start(); var_dump($_SESSION['permissions']);"
```
(Après connexion, devrait afficher 22 permissions)

---

## 🛠️ Réattribuer les Permissions (si nécessaire)

Si jamais vous perdez vos permissions ou ajoutez de nouvelles permissions :

```bash
cd c:\xampp\htdocs\kms_app
C:\xampp\php\php.exe grant_all_perms_admin.php
```

Puis **reconnectez-vous** impérativement.

---

## 📝 Liste Complète des Permissions Admin

```
ACHATS_LIRE
CAISSE_LIRE
CAISSE_ECRIRE
CLIENTS_LIRE
CLIENTS_ECRIRE
COMPTABILITE_LIRE
COMPTABILITE_ECRIRE
DEVIS_LIRE
DEVIS_CREER
DEVIS_VALIDER
FORMATION_LIRE
HOTEL_LIRE
PRODUITS_LIRE
PRODUITS_CREER
PRODUITS_MODIFIER
PRODUITS_SUPPRIMER
PROMOTIONS_LIRE
REPORTING_LIRE
SATISFACTION_LIRE
VENTES_LIRE
VENTES_CREER
VENTES_VALIDER
```

---

## ⚠️ Dépannage

### Problème : Toujours "Accès refusé" après reconnexion

**Solution 1 : Vider les sessions**
```bash
cd c:\xampp\htdocs\kms_app
C:\xampp\php\php.exe -r "session_start(); session_destroy(); echo 'Sessions vidées';"
```

**Solution 2 : Vider le cache navigateur**
- Ctrl + Shift + Delete
- Cocher "Cookies et données de sites"
- Cliquer "Effacer les données"

**Solution 3 : Vérifier que vous êtes bien admin**
```bash
cd c:\xampp\htdocs\kms_app
C:\xampp\php\php.exe -r "require 'db/db.php'; `$stmt = `$pdo->query('SELECT u.login, r.nom FROM utilisateurs u JOIN utilisateur_role ur ON u.id = ur.utilisateur_id JOIN roles r ON ur.role_id = r.id WHERE u.login = \"admin\"'); `$result = `$stmt->fetch(); print_r(`$result);"
```

### Problème : Certains modules ne s'affichent pas

**Vérifier que le module utilise bien la permission**
- Ouvrir le fichier PHP du module
- Chercher `exigerPermission('XXX_XXX')`
- Vérifier que cette permission existe dans la base

**Ajouter une permission manquante** :
```sql
INSERT INTO permissions (code, description) 
VALUES ('NOUVEAU_MODULE_LIRE', 'Lecture module nouveau');
```
Puis réexécuter `grant_all_perms_admin.php`

---

## 🎯 Commandes Utiles

### Lister toutes vos permissions actuelles
```bash
cd c:\xampp\htdocs\kms_app
C:\xampp\php\php.exe -r "require 'db/db.php'; `$stmt = `$pdo->query('SELECT p.code FROM permissions p JOIN role_permission rp ON p.id = rp.permission_id WHERE rp.role_id = 1 ORDER BY p.code'); while(`$r = `$stmt->fetch()) { echo `$r['code'] . PHP_EOL; }"
```

### Compter vos permissions
```bash
cd c:\xampp\htdocs\kms_app
C:\xampp\php\php.exe -r "require 'db/db.php'; `$stmt = `$pdo->query('SELECT COUNT(*) as total FROM role_permission WHERE role_id = 1'); `$r = `$stmt->fetch(); echo 'Total permissions ADMIN: ' . `$r['total'] . PHP_EOL;"
```

### Voir tous les rôles et leurs permissions
```bash
cd c:\xampp\htdocs\kms_app
C:\xampp\php\php.exe -r "require 'db/db.php'; `$stmt = `$pdo->query('SELECT r.nom, COUNT(rp.permission_id) as nb_perms FROM roles r LEFT JOIN role_permission rp ON r.id = rp.role_id GROUP BY r.id ORDER BY nb_perms DESC'); while(`$r = `$stmt->fetch()) { printf('%-25s : %2d permissions\n', `$r['nom'], `$r['nb_perms']); }"
```

---

## ✅ Checklist Post-Configuration

- [x] Script `grant_all_perms_admin.php` exécuté avec succès
- [x] 22 permissions attribuées au rôle ADMIN
- [ ] **Déconnexion effectuée**
- [ ] **Reconnexion effectuée**
- [ ] Dashboard accessible
- [ ] Module Magasinier accessible
- [ ] Module Comptabilité accessible
- [ ] Tous les autres modules accessibles

---

## 📞 En Cas de Problème

Si vous rencontrez toujours des problèmes après avoir suivi ce guide :

1. **Vérifier les logs** : Regardez `c:\xampp\apache\logs\error.log`
2. **Vérifier la BD** : Ouvrir phpMyAdmin → Vérifier tables `roles`, `permissions`, `role_permission`, `utilisateur_role`
3. **Réexécuter** : `grant_all_perms_admin.php`
4. **Session propre** : Fermer complètement le navigateur avant de reconnecter

---

**Date de configuration** : 2025-12-11  
**Rôle configuré** : ADMIN (ID: 1)  
**Permissions totales** : 22  
**Statut** : ✅ ACTIF

**IMPORTANT** : N'oubliez pas de vous **RECONNECTER** ! 🔄
