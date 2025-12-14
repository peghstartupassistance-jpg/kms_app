# Guide Opérationnel : Résolution des Litiges & Retours

**Version**: 2.0 - Système Synchronisé Stock + Caisse + Comptabilité
**Date**: Décembre 2025
**Public**: Direction, Magasiner, Caissier, Responsable SAV

---

## 📋 Table des matières

1. [Accès & Permissions](#accès--permissions)
2. [Créer un nouveau litige](#créer-un-nouveau-litige)
3. [4 Actions de Résolution](#4-actions-de-résolution)
4. [Impacts Métier par Action](#impacts-métier-par-action)
5. [Vérification & Audit](#vérification--audit)
6. [Questions Fréquentes](#questions-fréquentes)

---

## Accès & Permissions

**URL** : `http://localhost/kms_app/coordination/litiges.php`

**Permissions requises** : `VENTES_CREER` ou `VENTES_LIRE`

**Utilisateurs autorisés** :
- ✅ Administrateur
- ✅ Direction (SAV, Qualité)
- ✅ Responsable commercial
- ✅ Magasinier (lecture + certaines actions)
- ✅ Caissier (pour validation remboursements)

---

## Créer un nouveau litige

### 👉 Étapes

1. Cliquez sur **« Nouveau litige »** (bouton bleu en haut à gauche)
2. Remplissez les champs obligatoires :
   - **Client** : Tapez le nom/téléphone du client (autocomplétion)
   - **Vente** (optionnel) : La vente concernée (autocomplétion)
   - **Produit** (optionnel) : Le produit retourné (autocomplétion)
   - **Type de problème** : Sélectionnez parmi :
     - Défaut produit
     - Livraison non conforme
     - Retard livraison
     - Erreur commande
     - Insatisfaction client
   - **Date retour** : Date de retour du produit
   - **Motif détaillé** : Description précise du problème
3. Cliquez sur **« Créer »**

### 📊 Résultat

✅ Le litige est créé avec statut **EN_COURS**
✅ Numéro litige auto-généré
✅ Si quantité de retour fournie → entrée stock automatique tracée

---

## 4 Actions de Résolution

### 1️⃣ REMBOURSEMENT

**Quand l'utiliser** : Le client doit être remboursé (produit défectueux, non livré, etc.)

**Données à fournir** :
- Montant exact à rembourser (FCFA)
- Motif / observations (libre)

**Impacts SIMULTANÉS** :
| Système | Effets | Comptes comptables |
|---------|--------|-------------------|
| **Caisse** | ❌ Sortie remboursement | Débit 411 Client, Crédit 512 Caisse |
| **Stock** | Aucun (ou retour enregistré séparément) | - |
| **Compta** | ✅ Pièce REMB-YYYY-MM-DD créée avec écritures RRR | Compte 411 & 512 |

**Statut final** : `REMBOURSEMENT_EFFECTUE`

**Exemple** :
```
Client achète 5 tables à 50 000 FCFA = 250 000 FCFA
Table livrée défectueuse → remboursement 50 000 FCFA
  → Caisse -50 000
  → Compta: REMB-2025-12-14-00001 créé
  → RRR 701: -50 000 (réduction de revenu)
```

---

### 2️⃣ REMPLACEMENT

**Quand l'utiliser** : Livrer un produit neuf à la place de l'ancien (défaut de fabrication)

**Données à fournir** :
- Quantité à remplacer (nombre d'unités)
- Motif / observations

**Impacts SIMULTANÉS** :
| Système | Effets | Trace |
|---------|--------|-------|
| **Stock** | ✅ Entrée : retour du produit défectueux | Mouvement "Retour produit défectueux - Litige #X" |
| **Stock** | ✅ Sortie : livraison du remplacement | Mouvement "Livraison remplacement - Litige #X" |
| **Caisse** | Aucun (pas de cash) | - |
| **Compta** | Aucun (mouvement interne) | - |

**Statut final** : `REMPLACEMENT_EFFECTUE`

**Exemple** :
```
Client a 5 chaises cassées → doit les remplacer
Quantité: 5
  → Stock +5 chaises (retour)
  → Stock -5 chaises (livraison remplacement)
  → Stock net: inchangé (échange)
  → Journal: 2 mouvements tracés par Litige #2
```

---

### 3️⃣ AVOIR

**Quand l'utiliser** : Insatisfaction partielle ou légère → crédit sur prochaine commande

**Données à fournir** :
- Montant de l'avoir (FCFA) - **partiel** par rapport au prix
- Motif / observations

**Impacts SIMULTANÉS** :
| Système | Effets | Comptes comptables |
|---------|--------|-------------------|
| **Caisse** | Aucun (crédit futur, pas cash) | - |
| **Stock** | Aucun | - |
| **Compta** | ✅ Pièce AVOIR-YYYY-MM-DD créée | Débit 411 Client, Crédit 701 (RRR) |

**Statut final** : `RESOLU`

**Exemple** :
```
Client a produit avec petit défaut cosmétique → accord partiel
Montant initial: 100 000 FCFA
Avoir accordé: 20 000 FCFA (20%)
  → Compta AVOIR-2025-12-14-00001 créé
  → Client a crédit 20 000 FCFA sur prochaine achat
  → RRR 701: -20 000 (déduction de revenu)
```

---

### 4️⃣ ABANDON

**Quand l'utiliser** : Litige non justifié, client retiré demande, délai expiré

**Données à fournir** :
- Raison de l'abandon (justification)

**Impacts** :
| Système | Effets |
|---------|--------|
| **Caisse** | Aucun |
| **Stock** | Aucun |
| **Compta** | Aucun |

**Statut final** : `ABANDONNE`

**Remarque** : ⚠️ Cette action ne peut pas être facilement annulée. Vérifier avant de valider.

---

## Impacts Métier par Action

### Tableau Récapitulatif

| Action | Stock ↕️ | Caisse 💰 | Compta 📋 | Trace | Statut |
|--------|---------|---------|---------|-------|--------|
| **Remboursement** | Non | ❌ -Montant | ✅ REMB-... | Oui | REMBOURSEMENT_EFFECTUE |
| **Remplacement** | ✅ ±Quantité | Non | Non | Oui | REMPLACEMENT_EFFECTUE |
| **Avoir** | Non | Crédit futur | ✅ AVOIR-... | Oui | RESOLU |
| **Abandon** | Non | Non | Non | Justif. | ABANDONNE |

### Traçabilité Complète

Chaque action laisse des traces dans 3 bases :

**1. Litige** (table `retours_litiges`)
```
id | statut | montant_rembourse | montant_avoir | solution | date_resolution
1  | REMBOURSEMENT_EFFECTUE | 50000 | 0 | Défaut détecté | 2025-12-14
```

**2. Stock** (table `stocks_mouvements`)
```
produit_id | type | quantite | raison | date
42 | ENTREE | 5 | Retour client - Litige #2 - Produit cassé | 2025-12-14
42 | SORTIE | 5 | Livraison remplacement - Litige #2 | 2025-12-14
```

**3. Caisse** (table `journal_caisse`)
```
type_operation | montant | libelle | date
REMBOURSEMENT_CLIENT_LITIGE | 50000 | Remboursement client litige #1 | 2025-12-14
```

**4. Comptabilité** (tables `compta_pieces` + `compta_ecritures`)
```
numero_piece | libelle | compte | debit | credit
REMB-2025-12-14-00001 | Remboursement suite litige #1 | 411001 | 50000 | 0
REMB-2025-12-14-00001 | Remboursement suite litige #1 | 512001 | 0 | 50000
```

---

## Vérification & Audit

### 👁️ Visualiser une Résolution Complète

Après avoir enregistré une action, accédez à :
```
http://localhost/kms_app/coordination/litiges_synchronisation.php?id=1
```

**Onglets disponibles** :
1. **Stock** : Tous les mouvements de retour/remplacement
2. **Caisse** : Remboursements et opérations monétaires
3. **Compta** : Pièces et écritures comptables générées
4. **Cohérence** : Vérification automatique de la synchronisation

### 🔍 Audit Automatique

Endpoint d'audit pour détecter les anomalies :
```
GET /coordination/api/audit_synchronisation.php
```

Vérifie :
- ✅ Litiges sans trace stock
- ✅ Litiges sans trace caisse
- ✅ Litiges sans trace compta
- ✅ Stock orphelin
- ✅ Remboursements orphelins
- ✅ Écritures comptables non rattachées

**Réponse JSON** :
```json
{
  "audit": [
    {"check": "Litiges sans trace stock", "count": 0, "status": "OK"},
    {"check": "Litiges sans trace caisse", "count": 0, "status": "OK"},
    ...
  ],
  "statistiques": {
    "total_litiges": 5,
    "en_cours": 1,
    "resolus": 4,
    "total_remboursements": 150000,
    "total_stock_mouvements": 8
  }
}
```

---

## Questions Fréquentes

### ❓ Puis-je modifier une action après l'avoir enregistrée ?

**Remboursement/Remplacement/Avoir** : ⚠️ Non, les impacts stock + caisse + compta sont appliqués immédiatement. Pour corriger :
1. Contacter l'administrateur
2. Créer une contre-opération (remboursement inverse, etc.)

**Abandon** : Peut être converti en autre statut si justifié (contacter direction).

---

### ❓ Quel montant rembourser : prix TTC ou HT ?

**Réponse** : TTC (avec TVA). Le montant facturé au client doit être celui remboursé.

---

### ❓ Un client veut 50% remboursement, 50% remplacement ?

**Solution** :
1. Créez 2 actions en cascade :
   - **Action 1** : Remboursement 50% du montant
   - **Action 2** : Remplacement quantité partagée

2. Justifiez chaque action avec observations complètes

3. Le litige aura 2 résolutions tracées

---

### ❓ Quand aura lieu le remboursement effectif du client ?

**Processus** :
1. Action **Remboursement** → Enregistrement en caisse (opération)
2. Caissier encaisse ou transfère selon mode de paiement client
3. Journal caisse reflète la sortie
4. Vérifier solde caisse en fin de jour

---

### ❓ Comment annuler une résolution ?

**Option 1** : Créer une contre-opération
- Remboursement de 50 000 → Créer un "paiement client" de 50 000 (inverse)

**Option 2** : Contacter administrateur pour rollback transactionnel

---

### ❓ Le système vérifie-t-il la validité des montants ?

**Oui** :
- Montant remboursement ≤ prix original facturé
- Montant avoir ≤ prix original
- Quantité remplacement ≤ quantité achetée
- Montant > 0 (obligatoire)

---

### ❓ Comment consulter le suivi d'un litige ?

**Page de suivi** :
```
http://localhost/kms_app/coordination/litiges.php
```

**Filtres disponibles** :
- Par statut (En cours, Résolu, etc.)
- Par type problème
- Par plage de dates

---

## 📞 Support & Escalade

**Problème lors d'une résolution** ?

1. **Erreur de formulaire** : Vérifier que tous les champs sont remplis
2. **Erreur de permission** : Contacter administrateur
3. **Erreur système** (500 Internal Server Error) : Notifier IT avec screenshot
4. **Anomalie de synchronisation** : Lancer audit (`audit_synchronisation.php`), noter le résultat

**Contact** :
- Direction : direction@kennemulti-services.com
- IT / Admin : admin@kennemulti-services.com

---

## 🎯 Checklist Avant Résolution

Avant de cliquer sur **Enregistrer**, vérifiez :

- [ ] Litige bien identifié (n° et client correct)
- [ ] Client contacté et accord obtenu
- [ ] Type d'action adapté au problème
- [ ] Montants / quantités vérifiés et corrects
- [ ] Motif / observations détaillés et clairs
- [ ] Délai de 48h respecté si possible
- [ ] Fonds disponibles (pour remboursement)
- [ ] Stock suffisant (pour remplacement)

---

**Fin du guide. Merci de respecter ce protocole pour assurer la qualité de nos opérations.**
