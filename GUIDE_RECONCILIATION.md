# Guide Réconciliation Caisse

## Vue d'ensemble
La page **Réconciliation Caisse** permet de clôturer quotidiennement la caisse en comparant les montants calculés (basés sur les opérations enregistrées) avec les montants réellement comptés par le caissier.

## Accès
- Menu: **Finance** → **Réconciliation**
- URL: `/caisse/reconciliation.php`
- Permission requise: `CAISSE_LIRE` (+ `CAISSE_ECRIRE` pour valider)

## Workflow quotidien

### 1. Sélectionner la date
- Par défaut: date du jour
- Navigation: jour précédent | aujourd'hui
- Charger les données de la date choisie

### 2. Vérifier les KPIs calculés
4 indicateurs automatiques basés sur `journal_caisse`:
- **Recettes**: Total des encaissements (RECETTE, non annulées)
- **Dépenses**: Total des sorties (DEPENSE, non annulées)
- **Solde attendu**: Recettes - Dépenses
- **Opérations**: Nombre total (ventes | annulations)

### 3. Déclarer les montants comptés
Le caissier saisit les montants **réellement comptés** par mode:
- 💰 **Espèces**: Argent liquide en caisse
- 💳 **Chèques**: Total des chèques reçus
- 🏦 **Virements bancaires**: Confirmés en banque
- 📱 **Mobile Money**: MTN/Orange Money

### 4. Analyser l'écart (automatique)
**Calcul en temps réel:**
```
Écart = Total déclaré - Solde attendu
```

- ✅ **Écart = 0**: Parfait, aucune différence
- ⬆️ **Écart > 0**: Excédent (plus d'argent que prévu)
- ⬇️ **Écart < 0**: Déficit (moins d'argent que prévu)

### 5. Justifier l'écart (si nécessaire)
Si écart ≠ 0:
- Zone **Justification de l'écart** obligatoire
- Exemples:
  - "Erreur de comptage espèces"
  - "Chèque non encaissé comptabilisé"
  - "Arrondi sur monnaie rendue"

### 6. Choisir l'action
Deux options:
- **Sauvegarder brouillon**: Enregistre sans valider (statut `BROUILLON`)
  - Permet modifications ultérieures
  - Pas définitif
- **Valider la clôture définitivement**: Statut `VALIDE`
  - ⚠️ **Action irréversible**
  - Confirmation obligatoire
  - Verrouille la clôture

## États d'une clôture

| Statut | Badge | Description |
|--------|-------|-------------|
| `BROUILLON` | 🟡 Jaune | En cours, modifiable |
| `VALIDE` | 🟢 Vert | Définitive, verrouillée |
| `ANNULE` | ⚫ Gris | Annulée (rare) |

## Sections complémentaires

### Répartition par mode de paiement
Tableau détaillé des recettes par mode (calculé):
- Espèces: X FCFA
- Virements: Y FCFA
- Mobile: Z FCFA
- Total: XX FCFA

### Historique des clôtures
Les 10 dernières clôtures:
- Date
- Caissier
- Solde
- Écart
- Statut
- Lien vers détail

### Dernières opérations du jour
20 opérations les plus récentes:
- N° pièce
- Nature
- Client
- Mode paiement
- Montant (vert RECETTE / rouge DEPENSE)
- Statut

## Bonnes pratiques

### ✅ À faire
- Clôturer **chaque jour** avant fermeture
- Vérifier la **répartition par mode** avant de valider
- **Justifier tout écart** même minime
- Faire un **brouillon** si incertain, valider plus tard
- Consulter l'**historique** pour détecter anomalies récurrentes

### ❌ À éviter
- Valider sans vérifier les montants
- Laisser un écart sans justification
- Modifier `journal_caisse` après clôture validée
- Clôturer plusieurs jours en retard

## Données de test

### Créer des données
```bash
php create_test_reconciliation_data.php
```

Génère 10 opérations (recettes + dépenses) pour aujourd'hui.

### Tester le workflow
```bash
php test_workflow_cloture.php
```

Simule une clôture complète (brouillon → validation).

## Dépannage

### Écart inexpliqué
1. Vérifier le **tableau opérations** du jour
2. Chercher opérations annulées (barrées)
3. Comparer avec **journal caisse** complet
4. Vérifier les modes de paiement (erreur d'attribution?)

### Clôture validée par erreur
⚠️ **Impossible de modifier** une clôture `VALIDE`.
Solution: Contacter administrateur technique pour correction SQL directe.

### Pas de données pour la date
- Aucune opération dans `journal_caisse` pour cette date
- KPIs à 0
- Clôture possible avec montants déclarés = 0

## Tables SQL

### `caisses_clotures`
Stocke les clôtures quotidiennes.

**Colonnes principales:**
- `date_cloture`: Date de la journée
- `total_recettes`, `total_depenses`, `solde_calcule`: Calculés
- `montant_especes_declare`, `montant_cheques_declare`, etc.: Déclarés
- `total_declare`: Somme des déclarés
- `ecart`: `total_declare - solde_calcule`
- `justification_ecart`: Texte libre
- `statut`: BROUILLON | VALIDE | ANNULE
- `caissier_id`, `validateur_id`: Traçabilité

### `journal_caisse`
Source des opérations (recettes/dépenses).

**Colonnes utilisées:**
- `date_operation`: Date de l'opération
- `sens`: RECETTE | DEPENSE
- `montant`: Montant en FCFA
- `mode_paiement_id`: Lien vers `modes_paiement`
- `est_annule`: 0 (active) | 1 (annulée)
- `vente_id`: Lien vers vente si applicable

## Support
Pour toute question: voir `caisse/reconciliation.php` (commentaires dans le code)
