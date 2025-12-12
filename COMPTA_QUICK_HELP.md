# 🆘 AIDE RAPIDE - Module Comptabilité

## ❓ Questions fréquentes

### Q: Comment accéder au module comptabilité ?
**R:** Trois façons :
1. **Via le menu sidebar** → Cliquer sur "Comptabilité"
2. **URL directe** → http://localhost/kms_app/compta/
3. **Vérification** → http://localhost/kms_app/compta_check.php

---

### Q: Où ajouter un nouveau compte comptable ?
**R:** 
1. Aller à `/compta/plan_comptable.php`
2. Cliquer sur "Créer un nouveau compte"
3. Remplir le formulaire (numéro, libellé, classe, nature)
4. Cliquer "Créer"

---

### Q: Comment configurer les mappings automatiques ?
**R:**
1. Aller à `/compta/parametrage_mappings.php`
2. Remplir le formulaire :
   - Type d'opération (VENTE, ACHAT, CAISSE, etc.)
   - Code opération (ex: VENTE_PRODUITS)
   - Journal (VE, AC, TR, OD, PA)
   - Compte Débit (ex: 411)
   - Compte Crédit (ex: 707)
3. Cliquer "Créer"

---

### Q: Où voir les écritures générées ?
**R:**
1. Aller à `/compta/journaux.php`
2. Cliquer sur un journal (VE, AC, TR, etc.)
3. Cliquer sur "Consulter" → Voir pièces
4. Cliquer sur une pièce → Voir écritures détaillées

---

### Q: Comment vérifier le bilan ?
**R:**
1. Aller à `/compta/balance.php`
2. Voir Actif (classes 1-2) vs Passif (classes 3-4)
3. Voir Compte de résultat (charges vs produits)
4. Vérifier badge "✓ Équilibré" en bas

---

### Q: Où voir tous les mouvements d'un compte ?
**R:**
1. Aller à `/compta/grand_livre.php`
2. Cliquer sur une classe de compte (1-8)
3. Cliquer sur un compte
4. Voir tous les mouvements avec solde courant

---

## 🔧 Configuration essentiels

### Configuration minimale requise

**1. Configurer les mappings** (obligatoire pour auto-génération)

Exemple VENTE :
```
Type d'opération: VENTE
Code opération: VENTE_PRODUITS
Journal: VE
Compte Débit: 411 (Clients)
Compte Crédit: 707 (Ventes)
```

Exemple ACHAT :
```
Type d'opération: ACHAT
Code opération: ACHAT_PRODUITS
Journal: AC
Compte Débit: 60 (Achats)
Compte Crédit: 401 (Fournisseurs)
```

**2. Intégrer dans ventes/achats** (optionnel mais recommandé)

Ajouter après validation d'une vente :
```php
require_once __DIR__ . '/../lib/compta.php';
compta_creer_ecritures_vente($pdo, $vente_id);
```

---

## 🐛 Dépannage

### "J'accède à /compta/ mais voir une erreur 404"
1. Vérifier que le dossier `compta/` existe
2. Vérifier que le fichier `compta/index.php` existe
3. Vérifier les permissions du répertoire

### "Les comptes n'apparaissent pas dans le plan comptable"
1. Aller à `/compta_check.php`
2. Vérifier que "Comptes actifs" > 0
3. Si 0 : Re-exécuter `php setup_compta.php`

### "Les écritures ne s'affichent pas après une vente"
1. Vérifier que le mapping VENTE est configuré
2. Vérifier que l'appel `compta_creer_ecritures_vente()` existe dans ventes/edit.php
3. Vérifier error_log pour erreurs
4. Vérifier dans compta/journaux.php → Journal VE

### "Le bilan n'est pas équilibré"
Causes possibles :
1. Écritures incomplètes (vérifie que debit = credit)
2. Vérifier que TOUS les mappings sont configurés
3. Vérifier error_log pour erreurs de création

### "Erreur de permission : COMPTABILITE_LIRE non trouvée"
Cela peut arriver si vous avez un système de permissions :
1. Ajouter la permission dans votre système utilisateurs
2. Ou modifier le code pour ne pas vérifier la permission

### "Les colonnes client_id/fournisseur_id existent déjà dans journal_caisse"
C'est normal ! Le script de migration a une gestion d'erreur pour ça.
Rien à faire, continuer.

---

## 📞 Informations techniques

### Structure table compta_journaux
```sql
id               INT PRIMARY KEY
code             VARCHAR(10)  -- VE, AC, TR, OD, PA
libelle          VARCHAR(100)
type             ENUM (VENTE, ACHAT, TRESORERIE, ...)
observations     TEXT
```

### Structure table compta_comptes
```sql
id               INT PRIMARY KEY
numero_compte    VARCHAR(20)  -- 411, 707, etc.
libelle          VARCHAR(150)
classe           CHAR(1)      -- 1-8
nature           ENUM (CREANCE, DETTE, STOCK, ...)
est_actif        TINYINT(1)
```

### Structure table compta_pieces
```sql
id               INT PRIMARY KEY
numero_piece     VARCHAR(50)  -- VE-2024-001
date_piece       DATE
journal_id       INT FK
exercice_id      INT FK
reference_type   VARCHAR(50)  -- VENTE, ACHAT, CAISSE
reference_id     INT          -- ID de la vente/achat
tiers_client_id  INT FK
```

### Structure table compta_ecritures
```sql
id               INT PRIMARY KEY
piece_id         INT FK
compte_id        INT FK
libelle_ecriture VARCHAR(200)
debit            DECIMAL(15,2)
credit           DECIMAL(15,2)
tiers_client_id  INT FK
```

---

## 💡 Trucs & astuces

### Tric 1: Exporter les données comptables
```
→ Accéder à /compta/grand_livre.php
→ Cliquer droit → Imprimer
→ Imprimer au format PDF
```

### Tric 2: Retrouver d'où vient une écriture
```
→ Aller dans /compta/journaux.php
→ Cliquer sur la pièce
→ Voir "reference_type" et "reference_id"
→ Exemple: VENTE #1234 → Aller dans ventes/edit.php?id=1234
```

### Tric 3: Corriger une écriture
```
→ Créer une pièce inverse (débit/crédit inversés)
→ Ou éditer directement compta_ecritures (si expert)
```

### Tric 4: Générer des comptes rapidement
```
→ Via un script SQL :
INSERT INTO compta_comptes (numero_compte, libelle, classe, nature)
VALUES
  ('411', 'Clients', '3', 'CREANCE'),
  ('401', 'Fournisseurs', '3', 'DETTE'),
  ('707', 'Ventes', '7', 'VENTE');
```

---

## 📖 Lire la documentation

Consulter :
- `compta/README.md` - Installation détaillée
- `INDEX_COMPTA.md` - Inventaire complet
- `CHECKLIST_COMPTA.md` - Validation étape par étape

---

## ☎️ Besoin d'aide ?

1. **Vérification rapide** → http://localhost/kms_app/compta_check.php
2. **Documentation** → compta/README.md
3. **Error log** → /xampp/apache/logs/error.log
4. **PHP error log** → error_log (racine du projet)

---

**Vous êtes prêt à utiliser le module comptabilité !** 🚀

Bon courage ! 💪
