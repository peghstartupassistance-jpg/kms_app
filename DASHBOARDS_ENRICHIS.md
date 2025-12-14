# Dashboards Enrichis - Instructions d'installation

## Modifications apportées

### 1. Dashboard Commercial (`commercial/dashboard.php`)
**Améliorations :**
- ✅ Graphiques interactifs Chart.js (évolution CA, ventes par canal, top produits)
- ✅ KPIs enrichis (panier moyen, CA annuel, taux conversion)
- ✅ Top 5 commerciaux performants
- ✅ Design moderne avec cards shadow et icônes Bootstrap
- ✅ Lien vers tunnel de conversion
- ✅ Métriques avancées (6 derniers mois d'évolution)

### 2. Dashboard Coordination (`coordination/dashboard.php`)
**Améliorations :**
- ✅ Graphique temps réel de synchronisation
- ✅ Alertes visuelles prioritaires (anomalies critiques en rouge)
- ✅ Vue fil d'attente des ordres de préparation
- ✅ Statistiques de livraison (taux réussite, délai moyen)
- ✅ Tableau de bord litiges avec filtres rapides
- ✅ Graphique évolution anomalies (7 derniers jours)

### 3. Dashboard Marketing (`reporting/dashboard_marketing.php`)
**Améliorations :**
- ✅ Graphiques comparatifs multi-canaux
- ✅ ROI par canal (digital avec coût acquisition)
- ✅ Funnel de conversion interactif
- ✅ Heatmap performances par jour/canal
- ✅ Métriques prédictives (tendance, projection)
- ✅ Intégration tunnel de conversion

## Installation

### Étape 1: Vérifier Chart.js (déjà inclus dans les nouveaux fichiers)
Les fichiers incluent automatiquement Chart.js via CDN

### Étape 2: Tester les nouveaux dashboards

```bash
# Copier les fichiers
cp commercial/dashboard_new.php commercial/dashboard.php
cp coordination/dashboard_new.php coordination/dashboard.php  
cp reporting/dashboard_marketing_new.php reporting/dashboard_marketing.php
```

### Étape 3: Vérifier les URLs
- http://localhost/kms_app/commercial/dashboard.php
- http://localhost/kms_app/coordination/dashboard.php
- http://localhost/kms_app/reporting/dashboard_marketing.php

## Rollback (si besoin)
Les anciennes versions sont sauvegardées avec le suffixe `_old.php`

```bash
cp commercial/dashboard_old.php commercial/dashboard.php
cp coordination/dashboard_old.php coordination/dashboard.php
cp reporting/dashboard_marketing_old.php reporting/dashboard_marketing.php
```

## Nouvelles fonctionnalités clés

### Charts disponibles
1. **Évolution CA** - Line chart des 6 derniers mois
2. **Ventes par canal** - Doughnut chart avec répartition
3. **Top produits** - Horizontal bar chart
4. **Synchronisation** - Gauge chart d'état
5. **Funnel conversion** - Tunnel visuel animé
6. **ROI canaux** - Bar chart comparatif

### Interactions
- Hover sur graphiques pour détails
- Click sur cartes KPI pour filtrer
- Tooltips enrichis avec formatage FCFA
- Responsive sur mobile

### Optimisations performances
- Requêtes SQL optimisées avec indexes
- Lazy loading des graphiques
- Cache des KPIs (optionnel, à activer)
- Pagination intelligente des tableaux

## Support
En cas de problème :
1. Vérifier les logs Apache/PHP
2. F12 pour console JavaScript
3. Vérifier que toutes les tables existent
4. Restaurer depuis les backups `_old.php`
