# 📦 MODULE MAGASINIER - Guide d'Utilisation

**Date:** 2025-12-11  
**Version:** 1.0  
**Utilisateurs concernés:** Magasiniers, Gestionnaires de stock

---

## 🎯 Vue d'Ensemble

Le module magasinier centralise toutes les fonctionnalités essentielles pour la gestion quotidienne du stock, des préparations de commandes, des livraisons et des litiges.

### Accès Rapide
**Dashboard:** http://localhost/kms_app/magasin/dashboard.php

---

## 📊 Dashboard Magasinier

### Indicateurs Clés (KPIs)
- **Ordres en attente** : Commandes à préparer (avec indicateur urgence)
- **Produits en alerte** : Stock bas ou en rupture
- **Livraisons récentes** : BL des 7 derniers jours (signés/non signés)
- **Litiges actifs** : Retours/réclamations clients en cours

### Actions Rapides
```
[Ajustement stock]  → Correction manuelle (inventaire, casse, perte)
[Nouvelle réception] → Enregistrer un achat/réapprovisionnement
[Signaler rupture]   → Alerter le marketing d'une rupture
[Inventaire]         → Contrôle physique du stock
```

---

## 📦 Gestion du Stock

### 1. Alertes Stock (`stock/alertes.php`)

**Fonctionnalités:**
- Vue consolidée des produits en alerte ou rupture
- Statistiques : Ruptures / Alertes / Stock OK
- Filtres : Ruptures | Alertes | Tous
- Analyse des sorties (semaine/mois) pour anticiper
- Actions directes : Commander | Signaler rupture

**Critères d'alerte:**
- 🔴 **Rupture** : Stock = 0
- 🟡 **Alerte** : Stock ≤ Seuil alerte
- 🟢 **OK** : Stock > Seuil alerte

**Actions possibles:**
```
[👁️ Détails]         → Fiche produit complète
[🛒 Commander]       → Créer un achat pour réapprovisionner
[⚠️ Signaler]        → Notifier rupture au marketing
```

---

### 2. Ajustement Stock (`stock/ajustement.php`)

**Cas d'usage:**
- Inventaire physique (écart comptage)
- Correction d'erreur de saisie
- Produit cassé/endommagé
- Perte ou vol
- Péremption

**Processus:**
1. Rechercher le produit (code ou désignation)
2. Sélectionner le produit
3. Indiquer le **nouveau stock** (après comptage)
4. Choisir le motif (INVENTAIRE, CORRECTION, CASSE, PERTE...)
5. Valider

**Traçabilité:**
- L'écart est automatiquement calculé
- Un mouvement AJUSTEMENT est créé
- L'utilisateur et la date sont enregistrés
- Le commentaire est sauvegardé

**Exemple:**
```
Stock actuel : 50
Nouveau stock: 48 (comptage physique)
Écart        : -2 (2 unités manquantes)
Motif        : INVENTAIRE - Différence comptage
```

---

### 3. Mouvements Stock (`stock/mouvements.php`)

**Affichage:**
- Historique complet des mouvements (500 derniers)
- Types : ENTREE (vert) | SORTIE (rouge) | AJUSTEMENT (orange)
- Source : ACHAT, VENTE, AJUSTEMENT, etc.

**Filtres:**
- Par produit
- Par type de mouvement
- Par période (date début/fin)

**Statistiques période:**
- Total mouvements
- Total entrées (+)
- Total sorties (-)
- Total ajustements

---

## 📋 Ordres de Préparation

### 1. Liste des Ordres (`coordination/ordres_preparation.php`)

**Statuts:**
- 🟡 **EN_ATTENTE** : Non encore commencé
- 🔵 **EN_PREPARATION** : En cours de préparation
- 🟢 **PRET** : Prêt à livrer
- ⚫ **LIVRE** : Livré au client

**Priorités:**
- 🔴 **URGENTE** : À traiter en priorité
- 🟠 **TRES_URGENTE** : Priorité absolue
- ⚪ **NORMALE** : Traitement standard

**Workflow:**
```
1. Commercial crée l'ordre (vente validée)
2. Magasinier le voit dans EN_ATTENTE
3. Magasinier clique [▶️ Suivant] → EN_PREPARATION
4. Magasinier prépare les produits
5. Magasinier clique [▶️ Suivant] → PRET
6. Livraison effectuée
7. Magasinier clique [▶️ Suivant] → LIVRE
```

**Filtres:**
- Par statut
- Par type de commande (SHOWROOM, TERRAIN, DIGITAL...)

---

### 2. Créer/Éditer un Ordre (`coordination/ordres_preparation_edit.php`)

**Création (par commercial):**
1. Sélectionner la vente concernée
2. Définir la priorité (NORMALE, URGENTE, TRES_URGENTE)
3. Indiquer date de préparation demandée
4. Ajouter observations/instructions

**Champs:**
- **Vente** : Lien vers la vente (obligatoire)
- **Priorité** : NORMALE | URGENTE | TRES_URGENTE
- **Date demandée** : Date souhaitée de préparation
- **Observations** : Instructions spéciales (emballage, adresse livraison...)

**Note:** Seules les ventes validées **sans ordre actif** sont proposées.

---

## 🚚 Livraisons

**Module existant** : `livraisons/list.php`

**Fonctionnalités:**
- Générer BL depuis une vente/ordre
- Marquer BL comme signé (`marquer_signe.php`)
- Historique des livraisons

**Amélioration suggérée:**
- Lier automatiquement ordre préparation → BL
- Scanner QR code pour signature mobile

---

## ⚠️ Litiges & Retours

### Module Litiges (`coordination/litiges.php`)

**Types de problèmes:**
- **DEFAUT_PRODUIT** : Produit défectueux
- **ERREUR_LIVRAISON** : Mauvais produit/quantité
- **INSATISFACTION_CLIENT** : Client mécontent
- **AUTRE** : Autre motif

**Statuts:**
- 🟡 **EN_COURS** : Litige ouvert
- 🟢 **RESOLU** : Problème résolu
- 🔴 **ABANDONNE** : Abandon du traitement

**Actions magasinier:**
1. Enregistrer le retour (produit, motif, client)
2. Choisir la solution :
   - Remplacement produit
   - Remboursement
   - Avoir commercial
3. Marquer comme résolu avec montant remboursé/avoir

**Champs:**
- Date retour
- Client concerné
- Produit retourné
- Vente origine
- Motif détaillé
- Solution proposée
- Montant remboursé
- Montant avoir

---

## 🚨 Ruptures Signalées

**Module** : `coordination/ruptures_signalees_list.php`

**But:** Notifier le marketing des ruptures de stock pour:
- Impact commercial (ventes perdues)
- Actions correctives (réapprovisionnement urgent, produit alternatif)
- Suivi de la résolution

**Workflow:**
```
1. Magasinier constate rupture
2. Magasinier signale via [Signaler rupture]
3. Fiche créée : Produit, stock actuel, seuil, impact
4. Marketing notifié
5. Action proposée (commande urgente, promo, alternatif)
6. Résolution trackée
```

**Statuts:**
- **SIGNALE** : Vient d'être signalée
- **EN_COURS** : Traitement en cours
- **RESOLU** : Rupture résolue (réappro effectué)
- **ABANDONNE** : Abandon (produit discontinué)

---

## 🔐 Permissions Requises

### Lecture (consultation)
- `STOCK_LIRE` : Voir stock, mouvements, alertes
- `VENTES_LIRE` : Voir ordres de préparation, livraisons

### Écriture (modification)
- `STOCK_ECRIRE` : Ajuster stock, créer achats
- `VENTES_MODIFIER` : Changer statut ordres, créer BL

### Rôle recommandé: **MAGASINIER**
```sql
-- Permissions à attribuer
STOCK_LIRE
STOCK_ECRIRE
VENTES_LIRE
VENTES_MODIFIER (limité aux ordres préparation)
```

---

## 📈 Flux Quotidien Type

### Matin (8h00)
1. **Consulter dashboard** : Ordres urgents ? Alertes stock ?
2. **Traiter ordres urgents/très urgents** en priorité
3. **Vérifier alertes stock** : Produits à commander ?

### Journée
4. **Préparer ordres** : EN_ATTENTE → EN_PREPARATION → PRET
5. **Générer BL** pour ordres PRET
6. **Livraisons** : Remettre BL, faire signer
7. **Réceptions** : Enregistrer achats (entrées stock)
8. **Traiter litiges** : Retours clients, solutions

### Fin de journée (17h00)
9. **Ajustements stock** : Corriger écarts inventaire
10. **Signaler ruptures** critiques au marketing
11. **Vérifier BL non signés** : Relancer clients

---

## 🛠️ Outils Complémentaires

### Inventaire
**À développer** : `stock/inventaire.php`
- Scanner codes-barres
- Comparaison stock théorique vs physique
- Génération rapports écarts

### Rapports
- Rotation stock (produits rapides/lents)
- Valorisation stock
- Prévisions ruptures (basé sur sorties)

---

## 📞 Support & Documentation

### Fichiers de référence
- `MAPPING_ORDRES_PREPARATION.md` : Structure BDD ordres
- `CORRECTIONS_UI_MARKETING.md` : Historique corrections UI
- `lib/stock.php` : API fonctions stock

### Problèmes courants

**Q: Ordre de préparation ne s'affiche pas**  
R: Vérifier que la vente est bien validée et sans ordre actif

**Q: Ajustement stock ne fonctionne pas**  
R: Vérifier permission `STOCK_ECRIRE` + motif obligatoire

**Q: Produit en rupture non affiché**  
R: Vérifier que `actif = 1` et `stock_actuel = 0`

---

## ✅ Checklist Mise en Prod

- [ ] Créer rôle MAGASINIER avec permissions
- [ ] Former utilisateurs au workflow ordres préparation
- [ ] Paramétrer seuils d'alerte par produit
- [ ] Tester cycle complet : Ordre → Préparation → BL → Livraison
- [ ] Tester ajustement stock + traçabilité
- [ ] Configurer alertes automatiques (email ruptures)

---

**Version:** 1.0  
**Dernière mise à jour:** 2025-12-11  
**Auteur:** GitHub Copilot (Claude Sonnet 4.5)
