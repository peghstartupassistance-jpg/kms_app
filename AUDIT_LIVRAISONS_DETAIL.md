# AUDIT RÉGRESSION - livraisons/detail.php

## Problèmes Identifiés

### PROBLÈME 1: Colonne inexistante "numero_bl" (ligne 63)
**Localisation**: Ligne 63
**Erreur**: `$bl['numero_bl']`
**Réalité DB**: La colonne s'appelle `numero` (vérifiée dans bons_livraison)
**Impact**: Génère "Undefined array key 'numero_bl'"
**Correction**: Remplacer `$bl['numero_bl']` → `$bl['numero']`

---

### PROBLÈME 2: Colonne inexistante "signature" (ligne 96, 287, 298)
**Localisation**: 
- Ligne 96: `if ($bl['statut'] !== 'ANNULE' && !$bl['signature']):`
- Ligne 287: `if ($bl['signature']):`
- Ligne 298: `base64_encode($bl['signature'])`

**Réalité DB**: La colonne s'appelle `signe_client` (type LONGBLOB - image signature)
**Impact**: Génère "Undefined array key 'signature'" (2x)
**Correction**: Remplacer tous `$bl['signature']` → `$bl['signe_client']`
**Note**: Le code utilise déjà `$bl['signature_client_nom']` et `$bl['signature_date']` ligne 309 & 313 - ces colonnes existent et sont correctes

---

### PROBLÈME 3: Colonnes manquantes dans requête lignes (lignes 45-48)
**Localisation**: Lignes 45-48
**Requête actuelle**: 
```sql
SELECT bll.*, p.code_produit, p.stock_actuel
FROM bons_livraison_lignes bll
LEFT JOIN produits p ON p.id = bll.produit_id
WHERE bll.bon_livraison_id = ?
```

**Problème**: N'inclut pas:
- `p.designation` (utilisé ligne 206)
- `p.prix_vente` ou prix unitaire similaire (utilisé ligne 201 & 216 comme `$ligne['prix_unitaire']`)

**Table bons_livraison_lignes**: Possède `quantite`, `quantite_commandee`, `quantite_restante`
**Table produits**: Possède `designation`, `prix_vente`, `prix_achat`, etc.

**Erreurs générées**:
- Ligne 201: `Undefined array key 'prix_unitaire'`
- Ligne 206: `Undefined array key 'designation'`  
- Ligne 216: `Undefined array key 'prix_unitaire'`

**Correction**: Ajouter colonnes à la requête SELECT:
```sql
SELECT bll.*, p.code_produit, p.stock_actuel, p.designation, p.prix_vente as prix_unitaire
```

**Décision prix**: Utiliser `p.prix_vente` (prix de vente du produit) ou créer colonne dans bons_livraison_lignes?
- Approche simple: utiliser `p.prix_vente` (OK pour this use case - display only)

---

## Fichiers Affectés par ces Erreurs
1. `livraisons/detail.php` - Lieu des erreurs
2. Aucun autre fichier n'est affecté (livraisons/detail.php ne partage pas ces variables globales)

---

## Stratégie de Correction
1. Corriger la requête ligne 45-48 (ajouter designation, prix_vente)
2. Corriger ligne 63 (numero_bl → numero)
3. Corriger lignes 96, 287, 298 (signature → signe_client)
4. Relancer test_regression_modifications.php pour valider
5. Tester page dans navigateur
6. Supprimer le test obsolète test_phase1_2.php (remplacé par test_phase1_2_corrige.php)

---

## Risques
- ⚠️ **FAIBLE**: Ces corrections sont isolées à livraisons/detail.php
- ⚠️ **FAIBLE**: Utiliser `prix_vente` pour afficher prix peut ne pas refléter prix exact de la vente (mais c'est display-only, pas stockage)
- ⚠️ **MOYEN**: Si d'autres pages utilisent `bons_livraison_lignes`, elles pourraient avoir besoin des mêmes corrections

---

## Action Suivante
Après correction: Scanner TOUS les fichiers qui chargent `bons_livraison` ou `bons_livraison_lignes` pour vérifier syntaxe similaire.
