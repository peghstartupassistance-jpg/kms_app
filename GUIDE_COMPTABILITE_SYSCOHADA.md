# 📘 GUIDE COMPLET : Module Comptabilité SYSCOHADA (Style Sage)

## 🎯 OBJECTIF

Transformer le module comptabilité de KMS Gestion pour fonctionner comme **Sage Comptabilité** avec le plan comptable **SYSCOHADA** (Système Comptable OHADA).

---

## ⚙️ INSTALLATION DU PLAN COMPTABLE

### Étape 1 : Importer le plan SYSCOHADA

```bash
# Dans phpMyAdmin ou en ligne de commande MySQL
mysql -u root -p kms_gestion < C:\xampp\htdocs\kms_app\db\import_plan_syscohada.sql
```

OU via phpMyAdmin :
1. Ouvrir phpMyAdmin → Base `kms_gestion`
2. Onglet **SQL**
3. Coller le contenu du fichier `db/import_plan_syscohada.sql`
4. Cliquer sur **Exécuter**

✅ **Résultat attendu** : ~100 comptes importés avec la structure SYSCOHADA complète (classes 1 à 9)

---

## 📊 STRUCTURE DU PLAN SYSCOHADA

### **Classe 1 : Comptes de ressources durables (Capitaux)**
- **10** : Capital
- **11** : Réserves
- **12** : Report à nouveau
- **13** : Résultat net de l'exercice
- **14** : Subventions d'investissement
- **16** : Emprunts et dettes assimilées
- **18** : Dettes liées à des participations

### **Classe 2 : Actif immobilisé**
- **21** : Immobilisations incorporelles
- **22** : Terrains
- **23** : Bâtiments, installations techniques
- **24** : Matériel, mobilier et actifs biologiques
- **26** : Titres de participation
- **28** : Amortissements
- **29** : Provisions pour dépréciation des immobilisations

### **Classe 3 : Comptes de stocks**
- **31** : Marchandises
- **32** : Matières premières et fournitures liées
- **33** : Autres approvisionnements
- **34** : Produits en cours
- **36** : Produits finis
- **39** : Dépréciations des stocks

### **Classe 4 : Comptes de tiers**
- **40** : Fournisseurs et comptes rattachés
  - **401** : Fournisseurs - dettes en compte
- **41** : Clients et comptes rattachés
  - **411** : Clients
- **42** : Personnel
  - **421** : Rémunérations dues
- **43** : Organismes sociaux
  - **431** : Sécurité sociale
- **44** : État et collectivités publiques
  - **441** : Impôts sur les bénéfices
  - **443** : TVA facturée (collectée)
  - **445** : TVA récupérable (déductible)
- **47** : Débiteurs et créditeurs divers

### **Classe 5 : Comptes de trésorerie**
- **50** : Titres de placement
- **52** : Banques, établissements financiers
  - **521** : Banques locales
- **57** : Caisse
  - **571** : Caisse siège social

### **Classe 6 : Comptes de charges**
- **60** : Achats et variations de stocks
  - **601** : Achats de marchandises
  - **607** : Achats de marchandises (détaillé)
- **61** : Transports
- **62** : Services extérieurs A
  - **622** : Locations
  - **624** : Entretien et réparations
- **63** : Autres services extérieurs B
  - **631** : Frais bancaires
- **64** : Impôts et taxes
- **66** : Charges de personnel
  - **661** : Salaires et appointements
  - **664** : Charges sociales
- **67** : Frais financiers

### **Classe 7 : Comptes de produits**
- **70** : Ventes
  - **701** : Ventes de produits finis
  - **706** : Prestations de services
  - **707** : Ventes de marchandises
- **77** : Revenus financiers

### **Classe 8 : Autres charges et produits**
- **81** : Valeurs comptables des cessions
- **82** : Produits des cessions
- **83-84** : Charges/Produits hors activités ordinaires

### **Classe 9 : Comptabilité analytique**
- Comptes de gestion interne

---

## 🖥️ UTILISATION : SAISIE MODE SAGE

### Accès
**Menu** : Comptabilité → **Saisie (mode Sage)**  
**URL** : `http://localhost/kms_app/compta/saisie_ecritures.php`

### Interface

L'écran ressemble à Sage avec :
- **En-tête** : Sélection journal, date, libellé général
- **Tableau de saisie** : Lignes d'écritures (Compte / Libellé / Débit / Crédit)
- **Totaux automatiques** : Affichage temps réel
- **Vérification équilibre** : Débit = Crédit obligatoire

---

## 📝 EXEMPLE PRATIQUE 1 : Vente de marchandises

### Contexte
Vente de 500 000 FCFA HT à un client (TVA 19.25%)

### Calculs
- **Montant HT** : 500 000 FCFA
- **TVA (19.25%)** : 96 250 FCFA
- **Montant TTC** : 596 250 FCFA

### Saisie dans Sage-KMS

1. **Journal** : VE (Ventes)
2. **Date** : 11/12/2025
3. **Libellé** : Vente marchandises client ABC

| N° | Compte | Libellé | Débit | Crédit |
|----|--------|---------|-------|--------|
| 1 | 411 - Clients | Vente client ABC | 596 250 | 0 |
| 2 | 707 - Ventes de marchandises | Vente HT | 0 | 500 000 |
| 3 | 443 - TVA facturée | TVA collectée 19.25% | 0 | 96 250 |

**Totaux** :
- Débit : 596 250 FCFA
- Crédit : 596 250 FCFA
- ✅ **Équilibré**

4. Cliquer sur **Enregistrer et valider**

---

## 📝 EXEMPLE PRATIQUE 2 : Achat de marchandises

### Contexte
Achat 300 000 FCFA HT auprès d'un fournisseur

### Calculs
- **Montant HT** : 300 000 FCFA
- **TVA récupérable** : 57 750 FCFA
- **Montant TTC** : 357 750 FCFA

### Saisie

1. **Journal** : AC (Achats)
2. **Date** : 11/12/2025
3. **Libellé** : Achat marchandises fournisseur XYZ

| N° | Compte | Libellé | Débit | Crédit |
|----|--------|---------|-------|--------|
| 1 | 607 - Achats de marchandises | Achat HT | 300 000 | 0 |
| 2 | 445 - TVA récupérable | TVA déductible | 57 750 | 0 |
| 3 | 401 - Fournisseurs | Dette fournisseur XYZ | 0 | 357 750 |

**Totaux** :
- Débit : 357 750 FCFA
- Crédit : 357 750 FCFA
- ✅ **Équilibré**

---

## 📝 EXEMPLE PRATIQUE 3 : Encaissement client

### Contexte
Encaissement en espèces de 596 250 FCFA (règlement vente précédente)

### Saisie

1. **Journal** : CA (Caisse)
2. **Date** : 12/12/2025
3. **Libellé** : Encaissement client ABC

| N° | Compte | Libellé | Débit | Crédit |
|----|--------|---------|-------|--------|
| 1 | 571 - Caisse | Espèces reçues | 596 250 | 0 |
| 2 | 411 - Clients | Règlement client ABC | 0 | 596 250 |

---

## 📝 EXEMPLE PRATIQUE 4 : Paiement fournisseur

### Contexte
Paiement par chèque de 357 750 FCFA au fournisseur

### Saisie

1. **Journal** : BQ (Banque)
2. **Date** : 13/12/2025
3. **Libellé** : Paiement fournisseur XYZ

| N° | Compte | Libellé | Débit | Crédit |
|----|--------|---------|-------|--------|
| 1 | 401 - Fournisseurs | Règlement dette | 357 750 | 0 |
| 2 | 521 - Banques locales | Chèque n° 123456 | 0 | 357 750 |

---

## 📝 EXEMPLE PRATIQUE 5 : Salaires du personnel

### Contexte
Paiement salaires 1 500 000 FCFA + charges sociales 300 000 FCFA

### Saisie

1. **Journal** : OD (Opérations Diverses)
2. **Date** : 30/12/2025
3. **Libellé** : Salaires décembre 2025

| N° | Compte | Libellé | Débit | Crédit |
|----|--------|---------|-------|--------|
| 1 | 661 - Salaires | Salaires bruts | 1 500 000 | 0 |
| 2 | 664 - Charges sociales | Cotisations patronales | 300 000 | 0 |
| 3 | 421 - Rémunérations dues | Salaires nets à payer | 0 | 1 500 000 |
| 4 | 431 - Sécurité sociale | Charges sociales dues | 0 | 300 000 |

---

## ✅ VÉRIFICATION

Après saisie, vérifier dans :

### **1. Grand Livre**
Menu : Comptabilité → Grand livre  
Sélectionner un compte (ex: 411 Clients)  
Voir toutes les écritures du compte

### **2. Balance**
Menu : Comptabilité → Balance & Bilan  
Vérifier :
- Total Débit = Total Crédit
- Soldes de chaque compte

### **3. Bilan**
Menu : Comptabilité → Balance & Bilan (section bilan)  
Vérifier :
- Actif = Passif
- Résultat net cohérent

---

## 🔐 BONNES PRATIQUES

### ✅ À FAIRE
- Toujours vérifier l'équilibre Débit = Crédit
- Utiliser des libellés clairs et explicites
- Saisir chronologiquement (ordre de dates)
- Conserver les justificatifs (factures, reçus)
- Valider les pièces après vérification

### ❌ À ÉVITER
- Saisir des pièces non équilibrées
- Utiliser des comptes inexistants
- Modifier des pièces validées (créer contrepartie)
- Saisir en dehors de l'exercice actif

---

## 🆚 DIFFÉRENCES SAGE vs KMS

| Fonctionnalité | Sage | KMS Gestion |
|----------------|------|-------------|
| Plan comptable | SYSCOHADA | ✅ SYSCOHADA |
| Saisie au km | ✅ Oui | ✅ Oui (interface similaire) |
| Équilibrage auto | ✅ Oui | ✅ Oui (temps réel) |
| Grand livre | ✅ Oui | ✅ Oui |
| Balance | ✅ Oui | ✅ Oui |
| Bilan | ✅ Oui | ✅ Oui (OHADA) |
| Lettrage | ⚠️ Oui | ❌ Non (future) |
| Écritures types | ✅ Oui | ✅ Oui (via mappings) |
| Analytique | ✅ Oui | ⚠️ Classe 9 disponible |

---

## 🚀 WORKFLOW COMPLET

```
1. CONFIGURATION INITIALE
   └─> Créer exercice 2025
   └─> Importer plan SYSCOHADA
   └─> Créer journaux (VE, AC, CA, BQ, OD)

2. SAISIE QUOTIDIENNE
   └─> Saisie mode Sage
   └─> Ventes / Achats / Caisse
   └─> Vérification équilibre

3. CONTRÔLES PÉRIODIQUES
   └─> Consulter Grand Livre
   └─> Éditer Balance
   └─> Vérifier équilibre

4. CLÔTURE MENSUELLE
   └─> Valider toutes les pièces
   └─> Éditer Bilan
   └─> Exporter Balance Excel

5. CLÔTURE ANNUELLE
   └─> Générer Bilan final
   └─> Calculer résultat
   └─> Clôturer exercice
```

---

## 📞 SUPPORT

Pour toute question :
- Consulter `compta/README_COMPTA.md`
- Voir exemples dans `tests/test_compta.php`
- Documentation SYSCOHADA : [Site OHADA](http://www.ohada.com)

---

**✅ Module comptabilité SYSCOHADA (style Sage) opérationnel !**
