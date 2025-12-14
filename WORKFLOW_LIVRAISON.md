# 📦 WORKFLOW DE LIVRAISON KMS

## 🎯 Vue d'ensemble

Le système KMS propose **2 workflows** pour gérer les livraisons client selon la complexité de la vente.

---

## 📋 Workflow 1 : PROCESSUS COMPLET (Recommandé pour ventes importantes)

### Étape 1 : Vente confirmée
- **Page** : `ventes/detail.php?id=XX`
- **Statut** : `EN_ATTENTE_LIVRAISON`
- **Action** : Cliquer sur **"Créer ordre de préparation"** (bouton jaune)

### Étape 2 : Ordre de préparation créé
- **Page** : `coordination/ordres_preparation_edit.php?vente_id=XX`
- **Responsable** : Commercial / Marketing
- **Actions** :
  - Sélectionner la vente
  - Définir la priorité (NORMALE, URGENTE, TRÈS_URGENTE)
  - Ajouter des observations
  - Assigner un magasinier (optionnel)
- **Résultat** : Ordre créé avec statut `EN_ATTENTE`

### Étape 3 : Signature responsable marketing
- **Page** : `coordination/ordres_preparation_edit.php?id=XX`
- **Action** : Le responsable marketing valide l'ordre
- **Résultat** : Ordre signé → passe au magasinier

### Étape 4 : Préparation physique
- **Page** : `coordination/ordres_preparation_edit.php?id=XX`
- **Responsable** : Magasinier
- **Actions** :
  - Consulter la liste des produits à préparer
  - Préparer physiquement les articles dans le magasin
  - Changer le statut : `EN_ATTENTE` → `EN_PREPARATION` → `PRET`
- **Important** : Le stock n'est PAS encore mis à jour

### Étape 5 : Création du bon de livraison
- **Page** : `coordination/ordres_preparation_edit.php?id=XX`
- **Condition** : Statut de l'ordre = `PRET`
- **Action** : Cliquer sur **"Créer bon de livraison"** (bouton vert en haut)
- **Redirection** : `livraisons/create.php?ordre_id=XX&vente_id=YY`
- **Affichage** : Encart vert montrant l'ordre de préparation source

### Étape 6 : Saisie détails de livraison
- **Page** : `livraisons/create.php`
- **Actions** :
  - Sélectionner le livreur
  - Indiquer le transporteur
  - Ajuster les quantités à livrer (support livraison partielle)
  - Vérifier le stock disponible
  - Ajouter des observations
- **Validation** : Cliquer "Créer le bon de livraison"

### Étape 7 : Livraison physique
- **Résultat automatique** :
  - ✅ Bon de livraison créé avec numéro unique (BL-YYYYMMDD-XXXX)
  - ✅ **Mouvements de stock enregistrés** (SORTIE via `stock_enregistrer_mouvement()`)
  - ✅ Statut vente mis à jour : `PARTIELLEMENT_LIVREE` ou `LIVREE`
  - ✅ Ordre de préparation marqué `LIVRE`
- **Document** : Imprimer le BL pour remise au client

### Étape 8 : Signature client
- **Page** : `livraisons/marquer_signe.php` (futur)
- **Action** : Marquer le BL comme signé après réception client
- **Résultat** : Transfert de responsabilité juridique

---

## ⚡ Workflow 2 : PROCESSUS RAPIDE (Pour petites ventes)

### Étape 1 : Vente confirmée
- **Page** : `ventes/detail.php?id=XX` ou `ventes/list.php`
- **Statut** : `EN_ATTENTE_LIVRAISON`
- **Action** : Cliquer sur **"Créer bon de livraison"** (bouton bleu/vert)

### Étape 2 : Création directe du BL
- **Page** : `livraisons/create.php?vente_id=XX`
- **Actions** : (mêmes que Workflow 1 - Étape 6)
  - Sélectionner livreur
  - Quantités à livrer
  - Transporteur
  - Observations

### Étape 3 : BL créé → Stock mis à jour
- **Résultat** : Identique au Workflow 1 - Étape 7
- **Note** : Pas d'ordre de préparation intermédiaire

---

## 🔗 Navigation inter-pages

### Depuis `ventes/detail.php`
| Bouton | Destination | Condition |
|--------|-------------|-----------|
| **Créer ordre de préparation** | `coordination/ordres_preparation_edit.php?vente_id=XX` | Statut = EN_ATTENTE_LIVRAISON |
| **Créer bon de livraison** | `livraisons/create.php?vente_id=XX` | Statut = EN_ATTENTE_LIVRAISON |

### Depuis `coordination/ordres_preparation_edit.php`
| Bouton | Destination | Condition |
|--------|-------------|-----------|
| **Créer bon de livraison** | `livraisons/create.php?ordre_id=XX&vente_id=YY` | Statut ordre = PRET |
| **Retour à la vente** | `ventes/detail.php?id=YY` | Toujours (via lien) |

### Depuis `ventes/list.php`
| Icône | Action | Condition |
|-------|--------|-----------|
| 📋 (clipboard) | Créer ordre préparation | Statut = EN_ATTENTE_LIVRAISON |
| 🚚 (truck) | Créer BL direct | Statut = EN_ATTENTE_LIVRAISON |

---

## 📊 Affichage des documents liés

### Dans `ventes/detail.php`
**3 sections affichées :**

1. **Ordres de préparation** (si existants)
   - Tableau avec : N° ordre, Date, Commercial, Magasinier, Priorité, Statut
   - Bouton "Créer BL" sur les ordres PRET
   - Badge de comptage

2. **Bons de livraison** (si existants)
   - Tableau avec : N° BL, Date, Transport, Signé client, Magasinier
   - Liens vers détails
   - Badge de comptage

3. **Encart d'aide workflow** (si aucun document créé)
   - Carte expliquant les 2 options
   - Guidage visuel avec numérotation

### Dans `livraisons/create.php`
**Alerte ordre source** (si créé depuis un ordre) :
- Bandeau vert en haut
- Lien vers l'ordre de préparation
- Badge du statut de l'ordre

---

## 🎨 Code couleurs

| Élément | Couleur | Classe Bootstrap |
|---------|---------|------------------|
| Ordre de préparation | Jaune/Orange | `btn-warning` |
| Bon de livraison | Vert | `btn-success` / `btn-primary` |
| Statut EN_ATTENTE | Jaune | `bg-warning` |
| Statut EN_PREPARATION | Bleu | `bg-info` |
| Statut PRET | Vert | `bg-success` |
| Statut LIVRE | Primaire | `bg-primary` |

---

## 🔄 Gestion des livraisons partielles

### Fonctionnement
- **Une vente peut avoir plusieurs BL** (livraisons multiples)
- Chaque BL décrémente le stock et met à jour les quantités livrées
- Le système calcule automatiquement :
  - Quantité déjà livrée (somme de tous les BL précédents)
  - Quantité restante à livrer
  - Statut de la vente (PARTIELLEMENT_LIVREE vs LIVREE)

### Interface `livraisons/create.php`
| Colonne | Calcul |
|---------|--------|
| Qté commandée | Depuis ventes_lignes |
| Déjà livrée | SUM(bons_livraison_lignes) WHERE vente_id = X |
| Reste à livrer | Commandée - Déjà livrée |
| Stock dispo | produits.stock_actuel |
| Qté à livrer | Input utilisateur (max = Reste à livrer) |

### Exemple
```
Vente #V123 : 10 chaises

Livraison 1 : 6 chaises
  → BL-001 créé
  → Statut vente = PARTIELLEMENT_LIVREE
  → Stock : -6

Livraison 2 : 4 chaises restantes
  → BL-002 créé
  → Statut vente = LIVREE
  → Stock : -4

Total livré : 10 (= commandé) ✅
```

---

## 📝 Fichiers modifiés pour ce workflow

| Fichier | Modifications |
|---------|--------------|
| `ventes/detail.php` | + Section ordres préparation<br>+ Bouton "Créer ordre"<br>+ Encart aide workflow<br>+ Badges de comptage |
| `ventes/list.php` | + Bouton ordre préparation (icône clipboard)<br>+ Bouton BL (icône truck) |
| `coordination/ordres_preparation_edit.php` | + Bouton "Créer BL" si statut PRET<br>+ Support paramètre vente_id<br>+ Présélection vente |
| `livraisons/create.php` | + Affichage ordre source<br>+ Lien retour vente |
| `ventes/generer_bl.php` | + require stock.php |
| `ventes/detail.php` | + require stock.php |

---

## ✅ Points de contrôle

### Avant de créer un ordre de préparation
- [ ] Vente en statut EN_ATTENTE_LIVRAISON
- [ ] Pas d'ordre actif déjà existant pour cette vente
- [ ] Commercial/marketing connecté avec permissions

### Avant de créer un BL depuis un ordre
- [ ] Ordre en statut PRET
- [ ] Articles physiquement préparés dans le magasin
- [ ] Stock suffisant pour les quantités

### Avant de créer un BL direct
- [ ] Vente en statut EN_ATTENTE_LIVRAISON ou PARTIELLEMENT_LIVREE
- [ ] Stock suffisant vérifié
- [ ] Livreur identifié

### Après création d'un BL
- [ ] Stock mis à jour (vérifier stocks_mouvements)
- [ ] Statut vente correct (PARTIELLEMENT_LIVREE ou LIVREE)
- [ ] Numéro BL unique généré
- [ ] Document imprimable disponible

---

## 🚀 Prochaines améliorations

- [ ] Signature électronique client sur BL
- [ ] Notification email au client lors de la livraison
- [ ] Suivi GPS du livreur
- [ ] Photos de livraison (preuve de réception)
- [ ] Intégration comptable automatique (facture depuis BL)
