# SYNCHRONISATION COMPLÈTE MÉTIER
## Stock • Caisse • Comptabilité

**Date** : 14 décembre 2025  
**État** : ✅ Phase 3-5 Implémentée  
**Couverture** : Litiges, Retours, Remboursements, Remplacements, Avoirs

---

## 1. PRINCIPES FONDAMENTAUX

### 1.1 Atomicité transactionnelle
Chaque opération métier est une **TRANSACTION englobante** qui garantit que si une partie échoue, tout est annulé. Aucune opération isolée n'est exécutée.

### 1.2 Sources de vérité
- **Stock** : Table `stocks_mouvements` via `lib/stock.php`
- **Caisse** : Table `journal_caisse` via `lib/caisse.php`
- **Compta** : Tables `compta_pieces` + `compta_ecritures` via `lib/compta.php`

### 1.3 Trace et traçabilité
Chaque mouvement inclut :
- Identifiant de l'opération source (ex: `litige_id`, `vente_id`)
- Raison/libellé descriptif
- Date/heure horodatée
- Utilisateur responsable

### 1.4 Validation métier
Avant d'exécuter, les fonctions valident :
- L'existence de l'opération source
- L'état du système (exercice comptable ouvert, stock suffisant, etc.)
- Les règles métier (statuts valides, montants cohérents, etc.)

---

## 2. OPÉRATIONS MÉTIER SYNCHRONISÉES

### 2.1 Création de litige
**Flux** : Enregistrement du litige + Mouvement retour stock (optionnel)

**Points de synchronisation** :
```
┌─────────────────────────────────────────┐
│ 1. Créer retours_litiges (état EN_COURS) │
│ 2. Si quantité_retournée > 0:            │
│    └─ Enregistrer mouvement ENTREE stock │
└─────────────────────────────────────────┘
```

**Trace exploitable** :
- `retours_litiges.id` → Identifiant unique
- `stocks_mouvements.raison` → "Retour client - Litige #123 - ..."
- Lien vers `vente_id` original

**API** : `POST /coordination/api/litiges_create.php`

---

### 2.2 Résolution avec REMBOURSEMENT
**Flux** : Sortie caisse + Écriture comptable RRR

**Points de synchronisation** :
```
┌──────────────────────────────────────────────────┐
│ 1. Enregistrer sortie caisse                      │
│    (journal_caisse.type_operation =               │
│     'REMBOURSEMENT_CLIENT_LITIGE')                │
│ 2. Créer pièce comptable "REMB-..."              │
│    Débit  : 411001 (Dettes clients)              │
│    Crédit : 512001 (Banque/Caisse)               │
│ 3. Marquer litige REMBOURSEMENT_EFFECTUE         │
│    + montant_rembourse = XXX FCFA                │
└──────────────────────────────────────────────────┘
```

**Trace exploitable** :
- `journal_caisse` → Sortie de trésorerie
- `compta_pieces.numero_piece` → "REMB-2025-12-14-00001"
- `compta_ecritures` → Lignes débit/crédit
- `retours_litiges.montant_rembourse` → Montant accordé

**API** : `POST /coordination/api/litiges_update.php` avec `statut=REMBOURSEMENT_EFFECTUE`

---

### 2.3 Résolution avec REMPLACEMENT
**Flux** : Retour stock + Sortie stock (compensation) + Pas d'écriture compta

**Points de synchronisation** :
```
┌──────────────────────────────────────────────────┐
│ 1. Enregistrer ENTREE stock (produit retourné)   │
│    Raison: "Retour produit défectueux - ..."     │
│ 2. Enregistrer SORTIE stock (produit remplacé)   │
│    Raison: "Livraison remplacement - ..."        │
│    → Quantités compensées = Aucun impact compta  │
│ 3. Marquer litige REMPLACEMENT_EFFECTUE          │
└──────────────────────────────────────────────────┘
```

**Trace exploitable** :
- `stocks_mouvements` (2 lignes opposées)
- Stock net invariant (IN = OUT)
- Pas d'impact caisse/compta

**API** : `POST /coordination/api/litiges_update.php` avec `statut=REMPLACEMENT_EFFECTUE`

---

### 2.4 Résolution avec AVOIR/RRR
**Flux** : Écriture comptable réduction créance client

**Points de synchronisation** :
```
┌──────────────────────────────────────────────────┐
│ 1. Créer pièce comptable "AVOIR-..."             │
│    Débit  : 411001 (Dettes clients)              │
│    Crédit : 701001 (RRR Clients)                 │
│ 2. Marquer litige RESOLU                         │
│    + montant_avoir = XXX FCFA                    │
│ 3. Aucun mouvement caisse ni stock               │
│    (compensation intra-compta)                   │
└──────────────────────────────────────────────────┘
```

**Trace exploitable** :
- `compta_pieces.numero_piece` → "AVOIR-2025-12-14-00001"
- `compta_ecritures` → Débit/crédit 411/701
- `retours_litiges.montant_avoir` → Montant accordé

**API** : `POST /coordination/api/litiges_update.php` avec `statut=RESOLU` + montant_avoir

---

### 2.5 Abandon de litige
**Flux** : Marquage statut uniquement (aucun impact financier)

**Trace exploitable** :
- `retours_litiges.statut_traitement = 'ABANDONNE'`
- `retours_litiges.solution` → Raison abandon

**API** : `POST /coordination/api/litiges_update.php` avec `statut=ABANDONNE`

---

## 3. VISUALISATION SYNCHRONISATION

### Page dédiée : `/coordination/litiges_synchronisation.php?id=123`

Affiche pour chaque litige :
1. **Informations litige** (client, produit, motif, statut)
2. **Tab Stock** → Tous les mouvements `stocks_mouvements` liés
3. **Tab Caisse** → Toutes les opérations `journal_caisse` liées
4. **Tab Compta** → Toutes les écritures `compta_ecritures` liées
5. **Vérification cohérence** → Check-list visuelle

```
┌─────────────────────────────────────────┐
│ Litige #123 → REMBOURSEMENT_EFFECTUE     │
├─────────────────────────────────────────┤
│ ✅ Stock     : 1 mouvement ENTREE        │
│ ✅ Caisse    : Remboursement 150k FCFA  │
│ ✅ Compta    : Pièce REMB-2025-12-14    │
│              Débit 411 / Crédit 512      │
└─────────────────────────────────────────┘
```

---

## 4. AUDIT AUTOMATISÉ

### API : `/coordination/api/audit_synchronisation.php`

Retourne JSON avec 6 audits :

1. **Litiges sans trace stock** → Liste des retours non enregistrés
2. **Litiges sans trace caisse** → Remboursements sans sortie caisse
3. **Litiges sans trace compta** → Avoirs/RRR sans écriture
4. **Stocks orphelins** → Retours sans lien litige/vente
5. **Remboursements orphelins** → Opérations caisse sans litige
6. **Compta orpheline** → Écritures RRR sans pièce

+ **Statistiques globales** :
  - Litiges par statut
  - Total mouvements stock "retour"
  - Total remboursements caisse
  - Total écritures RRR

**Usage** :
```bash
curl http://localhost/kms_app/coordination/api/audit_synchronisation.php
```

---

## 5. STRUCTURE BASE DE DONNÉES

### retours_litiges
```sql
id (PK)
date_retour
client_id (FK)
produit_id (FK)
vente_id (FK, optionnel)
motif (TEXT)
type_probleme (ENUM)
responsable_suivi_id (FK)
statut_traitement (EN_COURS | RESOLU | REMPLACEMENT_EFFECTUE | 
                   REMBOURSEMENT_EFFECTUE | ABANDONNE)
solution (TEXT)
montant_rembourse (DECIMAL 15,2)
montant_avoir (DECIMAL 15,2)
date_resolution (DATETIME)
```

### stocks_mouvements
```sql
Raison inclut: "Litige #XYZ", "Retour client", "Remplacement"
↓
Permet filtrage: WHERE raison LIKE '%Litige #123%'
```

### journal_caisse
```sql
type_operation = 'REMBOURSEMENT_CLIENT_LITIGE'
libelle inclut: "Remboursement client litige #XYZ"
↓
Permet filtrage: WHERE type_operation = '...' OR libelle LIKE '%litige #%'
```

### compta_pieces + compta_ecritures
```sql
numero_piece = 'REMB-2025-12-14-...' ou 'AVOIR-2025-12-14-...'
libelle inclut: "Remboursement client suite litige #XYZ"
↓
Permet filtrage: WHERE numero_piece LIKE 'REMB-%' OR libelle LIKE '%litige%'
```

---

## 6. INTÉGRATION AVEC MODULES EXISTANTS

### Module Stock (`lib/stock.php`)
- Fonction `stock_enregistrer_mouvement()` appelée
- Respecte try/catch/finally pour transaction
- Retourne succès ou lève Exception

### Module Caisse (`lib/caisse.php`)
- Fonction `caisse_enregistrer_operation()` appelée
- Enregistre dans `journal_caisse` (source unique)
- Sens ENTREE/SORTIE converti en RECETTE/DEPENSE

### Module Compta (`lib/compta.php`)
- Fonctions `compta_get_exercice_actif()` / INSERT directe
- Crée pièce et écritures
- Débit/Crédit avec montants positifs et sens implicite

---

## 7. CHECKLIST DE COHÉRENCE

**Pour chaque litige** :

- [ ] Si `statut_traitement = REMBOURSEMENT_EFFECTUE` :
  - [ ] Montant `montant_rembourse > 0`
  - [ ] Existe 1+ mouvements `journal_caisse` REMBOURSEMENT_CLIENT_LITIGE
  - [ ] Existe 1 pièce compta `REMB-*`
  - [ ] Pièce compta est équilibrée (débit = crédit)

- [ ] Si `statut_traitement = REMPLACEMENT_EFFECTUE` :
  - [ ] Existe 2+ mouvements stock (1 ENTREE + 1 SORTIE)
  - [ ] Quantités ENTREE = SORTIE
  - [ ] Aucune écriture compta créée (compensation)
  - [ ] `montant_rembourse = 0`, `montant_avoir = 0`

- [ ] Si `statut_traitement = RESOLU` (avec avoir) :
  - [ ] Montant `montant_avoir > 0`
  - [ ] Existe 1 pièce compta `AVOIR-*`
  - [ ] Pièce compta est équilibrée (débit = crédit)
  - [ ] Accounts 411/701 utilisés

- [ ] Si `statut_traitement = ABANDONNE` :
  - [ ] Pas de mouvements stock après litige
  - [ ] Pas d'opérations caisse
  - [ ] Pas d'écritures compta

- [ ] Si `statut_traitement = EN_COURS` :
  - [ ] `montant_rembourse = 0`, `montant_avoir = 0`
  - [ ] `date_resolution = NULL`
  - [ ] Aucune trace caisse/compta (sauf si stockage partiel)

---

## 8. COMMANDES AUDIT

### Vérifier synchronisation d'un litige
```bash
curl 'http://localhost/kms_app/coordination/litiges_synchronisation.php?id=123'
```

### Générer rapport d'audit complet
```bash
curl 'http://localhost/kms_app/coordination/api/audit_synchronisation.php' | jq
```

### Vérifier via CLI
```sql
-- Litiges avec remboursement mais sans trace caisse
SELECT rl.id, rl.montant_rembourse
FROM retours_litiges rl
WHERE rl.montant_rembourse > 0
  AND NOT EXISTS (
    SELECT 1 FROM journal_caisse jc
    WHERE jc.type_operation = 'REMBOURSEMENT_CLIENT_LITIGE'
      OR jc.libelle LIKE CONCAT('%litige #', rl.id, '%')
  );

-- Mouvements stock non liés
SELECT sm.id, sm.raison, sm.type_mouvement, sm.quantite
FROM stocks_mouvements sm
WHERE sm.raison LIKE '%Retour%'
  AND sm.raison NOT LIKE '%Litige%'
  AND sm.raison NOT LIKE '%vente%';
```

---

## 9. POINTS CRITIQUES

### ✅ Implémenté
- [x] API `litiges_creer_avec_retour()` → Création + stock
- [x] API `litiges_resoudre_avec_remboursement()` → Remboursement + caisse + compta
- [x] API `litiges_resoudre_avec_remplacement()` → Remplacement stock (compensation)
- [x] API `litiges_resoudre_avec_avoir()` → Avoir compta
- [x] Page visualisation `/coordination/litiges_synchronisation.php`
- [x] Audit `/coordination/api/audit_synchronisation.php`
- [x] Endpoints API update/create avec dispatching

### ⚠️ À tester
- [ ] Création litige avec retour quantité
- [ ] Remboursement : caisse + compta liés
- [ ] Remplacement : stock compensation
- [ ] Avoir : réduction créance
- [ ] Audit : détection anomalies
- [ ] Workflow complet client

### 🔄 Évolutions futures
- Paiement partiel remboursement
- Retours en plusieurs tranches
- Révision de solution
- Historique complet traçabilité
- Rapports KPI par client
- Export audit vers fichier

---

## 10. DÉPLOIEMENT

1. **Inclure lib** : `require_once __DIR__ . '/../lib/litiges.php';` dans les pages
2. **Tester API audit** : Vérifier aucune anomalie
3. **Valider workflows** : End-to-end par statut
4. **Documenter** : README utilisateur
5. **Former** : Utilisateurs sur workflow
6. **Monitorer** : Logs + audit hebdo

