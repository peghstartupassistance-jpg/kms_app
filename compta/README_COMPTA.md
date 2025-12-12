# 📊 Module Comptabilité - Guide Complet

## 🎯 Vue d'ensemble

Le module comptabilité de **KMS Gestion** fournit une gestion complète de la comptabilité générale basée sur les principes de la **partie double (OHADA)**.

### Fonctionnalités principales

✅ **Exercices comptables** → Créer, activer, clôturer exercices  
✅ **Plan comptable** → CRUD comptes avec classes 1-8  
✅ **Journaux comptables** → VE (Ventes), AC (Achats), TR (Trésorerie), OD (Opérations Diverses), PA (Paie)  
✅ **Écritures automatiques** → Génération auto depuis ventes/achats  
✅ **Validation des pièces** → Validation individuelle ou en masse  
✅ **Grand Livre** → Détail compte par période  
✅ **Balance** → Balance générale par classe/compte  
✅ **États financiers** → Bilan, Compte de résultat (à completer)  

---

## 📁 Architecture du Module

```
compta/
├── index.php                    # Dashboard comptabilité
├── exercices.php                # CRUD exercices
├── plan_comptable.php           # CRUD plan comptable
├── journaux.php                 # Consultation journaux + pièces
├── valider_piece.php            # Validation pièces (1 ou masse)
├── grand_livre.php              # Détail compte par période
├── balance.php                  # Balance générale/auxiliaires
├── parametrage_mappings.php     # Config comptes auto
├── README.md                    # Ce fichier
└── ...
```

---

## 🔄 Flux Comptable

### 1. **Création Automatique depuis Ventes**

```
Vente créée → lib/compta.php:compta_creer_ecritures_vente()
   ↓
Lecture mapping : VENTE/VENTE_PRODUITS
   ↓
411 (Clients)  ← DÉBIT
707 (Ventes)   ← CRÉDIT
   ↓
Pièce en BROUILLON (est_validee = 0)
   ↓
[Bouton Valider dans compta/valider_piece.php]
```

### 2. **Création Automatique depuis Achats**

```
Achat créé → lib/compta.php:compta_creer_ecritures_achat()
   ↓
Lecture mapping : ACHAT/ACHAT_STOCK
   ↓
607 (Achats)   ← DÉBIT
401 (Fournis)  ← CRÉDIT
   ↓
Pièce en BROUILLON
   ↓
[Valider]
```

### 3. **Validation Pièces**

```
compta/valider_piece.php
   ↓
Sélectionner pièces à valider (UI checkboxes)
   ↓
Vérifier équilibre (Débit = Crédit)
   ↓
Si OK → est_validee = 1
Si KO → message d'erreur
```

---

## 📋 Tables Principales

### `compta_exercices`
Gestion des années comptables :
- `id` → PK
- `annee` → Année (2024, 2025, etc.)
- `date_ouverture` → Ouverture
- `date_cloture` → Clôture (NULL si ouvert)
- `est_clos` → Booléen (0 = ouvert, 1 = clôturé)
- `est_actif` → Exercice courant (1 seul actif à la fois)

### `compta_comptes`
Plan comptable OHADA :
- `id` → PK
- `numero_compte` → Ex: "411", "707", "401"
- `libelle` → Ex: "Clients", "Ventes", "Fournisseurs"
- `classe` → "1" à "8"
- `type_compte` → ACTIF / PASSIF / CHARGE / PRODUIT
- `nature` → CREANCE / DETTE / STOCK / VENTE / CHARGE_VARIABLE / CHARGE_FIXE / AUTRE
- `est_actif` → 1 = actif, 0 = inactif
- `est_analytique` → 1 = analytique (sous-comptes)

### `compta_journaux`
Types de journaux :
- `id` → PK
- `code` → "VE", "AC", "TR", "OD", "PA"
- `libelle` → "Ventes", "Achats", "Trésorerie", etc.
- `type` → VENTE / ACHAT / TRESORERIE / OPERATION_DIVERSE / PAIE

### `compta_pieces`
Pièces comptables (factures, opérations) :
- `id` → PK
- `exercice_id` → FK exercices
- `journal_id` → FK journaux
- `numero_piece` → VE-2025-00001, AC-2025-00001, etc.
- `date_piece` → Date de la pièce
- `reference_type` → VENTE / ACHAT / CAISSE
- `reference_id` → Lien vers vente/achat/opération caisse
- `tiers_client_id` / `tiers_fournisseur_id` → FK clients/fournisseurs
- `est_validee` → 0 = brouillon, 1 = validée
- `observations` → Notes

### `compta_ecritures`
Lignes d'écritures (partie double) :
- `id` → PK
- `piece_id` → FK compta_pieces
- `compte_id` → FK compta_comptes
- `libelle_ecriture` → Ex: "Facture vente V-20251118-114131"
- `debit` → Montant débit
- `credit` → Montant crédit
- `tiers_client_id` / `tiers_fournisseur_id` → Analytique tiers
- `ordre_ligne` → Position dans la pièce

### `compta_mapping_operations`
Configuration : Vente/Achat → Comptes comptables
- `id` → PK
- `source_type` → "VENTE" / "ACHAT" / "CAISSE"
- `code_operation` → "VENTE_PRODUITS" / "ACHAT_STOCK" / etc.
- `journal_id` → FK compta_journaux
- `compte_debit_id` → FK compta_comptes (compte débité)
- `compte_credit_id` → FK compta_comptes (compte crédité)
- `actif` → 1 = utiliser ce mapping, 0 = inactif

---

## 🔧 Configurtion des Mappings

### Page : `compta/parametrage_mappings.php`

Chaque "type d'opération" doit avoir un mapping :

| Source | Code Opération | Débit | Crédit |
|--------|---|---|---|
| **VENTE** | VENTE_PRODUITS | 411 (Clients) | 707 (Ventes) |
| **ACHAT** | ACHAT_STOCK | 607 (Achats) | 401 (Fournisseurs) |
| **CAISSE** | ENCAISSEMENT_VENTE | 531 (Caisse) | 411 (Clients) |

---

## 📊 Utilisation : Étape par Étape

### **1. Créer un Exercice**

```
Menu → Comptabilité → Exercices
   ↓
Bouton "Nouvel Exercice"
   ↓
Année : 2025
Ouverture : 2025-01-01
   ↓
[Créer]
   ↓
L'exercice apparaît comme "Inactif"
   ↓
Cliquer [Activer] → devient l'exercice courant
```

### **2. Enrichir le Plan Comptable**

```
Menu → Comptabilité → Plan Comptable
   ↓
[Nouveau Compte]
   ↓
Numéro : 401
Libellé : Fournisseurs
Classe : 4
Type : PASSIF
Nature : DETTE
   ↓
[Créer]
```

### **3. Configurer Mappings**

```
Menu → Comptabilité → Paramétrages → Mappings
   ↓
Vérifier que chaque source/opération a un mapping
   (VENTE/VENTE_PRODUITS → 411 ↔ 707)
   (ACHAT/ACHAT_STOCK → 607 ↔ 401)
   ↓
Si manquant : créer le mapping
```

### **4. Créer une Vente**

```
Menu → Ventes → Nouvelle Vente
   ↓
Remplir : client, produits, montants
   ↓
[Enregistrer]
   ↓
Automatiquement :
   - Pièce VE-2025-00001 créée (brouillon)
   - Écritures générées :
     * Débit 411 (Client) : montant
     * Crédit 707 (Ventes) : montant
```

### **5. Valider Pièces**

```
Menu → Comptabilité → Validation
   ↓
Filtre : Exercice, Journal, Statut "À valider"
   ↓
Sélectionner pièces (checkbox)
   ↓
Vérifier équilibre (Débit = Crédit) → ✓ OK
   ↓
[Valider la sélection]
   ↓
Pièces → est_validee = 1
```

### **6. Consulter Grand Livre**

```
Menu → Comptabilité → Grand Livre
   ↓
Sélectionner : Exercice, Compte (ex: 411 Clients)
   ↓
Optionnel : Période (du / au)
   ↓
Affiche :
   - Toutes écritures du compte
   - Solde progressif
   - Solde final
```

### **7. Consulter Balance**

```
Menu → Comptabilité → Balance
   ↓
Filtre : Exercice, Classe (optionnel)
   ↓
Affiche :
   - Liste de tous les comptes
   - Montants débit/crédit/solde par compte
   - Totaux généraux
   - Vérification équilibre (Débit total = Crédit total)
```

---

## 🛠️ Opérations Avancées

### **Corriger une Pièce (avant validation)**

Actuellement : Supprimer la pièce + régénérer

Amélioration future : Édition directe des écritures en brouillon

### **Annuler une Pièce (après validation)**

Actuellement : Créer pièce inverse (écritures opposées)

Fonction PHP :
```php
compta_annuler_piece($pdo, $piece_id);
```

### **Clôturer un Exercice**

```
Menu → Comptabilité → Exercices
   ↓
Sélectionner exercice à clôturer
   ↓
[Clôturer]
   ↓
Vérifications :
   - Toutes pièces validées ?
   - Balance équilibrée ?
   ↓
Exercice bloqué (est_clos = 1)
```

---

## 📈 États Financiers

### **Bilan (État de Position Financière)**

**À implémenter** dans `compta/reporting_bilan.php`

Calcul :
- **ACTIF** = Classes 1, 2, 3, 5
- **PASSIF** = Classes 4 (Dettes)
- **CAPITAUX** = Classe 4 (Capitaux) + Résultats

### **Compte de Résultat (P&L)**

**À implémenter** dans `compta/reporting_compte_resultat.php`

Calcul :
- **PRODUITS** = Classe 7
- **CHARGES** = Classe 6
- **RÉSULTAT** = Produits - Charges

---

## 🐛 Dépannage

### "Pièce n'est pas équilibrée"

→ Vérifier que **Débit = Crédit**  
→ Vérifier les mappings dans `parametrage_mappings.php`

### "Compte non trouvé"

→ Vérifier que le compte existe dans `compta_comptes`  
→ Vérifier qu'il est actif (`est_actif = 1`)

### "Exercice clôturé"

→ Créer/utiliser un autre exercice ouvert  
→ Ou rouvrir l'exercice (non recommandé)

---

## 📚 Références

- **OHADA** : Plan comptable harmonisé
- **PDO** : Requêtes préparées (sécurité)
- **Bootstrap 5** : Interface responsive
- **lib/compta.php** : Fonctions métier (418 lignes)

---

**Besoin d'aide ?** Consultez les fichiers sources ou contactez le support technique.
