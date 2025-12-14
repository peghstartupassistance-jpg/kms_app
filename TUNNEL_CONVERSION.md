# Tunnel de Conversion Dynamique - KMS Gestion

## Vue d'ensemble

Le système de **tunnel de conversion dynamique** permet de modifier rapidement les statuts des entités commerciales (prospects, clients, devis, prospections) pour suivre leur progression dans le cycle de vente.

## Entités gérées

### 1. Clients (`clients`)
**Statuts disponibles :**
- 🟡 `PROSPECT` - Prospect actif
- 🟢 `CLIENT` - Client confirmé
- 🔵 `APPRENANT` - Inscrit en formation
- 🔵 `HOTE` - Client hôtel

**Fichiers concernés :**
- `clients/list.php` - Liste avec dropdown de changement
- `ajax/changer_statut.php` - Endpoint backend

### 2. Devis (`devis`)
**Statuts disponibles :**
- ⚪ `EN_ATTENTE` - Devis en attente de réponse
- 🟢 `ACCEPTE` - Devis accepté par le client
- 🔴 `REFUSE` - Devis refusé
- ⚫ `ANNULE` - Devis annulé

**Notes :**
- Les devis déjà convertis en vente ne peuvent pas changer de statut (badge fixe "CONVERTI")
- Le changement vers ACCEPTE facilite la conversion en vente

**Fichiers concernés :**
- `devis/list.php` - Liste avec dropdown de changement
- `ajax/changer_statut.php` - Endpoint backend

### 3. Prospections Terrain (`prospections_terrain`)
**Résultats disponibles :**
- 🟡 `Intéressé - à recontacter`
- 🔵 `Devis demandé`
- ⚪ `À rappeler plus tard`
- ⚫ `Non intéressé`
- 🟢 `Converti en client`
- 🔴 `Perdu`

**Fichiers concernés :**
- `terrain/prospections_list.php` - Liste avec dropdown de changement
- `ajax/changer_statut.php` - Endpoint backend

### 4. Prospects Formation (`prospects_formation`)
**Statuts disponibles :**
- 🔵 `Nouveau contact`
- 🟡 `En cours`
- 🔵 `Devis envoyé`
- 🟢 `Inscrit`
- 🔴 `Perdu`
- ⚪ `Reporté`

**Fichiers concernés :**
- (À implémenter dans `formation/` selon besoin)
- `ajax/changer_statut.php` - Endpoint backend déjà prêt

## Architecture technique

### Backend

**Fichier principal :** `ajax/changer_statut.php`

**Sécurité :**
- Vérification CSRF via header `X-CSRF-Token`
- Contrôle des permissions par entité :
  - `CLIENTS_MODIFIER` pour les clients
  - `DEVIS_MODIFIER` pour les devis
  - `TERRAIN_MODIFIER` pour les prospections
  - `FORMATION_MODIFIER` pour les prospects formation
- Validation des statuts (whitelist stricte)

**Format de requête :**
```json
POST /kms_app/ajax/changer_statut.php
Headers: {
  "Content-Type": "application/json",
  "X-CSRF-Token": "token_from_meta"
}
Body: {
  "entite": "client|devis|prospection|prospect_formation",
  "id": 123,
  "nouveau_statut": "CLIENT"
}
```

**Format de réponse :**
```json
{
  "success": true,
  "message": "Client ABC passé en statut : CLIENT",
  "nouveau_statut": "CLIENT"
}
```

### Frontend

**Fichier JavaScript :** `assets/js/tunnel-conversion.js`

**Initialisation automatique :**
```html
<div data-statut-change 
     data-entite="client" 
     data-id="123" 
     data-statut="PROSPECT">
  <!-- Sera transformé en dropdown Bootstrap par le script -->
</div>
```

**Configuration des couleurs et icônes :**
Chaque statut a sa couleur Bootstrap (`warning`, `success`, `danger`, etc.) et son icône Bootstrap Icons (`bi-person-check`, etc.).

**Fonctionnalités :**
- Dropdown Bootstrap avec couleur dynamique
- Changement AJAX sans rechargement
- Toast de confirmation
- Mise à jour visuelle immédiate
- Gestion des erreurs

### Intégration dans les pages

**1. Meta CSRF dans header :**
```php
<!-- partials/header.php -->
<meta name="csrf-token" content="<?= getCsrfToken() ?>">
```

**2. Script dans footer :**
```php
<!-- partials/footer.php -->
<script src="<?= ($appBaseUrl !== '' ? $appBaseUrl : '') ?>/assets/js/tunnel-conversion.js"></script>
```

**3. Usage dans les listes :**
```php
<td>
    <div data-statut-change 
         data-entite="client" 
         data-id="<?= (int)$c['id'] ?>" 
         data-statut="<?= htmlspecialchars($c['statut']) ?>">
    </div>
</td>
```

## Visualisation du tunnel

**Page :** `reporting/tunnel_conversion.php`

**Métriques affichées :**
- 📊 Prospects actifs (+ évolution mensuelle)
- 📊 Clients convertis (+ évolution mensuelle)
- 📊 Devis acceptés (+ taux de conversion)
- 📊 Taux de conversion global

**Visualisations :**
- Pipeline Clients (barres de progression par statut)
- Pipeline Devis (barres + montants)
- Résultats prospections terrain (cartes)
- Évolution mensuelle (tableau 3 derniers mois)

**Accès :**
- Menu latéral : **Marketing & Analyse** > **Tunnel de conversion**
- Permission requise : `REPORTING_LIRE`

## Workflow de conversion

### Scénario typique

1. **Contact initial** → Prospect créé avec statut `PROSPECT`
2. **Prospection terrain** → Résultat "Intéressé - à recontacter"
3. **Devis créé** → Statut `EN_ATTENTE`
4. **Relance** → Changement manuel vers `ACCEPTE`
5. **Conversion** → Devis devient vente, client passe en `CLIENT`

### Changements manuels

**À tout moment, l'utilisateur peut :**
- Cliquer sur le statut actuel (dropdown)
- Sélectionner un nouveau statut
- Confirmation automatique via AJAX
- Toast de succès

**Cas d'usage :**
- Prospect qui achète sans devis → Passer directement en `CLIENT`
- Devis oublié qui devient sans intérêt → Passer en `ANNULE`
- Prospection qui aboutit → Passer en "Converti en client"
- Apprenant qui devient client régulier → Passer en `CLIENT`

## Permissions requises

| Action | Permission |
|--------|-----------|
| Voir tunnel conversion | `REPORTING_LIRE` |
| Modifier statut client | `CLIENTS_MODIFIER` |
| Modifier statut devis | `DEVIS_MODIFIER` |
| Modifier prospection | `TERRAIN_MODIFIER` |
| Modifier prospect formation | `FORMATION_MODIFIER` |

## Fichiers créés/modifiés

### Nouveaux fichiers
- ✅ `ajax/changer_statut.php` - Endpoint backend
- ✅ `assets/js/tunnel-conversion.js` - Script frontend
- ✅ `reporting/tunnel_conversion.php` - Page de visualisation
- ✅ `TUNNEL_CONVERSION.md` - Documentation

### Fichiers modifiés
- ✅ `partials/header.php` - Ajout meta CSRF
- ✅ `partials/footer.php` - Inclusion du script JS
- ✅ `partials/sidebar.php` - Lien menu "Tunnel de conversion"
- ✅ `clients/list.php` - Dropdown de changement de statut
- ✅ `devis/list.php` - Dropdown de changement de statut
- ✅ `terrain/prospections_list.php` - Dropdown de changement de résultat

## Tests recommandés

### Test 1 : Changement statut client
1. Aller sur [clients/list.php](clients/list.php)
2. Cliquer sur le statut d'un client (ex: "PROSPECT")
3. Sélectionner "CLIENT"
4. Vérifier le toast de succès
5. Rafraîchir → statut bien enregistré en base

### Test 2 : Changement statut devis
1. Aller sur [devis/list.php](devis/list.php)
2. Cliquer sur un devis "EN_ATTENTE"
3. Sélectionner "ACCEPTE"
4. Vérifier la couleur change (vert)
5. Essayer de convertir en vente

### Test 3 : Tunnel de conversion
1. Aller sur [reporting/tunnel_conversion.php](reporting/tunnel_conversion.php)
2. Vérifier les 4 métriques en haut
3. Vérifier les barres de progression
4. Vérifier l'évolution mensuelle

### Test 4 : Permissions
1. Se connecter avec un utilisateur sans `CLIENTS_MODIFIER`
2. Essayer de changer un statut client
3. Vérifier l'erreur 403 ou message d'erreur

## Extensions futures

### Idées d'amélioration
- 📧 Email automatique au client quand devis passe en ACCEPTE
- 📲 Notification interne quand prospect devient CLIENT
- 📊 Graphique d'évolution en temps réel (Chart.js)
- 🔔 Alertes sur prospects "froids" (pas de changement depuis X jours)
- 🤖 Suggestions automatiques de statut (ML basique)
- 📝 Historique des changements de statut avec utilisateur et date
- 🎯 Objectifs de conversion par commercial (gamification)

## Support

Pour toute question ou bug :
- Vérifier les logs Apache/PHP
- Inspecter la console navigateur (F12) pour erreurs AJAX
- Vérifier les permissions utilisateur en base
- Consulter `security.php` pour la logique d'authentification

---

**Version :** 1.0  
**Date :** 13 décembre 2025  
**Auteur :** GitHub Copilot + KMS Team
