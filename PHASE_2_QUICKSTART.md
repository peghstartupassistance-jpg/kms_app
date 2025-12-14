# Phase 2 - Global Ventes/Achats Transactions: Quick Start Guide

**Objective:** Wrap entire vente and achat creation/modification flows in single atomic transaction to prevent partial updates and ensure stock+caisse+compta synchronization.

**Blocking Issues Addressed:** None remaining from Phase 1 ✅

---

## Phase 2 Scope

### Critical Files to Fix

1. **ventes/edit.php** (Lines 63-170)
   - **Issue:** Calls `commit()/rollBack()` without `beginTransaction()`
   - **Impact:** Corrupts PDO transaction state
   - **Fix:** Wrap all stock+caisse+compta operations in single transaction

2. **ventes/list.php** (If contains create functionality)
   - **Issue:** May have similar transaction pattern
   - **Fix:** Ensure all writes wrapped in transaction

3. **achats/edit.php** (If exists, similar to ventes/edit)
   - **Issue:** Likely has same problem
   - **Fix:** Same pattern as ventes/edit

### Operations to Wrap in Transaction

For each vente creation/modification:
```php
1. Validate input (BEFORE transaction)
2. Fetch vente and lignes (BEFORE transaction)
3. BEGIN TRANSACTION
   - Update/Insert vente
   - Update ventes_lignes
   - Call stock_synchroniser_vente()
   - Call caisse_enregistrer_ecriture() (for auto-encaissement)
   - Call compta_creer_ecritures_vente()
   - Handle BL generation if statut = LIVREE
4. COMMIT or ROLLBACK
5. Redirect/Flash message
```

### Key Principles

1. **All or Nothing:** If any step fails, entire operation rolls back
2. **No Orphaned Data:** No partial vente + stock + caisse entries
3. **Consistent State:** ventes_lignes quantities match stock movements
4. **Atomic Compta:** Journal entries created only once, in order

---

## Implementation Pattern

```php
<?php
// ventes/edit.php - PATTERN

require_once __DIR__ . '/../security.php';
exigerConnexion();
exigerPermission('VENTES_CREER');
global $pdo;

$id = $_GET['id'] ?? null;
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // ✅ PHASE 1: Validate input (NO database writes yet)
    $numero = trim($_POST['numero'] ?? '');
    $client_id = (int)($_POST['client_id'] ?? 0);
    $montant = (float)($_POST['montant_total_ttc'] ?? 0);
    $statut = $_POST['statut'] ?? 'EN_COURS';
    
    // Validate
    if (!$numero) $errors[] = 'Numéro requis';
    if ($client_id <= 0) $errors[] = 'Client requis';
    if ($montant <= 0) $errors[] = 'Montant doit être positif';
    if (!in_array($statut, ['EN_COURS', 'VALIDEE', 'LIVREE'])) $errors[] = 'Statut invalide';
    
    // ✅ PHASE 2: Fetch existing data (NO transaction yet)
    if ($id) {
        $stmt = $pdo->prepare("SELECT id, statut FROM ventes WHERE id = ?");
        $stmt->execute([$id]);
        $vente = $stmt->fetch();
        if (!$vente) $errors[] = 'Vente inexistante';
    }
    
    // ✅ PHASE 3: Check for ventes_lignes if modifying
    if ($id && !$errors) {
        $stmt = $pdo->prepare("SELECT id, quantite FROM ventes_lignes WHERE vente_id = ?");
        $stmt->execute([$id]);
        $lignes = $stmt->fetchAll();
        if (!$lignes) $errors[] = 'Vente doit avoir des lignes';
    }
    
    // ✅ Only proceed if no validation errors
    if (!$errors) {
        // ═══════════════════════════════════════════════════════════════
        // ✅ PHASE 4: TRANSACTION - All database writes
        // ═══════════════════════════════════════════════════════════════
        
        try {
            $pdo->beginTransaction();
            
            if ($id) {
                // Modifier vente existante
                $stmt = $pdo->prepare("
                    UPDATE ventes 
                    SET numero = :numero, client_id = :client_id, 
                        montant_total_ttc = :montant, statut = :statut,
                        date_modification = NOW()
                    WHERE id = :id
                ");
                $stmt->execute([
                    ':numero' => $numero,
                    ':client_id' => $client_id,
                    ':montant' => $montant,
                    ':statut' => $statut,
                    ':id' => $id,
                ]);
            } else {
                // Créer nouvelle vente
                $stmt = $pdo->prepare("
                    INSERT INTO ventes 
                    (numero, client_id, montant_total_ttc, statut, date_vente, date_creation)
                    VALUES (:numero, :client_id, :montant, :statut, NOW(), NOW())
                ");
                $stmt->execute([
                    ':numero' => $numero,
                    ':client_id' => $client_id,
                    ':montant' => $montant,
                    ':statut' => $statut,
                ]);
                $id = (int)$pdo->lastInsertId();
            }
            
            // ✅ Stock sync (wrapped in same transaction!)
            require_once __DIR__ . '/../lib/stock.php';
            stock_synchroniser_vente($pdo, $id);
            
            // ✅ Caisse sync (if auto-encaissement flag set)
            if ($_POST['auto_encaissement'] ?? false) {
                require_once __DIR__ . '/../lib/caisse.php';
                caisse_enregistrer_ecriture(
                    $pdo,
                    'RECETTE',
                    $montant,
                    'VENTE',
                    $id,
                    'Encaissement auto vente #' . $numero,
                    $_SESSION['user_id'] ?? null,
                    null,
                    $id  // vente_id
                );
            }
            
            // ✅ Compta sync (if enabled)
            if ($_POST['auto_compta'] ?? false) {
                require_once __DIR__ . '/../lib/compta.php';
                compta_creer_ecritures_vente($pdo, $id);
            }
            
            // ✅ BL generation (if LIVREE status)
            if ($statut === 'LIVREE' && !$id) {
                // Only on new ventes
                require_once __DIR__ . '/../lib/bons_livraison.php';
                bl_generer_depuis_vente($pdo, $id);
            }
            
            // ═══════════════════════════════════════════════════════════════
            // ✅ Transaction succeeded - commit all changes
            $pdo->commit();
            
            // Flash message and redirect
            $_SESSION['flash'] = [
                'type' => 'success',
                'message' => ($id ? 'Vente modifiée' : 'Vente créée') . ' avec succès'
            ];
            header('Location: ' . url_for('ventes/detail.php?id=' . $id));
            exit();
            
        } catch (Exception $e) {
            // ═══════════════════════════════════════════════════════════════
            // ✅ Transaction failed - rollback ALL changes
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            
            $errors[] = 'Erreur système: ' . $e->getMessage();
            error_log('[VENTES/EDIT] ' . $e->getMessage());
        }
    }
}

// Display form with errors
?>
```

---

## Validation Checklist for Phase 2

### Code Pattern Checks
- [ ] All validations BEFORE `beginTransaction()`
- [ ] All DB writes INSIDE transaction
- [ ] `try/catch` wraps transaction
- [ ] `rollBack()` called on Exception
- [ ] `commit()` called on success
- [ ] No early returns inside transaction block

### Integration Checks
- [ ] stock_synchroniser_vente() called inside transaction
- [ ] stock_synchroniser_achat() called inside transaction
- [ ] caisse_enregistrer_ecriture() called inside transaction
- [ ] compta_creer_ecritures_vente() called inside transaction
- [ ] BL generation inside transaction
- [ ] All FK constraints satisfied

### Testing Checklist
1. **Happy Path:** Create vente → verify all systems updated
2. **Partial Failure:** Simulate compta error → verify stock rolled back
3. **Concurrent Ops:** Create 2 ventes simultaneously → no deadlocks
4. **Payment Reversal:** Delete encaissement → verify caisse rolled back
5. **Stock Overflow:** Modify vente with excess quantity → verify validation before transaction

---

## Files to Create/Verify for Phase 2

```
admin/
  - test_phase_2.php          (New: Transaction atomicity tests)
  - test_concurrent_ventes.php (New: Deadlock stress test)

lib/
  - ventes_transaction_helpers.php (New: Transaction wrapper utilities)

ventes/
  - edit.php (MODIFY)          (Update with full transaction)
  - create.php (MODIFY if exists)
```

---

## Estimated Impact
- **Files Modified:** 3-5
- **Test Cases:** 10+
- **Risk Level:** Medium (affects critical vente flow)
- **Rollback Risk:** Low (can restore original files)
- **Time Estimate:** 4-6 hours (implementation + testing)

---

## Phase 2 Success Criteria

✅ **All vente creation/modification wrapped in atomic transactions**
✅ **No partial updates: stock+caisse+compta all succeed or all rollback**
✅ **No dangling transactions (verified by health check)**
✅ **Concurrent vente creations don't deadlock**
✅ **All test cases pass (10+ scenarios)**
✅ **Zero regression in existing vente flows**

---

## Next Phase

Once Phase 2 complete and validated:
- **Phase 3:** Coordination endpoint security (GET→POST, CSRF tokens)
- **Phase 4:** UI/KPI coherence fixes
- **Phase 5:** Compta workflow improvements
- **Phase 6:** Synchronization scripts
- **Phase 7:** Final smoke tests

---

**Phase 1 Status:** ✅ COMPLETE - Ready to start Phase 2
**Ready to Begin:** Yes - All blocking issues resolved
**Estimated Start:** Immediately after approval

