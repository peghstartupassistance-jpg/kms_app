# Guide Utilisateur - Gestion des Litiges & Retours Synchronisés

**KMS Gestion** | Révision 1.0 | Décembre 2025

---

## 📋 Sommaire

1. [Accéder à la gestion des litiges](#1-accéder-à-la-gestion-des-litiges)
2. [Créer un litige](#2-créer-un-litige)
3. [Résoudre un litige](#3-résoudre-un-litige)
4. [Visualiser la synchronisation](#4-visualiser-la-synchronisation)
5. [FAQ & Dépannage](#5-faq--dépannage)

---

## 1. Accéder à la gestion des litiges

### Via le menu principal
```
Coordination → Litiges & Retours
```

### URL directe
```
http://localhost/kms_app/coordination/litiges.php
```

### Ce que vous verrez
- **Tableau des litiges** : Liste de tous les litiges enregistrés
- **Statistiques KPI** : Total, En cours, Résolus, Montants remboursés
- **Filtres** : Par statut, type problème, date, client
- **Bouton "Nouveau litige"** pour créer

---

## 2. Créer un litige

### Étape 1 : Cliquer sur "Nouveau litige"
Un modal s'ouvre avec le formulaire.

### Étape 2 : Remplir les informations

**Champs obligatoires** :
- **Client** : Rechercher et sélectionner le client
- **Type de problème** : 
  - Défaut produit
  - Livraison non conforme
  - Retard livraison
  - Erreur commande
  - Insatisfaction client
- **Date retour** : Date du retour (pré-remplie à aujourd'hui)
- **Motif détaillé** : Description du problème

**Champs optionnels** :
- **Vente** : Numéro de vente associée (si applicable)
- **Produit** : Code/désignation du produit

### Étape 3 : Options avancées

Si le produit a été retourné physiquement, vous pouvez renseigner :
- **Quantité retournée** : Nombre d'unités retournées

👉 **Si vous entrez une quantité**, le stock sera immédiatement augmenté (ENTRÉE en stock).

### Étape 4 : Cliquer "Créer"

**Résultat** : Le litige passe au statut **EN COURS** avec :
- ✅ Enregistrement dans retours_litiges
- ✅ Mouvement stock (si quantité saisie)
- ✅ Traçabilité automatique

---

## 3. Résoudre un litige

### Prise en main d'un litige

Cliquez sur le bouton **Actions** dans la ligne du litige.

Trois options principales :

#### Option A : ✅ Résolu (simple)
Marque le litige comme résolu **sans action financière**.
- Utilisez si : Litige fermé sans remboursement/remplacement.

#### Option B : 📦 Remplacement effectué
Client reçoit un produit neuf en échange du défectueux.
- **Impact** :
  - ✅ Stock : Retour du produit défectueux (ENTRÉE)
  - ✅ Stock : Livraison du remplacement (SORTIE)
  - ✅ Compta : **Aucune** (compensation neutre)
  - ✅ Caisse : **Aucune** (pas de flux financier)

**À saisir** :
- Quantité remplacement (ex: 1)
- Solution apportée (ex: "Produit remplacé par lot neuf")

#### Option C : 💰 Remboursement effectué
Client reçoit un remboursement financier.
- **Impact** :
  - ✅ Caisse : Sortie de l'argent (REMBOURSEMENT_CLIENT_LITIGE)
  - ✅ Compta : Écriture de réduction créance (411 → 512)
  - ✅ Trace : Pièce comptable automatique (REMB-...)

**À saisir** :
- Montant remboursé (en FCFA)
- Solution apportée (ex: "Remboursement intégral demandé")

### Exemple de workflow complet

```
CLIENT APPELLE → CRÉER LITIGE (EN_COURS)
         ↓
ANALYSER LE PROBLÈME
         ↓
DÉCIDER TYPE RÉSOLUTION
         ↓
  ┌─────┼─────┬─────────────────┐
  │     │     │                  │
ABANDON  AVOIR REMPLACEMENT  REMBOURSEMENT
  │     │     │                  │
  └─────┴─────┴──────────────────┘
         ↓
   MARQUER LITIGE RESOLU
         ↓
  (Traces complètes générées automatiquement)
```

---

## 4. Visualiser la synchronisation

### Accéder au détail synchronisation

À côté de chaque litige, cliquez sur **"Voir synchronisation"**.

### Ce que vous verrez

**En haut** : Fiche du litige avec résolution appliquée

**Onglet "Stock"** :
```
Date : 14/12/2025 10:30
Type : ENTREE
Quantité : 1
Raison : Retour client - Litige #123 - Défaut écran
Montant stock : 45,000 FCFA
```

**Onglet "Caisse"** :
```
Date : 14/12/2025 10:31
Opération : REMBOURSEMENT_CLIENT_LITIGE
Description : Remboursement client litige #123
Sortie : 50,000 FCFA
```

**Onglet "Comptabilité"** :
```
Pièce : REMB-2025-12-14-00001
Date : 14/12/2025
Compte 411 (Clients) : Débit 50,000
Compte 512 (Caisse) : Crédit 50,000
```

**Vérification cohérence** :
```
✅ Stock     : Mouvement enregistré
✅ Caisse    : Remboursement tracé
✅ Compta    : Pièce équilibrée
```

---

## 5. FAQ & Dépannage

### Q : Je crée un litige mais le stock n'augmente pas ?
**R** : C'est normal ! Le stock augmente **seulement si** vous remplissez "Quantité retournée". 
- Sinon, cela signifie que le client n'a pas retourné la marchandise physiquement.

---

### Q : Quelle différence entre Remplacement et Remboursement ?
**R** :
- **Remplacement** = Client reçoit un nouveau produit → Stock compensé (neutre)
- **Remboursement** = Client reçoit de l'argent → Caisse affectée

---

### Q : Puis-je donner à la fois un remplacement ET un remboursement partiel ?
**R** : Non, une seule résolution par litige. Créez deux litiges si nécessaire.

---

### Q : Comment annuler une résolution ?
**R** : 
1. Allez à Coordination → Litiges
2. Cliquez sur le litige
3. Changez le statut en "EN_COURS"
4. Cliquez "Enregistrer"

⚠️ **Note** : Cela **ne désynchronise pas** les écritures caisse/compta. Vous devrez les corriger manuellement en comptabilité.

---

### Q : Les écritures comptables sont créées automatiquement ?
**R** : **OUI** ! 
- Remboursement → Pièce "REMB-..."
- Avoir → Pièce "AVOIR-..."

Ces pièces sont en statut **BROUILLON**. Vous devez les valider en Comptabilité → Valider pièces.

---

### Q : Je ne vois pas mon litige dans la liste ?
**R** : Vérifiez les filtres (Statut, Date, etc.). Déroulez tout avec "Tous" les statuts.

---

### Q : Qu'est-ce qu'un "Avoir" ?
**R** : C'est une réduction accordée au client **sans remboursement immédiat**. 
Le client peut l'utiliser pour une future commande. Comptablement = réduction créance.

---

### Q : Les mouvements stock générés sont-ils modifiables ?
**R** : Non. Ils sont immuables pour traçabilité. Si erreur, créez un mouvement **ajustement** opposé.

---

### Q : Quand utiliser le module Litiges vs le module Retours ?
**R** : 
- **Litiges** = Gestion client + Résolution (ce module)
- **Retours** = Gestion physique des produits retournés (si module séparé existe)

---

### Q : Comment générer un rapport des litiges du mois ?
**R** :
1. Allez à Coordination → Litiges
2. Filtrez par date (Du ... Au ...)
3. Sélectionnez tous les litiges
4. Bouton "Exporter PDF/CSV"

---

### Q : Y a-t-il un audit de synchronisation ?
**R** : **OUI** ! Allez à :
```
Coordination → API Audit Synchronisation
```

Cela affiche :
- ✅ Litiges sans trace stock
- ✅ Remboursements sans trace caisse
- ✅ Avoirs sans trace compta
- ✅ Statistiques globales

---

## 📊 Tableau statuts

| Statut | Signification | Actions possibles |
|--------|---------------|-------------------|
| **EN_COURS** | Litige ouvert, traitement en cours | Basculer vers RESOLU/REMBOURSEMENT/REMPLACEMENT/ABANDONNE |
| **RESOLU** | Résolu sans impact financier | Revert à EN_COURS |
| **REMBOURSEMENT_EFFECTUE** | Remboursement accordé | Revert à EN_COURS (attention : compta !) |
| **REMPLACEMENT_EFFECTUE** | Produit remplacé | Revert à EN_COURS (attention : stock !) |
| **ABANDONNE** | Litige fermé sans suite | Revert à EN_COURS |

---

## 🔐 Permissions requises

Pour accéder à la gestion des litiges, vous devez avoir la permission :
```
VENTES_CREER ou VENTES_LIRE
```

Contactez un administrateur si vous n'avez pas accès.

---

## 📞 Support

En cas de problème :
1. Consultez la section **FAQ** ci-dessus
2. Vérifiez que vous avez les bonnes **permissions**
3. Contactez l'équipe IT
4. Signalez l'anomalie via le formulaire de support

---

**Dernière mise à jour** : 14 décembre 2025  
**Version** : 1.0

