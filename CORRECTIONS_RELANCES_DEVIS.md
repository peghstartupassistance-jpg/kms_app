# 🔧 CORRECTIONS - reporting/relances_devis.php

**Date** : 2025-12-11  
**Fichier** : `reporting/relances_devis.php`  
**Statut** : ✅ CORRIGÉ

---

## 🐛 Erreurs Détectées

### Erreur 1 : Column 'c.prenom' not found
```
Fatal error: SQLSTATE[42S22]: Column not found: 1054 Unknown column 'c.prenom' 
in 'field list' in reporting\relances_devis.php:12
```

**Cause** : La table `clients` n'a que la colonne `nom`, pas `prenom`

### Erreur 2 : Column 'u.nom' et 'u.prenom' not found
**Cause** : La table `utilisateurs` utilise `nom_complet` au lieu de `nom` et `prenom` séparés

### Erreur 3 : Column 'cv.nom' not found
**Cause** : La table `canaux_vente` utilise `libelle` au lieu de `nom`

### Erreur 4 : Column 'd.date_validite' not found
**Cause** : La table `devis` n'a pas de colonne `date_validite`, mais `date_relance`

---

## ✅ Corrections Appliquées

### 1. Requête SQL Principale (lignes 12-28)

**AVANT** :
```sql
SELECT 
    d.*,
    c.nom as client_nom,
    c.prenom as client_prenom,  -- ❌ N'existe pas
    c.telephone as client_telephone,
    c.email as client_email,
    u.nom as utilisateur_nom,  -- ❌ N'existe pas
    u.prenom as utilisateur_prenom,  -- ❌ N'existe pas
    cv.nom as canal_nom,  -- ❌ Mauvais nom
    DATEDIFF(d.date_validite, CURDATE()) as jours_restants,  -- ❌ N'existe pas
```

**APRÈS** :
```sql
SELECT 
    d.*,
    c.nom as client_nom,  -- ✅ OK
    c.telephone as client_telephone,
    c.email as client_email,
    u.nom_complet as utilisateur_nom,  -- ✅ Corrigé
    cv.libelle as canal_nom,  -- ✅ Corrigé
    DATEDIFF(d.date_relance, CURDATE()) as jours_restants,  -- ✅ Corrigé
```

### 2. Affichage Client dans Tableau (ligne 182)

**AVANT** :
```php
<?= htmlspecialchars($d['client_nom'] . ' ' . $d['client_prenom']) ?>
```

**APRÈS** :
```php
<?= htmlspecialchars($d['client_nom']) ?>
```

### 3. Data Attribute Modal (ligne 225)

**AVANT** :
```php
data-client-nom="<?= htmlspecialchars($d['client_nom'] . ' ' . $d['client_prenom']) ?>"
```

**APRÈS** :
```php
data-client-nom="<?= htmlspecialchars($d['client_nom']) ?>"
```

### 4. Condition WHERE (ligne 27)

**AVANT** :
```sql
WHERE d.statut IN ('ENVOYE', 'EN_COURS')
  AND (d.date_validite IS NULL OR d.date_validite >= CURDATE())
```

**APRÈS** :
```sql
WHERE d.statut IN ('ENVOYE', 'EN_COURS')
  AND (d.date_relance IS NULL OR d.date_relance >= CURDATE())
```

### 5. Affichage Date (ligne 200)

**AVANT** :
```php
<?php if ($d['date_validite']): ?>
    <?= date('d/m/Y', strtotime($d['date_validite'])) ?>
```

**APRÈS** :
```php
<?php if ($d['date_relance']): ?>
    <?= date('d/m/Y', strtotime($d['date_relance'])) ?>
```

---

## 🧪 Tests Effectués

### Test 1 : Syntaxe PHP
```bash
C:\xampp\php\php.exe -l reporting/relances_devis.php
```
**Résultat** : ✅ No syntax errors detected

### Test 2 : Requête SQL
```bash
C:\xampp\php\php.exe test_relances_devis.php
```
**Résultat** : ✅ Tous les tests réussis (4/4)

**Détails** :
- ✅ Requête principale : 0 devis en attente
- ✅ Calcul statistiques : OK
- ✅ Structure table relances_devis : 9 colonnes
- ✅ Compte relances existantes : 0

---

## 📊 Mapping Colonnes

| Table           | ❌ Ancienne Colonne      | ✅ Nouvelle Colonne     |
|-----------------|--------------------------|-------------------------|
| `clients`       | `prenom`                 | (supprimé - n'existe pas) |
| `utilisateurs`  | `nom` + `prenom`         | `nom_complet`           |
| `canaux_vente`  | `nom`                    | `libelle`               |
| `devis`         | `date_validite`          | `date_relance`          |

---

## 🎯 Fonctionnalité du Module

Le module `reporting/relances_devis.php` permet de :

1. **Lister tous les devis en attente** (statuts ENVOYE, EN_COURS)
2. **Afficher des KPIs** :
   - Total devis en attente
   - Devis urgents (≤ 3 jours avant date relance)
   - Devis sans relance
   - Devis relancés cette semaine

3. **Enregistrer une relance** (téléphone, email, rendez-vous)
4. **Planifier une prochaine action**

**Note** : Le module utilise `date_relance` pour gérer les échéances, pas de date de validité stricte.

---

## ✅ Checklist Déploiement

- [x] Erreur SQL `c.prenom` corrigée
- [x] Erreur SQL `u.nom/prenom` corrigée  
- [x] Erreur SQL `cv.nom` corrigée
- [x] Erreur SQL `d.date_validite` corrigée
- [x] Affichage client corrigé (3 endroits)
- [x] Test syntaxe PHP : OK
- [x] Test requête SQL : OK
- [x] Script de test créé : `test_relances_devis.php`

---

## 🔗 Fichiers Associés

- **Fichier principal** : `reporting/relances_devis.php` ✅
- **Script de test** : `test_relances_devis.php` ✅
- **Tables BD** :
  - `devis` (date_relance, statut)
  - `clients` (nom, telephone, email)
  - `utilisateurs` (nom_complet)
  - `canaux_vente` (libelle)
  - `relances_devis` (date_relance, type_relance)

---

**Module opérationnel** : ✅ http://localhost/kms_app/reporting/relances_devis.php

**Permissions requises** : `DEVIS_LIRE` (déjà attribuée au rôle ADMIN)
