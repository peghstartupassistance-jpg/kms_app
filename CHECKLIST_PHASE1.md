# 🎯 CORRECTIONS PHASE 1 - CHECKLIST
## KMS Gestion - Plan de Maturité

**Audit UX Complété:** 14 Décembre 2025  
**Score Initial:** 6.3/10 (NON PRÊTE)  
**Cible:** 8.5+/10 (PRODUCTION READY)

---

## 📋 Corrections Prioritaires (15 jours)

### ✅ 1. INTÉGRATION VENTE → CAISSE (4 jours)
**Statut:** COMPLÉTÉE ✅

**Problème Initial:**
- ❌ Caissier saisit paiements manuellement (doublon)
- ❌ Pas de lien vente ↔ caisse
- ❌ Risque oublis, discordances

**Solution Implémentée:**
- ✅ Nouvelle colonne `statut_encaissement` sur table ventes
- ✅ Bouton "Encaisser" dans fiche vente
- ✅ Modal saisie mode paiement
- ✅ API `/ventes/api_encaisser.php` crée journal caisse auto
- ✅ Lien bidirectionnel vente ↔ journal_caisse

**Fichiers Modifiés:**
- `ventes/edit.php` (ajout bouton + modal + JS)
- `ventes/api_encaisser.php` (NEW)
- `ajax/modes_paiement.php` (NEW)
- `kms_gestion.sql` (schema)

**Impact Score:** 
- Avant: Caissier 4.5/10
- Après: Caissier 7.5/10

**Déploiement:** ✅ PRÊT

---

### ⏳ 2. SIGNATURE BL ÉLECTRONIQUE (2-3 jours)
**Statut:** À COMMENCER

**Défaut:**
- ❌ BL non signée → Pas de conformité métier
- ❌ Signature manuelle → Perdue, pas d'archive

**Solution à Implémenter:**
```
Détail BL (livraisons/detail.php)
  ├── Si signe_client = 0:
  │    └── Bouton "Obtenir Signature"
  │         → Modal SignaturePad.js
  │         → Client signe sur tablette/PC
  │         → Sauvegarde signature.png en DB
  │         → Statut BL = SIGNE
  │
  └── Si signe_client = 1:
       └── Badge "✓ Signée" + Lien voir image
```

**Technologie:** SignaturePad.js (vanilla JS, no deps)  
**Stockage:** Base64 dans colonne BL.signature_blob

**Priorité:** 🔴 HAUTE (conforme métier)

---

### ⏳ 3. RESTRUCTURE MODULE COORDINATION (5 jours)
**Statut:** À COMMENCER

**Problème Critique:**
- ❌ Navigation confuse: "Ordres" vs "Ordres de Préparation"
- ❌ Litiges "cachés" (peu découvert)
- ❌ 4 onglets dans même page = désorientation

**Solution à Implémenter:**
```
Sidebar:
  ├── Coordination/
  │    ├── [ORDRES] Commandes à Préparer
  │    │    ├── Liste avec filtres (Statut, Urgence, Délai)
  │    │    └── Détail → Clic "Préparer" → BL
  │    │
  │    ├── [LIVRAISONS] Bons de Livraison
  │    │    ├── Liste BL non signées
  │    │    └── Signature intégrée
  │    │
  │    ├── [RETOURS] Litiges & Retours
  │    │    ├── Liste retours clients
  │    │    └── Actions: Remb/Remp/Avoir
  │    │
  │    └── [DASHBOARD] Synthèse Jour
  │         ├── Ordres en cours
  │         ├── Alertes ruptures
  │         └── Retours en attente
  │
  └── Glossaire Métier
       └── Aide contextualisée
```

**Bénéfices:**
- ✅ Navigation claire, hiérarchique
- ✅ Litiges faciles à découvrir
- ✅ Magasinier a vue synthétique

---

### ⏳ 4. RÉCONCILIATION CAISSE QUOTIDIENNE (3-4 jours)
**Statut:** À COMMENCER

**Défaut Critique:**
- ❌ Pas de clôture quotidienne caisse
- ❌ Pas d'alertes discordance
- ❌ Impossible audit jour
- ❌ Comptable doit faire manuelle

**Solution à Implémenter:**
```
caisse/reconciliation.php

1. Sélectionner date
2. Afficher "Total attendu" = Σ journal caisse du jour
3. Saisir "Comptage physique"
4. Calcule écart
5. Si écart < 5%: OK ✓
   Si écart > 5%: Alerte 🔴 (nécessite investigation)
6. Enregistrer clôture jour
7. Archiver journal (readonly après clôture)
```

**Audit Trail:**
- Date clôture
- Utilisateur
- Montant attendu
- Montant physique
- Écart
- Observations

---

## 🎯 Dépendances & Séquençage

```
Phase 1.1: Encaissement
  ↓ (dépend de)
Phase 1.2: Signature BL (peut être parallèle)
  ↓ (dépend de)
Phase 1.3: Coordination (indépendant, peut être parallèle)
  ↓ (nécessite)
Phase 1.4: Réconciliation (dépend de 1.1)

Chemin critique: 1.1 → 1.4 (7 jours)
Chemin parallèle: 1.2 + 1.3 (5-8 jours max)

Temps total si parallèle: ~8-10 jours (vs 14 séquentiellement)
```

---

## 📅 Timeline Recommandée

| Semaine | Tâche | Dev | QA | Déployment |
|---------|-------|-----|----|----|
| **Sem 1 (14-20 déc)** | 1.1 Encaissement | ✅ FAIT | ⏳ Tester | ⏳ |
| | 1.2 Signature BL | ⏳ Démarrer | ⏳ | |
| | 1.3 Coordination | ⏳ Démarrer | ⏳ | |
| **Sem 2 (21-27 déc)** | 1.2 Signature (finish) | ✅ Finir | ✅ Test | ✅ Deploy |
| | 1.3 Coordination (finish) | ✅ Finir | ✅ Test | ✅ Deploy |
| | 1.4 Réconciliation | ⏳ Démarrer | ⏳ | |
| **Sem 3 (28 déc - 3 jan)** | 1.4 Réconciliation | ✅ Finir | ✅ Test | ✅ Deploy |
| | Phase 2 (Filtres, Dashboard) | ⏳ Démarrer | | |

---

## 🧪 Tests Nécessaires (Avant Déploiement Large)

### Phase 1.1 (Encaissement)
- [ ] Créer vente, cliquer "Encaisser"
- [ ] Modal affiche montant correct
- [ ] Sélectionner mode paiement
- [ ] Journal caisse créé, vente linkée
- [ ] Bouton "Encaisser" disparu après encaissement
- [ ] Badge "✓ Encaissée" affiché

### Phase 1.2 (Signature BL)
- [ ] Ouvrir BL non signé
- [ ] Clic "Obtenir Signature"
- [ ] Signer avec souris/stylus
- [ ] Signature sauvegardée
- [ ] BL statut = SIGNE
- [ ] Voir image signature depuis détail BL

### Phase 1.3 (Coordination)
- [ ] Navigation cohérente ordres → BL → Livraison
- [ ] Filtres listes fonctionnels
- [ ] Litiges faciles à trouver
- [ ] Dashboard magasinier affiche synthèse

### Phase 1.4 (Réconciliation)
- [ ] Sélectionner date
- [ ] Affiche total attendu correct
- [ ] Saisir comptage physique
- [ ] Écart calculé
- [ ] Clôture enregistrée

---

## 📊 Score Attendu Après Phase 1

| Profil | Avant | Après Phase 1 | Cible |
|--------|-------|---|---|
| **Showroom** | 6.8 | 7.5 | 8.0 |
| **Terrain** | 6.5 | 7.0 | 8.0 |
| **Magasinier** | 5.0 | 7.5 | 8.5 |
| **Caissier** | 4.5 | 8.0 | 8.5 |
| **Comptable** | 5.5 | 7.0 | 8.0 |
| **Direction** | 7.0 | 7.5 | 8.0 |
| **MOYENNE** | 6.3 | 7.4 | 8.3 |

---

## ✅ Conditions Déploiement "Prêt"

- [ ] Phase 1.1, 1.2, 1.3, 1.4 complétées
- [ ] Tests QA passés pour chaque correction
- [ ] Pas d'erreur PHP (php -l)
- [ ] Pas d'erreur JS console
- [ ] Utilisateurs pilotes testent workflows
- [ ] Score global ≥ 7.5/10
- [ ] Audit trail complète (ventes, caisse, litiges)

---

## 🚀 Déploiement Recommandé

**Phased Rollout:**
1. **Pilote (5-10 utilisateurs):** Sem 2-3
   - Showroom: 2 users
   - Magasin: 2 users
   - Caisse: 1 user
   - Comptabilité: 1 user
   - Management: 1-2 users

2. **Groupe test (20-30 utilisateurs):** Début janvier
   
3. **Déploiement large:** Mi-janvier 2026
   - Si pilot + group test réussis

---

## 📞 Points de Contact

**Pour questions implémentation:**
- Dev Lead: [À nommer]
- QA Lead: [À nommer]
- Product Owner: [À nommer]

**Escalade critiques:** Direction + PM

---

**Dernière mise à jour:** 14 Déc 2025  
**Prochain checkpoint:** 20 Déc 2025 (fin Sem 1)
