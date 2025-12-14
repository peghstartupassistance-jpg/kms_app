# 🔧 WORKFLOW DE CORRECTION DE SYNCHRONISATION

## 📋 Vue d'ensemble

Lorsque des anomalies de synchronisation sont détectées (ventes, livraisons, stock ou comptabilité incohérents), l'utilisateur peut les corriger **facilement et automatiquement** via une interface guidée.

---

## 🎯 Workflow utilisateur

### Étape 1 : Identifier les problèmes
**Page** : `/coordination/verification_synchronisation.php`

- **Tableau** affichant toutes les ventes
- **Colonne Status** :
  - 🟢 **OK** = Tout est synchronisé
  - 🔴 **ERREUR** = Problème détecté

- **Actions disponibles** :
  - 📌 **Chevron (↓)** = Afficher les détails des problèmes
  - 🔧 **Clé (wrench)** = Lancer le workflow de correction (uniquement si ERREUR)
  - 👁️ **Œil** = Voir la vente complète

### Étape 2 : Corriger les anomalies
**Page** : `/coordination/corriger_synchronisation.php?vente_id=XX`

Interface de correction avec **4 actions principales** :

#### **Action 1: Aucun bon de livraison** 🚚
**Condition d'affichage** :
- Nombre de BL = 0

**Problème détecté** :
- La vente n'a pas encore de bon de livraison associé
- Les produits ne sont pas formally livrés

**Correction** :
- Créer automatiquement un BL basé sur les lignes de vente
- Le BL porte le numéro : `BL-AUTO-YYYYMMDD-XXXX`
- Marquer le BL comme signé (livraison effectuée)
- Les lignes du BL reprennent les quantités commandées

**Résultat** :
- ✅ BL créé et affiché
- ✅ Vente maintenant associée à une livraison

---

#### **Action 2: Sorties stock manquantes** 📦
**Condition d'affichage** :
- Quantité en sortie stock = 0
- ET Quantité commandée > 0

**Problème détecté** :
- Les produits ont été livrés mais les sorties de stock n'ont pas été enregistrées
- **Impact** : Stock comptable désynchronisé du stock réel

**Correction** :
- Pour chaque ligne de vente, créer un mouvement de stock de type `SORTIE`
- Réference : `source_type = 'VENTE'`, `source_id = vente_id`
- Commentaire : "Correction : Sortie vente V-XXXXXXX"
- Quantité : Quantité commandée pour chaque produit

**Résultat** :
- ✅ Mouvements de stock créés dans `stocks_mouvements`
- ✅ Stock actuel des produits décrémenté
- ✅ Historique complet des mouvements disponible

---

#### **Action 3: Écritures comptables manquantes** 📚
**Condition d'affichage** :
- Nombre d'écritures comptables = 0

**Problème détecté** :
- La vente a eu lieu mais n'a pas d'écritures comptables
- **Impact** : Balance comptable incohérente, pas de trace en comptabilité

**Correction** :
- Appel à `enregistrer_vente_double_entree($pdo, $venteId)` depuis `lib/compta.php`
- Crée automatiquement les écritures selon le système OHADA :
  - Débit : Compte client (411)
  - Crédit : Compte vente (701)
  - Crédit : Compte TVA (449) si applicable

**Résultat** :
- ✅ Écritures comptables créées
- ✅ Piece comptable générée avec référence VENTE
- ✅ Balance comptable équilibrée

---

#### **Action 4: Synchroniser les statuts** 🔄
**Disponible toujours**

**Fonction** :
- Recalcule le statut de la vente basé sur :
  - Total des quantités livrées (SUM des BL)
  - Total des quantités commandées
  - État du stock

**Logique** :
```
Si qte_livree >= qte_commandee ET qte_commandee > 0 :
  → Statut = LIVREE
Sinon si qte_livree > 0 :
  → Statut = PARTIELLEMENT_LIVREE
Sinon :
  → Statut = EN_ATTENTE_LIVRAISON
```

**Résultat** :
- ✅ Statut vente mis à jour correctement
- ✅ Cohérence avec le physique

---

## 🔐 Protections et sécurité

### Transactions
- **Chaque action** est exécutée en transaction
- En cas d'erreur → **ROLLBACK** automatique
- Aucun changement partiel possible

### Permissions
- Seuls les utilisateurs avec permission `VENTES_MODIFIER` peuvent corriger
- CSRF token vérifié
- Confirmation utilisateur requise avant chaque action

### Idempotence
- Chaque action vérifie d'abord si la correction n'a pas déjà été faite
- Messages d'avertissement si action déjà effectuée
- Pas de doublon possible

---

## 📊 Exemple concret

### Situation initiale (vente en erreur)
```
Vente V-20251213-001
  - Montant : 2,744,000 FCFA
  - Qté commandée : 30
  - BL : 0 ❌
  - Stock sorties : 0 ❌
  - Écritures compta : 0 ❌
  - Statut : EN_ATTENTE_LIVRAISON

Status: 🔴 ERREUR
```

### Problèmes détectés
```
- Sorties stock (0) ≠ Livraisons (0)
- Aucune écriture comptable
- Livraisons (0) ≠ Vente (2,744,000)
```

### Corrections appliquées (dans l'ordre recommandé)

**1️⃣ Créer BL**
```
POST /coordination/corriger_synchronisation.php
action = creer_bl_automatique

Résultat :
→ BL-AUTO-20251213-001 créé
→ 30 articles associés
```

**2️⃣ Créer sorties stock**
```
POST /coordination/corriger_synchronisation.php
action = creer_mouvements_stock

Résultat :
→ 30 mouvements SORTIE enregistrés
→ Stock des 30 articles décrémenté
```

**3️⃣ Créer écritures comptables**
```
POST /coordination/corriger_synchronisation.php
action = creer_ecritures_compta

Résultat :
→ Écriture 411 (client) : 2,744,000
→ Écriture 701 (vente) : 2,500,000
→ Écriture 449 (TVA) : 244,000
→ Piece comptable créée
```

**4️⃣ Synchroniser statuts**
```
POST /coordination/corriger_synchronisation.php
action = synchroniser_livraisons

Résultat :
→ Statut vente = LIVREE (30 livré = 30 commandé)
```

### Situation finale (corrigée) ✅
```
Vente V-20251213-001
  - Montant : 2,744,000 FCFA ✅
  - Qté commandée : 30 ✅
  - BL : 1 ✅
  - Stock sorties : 30 ✅
  - Écritures compta : 3 ✅
  - Statut : LIVREE ✅

Status: 🟢 OK
```

---

## 🎨 Interface visuelle

### Page de vérification
```
[Tableau des ventes]
  - Colonne Status : 🔴 ERREUR | 🟢 OK
  - Boutons :
    ↓ Détails | 🔧 Corriger | 👁️ Voir
```

### Page de correction
```
[Diagnostic rapide]
├─ Bons de livraison : 0 ❌
├─ Qté livrée : 0
├─ Sorties stock : 0 ❌
└─ Écritures compta : 0 ❌

[Actions disponibles]
├─ 🔧 Aucun bon de livraison
│  └─ [Créer un BL]
├─ 🔴 Sorties stock manquantes
│  └─ [Créer sorties stock]
├─ 📚 Écritures comptables manquantes
│  └─ [Créer écritures]
└─ 🔄 Synchroniser les statuts
   └─ [Synchroniser]
```

---

## 📈 Flux de correction recommandé

**Ordre optimal** :

1. ✅ **Créer BL** (crée la livraison formelle)
2. ✅ **Créer mouvements stock** (décrémente le stock)
3. ✅ **Créer écritures comptables** (trace comptable)
4. ✅ **Synchroniser statuts** (met à jour le statut final)

---

## ⚠️ Cas spéciaux

### Livraisons partielles existantes
Si la vente a déjà des BL partiels :
- ✅ L'action "Créer BL" ne créera rien
- ✅ L'action "Sorties stock" créera les mouvements manquants
- ✅ Synchroniser statuts recalculera correctement (PARTIELLEMENT_LIVREE vs LIVREE)

### Ventes annulées
- ❌ Les corrections ne s'appliquent **pas** aux ventes annulées
- ✅ Message d'erreur explicite

### Ventes déjà complètes
- ✅ Chaque action vérifie la pré-condition
- ✅ Message informatif si action déjà effectuée

---

## 🔄 Retour et vérification

Après correction, **3 options** :

1. **Voir la vente corrigée**
   - Retour à `ventes/detail.php`
   - Voir le BL créé, les mouvements, les écritures

2. **Revérifier toutes les ventes**
   - Retour à `verification_synchronisation.php`
   - Vérifier que le statut est passé à ✅ OK

3. **Continuer la correction**
   - Aller à la vente suivante en erreur

---

## 📝 Logs et traçabilité

Chaque correction est tracée via :
- **Base de données** : Mouvements, écritures, pièces créées
- **Historique** : Chaque action enregistre l'utilisateur et la date
- **Sessions** : Messages flash success/error
- **Commentaires** : "Correction : ..." dans les mouvements de stock

---

## 🎯 Résumé

| Fonctionnalité | Utilisateur | Responsabilité | Automation |
|---|---|---|---|
| **Identifier problèmes** | Consulter tableau | Lire le status | ✅ Automatique |
| **Naviguer vers correction** | Cliquer 🔧 | Sélectionner action | ✅ Guidé |
| **Exécuter corrections** | Cliquer bouton | Confirmer (OK/Annuler) | ✅ Entièrement auto |
| **Vérifier résultats** | Voir les changements | Valider cohérence | ✅ Affichage temps réel |
| **Traçabilité** | Consulter logs | Audit trail complet | ✅ Automatique |

**Résultat** : **Aucun SQL**, **aucun code** requistement. Juste **clics** et **confirmations**.
