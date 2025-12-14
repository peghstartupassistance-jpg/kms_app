<?php
/**
 * Phase 1.1 - Validation Script
 * Tests: Transaction handling in stock.php synchronisation functions
 * 
 * This script validates that:
 * 1. PDO transactions don't remain open after function calls
 * 2. Early returns don't leave dangling transactions
 * 3. Exception handling properly closes transactions
 */

require_once __DIR__ . '/../security.php';

// Redirect if not admin
if (!isset($_SESSION['user_id'])) {
    header('Location: ' . url_for('login.php'));
    exit();
}

$result = ['status' => 'OK', 'checks' => []];

try {
    global $pdo;

    // Check 1: Verify PDO transaction state
    $inTransaction = method_exists($pdo, 'inTransaction') ? $pdo->inTransaction() : false;
    $result['checks']['01_pdo_state'] = [
        'name' => 'PDO Transaction State',
        'status' => $inTransaction ? 'FAIL' : 'PASS',
        'message' => $inTransaction ? 'PDO has dangling transaction!' : 'No active transaction',
        'critical' => true
    ];
    if ($inTransaction) {
        $result['status'] = 'FAIL';
    }

    // Check 2: Test stock_synchroniser_vente() - normal case
    if (class_exists('PDO')) {
        require_once __DIR__ . '/lib/stock.php';
        
        $testVenteId = 1; // Or fetch a real vente ID
        $stmt = $pdo->prepare("SELECT id FROM ventes LIMIT 1");
        $stmt->execute();
        $vente = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($vente) {
            $testVenteId = $vente['id'];
            $inTransactionBefore = $pdo->inTransaction();
            
            // Call the function
            stock_synchroniser_vente($pdo, $testVenteId);
            
            $inTransactionAfter = $pdo->inTransaction();
            
            $result['checks']['02_vente_sync_state'] = [
                'name' => 'stock_synchroniser_vente() - Transaction State',
                'status' => (!$inTransactionBefore && !$inTransactionAfter) ? 'PASS' : 'FAIL',
                'message' => sprintf('Before: %s, After: %s', $inTransactionBefore ? 'IN' : 'OUT', $inTransactionAfter ? 'IN' : 'OUT'),
                'critical' => true
            ];
            if ($inTransactionAfter) {
                $result['status'] = 'FAIL';
            }
        }
    }

    // Check 3: Verify stocks_mouvements table exists
    try {
        $stmt = $pdo->prepare("DESCRIBE stocks_mouvements");
        $stmt->execute();
        $columns = $stmt->fetchAll();
        
        $result['checks']['03_stocks_mouvements_table'] = [
            'name' => 'stocks_mouvements Table',
            'status' => !empty($columns) ? 'PASS' : 'FAIL',
            'message' => sprintf('Found %d columns', count($columns)),
            'critical' => true
        ];
    } catch (Exception $e) {
        $result['checks']['03_stocks_mouvements_table'] = [
            'name' => 'stocks_mouvements Table',
            'status' => 'FAIL',
            'message' => 'Table not found or error: ' . $e->getMessage(),
            'critical' => true
        ];
        $result['status'] = 'FAIL';
    }

    // Check 4: Test exception handling - ensure transaction rolls back
    if (class_exists('PDO')) {
        require_once __DIR__ . '/lib/stock.php';
        
        // Create a controlled exception by passing invalid data
        $inTransactionBefore = $pdo->inTransaction();
        
        // This should trigger error handling and rollback
        stock_synchroniser_vente($pdo, 999999); // Non-existent vente
        
        $inTransactionAfter = $pdo->inTransaction();
        
        $result['checks']['04_exception_handling'] = [
            'name' => 'Exception Handling - Transaction Cleanup',
            'status' => (!$inTransactionBefore && !$inTransactionAfter) ? 'PASS' : 'FAIL',
            'message' => sprintf('Before: %s, After: %s', $inTransactionBefore ? 'IN' : 'OUT', $inTransactionAfter ? 'IN' : 'OUT'),
            'critical' => true
        ];
        if ($inTransactionAfter) {
            $result['status'] = 'FAIL';
        }
    }

    // Check 5: Verify ventes table structure
    try {
        $stmt = $pdo->prepare("SELECT id, statut FROM ventes LIMIT 1");
        $stmt->execute();
        $vente = $stmt->fetch();
        
        $result['checks']['05_ventes_table'] = [
            'name' => 'Ventes Table Structure',
            'status' => ($vente && isset($vente['id']) && isset($vente['statut'])) ? 'PASS' : 'FAIL',
            'message' => 'Ventes table accessible with required columns',
            'critical' => true
        ];
    } catch (Exception $e) {
        $result['checks']['05_ventes_table'] = [
            'name' => 'Ventes Table Structure',
            'status' => 'FAIL',
            'message' => 'Error: ' . $e->getMessage(),
            'critical' => true
        ];
        $result['status'] = 'FAIL';
    }

} catch (Exception $e) {
    $result['status'] = 'ERROR';
    $result['error'] = $e->getMessage();
}

// Output result as JSON for easier parsing
header('Content-Type: application/json');
echo json_encode($result, JSON_PRETTY_PRINT);
