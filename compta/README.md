# Module Comptabilité - Documentation d'Installation

## 📋 Vue d'ensemble

Ce module ajoute une comptabilité complète au système KMS avec :
- **8 tables SQL** : exercices, journaux, comptes, pièces, écritures, mappings, trace
- **15+ fonctions PHP** : génération automatique d'écritures comptables
- **5 interfaces admin** : plan comptable, journaux, grand livre, bilan, paramétrage mappings

## 📁 Fichiers créés/modifiés

### Fichiers créés :
```
db/compta_schema.sql          (258 lignes) - Schéma complet avec CREATE/ALTER/INSERT
lib/compta.php                (418 lignes) - Librairie core avec 15+ fonctions
compta/index.php              (180 lignes) - Dashboard comptabilité
compta/plan_comptable.php     (312 lignes) - CRUD plan comptable
compta/journaux.php           (235 lignes) - Consultation journaux + pièces
compta/grand_livre.php        (250 lignes) - Affichage grand livre par compte
compta/balance.php            (350 lignes) - Bilan actif/passif + compte résultat
compta/parametrage_mappings.php (280 lignes) - Configuration des mappings auto
```

### Fichiers modifiés :
```
partials/sidebar.php          - Ajout du lien "Comptabilité" dans le menu
```

## 🚀 Étapes d'installation

### 1. Créer les tables SQL

```bash
cd db/
mysql -u root -p kms_gestion < compta_schema.sql
```

**OU via PhpMyAdmin :**
1. Aller dans `db/compta_schema.sql`
2. Copier tout le contenu
3. Aller dans PHPMyAdmin > base kms_gestion > onglet SQL
4. Coller et exécuter

**Attention :** Si vous avez d'autres colonnes dans `journal_caisse`, l'ALTER TABLE peut nécessiter un ajustement.

### 2. Vérifier la création des tables

```sql
SHOW TABLES LIKE 'compta_%';

-- Doit afficher :
-- compta_comptes
-- compta_ecritures
-- compta_exercices
-- compta_journaux
-- compta_mapping_operations
-- compta_operations_trace
-- compta_pieces
```

### 3. Vérifier les données initiales

```sql
-- Exercices
SELECT * FROM compta_exercices;
-- Doit avoir : 2024 (01/01/2024 - 31/12/2024), 2025 (01/01/2025 - 31/12/2025)

-- Journaux
SELECT * FROM compta_journaux;
-- Doit avoir : VE, AC, TR, OD, PA

-- Chart of accounts
SELECT COUNT(*) FROM compta_comptes;
-- Doit avoir 8 comptes de base (classe 1-8)
```

### 4. Configurer les permissions (optionnel)

Si vous utilisez un système de permissions, ajoutez :
```
COMPTABILITE_LIRE   - Pour lire la comptabilité
COMPTABILITE_ECRIRE - Pour modifier la comptabilité
```

## 📊 Structure de la comptabilité

### 8 Classes de comptes (OHADA)

| Classe | Nom | Type | Exemple |
|--------|-----|------|---------|
| 1 | Immobilisations | Actif | 11 Constructions, 12 Installations |
| 2 | Stocks | Actif | 20 Matières premières, 21 Produits finis |
| 3 | Tiers | Actif/Passif | 411 Clients, 401 Fournisseurs |
| 4 | Financier | Passif | 401 Capital, 512 Banques |
| 5 | Gestion | Résultat | 51 Résultats |
| 6 | Charges | Résultat | 60 Achats, 62 Services |
| 7 | Produits | Résultat | 70 Ventes, 71 Services |
| 8 | Spéciaux | Divers | 80 Comptes transitoires |

### 5 Journaux

| Code | Libellé | Type | Description |
|------|---------|------|-------------|
| VE | Ventes | Spécialisé | Écritures de vente |
| AC | Achats | Spécialisé | Écritures d'achat |
| TR | Trésorerie | Spécialisé | Paiements/encaissements |
| OD | Opérations Diverses | Divers | Autres opérations |
| PA | Paie | Spécialisé | Salaires et charges |

## 🔧 Fonctions principales de lib/compta.php

### Utilitaires
```php
compta_get_exercice_actif($pdo)           // Récupère l'exercice actif
compta_generer_numero_piece($pdo, ...)    // Génère numéro pièce (VE-2024-001)
```

### Génération d'écritures
```php
compta_creer_ecritures_vente($pdo, $vente_id)          // Crée écritures VENTE
compta_creer_ecritures_achat($pdo, $achat_id)          // Crée écritures ACHAT
compta_creer_ecritures_caisse($pdo, $journal_caisse_id) // Crée écritures CAISSE
```

### Consultation
```php
compta_get_grand_livre_compte($pdo, $compte_id)   // Mouvements d'un compte
compta_get_balance($pdo, $exercice_id)            // Soldes tous comptes
compta_get_pieces_journal($pdo, $journal_id)      // Pièces d'un journal
compta_get_ecritures_piece($pdo, $piece_id)       // Détail d'une pièce
```

## 📊 Interfaces disponibles

### Dashboard Comptabilité
- **URL** : `/compta/index.php`
- **Accès** : Permission `COMPTABILITE_LIRE`
- **Contenu** : Statistiques rapides + liens vers tous les modules

### Plan Comptable
- **URL** : `/compta/plan_comptable.php`
- **Actions** : Créer, éditer, supprimer comptes
- **Affichage** : Hiérarchie par classe (1-8)

### Journaux
- **URL** : `/compta/journaux.php`
- **Actions** : Lister journaux → Lister pièces → Détail pièce avec écritures
- **Affichage** : Nombre pièces à valider par journal

### Grand Livre
- **URL** : `/compta/grand_livre.php`
- **Actions** : Choisir compte → Afficher mouvements chronologiques
- **Affichage** : Solde courant + totaux débit/crédit

### Bilan & Résultat
- **URL** : `/compta/balance.php`
- **Actions** : Affichage auto du bilan complet
- **Contenu** : 
  - Bilan : Actif (classes 1-2) vs Passif (classes 3-4)
  - Compte de résultat : Charges (classe 6) vs Produits (classe 7)
  - Vérification d'équilibre

### Paramétrage Mappings
- **URL** : `/compta/parametrage_mappings.php`
- **Actions** : Créer, éditer, supprimer mappings d'auto-génération
- **Contenu** : Configuration des règles pour VENTE/ACHAT/CAISSE/INSCRIPTIONS/RESERVATIONS

## 🔗 Intégration dans les modules existants

### Ventes (ventes/edit.php)
À ajouter lors de la validation d'une vente :
```php
require_once __DIR__ . '/../lib/compta.php';
if (compta_creer_ecritures_vente($pdo, $vente_id)) {
    // Succès : écritures générées
} else {
    // Erreur : voir error_log
}
```

### Achats (achats/edit.php)
À ajouter lors de la validation d'un achat :
```php
require_once __DIR__ . '/../lib/compta.php';
if (compta_creer_ecritures_achat($pdo, $achat_id)) {
    // Succès
}
```

### Caisse (caisse/journal.php)
À ajouter lors de l'enregistrement d'une entrée caisse :
```php
require_once __DIR__ . '/../lib/compta.php';
if (compta_creer_ecritures_caisse($pdo, $journal_caisse_id)) {
    // Succès
}
```

## 📝 Tables créées

### compta_exercices
Exercices comptables (années fiscales)
```sql
- id, annee, date_ouverture, date_cloture, actif, created_at
```

### compta_journaux
Journaux comptables
```sql
- id, code (VE/AC/TR/OD/PA), libelle, type, created_at
```

### compta_comptes
Plan comptable avec 8 classes
```sql
- id, numero_compte, libelle, classe, nature, type_compte, 
  parent_id, actif, accepte_analytique, created_at
```

### compta_pieces
Pièces/documents comptables
```sql
- id, numero_piece, journal_id, date_piece, exercice_id,
  reference_type (VENTE/ACHAT/CAISSE), reference_id,
  tiers_client_id, tiers_fournisseur_id, observations,
  est_validee, created_at
```

### compta_ecritures
Lignes d'écritures (double-entrée)
```sql
- id, piece_id, compte_id, libelle_ecriture,
  debit, credit, tiers_client_id, tiers_fournisseur_id,
  created_at
```

### compta_mapping_operations
Configuration auto-génération
```sql
- id, source_type, code_operation, journal_id,
  compte_debit_id, compte_credit_id, description,
  actif, created_at
```

### compta_operations_trace
Audit trail des générations
```sql
- id, operation_id, source_type, piece_id,
  status (success/error/en_attente), messages,
  executed_at, created_at
```

## ✅ Points de contrôle

- [ ] Tables créées sans erreur
- [ ] Données initiales insérées (exercices, journaux, comptes)
- [ ] journal_caisse a les colonnes client_id et fournisseur_id
- [ ] Lien "Comptabilité" visible dans sidebar
- [ ] Index comptabilité accessible et affiche statistiques
- [ ] Plan comptable CRUD fonctionne
- [ ] Journaux → Pièces → Détail fonctionne
- [ ] Grand livre affiche mouvements et soldes
- [ ] Bilan affiche actif/passif/résultat équilibré
- [ ] Mappings CRUD fonctionne
- [ ] Appels compta_creer_ecritures_* intégrés dans ventes/achats/caisse

## 🐛 Dépannage

### "Erreur : table compta_pieces n'existe pas"
→ Exécuter `db/compta_schema.sql`

### "Écritures n'apparaissent pas dans journal"
→ Vérifier que mapping existe pour source_type/code_operation
→ Consulter `compta_operations_trace` pour erreurs

### "Bilan déséquilibré"
→ Vérifier que toutes les écritures sont créées (debit = credit)
→ Consulter logs pour erreurs de création

### "Permission COMPTABILITE_LIRE non reconnue"
→ Ajouter permission dans table `utilisateurs_permissions` si applicable

## 📞 Support

Vérifier l'`error_log` PHP pour les erreurs de création d'écritures.

---

**Module créé le** : 2024
**Version** : 1.0
**Auteur** : KMS Accounting Module
