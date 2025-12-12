# ✅ CHECKLIST D'INSTALLATION - Module Comptabilité

## 🎯 Avant de commencer

Avant d'utiliser le module, assurez-vous que :

- [ ] PHP >= 7.4 installé
- [ ] MySQL/MariaDB actif
- [ ] Accès XAMPP fonctionnel
- [ ] Base de données `kms_gestion` créée

---

## 📦 Phase 1 : Installation (TERMINÉE)

### ✅ Fichiers créés

- [x] **lib/compta.php** (418 lignes)
  - Librairie core accounting
  - 15+ fonctions
  - Syntaxe validée ✓

- [x] **compta/index.php** (180 lignes)
  - Dashboard comptabilité
  - Syntaxe validée ✓

- [x] **compta/plan_comptable.php** (312 lignes)
  - CRUD plan comptable
  - Syntaxe validée ✓

- [x] **compta/journaux.php** (235 lignes)
  - Consultation journaux
  - Syntaxe validée ✓

- [x] **compta/grand_livre.php** (250 lignes)
  - Grand livre comptable
  - Syntaxe validée ✓

- [x] **compta/balance.php** (350 lignes)
  - Bilan + compte résultat
  - Syntaxe validée ✓

- [x] **compta/parametrage_mappings.php** (280 lignes)
  - Configuration mappings
  - Syntaxe validée ✓

- [x] **db/compta_schema_clean.sql** (155 lignes)
  - Schéma SQL complet
  - 13 requêtes

- [x] **setup_compta.php** (220 lignes)
  - Script migration
  - Exécution réussie ✓

### ✅ Modifications existantes

- [x] **partials/sidebar.php**
  - + Lien "Comptabilité"
  - Permission COMPTABILITE_LIRE

### ✅ Documentation

- [x] **compta/README.md**
  - Installation & configuration
  - Fonctions principales
  - Dépannage

- [x] **COMPTA_DEPLOYMENT_SUMMARY.md**
  - Résumé déploiement
  - État des tables
  - Prochaines étapes

- [x] **INDEX_COMPTA.md**
  - Inventaire complet
  - Statistiques
  - Points clés

---

## 💾 Phase 2 : Migration Base de Données (TERMINÉE)

### ✅ Exécution du script

```bash
# Commande exécutée :
php setup_compta.php

# Résultat :
✓ Succès : 13
✗ Erreurs : 0
📊 Tables comptables créées : 7
```

### ✅ Tables créées

```
✓ compta_comptes              8 comptes (classe 1-8)
✓ compta_ecritures            (prête pour écritures)
✓ compta_exercices            2024, 2025 (actifs)
✓ compta_journaux             VE, AC, TR, OD, PA
✓ compta_mapping_operations   (prête pour config)
✓ compta_operations_trace     (audit trail vide)
✓ compta_pieces               (pièces vides)
```

### ✅ Données initiales

```sql
-- Exercices
✓ 2024 (01/01/2024 - 31/12/2024)
✓ 2025 (01/01/2025 - 31/12/2025)

-- Journaux
✓ VE - Ventes
✓ AC - Achats
✓ TR - Trésorerie
✓ OD - Opérations Diverses
✓ PA - Paie

-- Comptes (classe 1-8)
✓ 1 - Immobilisations
✓ 2 - Stocks
✓ 3 - Tiers
✓ 4 - Capitaux
✓ 5 - Résultats
✓ 6 - Charges
✓ 7 - Produits
✓ 8 - Spéciaux
```

### ✅ Colonnes ajoutées à journal_caisse

```
✓ client_id         (INT UNSIGNED, FK clients)
✓ fournisseur_id    (INT UNSIGNED, FK fournisseurs)
```

---

## 🌐 Phase 3 : Vérification Web (À faire)

### Avant de tester

- [ ] XAMPP/Apache démarré
- [ ] MySQL/MariaDB démarré
- [ ] Navigateur ouvert

### Tests d'accès

**URL** : http://localhost/kms_app/

1. **Dashboard comptabilité**
   - [ ] Aller à : `compta/`
   - [ ] Voir statistiques (comptes, journaux, pièces, écritures)
   - [ ] Voir menu de navigation

2. **Plan comptable**
   - [ ] Aller à : `compta/plan_comptable.php`
   - [ ] Voir 8 classes avec comptes
   - [ ] Tester CRUD (Créer/Éditer/Supprimer un compte)

3. **Journaux**
   - [ ] Aller à : `compta/journaux.php`
   - [ ] Voir 5 journaux (VE, AC, TR, OD, PA)
   - [ ] Cliquer sur "Consulter" → Voir liste pièces (vide pour l'instant)

4. **Grand livre**
   - [ ] Aller à : `compta/grand_livre.php`
   - [ ] Voir 8 classes avec comptes
   - [ ] Cliquer sur un compte → Voir mouvements (vide pour l'instant)

5. **Bilan**
   - [ ] Aller à : `compta/balance.php`
   - [ ] Voir Actif/Passif (zéro pour l'instant)
   - [ ] Voir Compte de résultat (zéro)
   - [ ] Voir vérification d'équilibre ✓

6. **Mappings**
   - [ ] Aller à : `compta/parametrage_mappings.php`
   - [ ] Voir liste mappings (vide)
   - [ ] Tester CRUD sur mappings

7. **Vérification rapide**
   - [ ] Aller à : `compta_check.php`
   - [ ] Voir status système ✓
   - [ ] Voir liens de navigation

---

## 🔧 Phase 4 : Configuration (À faire)

### Configuration des mappings

**Accés** : http://localhost/kms_app/compta/parametrage_mappings.php

1. **Créer mapping VENTE**
   - [ ] Type d'opération : `VENTE`
   - [ ] Code opération : `VENTE_PRODUITS`
   - [ ] Journal : `VE`
   - [ ] Compte Débit : `3` (Tiers)
   - [ ] Compte Crédit : `7` (Produits)

2. **Créer mapping ACHAT**
   - [ ] Type d'opération : `ACHAT`
   - [ ] Code opération : `ACHAT_PRODUITS`
   - [ ] Journal : `AC`
   - [ ] Compte Débit : `2` (Stocks)
   - [ ] Compte Crédit : `3` (Tiers)

3. **Créer mapping CAISSE**
   - [ ] Type d'opération : `CAISSE`
   - [ ] Code opération : `CAISSE_VENTE`
   - [ ] Journal : `TR`
   - [ ] Compte Débit : `4` (Capitaux - Banque)
   - [ ] Compte Crédit : `3` (Tiers - Ventes)

---

## 🔗 Phase 5 : Intégration (À faire)

### Intégrer dans ventes (ventes/edit.php)

```php
require_once __DIR__ . '/../lib/compta.php';

// Lors de la validation d'une vente
if (compta_creer_ecritures_vente($pdo, $vente_id)) {
    echo "✓ Écritures générées";
} else {
    echo "✗ Erreur génération";
}
```

### Intégrer dans achats (achats/edit.php)

```php
require_once __DIR__ . '/../lib/compta.php';

// Lors de la validation d'un achat
if (compta_creer_ecritures_achat($pdo, $achat_id)) {
    echo "✓ Écritures générées";
}
```

### Intégrer dans caisse (caisse/journal.php)

```php
require_once __DIR__ . '/../lib/compta.php';

// Lors de l'enregistrement dans caisse
if (compta_creer_ecritures_caisse($pdo, $journal_caisse_id)) {
    echo "✓ Écritures générées";
}
```

---

## 🧪 Phase 6 : Tests fonctionnels (À faire)

### Test 1 : Créer une vente

- [ ] Créer une vente dans ventes/list.php
- [ ] Valider la vente
- [ ] Vérifier dans compta/journaux.php → Journal VE
- [ ] Voir pièce créée avec écritures

### Test 2 : Créer un achat

- [ ] Créer un achat dans achats/list.php
- [ ] Valider l'achat
- [ ] Vérifier dans compta/journaux.php → Journal AC
- [ ] Voir pièce créée avec écritures

### Test 3 : Caisse

- [ ] Créer une entrée caisse
- [ ] Voir écritures dans compta/journaux.php → Journal TR

### Test 4 : Bilan

- [ ] Aller à compta/balance.php
- [ ] Vérifier Actif = Passif + Résultat
- [ ] Voir badge ✓ Équilibré

### Test 5 : Grand livre

- [ ] Aller à compta/grand_livre.php
- [ ] Cliquer sur compte avec mouvements
- [ ] Vérifier solde courant

---

## 🎯 Critères de succès

- [x] **Phase 1** : 100% complété
- [x] **Phase 2** : Migration réussie (13/13)
- [ ] **Phase 3** : Toutes interfaces accessibles
- [ ] **Phase 4** : Mappings configurés
- [ ] **Phase 5** : Intégrations en place
- [ ] **Phase 6** : Tests fonctionnels passés

---

## 📞 Dépannage rapide

### "Erreur 404 sur /compta/"
→ Vérifier que le dossier `compta/` existe et contient `index.php`

### "Erreur table compta_comptes n'existe pas"
→ Exécuter : `php setup_compta.php`

### "Aucun compte visible dans plan comptable"
→ Vérifier que l'INSERT des comptes a fonctionné : SELECT * FROM compta_comptes;

### "Écritures n'apparaissent pas"
→ Vérifier que le mapping existe pour cette opération
→ Consulter error_log pour erreurs

### "Bilan déséquilibré"
→ Vérifier que debit = credit pour chaque écriture
→ Vérifier que ALL les écritures sont créées

---

## 📚 Ressources

| Ressource | Lien |
|-----------|------|
| Documentation | `compta/README.md` |
| Résumé déploiement | `COMPTA_DEPLOYMENT_SUMMARY.md` |
| Inventaire | `INDEX_COMPTA.md` |
| Vérification | `http://localhost/kms_app/compta_check.php` |

---

**Status** : ✅ READY FOR TESTING

**Créé** : 2024
**Version** : 1.0

**À faire ensuite** :
1. Vérifier accès web (Phase 3)
2. Configurer mappings (Phase 4)
3. Intégrer dans modules (Phase 5)
4. Tests fonctionnels (Phase 6)

---

Bonne chance ! 🚀
