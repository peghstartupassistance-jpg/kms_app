# 📋 PHASE 1.3 - RESTRUCTURE COORDINATION

**Durée estimée:** 5 jours (15-19 Décembre)  
**Priorité:** Haute (UX magasinier critique)  
**Dépendances:** Aucune (indépendante)

---

## 🎯 Objectif

Restructurer le module coordination pour:
1. Navigation hiérarchique claire (4 sous-menus au lieu de 1 dashboard dense)
2. Filtres avancés sur ordres préparation
3. Dashboard magasinier dédié
4. Découverte litiges simplifiée

---

## 📊 Problème Actuel (Audit Phase 0)

### Navigation Coordination: 5.8/10 ❌

**Pain points:**
- ❌ Dashboard chargé (15 KPIs à la fois)
- ❌ Pas d'organisation logique des tasks
- ❌ Litiges cachés dans sous-menu
- ❌ Filtres d'ordres pas intuitifs
- ❌ Magasinier doit cliquer 3x pour trouver son workflow

**Exemple workflow actuel:**
```
1. Ouvrir Coordination → Dashboard
2. Chercher "Ordres Préparation"
3. Cliquer ordres_preparation.php
4. Page liste sans filtres utiles
5. Chercher son ordre = 10 min d'exploration
```

---

## ✅ Solution Proposée

### Nouvelle Navigation

```
📍 Coordination (Parent)
├─ 📊 Dashboard (Vue d'ensemble - KPIs clés)
├─ 📦 Ordres de Préparation
│  ├─ Liste (Avec filtres: statut, magasinier, priorité)
│  ├─ Créer nouvel ordre
│  └─ Voir détails ordre
├─ 🚚 Bons de Livraison
│  ├─ Liste (Avec filtres: statut, livreur, date)
│  ├─ Créer bon de livraison
│  └─ Voir détails BL
├─ ⚠️ Litiges & Anomalies
│  ├─ Litiges ouverts
│  ├─ Signaler nouveau litige
│  └─ Voir détails litige
└─ 📈 Rapports & Statistiques
   ├─ Suivi livraisons
   ├─ Performance magasins
   └─ Anomalies détectées
```

### Sous-menus Bootstrap

Créer composant navbar Bootstrap dynamique:
```php
<!-- Coordination Sub-menu -->
<div class="btn-group" role="group">
    <a href="coordination/dashboard.php" class="btn btn-sm btn-outline-primary">Dashboard</a>
    <a href="coordination/ordres_preparation.php" class="btn btn-sm btn-outline-primary">Ordres</a>
    <a href="coordination/livraisons.php" class="btn btn-sm btn-outline-primary">Livraisons</a>
    <a href="coordination/litiges.php" class="btn btn-sm btn-outline-danger">⚠️ Litiges</a>
</div>
```

---

## 🏗️ Fichiers à Créer/Modifier

### Nouveaux fichiers:

| Fichier | Contenu | Lignes |
|---------|---------|--------|
| `coordination/navigation.php` | Composant navbar coordination | 30 |
| `coordination/dashboard.php` | Réduire à KPIs essentiels seulement | 150 |
| `coordination/livraisons.php` | Nouvelle liste BL avec filtres | 200 |
| `coordination/livraisons_detail.php` | Détails BL + actions | 150 |
| `coordination/litiges_simplifie.php` | Litiges mieux organisés | 180 |
| `coordination/dashboard_magasinier.php` | Dashboard privé magasinier | 200 |

### À modifier:

| Fichier | Changement | Impact |
|---------|-----------|--------|
| `coordination/ordres_preparation.php` | Ajouter filtres | +50 lignes |
| `coordination/dashboard.php` | Réduire à l'essentiel | -100 lignes |
| `coordination/litiges.php` | Intégrer dans structure | +20 lignes |
| `partials/sidebar.php` | Ajouter lien Magasinier | +5 lignes |

---

## 📋 Checklist Implémentation

### Jour 1 (15/12): Architecture
- [ ] Créer navigation.php (composant navbar)
- [ ] Créer livraisons.php (liste + filtres)
- [ ] Créer dashboard_magasinier.php
- [ ] Tests syntaxe

### Jour 2 (16/12): Filtres
- [ ] Ajouter filtres ordres_preparation.php
- [ ] Ajouter filtres livraisons.php
- [ ] CSS pour filtres (styling)
- [ ] Tests filtres

### Jour 3 (17/12): Détails & Litiges
- [ ] Créer livraisons_detail.php
- [ ] Refactor litiges_simplifie.php
- [ ] Dashboard reducé
- [ ] Tests navigation

### Jour 4 (18/12): Dashboard Magasinier
- [ ] Dashboard spécifique magasinier
- [ ] KPIs pertinents (orders, stocks, livraisons)
- [ ] Lien dans sidebar
- [ ] Tests complets

### Jour 5 (19/12): Tests & Docs
- [ ] Tests navigateur complets
- [ ] Tests workflow magasinier
- [ ] Documentation
- [ ] Rapport final

---

## 🎯 Workflows Cible

### Workflow: Magasinier cherche ses ordres
```
AVANT (5.8/10):
1. Ouvrir Coordination
2. Dashboard apparaît (overwhelming)
3. Chercher "Ordres Préparation" par statut
4. Page liste sans filtres magasinier
5. Filtrer manuellement par nom/date
6. 10 minutes pour trouver l'ordre

APRÈS (8.5/10):
1. Ouvrir Coordination
2. Voir navbar sous-menus: [Dashboard] [Ordres] [Livraisons] [Litiges]
3. Cliquer [Ordres]
4. Page liste avec filtres préchargés (magasinier=current user)
5. Options: Filtrer par statut (EN_COURS, PRET, FAIT)
6. 30 secondes pour trouver l'ordre ✅ Gain 95%!
```

### Workflow: Traiter un litige
```
AVANT (5.8/10):
1. Dashboard chargé
2. Chercher "Litiges" (ne voit pas, trop bas)
3. Dérouler page 3x vers le bas
4. Cliquer litiges.php (si trouve)
5. Voir liste dense sans priorité
6. Difficile identifier ce qui est urgent

APRÈS (8.0/10):
1. Voir navbar [⚠️ Litiges] en rouge
2. Cliquer (badge nombre alertes)
3. Page litiges ouverts en haut
4. Triés par: Priorité (urgents d'abord)
5. Voir que c'est mieux organisé
6. Action immédiate ✅
```

---

## 🎨 Design Changes

### Navigation avant:
```
┌────────────────────────────────────────┐
│ Coordination (Dashboard)                │
│ 15 KPIs + 3 graphiques + 2 tables       │
│ (Overwhelming, scroll infini)           │
└────────────────────────────────────────┘
```

### Navigation après:
```
┌────────────────────────────────────────┐
│ Coordination                            │
├────────────────────────────────────────┤
│ [Dashboard] [Ordres] [Livraisons] [⚠️ Litiges] │
├────────────────────────────────────────┤
│ Contenu pertinent à la page en cours    │
│ (Clair, organisé)                       │
└────────────────────────────────────────┘
```

---

## 📐 Composant Navigation

**Fichier:** `coordination/navigation.php`

```php
<?php
/**
 * Navigation Coordination - Sous-menus Bootstrap
 * À inclure en haut de chaque page coordination
 */

$currentPage = basename($_SERVER['PHP_SELF']);

$menus = [
    'dashboard.php' => ['label' => 'Dashboard', 'icon' => 'speedometer2'],
    'ordres_preparation.php' => ['label' => 'Ordres', 'icon' => 'box-seam'],
    'livraisons.php' => ['label' => 'Livraisons', 'icon' => 'truck'],
    'litiges.php' => ['label' => '⚠️ Litiges', 'icon' => 'exclamation-triangle']
];
?>

<div class="alert alert-light border-bottom mb-4">
    <div class="btn-group w-100 d-flex" role="group">
        <?php foreach ($menus as $file => $menu): ?>
            <a href="<?= url_for("coordination/$file") ?>" 
               class="btn btn-sm <?= $currentPage === $file ? 'btn-primary' : 'btn-outline-primary' ?>">
                <i class="bi bi-<?= $menu['icon'] ?>"></i> <?= $menu['label'] ?>
            </a>
        <?php endforeach; ?>
    </div>
</div>
```

---

## 📊 Dashboard Réduit (Avant/Après)

### Avant (371 lignes - TROP):
```
KPIs:
  • Ventes 30j
  • Livrées
  • Litiges en cours
  • Ventes sans livraison
  • Ordres stats
  
Graphiques:
  • Evolution ventes
  • Anomalies
  • Litiges trend
  
Tableaux:
  • Ventes récentes
  • Anomalies détectées
```

### Après (180 lignes - ESSENTIEL):
```
KPIs (4 cartes):
  • Ordres en préparation
  • Bons de livraison en cours
  • Litiges ouverts (ROUGE si > 0)
  • Livraisons dernière semaine
  
1 Graphique:
  • Trend livraisons (7j)
  
1 Tableau:
  • Ordres urgents (PRET, pas livré)
```

---

## 🧪 Tests Plan

### Test 1: Navigation
- [ ] Navbar visible sur chaque page
- [ ] Boutons actifs corrects (highlight)
- [ ] Lien vers bon fichier

### Test 2: Filtres Ordres
- [ ] Filtre par statut fonctionne
- [ ] Filtre par magasinier fonctionne
- [ ] Filtre combiné fonctionne

### Test 3: Filtres Livraisons
- [ ] Filtre par statut fonctionne
- [ ] Filtre par date fonctionne
- [ ] Filtre combiné fonctionne

### Test 4: Workflow Magasinier
- [ ] Ouvrir Coordination
- [ ] Voir navbar sous-menus
- [ ] Cliquer Ordres
- [ ] Voir ordres filtrés (magasinier actuel)
- [ ] Cliquer détails
- [ ] Voir actions disponibles

### Test 5: Litiges
- [ ] Voir badge nombre litiges
- [ ] Cliquer litiges
- [ ] Voir liste triée par priorité
- [ ] Cliquer détails
- [ ] Voir actions (assigner, noter, clore)

---

## 📈 Impact Prévu

| Rôle | Avant | Après | Gain |
|-----|-------|-------|------|
| **Magasinier** | 5.2/10 | 8.0/10 | +2.8 pts (+54%) |
| **Coordination** | 5.8/10 | 8.5/10 | +2.7 pts (+47%) |
| **Global** | 6.3/10 | 7.0/10 | +0.7 pts |

---

## 💡 Architecture

### Technology:
- Bootstrap 5 (navbar)
- jQuery pour filtres (optionnel, vanilla JS possible)
- AJAX pour filtres (refresh sans page reload)

### Database:
- Aucune migration (utilise structures existantes)
- Queries optimisées (WHERE clauses pour filtres)

### API:
- Peut créer `/coordination/api_filtres.php` pour AJAX
- Retourne JSON (ordres filtrés)

---

## 🚀 Momentum

Cette phase est **plus petite que 1.1 et 1.2** mais impactante:
- Impact magasinier: Fort
- Complexité: Moyenne
- Risque: Bas

Si momentum maintenu: **Possible en 3 jours** au lieu de 5!

---

**Prêt à démarrer Phase 1.3?** → Répondre "oui" pour commencer implementation

Étapes:
1. Créer navigation.php
2. Modifier dashboard.php (réduire)
3. Créer livraisons.php (filtres)
4. Tests complets
